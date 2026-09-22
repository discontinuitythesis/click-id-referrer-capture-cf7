/**
 * Tests for assets/js/cidrc-capture.js.
 *
 * Runs the capture script against a very small fake DOM. No browser, no jsdom.
 *
 * Usage: node --test test/capture.test.js
 */

'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert' );
const path = require( 'node:path' );

function makeStorage() {
	const data = new Map();
	return {
		getItem: ( key ) => ( data.has( key ) ? data.get( key ) : null ),
		setItem: ( key, value ) => data.set( key, String( value ) ),
		removeItem: ( key ) => data.delete( key ),
		clear: () => data.clear(),
		_data: data
	};
}

function makeInput( name ) {
	return { name, value: '', tagName: 'INPUT' };
}

function makeEnvironment( options = {} ) {
	const jar = new Map();
	const inputs = ( options.fields || [] ).map( makeInput );

	const doc = {
		readyState: 'complete',
		referrer: options.referrer === undefined ? 'https://www.google.com/' : options.referrer,
		addEventListener() {},
		querySelectorAll( selector ) {
			const match = /input\[name="([^"]+)"\]/.exec( selector );
			if ( ! match ) {
				return [];
			}
			return inputs.filter( ( input ) => input.name === match[ 1 ] );
		}
	};

	Object.defineProperty( doc, 'cookie', {
		get() {
			return Array.from( jar.entries() ).map( ( [ key, value ] ) => `${ key }=${ value }` ).join( '; ' );
		},
		set( raw ) {
			const first = String( raw ).split( ';' )[ 0 ];
			const index = first.indexOf( '=' );
			if ( index > 0 ) {
				jar.set( first.slice( 0, index ).trim(), first.slice( index + 1 ) );
			}
		}
	} );

	const win = {
		location: {
			protocol: 'https:',
			pathname: options.pathname || '/contact/',
			search: options.search || ''
		},
		localStorage: makeStorage(),
		sessionStorage: makeStorage(),
		cidrcConfig: options.config || {}
	};

	return { win, doc, jar, inputs };
}

function loadCapture( env ) {
	global.window = env.win;
	global.document = env.doc;
	global.localStorage = env.win.localStorage;
	global.sessionStorage = env.win.sessionStorage;

	const file = path.join( __dirname, '..', 'assets', 'js', 'cidrc-capture.js' );
	delete require.cache[ require.resolve( file ) ];

	// eslint-disable-next-line global-require
	return require( file );
}

function readCookieJar( jar, name ) {
	const raw = jar.get( name );
	return raw ? JSON.parse( decodeURIComponent( raw ) ) : null;
}

const FIELDS = [
	'cidrc_gclid',
	'cidrc_msclkid',
	'cidrc_utm_source',
	'cidrc_utm_campaign',
	'cidrc_referrer',
	'cidrc_landing_page',
	'cidrc_first_seen',
	'cidrc_last_touch'
];

test( 'first touch is recorded and the cookie is written in always mode', () => {
	const env = makeEnvironment( { search: '?gclid=AAA111&utm_source=google', fields: FIELDS } );
	const api = loadCapture( env );

	const stored = readCookieJar( env.jar, 'cidrc' );

	assert.ok( stored, 'a cidrc cookie should be written' );
	assert.strictEqual( stored.first.gclid, 'AAA111' );
	assert.strictEqual( stored.last.gclid, 'AAA111' );
	assert.strictEqual( stored.first.utm_source, 'google' );
	assert.strictEqual( stored.first_referrer, 'https://www.google.com/' );
	assert.strictEqual( stored.first_landing_page, '/contact/?gclid=AAA111&utm_source=google' );
	assert.ok( stored.first_seen, 'first_seen should be set' );
	assert.strictEqual( typeof api.getValues, 'function' );
} );

test( 'first touch is not overwritten but last touch is', () => {
	const env = makeEnvironment( { search: '?gclid=AAA111&utm_source=google', fields: FIELDS } );
	const api = loadCapture( env );

	// Second visit, different click identifier and a different landing page.
	env.win.location.search = '?gclid=BBB222&utm_source=bing';
	env.win.location.pathname = '/pricing/';
	api.init();

	const stored = readCookieJar( env.jar, 'cidrc' );

	assert.strictEqual( stored.first.gclid, 'AAA111', 'first touch must not be overwritten' );
	assert.strictEqual( stored.last.gclid, 'BBB222', 'last touch must be overwritten' );
	assert.strictEqual( stored.first.utm_source, 'google' );
	assert.strictEqual( stored.last.utm_source, 'bing' );
	assert.strictEqual( stored.first_landing_page, '/contact/?gclid=AAA111&utm_source=google', 'the first landing page is kept' );
} );

