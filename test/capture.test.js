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
	const listeners = [];

	const doc = {
		readyState: 'complete',
		title: options.title || '',
		referrer: options.referrer === undefined ? 'https://www.google.com/' : options.referrer,
		addEventListener( type, fn, capture ) {
			listeners.push( { type, fn, capture: capture === true } );
		},
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
			hostname: options.hostname || 'www.example.co.uk',
			pathname: options.pathname || '/contact/',
			search: options.search || ''
		},
		localStorage: makeStorage(),
		sessionStorage: makeStorage(),
		cidrcConfig: options.config || {}
	};

	if ( options.dataLayer ) {
		win.dataLayer = options.dataLayer;
	}

	if ( options.tagData ) {
		win.google_tag_data = options.tagData;
	}

	return { win, doc, jar, inputs, listeners };
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

test( 'an internal page view never fills an empty first referrer (the benluong.com case)', () => {
	// First visit typed or bookmarked: no referrer at all.
	const env = makeEnvironment( { search: '?gclid=TEST123&utm_source=google', referrer: '', fields: FIELDS } );
	const api = loadCapture( env );

	// The visitor then moves around the site, so document.referrer is the site itself.
	env.doc.referrer = 'https://www.example.co.uk/?gclid=TEST123&utm_source=google';
	env.win.location.search = '';
	env.win.location.pathname = '/contact/';
	api.init();

	const stored = readCookieJar( env.jar, 'cidrc' );
	assert.strictEqual( stored.first_referrer, '', 'a self-referral must not become the first referrer' );
	assert.strictEqual( stored.first_landing_page, '/contact/?gclid=TEST123&utm_source=google', 'the first landing page is the first page seen' );
} );

test( 'a same-site referrer on the first page view is ignored, with or without www', () => {
	const env = makeEnvironment( { referrer: 'https://example.co.uk/blog/', fields: FIELDS } );
	loadCapture( env );

	const stored = readCookieJar( env.jar, 'cidrc' );
	assert.strictEqual( stored.first_referrer, '' );
} );

test( 'an external referrer on the first page view is kept', () => {
	const env = makeEnvironment( { referrer: 'https://www.bing.com/search?q=x', fields: FIELDS } );
	loadCapture( env );

	const stored = readCookieJar( env.jar, 'cidrc' );
	assert.strictEqual( stored.first_referrer, 'https://www.bing.com/search?q=x' );
} );

// gtag() pushes its arguments object, not an array, so the tests do the same.
function gtagArgs() {
	return arguments;
}

const CONSENT_FIELDS = FIELDS.concat( [ 'cidrc_ad_user_data', 'cidrc_ad_personalization' ] );

function fieldsByName( env ) {
	return Object.fromEntries( env.inputs.map( ( input ) => [ input.name, input.value ] ) );
}

test( 'consent is read from google_tag_data, and update beats default', () => {
	const env = makeEnvironment( {
		fields: CONSENT_FIELDS,
		tagData: {
			ics: {
				entries: {
					ad_user_data: { default: false, update: true },
					ad_personalization: { default: false }
				}
			}
		},
		// google_tag_data is the first source, so this dataLayer must be ignored.
		dataLayer: [ gtagArgs( 'consent', 'update', { ad_user_data: 'denied', ad_personalization: 'granted' } ) ]
	} );
	const api = loadCapture( env );

	assert.deepStrictEqual( api.readConsent(), { ad_user_data: 'granted', ad_personalization: 'denied' } );
	assert.deepStrictEqual( env.win.cidrc.readConsent(), api.readConsent(), 'exposed on window.cidrc' );

	const byName = fieldsByName( env );
	assert.strictEqual( byName.cidrc_ad_user_data, 'granted' );
	assert.strictEqual( byName.cidrc_ad_personalization, 'denied' );
} );

