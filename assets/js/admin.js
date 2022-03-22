/* global GeneratorObject */

jQuery( document ).ready( function( $ ) {
	const logSelector = '#kagg-generator-log';
	let index, chunkSize, number, data;

	function clearMessages() {
		$( logSelector ).html( '' );
	}

	function showMessage( message ) {
		$( logSelector ).append( `<p>${message}</p>` );
	}

	function showErrorMessage( response ) {
		showMessage( response.responseText.replace( /^(.+?)<!DOCTYPE.+$/gs, '$1' ).replace( /\n/gs, '<br />' ) );
	}

	function cacheFlush() {
		// noinspection JSUnresolvedVariable
		data = {
			action: GeneratorObject.cacheFlushAction,
			nonce: GeneratorObject.cacheFlushNonce
		};

		$.post( {
			url: GeneratorObject.cacheFlushAjaxUrl,
			data: data,
		} )
			.done( function( response ) {
				showMessage( response.data );
			} )
			.fail( function( response ) {
				showErrorMessage( response );
			} );
	}

	function ajax( data ) {
		if ( data.index >= data.number ) {
			cacheFlush();

			return;
		}

		// noinspection JSUnresolvedVariable
		$.post( {
			url: GeneratorObject.generateAjaxUrl,
			data: data,
		} )
			.done( function( response ) {
				showMessage( response.data );

				data.index += data.chunkSize;
				ajax( data );
			} )
			.fail( function( response ) {
				showErrorMessage( response );
			} );
	}

	$( '#kagg-generate-button' ).on( 'click', function( event ) {
		event.preventDefault();

		clearMessages();

		index = 0;
		chunkSize = parseInt( $( '#chunk_size' ).val() );
		number = parseInt( $( '#number' ).val() );

		// noinspection JSUnresolvedVariable
		data = {
			action: GeneratorObject.generateAction,
			data: JSON.stringify( $( 'form#kagg-generator-settings' ).serializeArray() ),
			index: index,
			chunkSize: chunkSize,
			number: number,
			nonce: GeneratorObject.generateNonce
		};

		ajax( data, index, chunkSize, number );
	} );
} );