test( 'hidden fields are populated', () => {
	const env = makeEnvironment( { search: '?gclid=AAA111&msclkid=MS999&utm_campaign=brand', fields: FIELDS } );
	loadCapture( env );

	const byName = Object.fromEntries( env.inputs.map( ( input ) => [ input.name, input.value ] ) );

	assert.strictEqual( byName.cidrc_gclid, 'AAA111' );
	assert.strictEqual( byName.cidrc_msclkid, 'MS999' );
	assert.strictEqual( byName.cidrc_utm_campaign, 'brand' );
	assert.strictEqual( byName.cidrc_referrer, 'https://www.google.com/' );
	assert.strictEqual( byName.cidrc_landing_page, '/contact/?gclid=AAA111&msclkid=MS999&utm_campaign=brand' );
	assert.ok( byName.cidrc_first_seen );

	const lastTouch = JSON.parse( byName.cidrc_last_touch );
	assert.strictEqual( lastTouch.gclid, 'AAA111' );
	assert.strictEqual( lastTouch.msclkid, 'MS999' );
} );

test( 'wait for consent mode does not write the cookie until consent is granted', () => {
	const env = makeEnvironment( {
		search: '?gclid=CCC333',
		fields: FIELDS,
		config: { storageMode: 'consent' }
	} );
	const api = loadCapture( env );

	assert.strictEqual( env.jar.has( 'cidrc' ), false, 'no cookie before consent' );
	assert.strictEqual( env.win.localStorage.getItem( 'cidrc' ), null, 'no localStorage mirror before consent' );

	const pending = env.win.sessionStorage.getItem( 'cidrc_pending' );
	assert.ok( pending, 'values are held in sessionStorage while waiting' );
	assert.strictEqual( JSON.parse( pending ).first.gclid, 'CCC333' );

	// The hidden fields are still filled so that a submission is not lost.
	const byName = Object.fromEntries( env.inputs.map( ( input ) => [ input.name, input.value ] ) );
	assert.strictEqual( byName.cidrc_gclid, 'CCC333' );

	api.consentGranted();

	const stored = readCookieJar( env.jar, 'cidrc' );
	assert.ok( stored, 'the cookie is written once consent is granted' );
	assert.strictEqual( stored.first.gclid, 'CCC333' );
	assert.ok( env.win.localStorage.getItem( 'cidrc' ), 'the localStorage mirror is written too' );
} );

test( 'a consent cookie that contains the configured value unlocks storage', () => {
	const env = makeEnvironment( {
		search: '?gclid=DDD444',
		fields: FIELDS,
		config: {
			storageMode: 'consent',
			consentCookieName: 'cookieyes-consent',
			consentCookieValue: 'advertisement:yes'
		}
	} );

	env.jar.set( 'cookieyes-consent', encodeURIComponent( 'consentid:abc,analytics:yes,advertisement:yes' ) );

	loadCapture( env );

	const stored = readCookieJar( env.jar, 'cidrc' );
	assert.ok( stored, 'the cookie is written when the consent cookie matches' );
	assert.strictEqual( stored.first.gclid, 'DDD444' );
} );

test( 'unknown query parameters and empty values are ignored', () => {
	const env = makeEnvironment( { search: '?gclid=&fbclid=FB1&random=nope', fields: FIELDS } );
	loadCapture( env );

	const stored = readCookieJar( env.jar, 'cidrc' );

	assert.strictEqual( stored.first.gclid, undefined, 'an empty gclid is not stored' );
	assert.strictEqual( stored.first.fbclid, 'FB1' );
	assert.strictEqual( stored.first.random, undefined );
} );

test( 'values are capped at the configured length', () => {
	const long = 'x'.repeat( 900 );
	const env = makeEnvironment( { search: '?gclid=' + long, fields: FIELDS } );
	loadCapture( env );

	const stored = readCookieJar( env.jar, 'cidrc' );

	assert.strictEqual( stored.first.gclid.length, 500 );
} );