test( 'the dataLayer fallback picks the latest update, then the latest default', () => {
	const env = makeEnvironment( {
		fields: CONSENT_FIELDS,
		dataLayer: [
			gtagArgs( 'consent', 'default', { ad_user_data: 'denied', ad_personalization: 'denied' } ),
			{ event: 'gtm.js' },
			gtagArgs( 'consent', 'update', { ad_user_data: 'granted', ad_personalization: 'granted' } ),
			[ 'consent', 'update', { ad_user_data: 'denied' } ],
			gtagArgs( 'config', 'AW-123' ),
			// A later default never outranks an earlier update.
			gtagArgs( 'consent', 'default', { ad_user_data: 'granted', ad_personalization: 'denied' } )
		]
	} );
	const api = loadCapture( env );

	assert.deepStrictEqual( api.readConsent(), { ad_user_data: 'denied', ad_personalization: 'granted' } );

	const defaultsOnly = makeEnvironment( {
		dataLayer: [
			gtagArgs( 'consent', 'default', { ad_user_data: 'granted' } ),
			gtagArgs( 'consent', 'default', { ad_user_data: 'denied', ad_personalization: 'GRANTED' } )
		]
	} );
	assert.deepStrictEqual( loadCapture( defaultsOnly ).readConsent(), { ad_user_data: 'denied', ad_personalization: 'granted' } );
} );

test( 'no consent signal gives empty values', () => {
	const env = makeEnvironment( { fields: CONSENT_FIELDS, dataLayer: [ { event: 'gtm.js' }, gtagArgs( 'config', 'G-1' ) ] } );
	const api = loadCapture( env );

	assert.deepStrictEqual( api.readConsent(), { ad_user_data: '', ad_personalization: '' } );

	const byName = fieldsByName( env );
	assert.strictEqual( byName.cidrc_ad_user_data, '' );
	assert.strictEqual( byName.cidrc_ad_personalization, '' );

	const bare = makeEnvironment( { tagData: { ics: {} } } );
	assert.deepStrictEqual( loadCapture( bare ).readConsent(), { ad_user_data: '', ad_personalization: '' } );
} );

test( 'consent is not stored in the cookie', () => {
	const env = makeEnvironment( {
		search: '?gclid=AAA111',
		fields: CONSENT_FIELDS,
		dataLayer: [ gtagArgs( 'consent', 'update', { ad_user_data: 'granted', ad_personalization: 'granted' } ) ]
	} );
	loadCapture( env );

	const raw = decodeURIComponent( env.jar.get( 'cidrc' ) );
	assert.strictEqual( raw.indexOf( 'ad_user_data' ), -1 );
	assert.strictEqual( raw.indexOf( 'granted' ), -1 );
} );

test( 'a capture phase submit listener refreshes the consent fields after consent changes', () => {
	const env = makeEnvironment( {
		search: '?gclid=SUB1',
		fields: CONSENT_FIELDS,
		dataLayer: [ gtagArgs( 'consent', 'default', { ad_user_data: 'denied', ad_personalization: 'denied' } ) ]
	} );
	loadCapture( env );

	assert.strictEqual( fieldsByName( env ).cidrc_ad_user_data, 'denied' );

	// The visitor accepts the banner after the page has loaded.
	env.win.dataLayer.push( gtagArgs( 'consent', 'update', { ad_user_data: 'granted', ad_personalization: 'granted' } ) );
	assert.strictEqual( fieldsByName( env ).cidrc_ad_user_data, 'denied', 'nothing changes until the form is sent' );

	const submit = env.listeners.filter( ( l ) => l.type === 'submit' );
	assert.strictEqual( submit.length, 1, 'one submit listener' );
	assert.strictEqual( submit[ 0 ].capture, true, 'registered in the capture phase' );

	// A form without plugin fields is left alone.
	const otherForm = { querySelector: () => null };
	submit[ 0 ].fn( { target: otherForm } );
	assert.strictEqual( fieldsByName( env ).cidrc_ad_user_data, 'denied' );

	// Blank a click ID field to prove the rest of the fields are refreshed too.
	env.inputs.find( ( input ) => input.name === 'cidrc_gclid' ).value = '';

	const cf7Form = {
		querySelector: ( selector ) => ( selector === 'input[name^="cidrc_"]' ? env.inputs[ 0 ] : null )
	};
	submit[ 0 ].fn( { target: cf7Form } );

	const byName = fieldsByName( env );
	assert.strictEqual( byName.cidrc_ad_user_data, 'granted' );
	assert.strictEqual( byName.cidrc_ad_personalization, 'granted' );
	assert.strictEqual( byName.cidrc_gclid, 'SUB1' );
} );

