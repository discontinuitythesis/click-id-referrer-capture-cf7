/**
 * Click ID & Referrer Capture for Contact Form 7.
 *
 * Reads advertising click identifiers and UTM parameters from the query string,
 * keeps a first touch and a last touch record in a first party cookie, and fills
 * the hidden fields the plugin adds to Contact Form 7 forms.
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
			cidrc_last_touch: 'last_touch'
		}
	};

	var config = merge( DEFAULTS, root && root.cidrcConfig ? root.cidrcConfig : {} );
	var consentInMemory = false;

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

	function load() {
		return parse( readCookie( config.cookieName ) ) ||
			parse( storeGet( 'localStorage', config.cookieName ) ) ||
			parse( storeGet( 'sessionStorage', config.cookieName + '_pending' ) ) ||
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

	function apply( values, incoming ) {
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
		if ( ! values.first_referrer && doc && doc.referrer ) {
			values.first_referrer = cap( doc.referrer );
		}
		if ( ! values.first_landing_page && root && root.location ) {
			values.first_landing_page = cap( ( root.location.pathname || '' ) + ( root.location.search || '' ) );
		}
		var now = new Date().toISOString();
		if ( ! values.first_seen ) {
			values.first_seen = now;
		}
		values.last_seen = now;
		return values;
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
		if ( ! consentOk() ) {
			storeSet( 'sessionStorage', config.cookieName + '_pending', json );
			return false;
		}
		writeCookie( config.cookieName, json, config.cookieDays );
		storeSet( 'localStorage', config.cookieName, json );
		return true;
	}

	function fieldValue( values, key ) {
		if ( key === 'last_touch' ) {
			return JSON.stringify( values.last || {} );
		}
		if ( key === 'first_referrer' || key === 'first_landing_page' || key === 'first_seen' ) {
			return values[ key ] || '';
		}
		return ( values.first && values.first[ key ] ) || ( values.last && values.last[ key ] ) || '';
	}

	function fillFields( values ) {
		if ( ! doc || typeof doc.querySelectorAll !== 'function' ) {
			return 0;
		}
		var filled = 0;
		var name, nodes, i;
		for ( name in config.fields ) {
			if ( ! Object.prototype.hasOwnProperty.call( config.fields, name ) ) {
				continue;
			}
			nodes = doc.querySelectorAll( 'input[name="' + name + '"]' );
			for ( i = 0; i < nodes.length; i++ ) {
				nodes[ i ].value = fieldValue( values, config.fields[ name ] );
				filled++;
			}
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
		configure: function ( extra ) {
			config = merge( DEFAULTS, merge( root && root.cidrcConfig ? root.cidrcConfig : {}, extra || {} ) );
			consentInMemory = false;
			return config;
		},
		getConfig: function () {
			return config;
		}
	};

	if ( root ) {
		root.cidrc = api;
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = api;
	}

	if ( doc && typeof doc.addEventListener === 'function' ) {
		doc.addEventListener( 'cidrc:consent', function () {
			api.consentGranted();
		} );
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
