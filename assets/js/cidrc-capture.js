/**
 * Click ID & Referrer Capture for Contact Form 7.
 *
 * Reads advertising click identifiers and UTM parameters from the query string,
 * keeps a first touch and a last touch record in a first party cookie, and fills
 * the hidden fields the plugin adds to Contact Form 7 forms. It also reads the
 * Google Consent Mode state for ad_user_data and ad_personalization live from the
 * page, never from the cookie, and writes it into two more hidden fields.
 *
 * With extended attribution switched on it also keeps a whole first touch and a
 * whole last touch, each with its own channel, and records the page the form was
 * sent from.
 *
 * No jQuery, no build step, no external requests.
 */
( function ( root, doc ) {
	'use strict';

	var DEFAULTS = {
		cookieName: 'cidrc',
		cookieDays: 90,
		maxLength: 500,
		storageMode: 'always',
		consentCookieName: '',
		consentCookieValue: '',
		extendedAttribution: false,
		params: [
			'gclid', 'gbraid', 'wbraid', 'dclid', 'msclkid', 'fbclid', 'ttclid', 'li_fat_id',
			'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'
		],
		fields: {
			cidrc_gclid: 'gclid',
			cidrc_gbraid: 'gbraid',
			cidrc_wbraid: 'wbraid',
			cidrc_msclkid: 'msclkid',
			cidrc_fbclid: 'fbclid',
			cidrc_ttclid: 'ttclid',
			cidrc_utm_source: 'utm_source',
			cidrc_utm_medium: 'utm_medium',
			cidrc_utm_campaign: 'utm_campaign',
			cidrc_utm_term: 'utm_term',
			cidrc_utm_content: 'utm_content',
			cidrc_referrer: 'first_referrer',
			cidrc_landing_page: 'first_landing_page',
			cidrc_first_seen: 'first_seen',
			cidrc_last_touch: 'last_touch',
			cidrc_ad_user_data: 'ad_user_data',
			cidrc_ad_personalization: 'ad_personalization'
		}
	};

	var CONSENT_KEYS = [ 'ad_user_data', 'ad_personalization' ];

	// Extended attribution only. These fields are never touched when it is off.
	var EXTENDED_FIELDS = {
		cidrc_form_page: 'form_page',
		cidrc_form_page_title: 'form_page_title',
		cidrc_touch_first: 'touch_first',
		cidrc_touch_last: 'touch_last'
	};

	var TOUCH_UTMS = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id' ];

	// In priority order: the first one present names the touch's click ID type.
	var CLICK_ID_TYPES = [ 'gclid', 'gbraid', 'wbraid', 'msclkid', 'fbclid', 'ttclid', 'li_fat_id', 'dclid' ];

	var SOCIAL_SOURCES = [ 'facebook', 'fb', 'instagram', 'ig', 'meta', 'linkedin', 'twitter', 'x', 'tiktok', 'reddit', 'pinterest', 'youtube', 'snapchat', 'threads' ];

	// Referrer hosts and their channel, checked in order, used only when a touch
	// has no UTM values. An entry ending in a dot matches that name at any
	// subdomain level with any domain ending (google. matches www.google.co.uk).
	// Any other entry matches that host or a subdomain of it. The AI assistants
	// come first so that gemini.google.com is not read as a Google search.
	var REFERRER_CHANNELS = [
		[ 'chatgpt.com', 'AI Assistant' ],
		[ 'chat.openai.com', 'AI Assistant' ],
		[ 'perplexity.ai', 'AI Assistant' ],
		[ 'claude.ai', 'AI Assistant' ],
		[ 'gemini.google.com', 'AI Assistant' ],
		[ 'copilot.microsoft.com', 'AI Assistant' ],
		[ 'google.', 'Organic Search' ],
		[ 'bing.', 'Organic Search' ],
		[ 'duckduckgo.', 'Organic Search' ],
		[ 'yahoo.', 'Organic Search' ],
		[ 'ecosia.', 'Organic Search' ],
		[ 'yandex.', 'Organic Search' ],
		[ 'baidu.', 'Organic Search' ],
		[ 'search.brave.', 'Organic Search' ],
		[ 'facebook.', 'Organic Social' ],
		[ 'instagram.', 'Organic Social' ],
		[ 'linkedin.', 'Organic Social' ],
		[ 'lnkd.in', 'Organic Social' ],
		[ 't.co', 'Organic Social' ],
		[ 'twitter.', 'Organic Social' ],
		[ 'x.com', 'Organic Social' ],
		[ 'reddit.', 'Organic Social' ],
		[ 'youtube.', 'Organic Social' ],
		[ 'tiktok.', 'Organic Social' ],
		[ 'pinterest.', 'Organic Social' ]
	];

	// A cookie over about 4 KB is dropped by the browser, so the touch URLs are
	// shortened step by step until the encoded record fits.
	var COOKIE_BUDGET = 3800;
	var TOUCH_URL_LIMITS = [ 200, 80 ];

	var config = merge( DEFAULTS, root && root.cidrcConfig ? root.cidrcConfig : {} );
	var consentInMemory = false;

	// Copy of the latest record, used when neither the cookie nor storage can be
	// read, so that a refresh never blanks fields that init() filled.
	var memory = '';

	function merge( base, extra ) {
		var out = {};
		var key;
		for ( key in base ) {
			if ( Object.prototype.hasOwnProperty.call( base, key ) ) {
				out[ key ] = base[ key ];
			}
		}
		for ( key in extra ) {
			if ( Object.prototype.hasOwnProperty.call( extra, key ) && extra[ key ] !== undefined && extra[ key ] !== null ) {
				out[ key ] = extra[ key ];
			}
		}
		return out;
	}

	function emptyValues() {
		return {
			first: {},
			last: {},
			first_referrer: '',
			first_landing_page: '',
			first_seen: '',
			last_seen: ''
		};
	}

	function cap( value ) {
		var text = ( value === undefined || value === null ) ? '' : String( value );
		return text.length > config.maxLength ? text.slice( 0, config.maxLength ) : text;
	}

	function readCookie( name ) {
		if ( ! doc || typeof doc.cookie !== 'string' ) {
			return '';
		}
		var parts = doc.cookie.split( ';' );
		var i, pair, index;
		for ( i = 0; i < parts.length; i++ ) {
			pair = parts[ i ].replace( /^\s+/, '' );
			index = pair.indexOf( '=' );
			if ( index > 0 && pair.slice( 0, index ) === name ) {
				return pair.slice( index + 1 );
			}
		}
		return '';
	}

	function writeCookie( name, value, days ) {
		if ( ! doc ) {
			return;
		}
		var expires = new Date( Date.now() + ( days * 86400000 ) ).toUTCString();
		var secure = ( root && root.location && root.location.protocol === 'https:' ) ? '; Secure' : '';
		doc.cookie = name + '=' + encodeURIComponent( value ) + '; Expires=' + expires + '; Path=/; SameSite=Lax' + secure;
	}

	function safeStore( kind ) {
		try {
			return root ? root[ kind ] : null;
		} catch ( e ) {
			return null;
		}
	}

	function storeGet( kind, key ) {
		var store = safeStore( kind );
		try {
			return store ? ( store.getItem( key ) || '' ) : '';
		} catch ( e ) {
			return '';
		}
	}

	function storeSet( kind, key, value ) {
		var store = safeStore( kind );
		try {
			if ( store ) {
				store.setItem( key, value );
			}
		} catch ( e ) {
			// Storage is unavailable, for example in private browsing. The cookie is enough.
		}
	}

	function parse( raw ) {
		if ( ! raw ) {
			return null;
		}
		try {
			var decoded = JSON.parse( decodeURIComponent( raw ) );
			if ( decoded && typeof decoded === 'object' ) {
				return merge( emptyValues(), decoded );
			}
		} catch ( e ) {
			return null;
		}
		return null;
	}

	function fromMemory() {
		try {
			return memory ? merge( emptyValues(), JSON.parse( memory ) ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function load() {
		return parse( readCookie( config.cookieName ) ) ||
			parse( storeGet( 'localStorage', config.cookieName ) ) ||
			parse( storeGet( 'sessionStorage', config.cookieName + '_pending' ) ) ||
			fromMemory() ||
			emptyValues();
	}

	function queryValues() {
		var search = ( root && root.location && root.location.search ) ? String( root.location.search ) : '';
		var found = {};
		if ( search.charAt( 0 ) === '?' ) {
			search = search.slice( 1 );
		}
		if ( ! search ) {
			return found;
		}
		var pairs = search.split( '&' );
		var i, index, name, value;
		for ( i = 0; i < pairs.length; i++ ) {
			index = pairs[ i ].indexOf( '=' );
			name = index < 0 ? pairs[ i ] : pairs[ i ].slice( 0, index );
			value = index < 0 ? '' : pairs[ i ].slice( index + 1 );
			try {
				name = decodeURIComponent( name );
				value = decodeURIComponent( value.replace( /\+/g, ' ' ) );
			} catch ( e ) {
				continue;
			}
			if ( value !== '' && config.params.indexOf( name ) > -1 ) {
				found[ name ] = cap( value );
			}
		}
		return found;
	}

	function isInternal( url ) {
		try {
			var host = ( root && root.location && root.location.hostname ) || '';
			var ref = String( url ).replace( /^[a-z]+:\/\//i, '' ).split( /[\/?#]/ )[ 0 ].toLowerCase();
			return !! host && ref.replace( /^www\./, '' ) === host.toLowerCase().replace( /^www\./, '' );
		} catch ( e ) {
			return false;
		}
	}

	function apply( values, incoming ) {
		var returning = !! values.first_seen;
		var i, name;
		for ( i = 0; i < config.params.length; i++ ) {
			name = config.params[ i ];
			if ( ! Object.prototype.hasOwnProperty.call( incoming, name ) ) {
				continue;
			}
			values.last[ name ] = incoming[ name ];
			if ( ! values.first[ name ] ) {
				values.first[ name ] = incoming[ name ];
			}
		}
		var now = new Date().toISOString();
		// First-touch fields are written on the very first page view only. Doing it
		// per field would let a later internal page view fill an empty referrer with
		// the site's own URL, which is a self-referral, not a traffic source.
		if ( ! values.first_seen ) {
			values.first_seen = now;
			if ( doc && doc.referrer && ! isInternal( doc.referrer ) ) {
				values.first_referrer = cap( doc.referrer );
			}
			if ( root && root.location ) {
				values.first_landing_page = cap( ( root.location.pathname || '' ) + ( root.location.search || '' ) );
			}
		}
		values.last_seen = now;
		if ( config.extendedAttribution ) {
			applyTouches( values, incoming, now, returning );
		}
		return values;
	}

	function lower( value ) {
		return ( value === undefined || value === null ) ? '' : String( value ).toLowerCase().replace( /^\s+|\s+$/g, '' );
	}

	function hostOf( url ) {
		return lower( url ).replace( /^[a-z][a-z0-9+.\-]*:\/\//, '' ).split( /[\/?#:]/ )[ 0 ].replace( /^www\./, '' );
	}

	function hostMatches( host, entry ) {
		if ( entry.charAt( entry.length - 1 ) === '.' ) {
			return host.indexOf( entry ) === 0 || host.indexOf( '.' + entry ) > -1;
		}
		return host === entry || ( host.length > entry.length && host.slice( -entry.length - 1 ) === '.' + entry );
	}

	function isSocialSource( source ) {
		var tokens = source.split( /[^a-z0-9]+/ );
		var i;
		for ( i = 0; i < tokens.length; i++ ) {
			if ( tokens[ i ] && SOCIAL_SOURCES.indexOf( tokens[ i ] ) > -1 ) {
				return true;
			}
		}
		return false;
	}

	function hasUtm( touch ) {
		var i;
		for ( i = 0; i < TOUCH_UTMS.length; i++ ) {
			if ( lower( touch[ TOUCH_UTMS[ i ] ] ) ) {
				return true;
			}
		}
		return false;
	}

	function classifyChannel( touch ) {
		var t = ( touch && typeof touch === 'object' ) ? touch : {};
		var click = lower( t.click_id_type );
		var source = lower( t.utm_source );
		var medium = lower( t.utm_medium );
		var host, i;

		if ( click === 'dclid' ) {
			return 'Display';
		}
		if ( [ 'gclid', 'gbraid', 'wbraid', 'msclkid' ].indexOf( click ) > -1 ) {
			return 'Paid Search';
		}
		if ( [ 'fbclid', 'ttclid', 'li_fat_id' ].indexOf( click ) > -1 ) {
			return 'Paid Social';
		}
		if ( /^(cpc|ppc|paid|paidsearch|sem)$/.test( medium ) ) {
			return 'Paid Search';
		}
		if ( medium === 'paidsocial' || ( /^(paid[_-]?social|social[_-]?paid|cpm)$/.test( medium ) && isSocialSource( source ) ) ) {
			return 'Paid Social';
		}
		if ( /display|banner|programmatic/.test( medium ) ) {
			return 'Display';
		}
		if ( /^e-?mail$/.test( medium ) || source.indexOf( 'newsletter' ) > -1 || medium.indexOf( 'newsletter' ) > -1 ) {
			return 'Email';
		}
		if ( /affiliate/.test( medium ) ) {
			return 'Affiliate';
		}
		if ( /^(social|social-network|sm)$/.test( medium ) ) {
			return 'Organic Social';
		}
		if ( medium === 'referral' ) {
			return 'Referral';
		}
		if ( hasUtm( t ) ) {
			return 'Other Campaign';
		}
		host = hostOf( t.referrer );
		if ( ! host ) {
			return 'Direct';
		}
		for ( i = 0; i < REFERRER_CHANNELS.length; i++ ) {
			if ( hostMatches( host, REFERRER_CHANNELS[ i ][ 0 ] ) ) {
				return REFERRER_CHANNELS[ i ][ 1 ];
			}
		}
		return 'Referral';
	}

	function currentPage() {
		return ( root && root.location ) ? cap( ( root.location.pathname || '' ) + ( root.location.search || '' ) ) : '';
	}

	function externalReferrer() {
		return ( doc && doc.referrer && ! isInternal( doc.referrer ) ) ? cap( doc.referrer ) : '';
	}

	// One touch, built from the given parameters and referrer. The key order is
	// the order the server and the exports use.
	function buildTouch( params, referrer, landing, at ) {
		var touch = {};
		var i;
		for ( i = 0; i < TOUCH_UTMS.length; i++ ) {
			touch[ TOUCH_UTMS[ i ] ] = params[ TOUCH_UTMS[ i ] ] ? cap( params[ TOUCH_UTMS[ i ] ] ) : '';
		}
		touch.click_id_type = '';
		for ( i = 0; i < CLICK_ID_TYPES.length; i++ ) {
			if ( params[ CLICK_ID_TYPES[ i ] ] ) {
				touch.click_id_type = CLICK_ID_TYPES[ i ];
				break;
			}
		}
		touch.referrer = referrer || '';
		touch.landing_page = landing || '';
		touch.at = at || '';
		touch.channel = classifyChannel( touch );
		return touch;
	}

	// A page view is a touch when it carries a tracked parameter or comes from
	// another site. Internal navigation and a plain revisit are not touches.
	function pageTouch( incoming, now ) {
		var referrer = externalReferrer();
		var name;
		var tracked = false;
		for ( name in incoming ) {
			if ( Object.prototype.hasOwnProperty.call( incoming, name ) ) {
				tracked = true;
			}
		}
		return ( tracked || referrer ) ? buildTouch( incoming, referrer, currentPage(), now ) : null;
	}

	function isTouch( touch ) {
		return !! touch && typeof touch === 'object' && typeof touch.channel === 'string' && touch.channel !== '';
	}

	function copyTouch( touch ) {
		return JSON.parse( JSON.stringify( touch ) );
	}

	function applyTouches( values, incoming, now, returning ) {
		var touches = ( values.touches && typeof values.touches === 'object' ) ? values.touches : {};
		var touch = pageTouch( incoming, now );

		if ( ! isTouch( touches.first ) ) {
			// A visitor recorded before extended attribution was switched on gets a
			// first touch rebuilt from the first-touch values already stored.
			// Otherwise this is the first page view, and a direct one if not a touch.
			touches.first = returning ?
				buildTouch( values.first || {}, values.first_referrer, values.first_landing_page, values.first_seen ) :
				( touch || buildTouch( {}, '', currentPage(), now ) );
			touches.last = touch || copyTouch( touches.first );
		} else if ( touch ) {
			// Replaced wholesale, never merged with the previous touch.
			touches.last = touch;
		} else if ( ! isTouch( touches.last ) ) {
			touches.last = copyTouch( touches.first );
		}

		values.touches = { first: touches.first, last: touches.last };
		fitCookie( values );
	}

	function fitCookie( values ) {
		var touches = [ values.touches.first, values.touches.last ];
		var i, j;
		for ( i = 0; i < TOUCH_URL_LIMITS.length; i++ ) {
			if ( encodeURIComponent( JSON.stringify( values ) ).length <= COOKIE_BUDGET ) {
				return;
			}
			for ( j = 0; j < touches.length; j++ ) {
				touches[ j ].referrer = String( touches[ j ].referrer ).slice( 0, TOUCH_URL_LIMITS[ i ] );
				touches[ j ].landing_page = String( touches[ j ].landing_page ).slice( 0, TOUCH_URL_LIMITS[ i ] );
			}
		}
	}

	function consentOk() {
		if ( config.storageMode !== 'consent' ) {
			return true;
		}
		if ( consentInMemory ) {
			return true;
		}
		if ( ! config.consentCookieName ) {
			return false;
		}
		var raw = readCookie( config.consentCookieName );
		if ( ! raw ) {
			return false;
		}
		if ( ! config.consentCookieValue ) {
			return true;
		}
		try {
			raw = decodeURIComponent( raw );
		} catch ( e ) {
			// Use the raw cookie value.
		}
		return raw.indexOf( config.consentCookieValue ) > -1;
	}

	function persist( values ) {
		var json = JSON.stringify( values );
		memory = json;
		if ( ! consentOk() ) {
			storeSet( 'sessionStorage', config.cookieName + '_pending', json );
			return false;
		}
		writeCookie( config.cookieName, json, config.cookieDays );
		storeSet( 'localStorage', config.cookieName, json );
		return true;
	}

	function consentState( value ) {
		if ( value === true ) {
			return 'granted';
		}
		return value === false ? 'denied' : '';
	}

	// Source (a): the consent state the Google tag keeps for itself.
	function consentFromTagData( key ) {
		var data = root ? root.google_tag_data : null;
		var entries = data && data.ics && data.ics.entries ? data.ics.entries : null;
		var entry = entries ? entries[ key ] : null;
		if ( ! entry || typeof entry !== 'object' ) {
			return '';
		}
		if ( typeof entry.update === 'boolean' ) {
			return consentState( entry.update );
		}
		if ( typeof entry[ 'default' ] === 'boolean' ) {
			return consentState( entry[ 'default' ] );
		}
		return '';
	}

	// Source (b): gtag( 'consent', 'update' | 'default', { ... } ) calls in the
	// dataLayer. The latest update wins, then the latest default.
	function consentFromDataLayer( key ) {
		var layer = root ? root.dataLayer : null;
		var fallback = '';
		var i, entry, value;
		if ( ! layer || typeof layer.length !== 'number' ) {
			return '';
		}
		for ( i = layer.length - 1; i >= 0; i-- ) {
			entry = layer[ i ];
			if ( ! entry || typeof entry !== 'object' || typeof entry.length !== 'number' || entry[ 0 ] !== 'consent' ) {
				continue;
			}
			if ( ( entry[ 1 ] !== 'update' && entry[ 1 ] !== 'default' ) || ! entry[ 2 ] || typeof entry[ 2 ] !== 'object' ) {
				continue;
			}
			if ( ! Object.prototype.hasOwnProperty.call( entry[ 2 ], key ) ) {
				continue;
			}
			value = String( entry[ 2 ][ key ] ).toLowerCase();
			if ( value !== 'granted' && value !== 'denied' ) {
				continue;
			}
			if ( entry[ 1 ] === 'update' ) {
				return value;
			}
			if ( ! fallback ) {
				fallback = value;
			}
		}
		return fallback;
	}

	function readGoogleConsent() {
		var out = { ad_user_data: '', ad_personalization: '' };
		var i, key;
		for ( i = 0; i < CONSENT_KEYS.length; i++ ) {
			key = CONSENT_KEYS[ i ];
			try {
				out[ key ] = consentFromTagData( key ) || consentFromDataLayer( key );
			} catch ( e ) {
				out[ key ] = '';
			}
		}
		return out;
	}

	function fieldValue( values, key, consent ) {
		var touches;
		if ( CONSENT_KEYS.indexOf( key ) > -1 ) {
			return consent[ key ] || '';
		}
		if ( key === 'form_page' ) {
			return currentPage();
		}
		if ( key === 'form_page_title' ) {
			return ( doc && doc.title ) ? String( doc.title ).slice( 0, 200 ) : '';
		}
		if ( key === 'touch_first' || key === 'touch_last' ) {
			touches = ( values.touches && typeof values.touches === 'object' ) ? values.touches : {};
			return JSON.stringify( touches[ key === 'touch_first' ? 'first' : 'last' ] || {} );
		}
		if ( key === 'last_touch' ) {
			return JSON.stringify( values.last || {} );
		}
		if ( key === 'first_referrer' || key === 'first_landing_page' || key === 'first_seen' ) {
			return values[ key ] || '';
		}
		return ( values.first && values.first[ key ] ) || ( values.last && values.last[ key ] ) || '';
	}

	function fillMap( map, values, consent ) {
		var filled = 0;
		var name, nodes, i;
		for ( name in map ) {
			if ( ! Object.prototype.hasOwnProperty.call( map, name ) ) {
				continue;
			}
			nodes = doc.querySelectorAll( 'input[name="' + name + '"]' );
			for ( i = 0; i < nodes.length; i++ ) {
				nodes[ i ].value = fieldValue( values, map[ name ], consent );
				filled++;
			}
		}
		return filled;
	}

	function fillFields( values ) {
		if ( ! doc || typeof doc.querySelectorAll !== 'function' ) {
			return 0;
		}
		var consent = readGoogleConsent();
		var filled = fillMap( config.fields, values, consent );
		if ( config.extendedAttribution ) {
			filled += fillMap( EXTENDED_FIELDS, values, consent );
		}
		return filled;
	}

	var api = {
		init: function () {
			var values = apply( load(), queryValues() );
			persist( values );
			fillFields( values );
			return values;
		},
		getValues: function () {
			return load();
		},
		refresh: function () {
			return fillFields( load() );
		},
		consentGranted: function () {
			consentInMemory = true;
			var values = load();
			persist( values );
			fillFields( values );
			return values;
		},
		hasConsent: function () {
			return consentOk();
		},
		readConsent: function () {
			return readGoogleConsent();
		},
		configure: function ( extra ) {
			config = merge( DEFAULTS, merge( root && root.cidrcConfig ? root.cidrcConfig : {}, extra || {} ) );
			consentInMemory = false;
			return config;
		},
		getConfig: function () {
			return config;
		},
		classifyChannel: classifyChannel
	};

	if ( root ) {
		root.cidrc = api;
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = api;
	}

	// Runs in the capture phase, so before Contact Form 7's own submit handler on
	// the form builds its FormData. Consent may have changed since the page loaded.
	function onSubmit( event ) {
		var form = event ? event.target : null;
		try {
			if ( form && typeof form.querySelector === 'function' && form.querySelector( 'input[name^="cidrc_"]' ) ) {
				api.refresh();
			}
		} catch ( e ) {
			// Never block the submission.
		}
	}

	if ( doc && typeof doc.addEventListener === 'function' ) {
		doc.addEventListener( 'cidrc:consent', function () {
			api.consentGranted();
		} );
		doc.addEventListener( 'submit', onSubmit, true );
		doc.addEventListener( 'wpcf7init', api.refresh );
		doc.addEventListener( 'wpcf7invalid', api.refresh );
		doc.addEventListener( 'wpcf7spam', api.refresh );
		doc.addEventListener( 'wpcf7mailsent', api.refresh );
		doc.addEventListener( 'wpcf7submit', api.refresh );

		if ( doc.readyState === 'loading' ) {
			doc.addEventListener( 'DOMContentLoaded', function () {
				api.init();
			} );
		} else {
			api.init();
		}
	}
}( typeof window !== 'undefined' ? window : null, typeof document !== 'undefined' ? document : null ) );