// Extended attribution.

const EXTENDED_FIELDS = [ 'cidrc_form_page', 'cidrc_form_page_title', 'cidrc_touch_first', 'cidrc_touch_last' ];
const EXTENDED_CONFIG = { extendedAttribution: true };

function extendedEnv( options = {} ) {
	return makeEnvironment( Object.assign( { fields: FIELDS.concat( EXTENDED_FIELDS ), config: EXTENDED_CONFIG }, options ) );
}

// Moves the fake browser to another page and runs the script's page view logic.
function visit( env, api, page ) {
	env.win.location.pathname = page.pathname || '/';
	env.win.location.search = page.search || '';
	env.doc.referrer = page.referrer || '';
	return api.init();
}

test( 'classifyChannel applies every rule in order', () => {
	const api = loadCapture( makeEnvironment() );
	const cases = [
		// Click IDs come first, whatever the UTMs say.
		[ { click_id_type: 'gclid', utm_medium: 'email' }, 'Paid Search' ],
		[ { click_id_type: 'gbraid' }, 'Paid Search' ],
		[ { click_id_type: 'wbraid' }, 'Paid Search' ],
		[ { click_id_type: 'msclkid' }, 'Paid Search' ],
		[ { click_id_type: 'dclid' }, 'Display' ],
		[ { click_id_type: 'fbclid' }, 'Paid Social' ],
		[ { click_id_type: 'ttclid' }, 'Paid Social' ],
		[ { click_id_type: 'li_fat_id' }, 'Paid Social' ],
		// UTM medium rules.
		[ { utm_source: 'google', utm_medium: 'cpc' }, 'Paid Search' ],
		[ { utm_source: 'bing', utm_medium: 'PPC' }, 'Paid Search' ],
		[ { utm_medium: 'paidsearch' }, 'Paid Search' ],
		[ { utm_medium: 'sem' }, 'Paid Search' ],
		[ { utm_medium: 'paid' }, 'Paid Search' ],
		[ { utm_source: 'facebook', utm_medium: 'paid_social' }, 'Paid Social' ],
		[ { utm_source: 'linkedin.com', utm_medium: 'social-paid' }, 'Paid Social' ],
		[ { utm_source: 'ig', utm_medium: 'cpm' }, 'Paid Social' ],
		[ { utm_source: 'bing', utm_medium: 'paidsocial' }, 'Paid Social' ],
		[ { utm_source: 'google', utm_medium: 'paid_social' }, 'Other Campaign' ],
		[ { utm_source: 'dv360', utm_medium: 'display' }, 'Display' ],
		[ { utm_medium: 'Banner' }, 'Display' ],
		[ { utm_medium: 'programmatic-video' }, 'Display' ],
		[ { utm_source: 'mailchimp', utm_medium: 'email' }, 'Email' ],
		[ { utm_medium: 'E-Mail' }, 'Email' ],
		[ { utm_source: 'september-newsletter', utm_medium: 'blast' }, 'Email' ],
		[ { utm_medium: 'weekly_newsletter' }, 'Email' ],
		[ { utm_medium: 'affiliates' }, 'Affiliate' ],
		[ { utm_source: 'twitter', utm_medium: 'social' }, 'Organic Social' ],
		[ { utm_medium: 'social-network' }, 'Organic Social' ],
		[ { utm_medium: 'SM' }, 'Organic Social' ],
		[ { utm_source: 'partner.co.uk', utm_medium: 'referral' }, 'Referral' ],
		[ { utm_source: 'google', utm_medium: 'organic' }, 'Other Campaign' ],
		[ { utm_campaign: 'spring', referrer: 'https://www.google.com/' }, 'Other Campaign' ],
		// No UTMs: the referrer host decides.
		[ { referrer: 'https://www.google.co.uk/' }, 'Organic Search' ],
		[ { referrer: 'https://www.bing.com/search?q=plumber' }, 'Organic Search' ],
		[ { referrer: 'https://duckduckgo.com/' }, 'Organic Search' ],
		[ { referrer: 'https://uk.search.yahoo.com/' }, 'Organic Search' ],
		[ { referrer: 'https://www.ecosia.org/' }, 'Organic Search' ],
		[ { referrer: 'https://yandex.ru/' }, 'Organic Search' ],
		[ { referrer: 'https://www.baidu.com/' }, 'Organic Search' ],
		[ { referrer: 'https://search.brave.com/search?q=x' }, 'Organic Search' ],
		[ { referrer: 'https://l.facebook.com/' }, 'Organic Social' ],
		[ { referrer: 'https://www.instagram.com/' }, 'Organic Social' ],
		[ { referrer: 'https://www.linkedin.com/' }, 'Organic Social' ],
		[ { referrer: 'https://lnkd.in/abc' }, 'Organic Social' ],
		[ { referrer: 'https://t.co/xyz' }, 'Organic Social' ],
		[ { referrer: 'https://x.com/someone' }, 'Organic Social' ],
		[ { referrer: 'https://old.reddit.com/r/x' }, 'Organic Social' ],
		[ { referrer: 'https://m.youtube.com/' }, 'Organic Social' ],
		[ { referrer: 'https://www.tiktok.com/' }, 'Organic Social' ],
		[ { referrer: 'https://uk.pinterest.com/' }, 'Organic Social' ],
		[ { referrer: 'https://chatgpt.com/' }, 'AI Assistant' ],
		[ { referrer: 'https://chat.openai.com/' }, 'AI Assistant' ],
		[ { referrer: 'https://www.perplexity.ai/' }, 'AI Assistant' ],
		[ { referrer: 'https://claude.ai/' }, 'AI Assistant' ],
		[ { referrer: 'https://gemini.google.com/app' }, 'AI Assistant' ],
		[ { referrer: 'https://copilot.microsoft.com/' }, 'AI Assistant' ],
		[ { referrer: 'https://www.box.com/' }, 'Referral' ],
		[ { referrer: 'https://googleadservices.com/' }, 'Referral' ],
		[ { referrer: 'https://blog.example.org/post' }, 'Referral' ],
		[ { referrer: '' }, 'Direct' ],
		[ {}, 'Direct' ],
		[ null, 'Direct' ]
	];

	for ( const [ touch, expected ] of cases ) {
		assert.strictEqual( api.classifyChannel( touch ), expected, JSON.stringify( touch ) );
	}
} );

