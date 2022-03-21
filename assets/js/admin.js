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

	function ajax( data ) {
		if ( data.index >= data.number ) {
			return;
		}

		$.post( {
			url: GeneratorObject.ajaxUrl,
			data: data,
		} )
			.done( function( response ) {
				showMessage( response.data );

				data.index += data.chunkSize;
				ajax( data );
			} )
			.fail( function( response ) {
					showMessage( response.responseText.replace( /^(.+?)<!DOCTYPE.+$/gs, '$1' ).replace( /\n/gs, '<br />' ) );
				}
			);
	}

	$( '#kagg-generate-button' ).on( 'click', function( event ) {
		event.preventDefault();

		clearMessages();

		index = 0;
		chunkSize = parseInt( $( '#chunk_size' ).val() );
		number = parseInt( $( '#number' ).val() );

		data = {
			action: 'kagg-generate',
			data: JSON.stringify( $( 'form#kagg-generator-settings' ).serializeArray() ),
			index: index,
			chunkSize: chunkSize,
			number: number,
			nonce: GeneratorObject.nonce
		};

		ajax( data, index, chunkSize, number );
	} );
} );
