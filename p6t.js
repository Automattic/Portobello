
jQuery( document ).ready( function ($) {
	var $visibles;

	// cargo cult
	$.fn.flash = function (color, duration) {
	   var current = this.css('color');
	   this.animate({ color: color }, duration / 2)
	   .animate({ color: current }, duration / 2)
	   .animate({ color: color }, duration / 2)
	   .animate({ color: current }, duration / 2);
	}

	var p6t = {};
	var ajaxurl = '/wp-admin/admin-ajax.php';
	p6t.$form = $( '#p6t-editor-form' );
	p6t.$translations = $( '.p6t-translation' );
	p6t.$translations.hide();
	p6t.$locale = $( '#p6t-locale' );

	p6t.$low_priority_strings = $( '.low-priority' )
	p6t.$translated_strings = $( 'li.translated' );
	p6t.$translated_strings.hide();

	p6t.$search = $( '#p6t-translations-filter' );
	p6t.$list = $( 'ul.translation-list' );

	if ( typeof p6t.$locale.select2 == 'function' ) {
		p6t.$locale.select2();
	}

	$visibles = $( ":not(#p6t-editor *)").not( ':hidden' ).filter( function() {
		var $this = $( this );
		var clip = $this.css( 'clip' );

		if ( 'auto' !== clip && clip ) {
			return false;
		}

		if ( 'hidden' === $this.css( 'visibility' ) ) {
			return false;
		}

		if ( ! $this.css( 'opacity' ) ) {
			return false;
		}

		return true;
	} );

	p6t.$list.find('li').each(function() {
		var original_string = $(this).text();
		if ( ! $visibles.filter( function() {
			return $( this ).text() === original_string;
		} ).size() ) {
			$(this).remove();
		}
	});

	delete $visibles;

	$('#p6t-loading').hide();
	p6t.$list.show();

	p6t.$locale.change( function() {
		var href = location.href.split( '?' )[0];
		var qs = location.search.replace( '?', '' ).split( '&' );
		var locale_url = href + '?';
		var found_locale_in_qs = false;
		for ( var i = 0; i < qs.length; i++ ) {
			var name = qs[i].split( '=' )[0];
			var value = qs[i].split( '=' )[1];
			if ( 'sa-locale' == name ) {
				locale_url += 'sa-locale=' + $( this ).val();
				found_locale_in_qs = true;
			} else {
				locale_url += qs[i];
			}
			locale_url += '&';
		}
		if ( !found_locale_in_qs )
			locale_url += 'sa-locale=' +  $( this ).val();
		document.location.href = locale_url;
	} );

	$( '#p6t-save' ).click( function() {
		var translations = [];
		p6t.$translations.find( 'textarea' ).each( function() {
    		translations.push( $( this ).val() );
		});

		var saveSuccess = function (jqXHR, textStatus)  {
			$('#p6t-save').siblings('.p6t-translation-success').fadeIn(100).delay(1000).fadeOut(500);
		}

		var saveError = function (jqXHR, textStatus, errorThrown)  {
			$('#p6t-save').parent().find('.p6t-error').fadeIn(100);
			console.log('error saving translation', errorThrown);
		}

		$.ajax( ajaxurl, {
			'type': 'POST',
			'data': {
				'action': 'p6t_save',
				'glotpress_id': p6t.glotpress_id,
				'translations[]': translations,
				'is_plural': p6t.is_plural,
				'locale': p6t.$locale.val()
			},
			'success': saveSuccess,
			'error': saveError
		});
	} );

	$( '#p6t-editor textarea' ).click( function( event ) {
		event.stopPropagation();
	} );

	$( '#p6t-editor li' ).click( function() {
		var original_string = $( this ).attr( 'data-original-singular' );

		var $el = $(":not(#p6t-editor *)").filter(function() {
		    return $(this).text() === original_string;
		});


		if ($el.length) {
			$('html, body').animate(
				{
					scrollTop: $($el).offset().top - 32
				},
				{
					duration: 500,
					complete: function() {
						$($el).flash('yellow', 1000);
					}
				});
		}
	});
		
	$( '#p6t-editor li' ).click( function() {
		$('.p6t-error').hide();
		$( this ).append( p6t.$form );

		data = $( this).data();
		data.translations = [];
		for( var prop in data ) {
			if ( 0 == prop.indexOf( 'currentTranslation' ) )
				data.translations.push( data[prop] );
		}

		p6t.original_string = $( this ).attr( 'data-original-singular' );
		p6t.original_string_plural = $( this ).attr( 'data-original-plural' );
		p6t.is_plural = false;
		p6t.context = $( this ).find( '.p6t-context' ).text();
		p6t.glotpress_id = data.glotpressId;

		// TODO: This finds some matching strings in the markup and highlights them
		// when their counterparts are clicked in the sidebar. It's not efficient or consistent.
		// May wind up just nixing it.
		$( '.highlight' ).removeClass( 'highlight' );
		$( '#contents' ).find( '*' ).map( function() {
			if ( p6t.original_string == $( this ).html() ) {
				$( this ).addClass( 'highlight' );
			}
		} );

		if ( 'plural' == data.type ) {
			p6t.is_plural = true;
			p6t.$translations.each( function( index ) {
				$( this ).find( 'label' ).show(); //just in case it was hidden before;
				$( this ).find( 'textarea' ).val( data.translations[index] );
				$( this ).show();
			});
		} else {
			p6t.$translations.hide();
			//No description needed for singular only translations
			p6t.$translations.first().find( 'label' ).hide();
			p6t.$translations.first().show().find( 'textarea' ).val( data.translations[0] );
			//Clear the value for the rest of the textareas, so we don't submit those.
			p6t.$translations.slice(1).find( 'textarea' ).val( '' );
		}
		p6t.$form.fadeIn( 'fast' );
	} );

	$( '#p6t-toggle-priority' ).click( function() {
		p6t.$search.val('');
		if ( $(this).is(':checked') ) {
			p6t.$low_priority_strings.fadeIn( 'fast' );
		} else {
			p6t.$low_priority_strings.fadeOut( 'fast' );
		}
	} );

	$( '#p6t-toggle-translated' ).click( function() {
		p6t.$search.val('');
		p6t.$list.find( 'li' ).show();
		if ( $(this).is(':checked') ) {
			if ( $( '#p6t-toggle-priority' ).is( 'checked' ) ) {
				p6t.$translated_strings.show();
			} else {
				p6t.$translated_strings.not( p6t.$low_priority_strings ).show();
			}
		} else {
			if ( $( '#p6t-toggle-priority' ).is( 'checked' ) ) {
				p6t.$translated_strings.not( p6t.$low_priority_strings ).hide();
			} else {
				p6t.$translated_strings.hide();
			}
		}
	} );

	jQuery.expr[':'].Contains = function(a, i, m) {
	    return $.trim( ( a.textContent || a.innerText || "" ) ).toUpperCase().indexOf( m[3].toUpperCase() ) !== -1;
	};

	p6t.$search.keyup(function(e) {
	    var filter = $.trim( p6t.$search.val() ).toLowerCase();

   		p6t.$form.hide();
		$( '#p6t-toggle-priority, #p6t-toggle-translated' ).attr( 'checked', 'checked' );

	    if ( filter.length > 0 ) {
	        // Show matches.
	        p6t.$list.find( "li:Contains(" + filter + ")" ).show();
	        p6t.$list.find( "li:not(:Contains(" + filter + "))" ).hide();
	    }
	    else {
	        p6t.$list.find( 'li' ).show();
	    }

	})

} );