test( 'first and last touch across organic, internal, paid and direct page views', () => {
	const env = extendedEnv( { referrer: 'https://www.google.co.uk/', pathname: '/blog/x/' } );
	const api = loadCapture( env );

	let stored = readCookieJar( env.jar, 'cidrc' );
	assert.strictEqual( stored.touches.first.channel, 'Organic Search' );
	assert.strictEqual( stored.touches.first.referrer, 'https://www.google.co.uk/' );
	assert.strictEqual( stored.touches.first.landing_page, '/blog/x/' );
	assert.match( stored.touches.first.at, /^\d{4}-\d{2}-\d{2}T/ );
	assert.deepStrictEqual( stored.touches.last, stored.touches.first, 'the first touch is also the last touch so far' );
	const firstTouch = stored.touches.first;

	// Internal navigation is never a touch.
	visit( env, api, { pathname: '/services/', referrer: 'https://www.example.co.uk/blog/x/' } );
	stored = readCookieJar( env.jar, 'cidrc' );
	assert.deepStrictEqual( stored.touches.first, firstTouch );
	assert.deepStrictEqual( stored.touches.last, firstTouch, 'last is unchanged by internal navigation' );

	// A paid click days later.
	visit( env, api, { pathname: '/', search: '?gclid=G1&utm_source=google&utm_medium=cpc&utm_campaign=brand&utm_term=emergency%20plumber', referrer: 'https://www.google.com/' } );
	stored = readCookieJar( env.jar, 'cidrc' );
	assert.deepStrictEqual( stored.touches.first, firstTouch, 'first is set once' );
	assert.deepStrictEqual(
		Object.assign( {}, stored.touches.last, { at: '' } ),
		{
			utm_source: 'google',
			utm_medium: 'cpc',
			utm_campaign: 'brand',
			utm_term: 'emergency plumber',
			utm_content: '',
			utm_id: '',
			click_id_type: 'gclid',
			referrer: 'https://www.google.com/',
			landing_page: '/?gclid=G1&utm_source=google&utm_medium=cpc&utm_campaign=brand&utm_term=emergency%20plumber',
			at: '',
			channel: 'Paid Search'
		}
	);
	const paidTouch = stored.touches.last;

	// The flat first and last maps behave as before.
	assert.strictEqual( stored.first.gclid, 'G1' );
	assert.strictEqual( stored.first_referrer, 'https://www.google.co.uk/' );
	assert.strictEqual( stored.first_landing_page, '/blog/x/' );

	// A direct revisit, typed in: no referrer and no parameters, so not a touch.
	visit( env, api, { pathname: '/contact/' } );
	stored = readCookieJar( env.jar, 'cidrc' );
	assert.deepStrictEqual( stored.touches.last, paidTouch, 'a direct revisit does not replace the last touch' );
	assert.deepStrictEqual( stored.touches.first, firstTouch );

	const byName = fieldsByName( env );
	assert.deepStrictEqual( JSON.parse( byName.cidrc_touch_first ), firstTouch );
	assert.deepStrictEqual( JSON.parse( byName.cidrc_touch_last ), paidTouch );
} );

