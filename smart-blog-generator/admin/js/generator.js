/**
 * Smart Blog Generator — admin generator page JS
 *
 * Handles the AJAX form submission from the generator page, manages UI state
 * (spinner, button label, disabling inputs during generation), and renders
 * success/error notices in the result area.
 *
 * Depends on:
 *   – jQuery (bundled with WordPress)
 *   – SBG global object injected via wp_localize_script() containing:
 *       SBG.ajaxUrl  string  URL to wp-admin/admin-ajax.php
 *       SBG.nonce    string  wp_create_nonce( 'sbg_generate_post' )
 *       SBG.i18n     object  Localised strings
 */

/* global SBG */
( function ( $ ) {
	'use strict';

	// Cache jQuery selectors resolved once on DOMContentLoaded.
	var $form    = $( '#sbg-generator-form' );
	var $submit  = $( '#sbg-submit' );
	var $spinner = $( '.sbg-spinner' );
	var $result  = $( '#sbg-result' );

	// Guard: if the form doesn't exist on this page, exit early.
	if ( ! $form.length ) {
		return;
	}

	// ── Form submission ───────────────────────────────────────────────────────

	$form.on( 'submit', function ( event ) {
		event.preventDefault();

		// ── Client-side validation ────────────────────────────────────────────
		var topic   = $.trim( $( '#sbg_topic' ).val() );
		var keyword = $.trim( $( '#sbg_keyword' ).val() );

		if ( '' === topic || '' === keyword ) {
			showError( 'Topic and Target Keyword are required fields.' );
			return;
		}

		// ── UI: enter loading state ───────────────────────────────────────────
		setLoading( true );
		clearResult();

		// ── Build FormData payload ────────────────────────────────────────────
		var data = {
			action   : 'sbg_generate_post',
			nonce    : SBG.nonce,
			sbg_topic   : topic,
			sbg_keyword : keyword,
			sbg_tone    : $( '#sbg_tone' ).val()    || 'informational',
			sbg_category: $( '#sbg_category' ).val() || '0'
		};

		// ── AJAX request ──────────────────────────────────────────────────────
		$.ajax( {
			url     : SBG.ajaxUrl,
			type    : 'POST',
			data    : data,
			timeout : 120000, // 120 s — generation can be slow on the first request.

			success: function ( response ) {
				setLoading( false );

				if ( response.success ) {
					handleSuccess( response.data );
				} else {
					// WordPress sends error data as response.data.message or a
					// plain string depending on how wp_send_json_error was called.
					var msg = ( response.data && response.data.message )
						? response.data.message
						: SBG.i18n.error;
					showError( msg );
				}
			},

			error: function ( jqXHR, textStatus ) {
				setLoading( false );

				var msg;
				if ( 'timeout' === textStatus ) {
					msg = 'The request timed out. The server may still be generating the post — check Draft Posts in a moment.';
				} else {
					msg = SBG.i18n.error + ' (' + textStatus + ')';
				}
				showError( msg );
			}
		} );
	} );

	// ── Success handler ───────────────────────────────────────────────────────

	/**
	 * Renders the success notice and optional image warning into #sbg-result.
	 *
	 * @param {Object} data  Response data from wp_send_json_success().
	 *   data.post_title    string  The post's H1 title.
	 *   data.edit_url      string  URL to wp-admin post editor.
	 *   data.has_image     bool    Whether a featured image was attached.
	 *   data.image_notice  string  Non-empty when image attachment failed.
	 */
	function handleSuccess( data ) {
		var title   = escapeHtml( data.post_title   || '' );
		var editUrl = escapeHtml( data.edit_url      || '' );

		// Primary success notice.
		var html = '<div class="notice notice-success" style="margin:0;">'
			+ '<p>'
			+ '<strong>' + title + '</strong> '
			+ 'has been saved as a draft. '
			+ ( editUrl
				? '<a href="' + editUrl + '">' + SBG.i18n.editPost + '</a>'
				: '' )
			+ '</p>'
			+ '</div>';

		// Secondary image notice (warning, non-fatal).
		if ( data.image_notice ) {
			html += '<div class="notice notice-warning" style="margin:8px 0 0;">'
				+ '<p>' + escapeHtml( data.image_notice ) + '</p>'
				+ '</div>';
		}

		$result.html( html ).show();

		// Scroll the result into view in case the form is long.
		scrollToResult();
	}

	// ── UI helpers ────────────────────────────────────────────────────────────

	/**
	 * Shows an error notice in the result area.
	 *
	 * @param {string} message Error message to display.
	 */
	function showError( message ) {
		var html = '<div class="notice notice-error" style="margin:0;">'
			+ '<p>' + escapeHtml( message ) + '</p>'
			+ '</div>';

		$result.html( html ).show();
		scrollToResult();
	}

	/** Clears and hides the result area. */
	function clearResult() {
		$result.html( '' ).hide();
	}

	/**
	 * Toggles the loading state of the form.
	 *
	 * @param {boolean} loading  True to show spinner and disable inputs.
	 */
	function setLoading( loading ) {
		if ( loading ) {
			$submit.prop( 'disabled', true ).text( SBG.i18n.generating );
			$spinner.css( 'display', 'inline-block' );

			// Disable all form inputs to prevent double-submission.
			$form.find( 'input, select' ).prop( 'disabled', true );
		} else {
			$submit.prop( 'disabled', false ).text( SBG.i18n.generate );
			$spinner.hide();

			// Re-enable all form inputs.
			$form.find( 'input, select' ).prop( 'disabled', false );
		}
	}

	/** Smoothly scrolls to the result area. */
	function scrollToResult() {
		if ( $result.length ) {
			$( 'html, body' ).animate(
				{ scrollTop: $result.offset().top - 80 },
				300
			);
		}
	}

	// ── Security helper ───────────────────────────────────────────────────────

	/**
	 * Escapes a string for safe insertion as HTML text content.
	 *
	 * Prevents XSS from API response values that are included in notices.
	 * This is a defence-in-depth measure — the PHP side also escapes output.
	 *
	 * @param  {string} str  Raw string.
	 * @return {string}      HTML-escaped string.
	 */
	function escapeHtml( str ) {
		if ( 'string' !== typeof str ) {
			return '';
		}
		return str
			.replace( /&/g,  '&amp;' )
			.replace( /</g,  '&lt;'  )
			.replace( />/g,  '&gt;'  )
			.replace( /"/g,  '&quot;' )
			.replace( /'/g,  '&#039;' );
	}

} )( jQuery );
