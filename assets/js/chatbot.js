(function () {
	if ( typeof GimaChatbot === 'undefined' ) {
		return;
	}

	function sessionId() {
		var key = 'gima_chatbot_session';
		var id;
		try {
			id = localStorage.getItem( key );
		} catch ( e ) {
			id = null;
		}
		if ( ! id ) {
			id = 'gc_' + Math.random().toString( 36 ).slice( 2 ) + Date.now();
			try {
				localStorage.setItem( key, id );
			} catch ( e ) {}
		}
		return id;
	}

	function el( tag, attrs, html ) {
		var e = document.createElement( tag );
		if ( attrs ) {
			for ( var k in attrs ) {
				e.setAttribute( k, attrs[ k ] );
			}
		}
		if ( html !== undefined ) {
			e.innerHTML = html;
		}
		return e;
	}

	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	// Minimal, safe Markdown -> HTML renderer for AI replies (links, bold,
	// italics, inline code, lists, headings, pipe tables). Input is
	// HTML-escaped first, so nothing the model outputs can inject real markup;
	// the only href scheme allowed is http(s), so the link step can't turn
	// escaped text back into a javascript: URL or similar.
	// Handles [label](url), <url> autolinks, and bare URLs in a single pass
	// (one regex, tried left-to-right per position) so a URL already consumed
	// by one form is never re-matched and wrapped again by another.
	var LINK_PATTERN = /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)|&lt;(https?:\/\/[^\s&]+)&gt;|(https?:\/\/[^\s<]+)/g;

	function linkify( text ) {
		return text.replace( LINK_PATTERN, function ( match, mdLabel, mdUrl, autoUrl, bareUrl ) {
			var url = mdUrl || autoUrl || bareUrl;
			var label = mdLabel || url;
			return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
		} );
	}

	function renderInline( text ) {
		// The model sometimes writes a literal <br> for a line break inside a
		// table cell (the only place a line break can't be a plain newline).
		// escapeHtml() turned it into text ("&lt;br&gt;"); turn it back into
		// a real line break here rather than leaving it visible as text.
		text = text.replace( /&lt;br\s*\/?&gt;/gi, '<br>' );
		text = linkify( text );
		text = text.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' );
		text = text.replace( /(^|[^*])\*([^*\s][^*]*?)\*(?!\*)/g, '$1<em>$2</em>' );
		text = text.replace( /`([^`]+)`/g, '<code>$1</code>' );
		return text;
	}

	function renderTable( lines ) {
		var rows = lines.filter( function ( l ) {
			return ! /^\s*\|[\s:|-]+\|\s*$/.test( l );
		} );
		if ( ! rows.length ) {
			return '';
		}
		var cells = rows.map( function ( r ) {
			return r.trim().replace( /^\||\|$/g, '' ).split( '|' ).map( function ( c ) {
				return c.trim();
			} );
		} );
		var html = '<div class="gima-chatbot-table-wrap"><table class="gima-chatbot-table">';
		cells.forEach( function ( row, idx ) {
			var tag = idx === 0 ? 'th' : 'td';
			html += '<tr>' + row.map( function ( c ) {
				return '<' + tag + '>' + renderInline( c ) + '</' + tag + '>';
			} ).join( '' ) + '</tr>';
		} );
		html += '</table></div>';
		return html;
	}

	function renderMarkdown( raw ) {
		var lines = escapeHtml( raw ).split( '\n' );
		var html = '';
		var i = 0;

		while ( i < lines.length ) {
			var line = lines[ i ];

			if ( /^\s*\|.*\|\s*$/.test( line ) ) {
				var tableLines = [];
				while ( i < lines.length && /^\s*\|.*\|\s*$/.test( lines[ i ] ) ) {
					tableLines.push( lines[ i ] );
					i++;
				}
				html += renderTable( tableLines );
				continue;
			}

			if ( /^\s*[-*]\s+/.test( line ) ) {
				var ulItems = [];
				while ( i < lines.length && /^\s*[-*]\s+/.test( lines[ i ] ) ) {
					ulItems.push( lines[ i ].replace( /^\s*[-*]\s+/, '' ) );
					i++;
				}
				html += '<ul>' + ulItems.map( function ( it ) {
					return '<li>' + renderInline( it ) + '</li>';
				} ).join( '' ) + '</ul>';
				continue;
			}

			if ( /^\s*\d+\.\s+/.test( line ) ) {
				var olItems = [];
				while ( i < lines.length && /^\s*\d+\.\s+/.test( lines[ i ] ) ) {
					olItems.push( lines[ i ].replace( /^\s*\d+\.\s+/, '' ) );
					i++;
				}
				html += '<ol>' + olItems.map( function ( it ) {
					return '<li>' + renderInline( it ) + '</li>';
				} ).join( '' ) + '</ol>';
				continue;
			}

			if ( /^\s*#{1,6}\s+/.test( line ) ) {
				var level = Math.min( line.match( /^\s*(#{1,6})/ )[ 1 ].length + 2, 6 );
				var headingText = line.replace( /^\s*#{1,6}\s+/, '' );
				html += '<h' + level + '>' + renderInline( headingText ) + '</h' + level + '>';
				i++;
				continue;
			}

			if ( line.trim() === '' ) {
				i++;
				continue;
			}

			var paraLines = [ line ];
			i++;
			while (
				i < lines.length &&
				lines[ i ].trim() !== '' &&
				! /^\s*\|.*\|\s*$/.test( lines[ i ] ) &&
				! /^\s*[-*]\s+/.test( lines[ i ] ) &&
				! /^\s*\d+\.\s+/.test( lines[ i ] )
			) {
				paraLines.push( lines[ i ] );
				i++;
			}
			html += '<p>' + paraLines.map( renderInline ).join( '<br>' ) + '</p>';
		}

		return html;
	}

	function buildChips( questions, onPick ) {
		var wrap = el( 'div', { class: 'gima-chatbot-chips' } );
		questions.forEach( function ( q ) {
			var chip = el( 'button', { type: 'button', class: 'gima-chatbot-chip-btn' } );
			chip.textContent = q;
			chip.addEventListener( 'click', function () {
				onPick( q );
			} );
			wrap.appendChild( chip );
		} );
		return wrap;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.getElementById( 'gima-chatbot-root' );
		if ( ! root ) {
			return;
		}

		var suggestions = Array.isArray( GimaChatbot.suggestions ) ? GimaChatbot.suggestions : [];

		var launcher = el( 'button', { id: 'gima-chatbot-launcher', 'aria-label': 'Open chat' }, '💬' );
		var panel = el( 'div', { id: 'gima-chatbot-panel', style: 'display:none;' } );
		panel.innerHTML =
			'<div id="gima-chatbot-header">' +
				'<span></span>' +
				'<button id="gima-chatbot-close" aria-label="Close chat">&times;</button>' +
			'</div>' +
			'<div id="gima-chatbot-messages"></div>' +
			'<form id="gima-chatbot-form">' +
				'<input type="text" id="gima-chatbot-input" placeholder="Type your question..." autocomplete="off" />' +
				'<button type="submit">Send</button>' +
			'</form>';
		panel.querySelector( '#gima-chatbot-header span' ).textContent = GimaChatbot.title;

		root.appendChild( panel );
		root.appendChild( launcher );

		var messagesEl = panel.querySelector( '#gima-chatbot-messages' );
		var formEl = panel.querySelector( '#gima-chatbot-form' );
		var inputEl = panel.querySelector( '#gima-chatbot-input' );
		var opened = false;
		var starterChipsEl = null;

		function addMessage( role, text ) {
			var msg = el( 'div', { class: 'gima-chatbot-msg gima-chatbot-' + role } );
			if ( role === 'assistant' ) {
				msg.innerHTML = renderMarkdown( text );
			} else {
				msg.textContent = text;
			}
			messagesEl.appendChild( msg );
			messagesEl.scrollTop = messagesEl.scrollHeight;
			return msg;
		}

		function setMessage( node, text ) {
			node.innerHTML = renderMarkdown( text );
			messagesEl.scrollTop = messagesEl.scrollHeight;
		}

		function removeStarterChips() {
			if ( starterChipsEl && starterChipsEl.parentNode ) {
				starterChipsEl.parentNode.removeChild( starterChipsEl );
			}
			starterChipsEl = null;
		}

		function sendMessage( text ) {
			text = ( text || '' ).trim();
			if ( ! text ) {
				return;
			}
			removeStarterChips();
			addMessage( 'user', text );
			inputEl.value = '';
			var typingNode = addMessage( 'assistant', '…' );

			fetch( GimaChatbot.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': GimaChatbot.nonce,
				},
				body: JSON.stringify( { message: text, session_id: sessionId() } ),
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( data ) {
					setMessage( typingNode, data.reply || 'Sorry, something went wrong.' );
				} )
				.catch( function () {
					setMessage( typingNode, 'Sorry, something went wrong. Please try again.' );
				} );
		}

		// Plain `overflow: hidden` on body does not reliably block touch-scrolling
		// on mobile browsers, so pin the body in place at its current scroll
		// offset instead, and restore the scroll position when closing.
		var lockedScrollY = 0;

		function lockBodyScroll() {
			lockedScrollY = window.scrollY || window.pageYOffset || 0;
			document.body.style.position = 'fixed';
			document.body.style.top = ( -lockedScrollY ) + 'px';
			document.body.style.left = '0';
			document.body.style.right = '0';
			document.body.style.width = '100%';
			document.body.classList.add( 'gima-chatbot-body-lock' );
		}

		function unlockBodyScroll() {
			document.body.classList.remove( 'gima-chatbot-body-lock' );
			document.body.style.position = '';
			document.body.style.top = '';
			document.body.style.left = '';
			document.body.style.right = '';
			document.body.style.width = '';
			window.scrollTo( 0, lockedScrollY );
		}

		function openPanel() {
			opened = true;
			panel.style.display = 'flex';
			lockBodyScroll();
			hideTeaser();
			if ( ! messagesEl.hasChildNodes() ) {
				addMessage( 'assistant', GimaChatbot.greeting );
				if ( suggestions.length ) {
					starterChipsEl = buildChips( suggestions, sendMessage );
					messagesEl.appendChild( starterChipsEl );
				}
			}
		}

		function closePanel() {
			opened = false;
			panel.style.display = 'none';
			unlockBodyScroll();
		}

		launcher.addEventListener( 'click', function () {
			if ( opened ) {
				closePanel();
			} else {
				openPanel();
			}
		} );

		panel.querySelector( '#gima-chatbot-close' ).addEventListener( 'click', closePanel );

		formEl.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			sendMessage( inputEl.value );
		} );

		// --- Teaser popup: shown above the launcher while the chat is closed,
		// nudging visitors with a greeting + quick questions they can tap. ---
		var TEASER_DISMISS_KEY = 'gima_chatbot_teaser_dismissed';
		var teaserEl = null;

		function teaserDismissed() {
			try {
				return sessionStorage.getItem( TEASER_DISMISS_KEY ) === '1';
			} catch ( e ) {
				return false;
			}
		}

		function dismissTeaserPermanently() {
			try {
				sessionStorage.setItem( TEASER_DISMISS_KEY, '1' );
			} catch ( e ) {}
		}

		function hideTeaser() {
			if ( teaserEl ) {
				teaserEl.style.display = 'none';
			}
		}

		function showTeaser() {
			if ( opened || teaserDismissed() || ! GimaChatbot.teaserText ) {
				return;
			}

			teaserEl = el( 'div', { id: 'gima-chatbot-teaser' } );
			teaserEl.innerHTML =
				'<button id="gima-chatbot-teaser-close" aria-label="Dismiss">&times;</button>' +
				'<div id="gima-chatbot-teaser-text"></div>';
			teaserEl.querySelector( '#gima-chatbot-teaser-text' ).textContent = GimaChatbot.teaserText;

			if ( suggestions.length ) {
				teaserEl.appendChild( buildChips( suggestions.slice( 0, 3 ), function ( q ) {
					dismissTeaserPermanently();
					openPanel();
					sendMessage( q );
				} ) );
			}

			teaserEl.querySelector( '#gima-chatbot-teaser-close' ).addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				dismissTeaserPermanently();
				hideTeaser();
			} );

			teaserEl.addEventListener( 'click', function () {
				openPanel();
			} );

			root.insertBefore( teaserEl, launcher );
		}

		setTimeout( showTeaser, 1800 );
	} );
})();