test( 'a new touch replaces the last touch wholesale, without merging UTMs', () => {
	const env = extendedEnv( { search: '?utm_source=google&utm_medium=cpc&utm_term=boiler&utm_content=ad1', referrer: '' } );
	const api = loadCapture( env );

	visit( env, api, { pathname: '/offers/', search: '?utm_source=newsletter&utm_medium=email', referrer: '' } );
	let stored = readCookieJar( env.jar, 'cidrc' );
	assert.strictEqual( stored.touches.last.channel, 'Email' );
	assert.strictEqual( stored.touches.last.utm_term, '', 'the earlier term is not carried over' );
	assert.strictEqual( stored.touches.last.utm_content, '' );
	assert.strictEqual( stored.touches.first.utm_term, 'boiler' );

	visit( env, api, { pathname: '/', referrer: 'https://l.facebook.com/' } );
	stored = readCookieJar( env.jar, 'cidrc' );
	assert.strictEqual( stored.touches.last.channel, 'Organic Social' );
	assert.strictEqual( stored.touches.last.utm_source, '' );
	assert.strictEqual( stored.touches.first.channel, 'Paid Search' );
} );

test( 'a direct first page view records a direct first touch', () => {
	const env = extendedEnv( { referrer: '', pathname: '/contact/' } );
	const api = loadCapture( env );

	let stored = readCookieJar( env.jar, 'cidrc' );
	assert.strictEqual( stored.touches.first.channel, 'Direct' );
	assert.strictEqual( stored.touches.first.landing_page, '/contact/' );
	assert.strictEqual( stored.touches.first.referrer, '' );
	assert.strictEqual( stored.touches.last.channel, 'Direct' );

	visit( env, api, { search: '?msclkid=M1', referrer: 'https://www.bing.com/' } );
	stored = readCookieJar( env.jar, 'cidrc' );
	assert.strictEqual( stored.touches.first.channel, 'Direct' );
	assert.strictEqual( stored.touches.last.channel, 'Paid Search' );
	assert.strictEqual( stored.touches.last.click_id_type, 'msclkid' );
} );

