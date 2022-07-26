/* global GeneratorObject */

/**
 * @param GeneratorObject.generateAjaxUrl
 * @param GeneratorObject.generateAction
 * @param GeneratorObject.generateNonce
 * @param GeneratorObject.adminAjaxUrl
 * @param GeneratorObject.updateCommentCountsAction
 * @param GeneratorObject.updateCommentCountsNonce
 * @param GeneratorObject.cacheFlushAction
 * @param GeneratorObject.cacheFlushNonce
 * @param GeneratorObject.downloadSQLAction
 * @param GeneratorObject.downloadSQLNonce
 * @param GeneratorObject.deleteAction
 * @param GeneratorObject.deleteNonce
 * @param GeneratorObject.nothingToDo
 * @param GeneratorObject.deleteConfirmation
 * @param GeneratorObject.generating
 * @param GeneratorObject.deleting
 * @param GeneratorObject.updatingCommentCounts
 * @param GeneratorObject.totalTimeUsed
 */
jQuery( document ).ready( function( $ ) {
	const logSelector = '#kagg-generator-log';
	const formSelector = '#kagg-generator-settings';
	let action, index, chunkSize, number, data, startTime;

	function clearMessages() {
		$( logSelector ).html( '' );
	}

	function showMessage( message ) {
		$( logSelector ).append( `<div>${message}</div>` );
	}

	function showSuccessMessage( response ) {
		showMessage( typeof response.data !== 'undefined' ? response.data: response );
	}

	function showErrorMessage( response ) {
		showMessage( response.responseText.replace( /^(.+?)<!DOCTYPE.+$/gs, '$1' ).replace( /\n/gs, '<br />' ) );
	}

	function updateCommentCounts() {
		showMessage( GeneratorObject.updatingCommentCounts );

		data = {
			action: GeneratorObject.updateCommentCountsAction,
			nonce: GeneratorObject.updateCommentCountsNonce
		};

		$.post( {
			url: GeneratorObject.adminAjaxUrl,
			data: data,
		} )
			.done( function( response ) {
				showSuccessMessage( response );
			} )
			.fail( function( response ) {
				showErrorMessage( response );
			} )
			.always( function() {
				cacheFlush();
			});
	}

	function cacheFlush() {
		data = {
			action: GeneratorObject.cacheFlushAction,
			nonce: GeneratorObject.cacheFlushNonce
		};

		$.post( {
			url: GeneratorObject.adminAjaxUrl,
			data: data,
		} )
			.done( function( response ) {
				showSuccessMessage( response );
			} )
			.fail( function( response ) {
				showErrorMessage( response );
			} )
			.always( function() {
				const endTime = performance.now();
				showMessage( GeneratorObject.totalTimeUsed.replace( /%s/, ( ( endTime - startTime ) / 1000 ).toFixed( 3 ) ) );
				maybeDownloadSQL();
			} );
	}

	function maybeDownloadSQL() {
		if ( action !== GeneratorObject.generateAction || ! $( formSelector + ' #sql_1' ).is( ':checked' ) ) {
			return;
		}

		let $form = $( '#downloadForm' );

		if ( $form.length === 0 ) {
			$form = $( '<form>' ).attr( {
				id: 'downloadForm',
				method: 'POST',
				action: GeneratorObject.adminAjaxUrl,
			} ).hide();
			$( 'body' ).append( $form );
		}

		$form.append('<input name="action" value="' + GeneratorObject.downloadSQLAction + '" type="hidden"/>');
		$form.append('<input name="nonce" value="' + GeneratorObject.downloadSQLNonce + '" type="hidden"/>');
		$form.append('<input name="data" value=\'' + JSON.stringify( $( formSelector ).serializeArray() ) + '\' type="hidden"/>');
		$form.submit();
	}

	function generateAjax( data ) {
		$.post( {
			url: GeneratorObject.generateAjaxUrl,
			data: data,
		} )
			.done( function( response ) {
				showSuccessMessage( response );

				data.index += data.chunkSize;

				if ( ! response.success || data.index >= data.number ) {
					if( 'comment' === $( 'select[name="kagg_generator_settings[post_type]"]' ).val() ) {
						updateCommentCounts();
					} else {
						cacheFlush();
					}

					return;
				}

				generateAjax( data );
			} )
			.fail( function( response ) {
				showErrorMessage( response );
			} );
	}

	$( '#kagg-generate-button' ).on( 'click', function( event ) {
		event.preventDefault();

		clearMessages();

		startTime = performance.now();
		action = GeneratorObject.generateAction;
		index = 0;
		chunkSize = parseInt( $( '#chunk_size' ).val() );
		number = parseInt( $( '#number' ).val() );

		if ( number <= 0 ) {
			showMessage( GeneratorObject.nothingToDo );

			return;
		}

		showMessage( GeneratorObject.generating );

		data = {
			action: GeneratorObject.generateAction,
			nonce: GeneratorObject.generateNonce,
			data: JSON.stringify( $( formSelector ).serializeArray() ),
			index: index,
			chunkSize: chunkSize,
			number: number
		};

		generateAjax( data );
	} );

	$( '#kagg-delete-button' ).on( 'click', function( event ) {
		event.preventDefault();

		clearMessages();

		if ( ! confirm( GeneratorObject.deleteConfirmation ) ) {
			return;
		}

		showMessage( GeneratorObject.deleting );

		startTime = performance.now();
		action = GeneratorObject.deleteAction;

		data = {
			action: GeneratorObject.deleteAction,
			nonce: GeneratorObject.deleteNonce
		};

		$.post( {
			url: GeneratorObject.adminAjaxUrl,
			data: data,
		} )
			.done( function( response ) {
				showSuccessMessage( response );
			} )
			.fail( function( response ) {
				showErrorMessage( response );
			} )
			.always( function() {
				updateCommentCounts();
			} );
	} );
} );