test( 'a visitor stored before extended attribution was on gets a first touch from the stored values', () => {
	const env = makeEnvironment( { search: '?gclid=OLD1&utm_source=google&utm_medium=cpc', pathname: '/landing/', fields: FIELDS } );
	const api = loadCapture( env );
	assert.strictEqual( readCookieJar( env.jar, 'cidrc' ).touches, undefined );

	api.configure( { extendedAttribution: true } );
	visit( env, api, { pathname: '/contact/', referrer: 'https://www.example.co.uk/landing/' } );

	const stored = readCookieJar( env.jar, 'cidrc' );
	assert.strictEqual( stored.touches.first.channel, 'Paid Search' );
	assert.strictEqual( stored.touches.first.click_id_type, 'gclid' );
	assert.strictEqual( stored.touches.first.landing_page, '/landing/?gclid=OLD1&utm_source=google&utm_medium=cpc' );
	assert.strictEqual( stored.touches.first.at, stored.first_seen );
	assert.deepStrictEqual( stored.touches.last, stored.touches.first );
} );

test( 'the form page fields are filled on load and refreshed at submit', () => {
	const env = extendedEnv( { pathname: '/contact/', search: '?ref=menu', title: 'Contact us' } );
	loadCapture( env );

	let byName = fieldsByName( env );
	assert.strictEqual( byName.cidrc_form_page, '/contact/?ref=menu' );
	assert.strictEqual( byName.cidrc_form_page_title, 'Contact us' );

	// The page changes without a reload, then the form is sent.
	env.win.location.pathname = '/quote/';
	env.win.location.search = '';
	env.doc.title = 'Q'.repeat( 300 );

	const submit = env.listeners.find( ( l ) => l.type === 'submit' );
	submit.fn( { target: { querySelector: () => env.inputs[ 0 ] } } );

	byName = fieldsByName( env );
	assert.strictEqual( byName.cidrc_form_page, '/quote/' );
	assert.strictEqual( byName.cidrc_form_page_title.length, 200, 'the title is capped at 200 characters' );
	assert.strictEqual( JSON.parse( byName.cidrc_touch_first ).channel, 'Organic Search' );
} );

test( 'with extended attribution off the new fields are untouched and no touches are stored', () => {
	const env = makeEnvironment( { search: '?gclid=OFF1', fields: FIELDS.concat( EXTENDED_FIELDS ), title: 'Contact us' } );
	env.inputs.forEach( ( input ) => {
		if ( EXTENDED_FIELDS.indexOf( input.name ) > -1 ) {
			input.value = 'untouched';
		}
	} );
	loadCapture( env );

	const submit = env.listeners.find( ( l ) => l.type === 'submit' );
	submit.fn( { target: { querySelector: () => env.inputs[ 0 ] } } );

	const byName = fieldsByName( env );
	for ( const name of EXTENDED_FIELDS ) {
		assert.strictEqual( byName[ name ], 'untouched', name );
	}
	assert.strictEqual( byName.cidrc_gclid, 'OFF1' );

	const stored = readCookieJar( env.jar, 'cidrc' );
	assert.strictEqual( Object.prototype.hasOwnProperty.call( stored, 'touches' ), false );
	assert.strictEqual( env.win.localStorage.getItem( 'cidrc' ).indexOf( 'touches' ), -1 );
} );

test( 'touch URLs are shortened when the record would not fit in a cookie', () => {
	const long = 'a'.repeat( 480 );
	const env = extendedEnv( { search: '?gclid=' + long + '&utm_campaign=' + long, referrer: 'https://www.google.com/?q=' + long } );
	loadCapture( env );

	const stored = readCookieJar( env.jar, 'cidrc' );
	assert.ok( stored.touches.first.landing_page.length <= 200 );
	assert.ok( stored.touches.first.referrer.length <= 200 );
	assert.strictEqual( stored.first_landing_page.length, 500, 'the 1.1.0 values are left alone' );

	const small = extendedEnv( { search: '?gclid=S1' } );
	loadCapture( small );
	assert.strictEqual( readCookieJar( small.jar, 'cidrc' ).touches.first.landing_page, '/contact/?gclid=S1' );
} );
