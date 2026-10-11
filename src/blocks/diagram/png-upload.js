/**
 * Persist rendered SVG as PNG in the Media Library.
 */

import apiFetch from '@wordpress/api-fetch';

/**
 * @param {string} svgMarkup
 * @return {Promise<string>} data:image/png;base64,…
 */
export async function svgToPngDataUrl( svgMarkup ) {
	const svg = svgMarkup.trim();
	if ( ! svg ) {
		throw new Error( 'Empty SVG' );
	}

	const blob = new Blob( [ svg ], { type: 'image/svg+xml;charset=utf-8' } );
	const url = URL.createObjectURL( blob );

	try {
		const img = await loadImage( url );
		const width = Math.max( 1, img.naturalWidth || 800 );
		const height = Math.max( 1, img.naturalHeight || 600 );
		const canvas = document.createElement( 'canvas' );
		canvas.width = width;
		canvas.height = height;
		const ctx = canvas.getContext( '2d' );
		if ( ! ctx ) {
			throw new Error( 'Canvas unavailable' );
		}
		ctx.fillStyle = '#ffffff';
		ctx.fillRect( 0, 0, width, height );
		ctx.drawImage( img, 0, 0, width, height );
		return canvas.toDataURL( 'image/png' );
	} finally {
		URL.revokeObjectURL( url );
	}
}

/**
 * @param {string} src
 * @return {Promise<HTMLImageElement>}
 */
function loadImage( src ) {
	return new Promise( ( resolve, reject ) => {
		const img = new Image();
		img.onload = () => resolve( img );
		img.onerror = () => reject( new Error( 'SVG image load failed' ) );
		img.src = src;
	} );
}

/**
 * @param {Object} opts
 * @param {string} opts.dataUrl
 * @param {string} [opts.title]
 * @param {string} [opts.alt]
 * @param {number} [opts.postId]
 * @param {number} [opts.pngId]
 */
export async function uploadDiagramPng( opts ) {
	return apiFetch( {
		path: '/forwp-advanced-code/v1/diagram/png',
		method: 'POST',
		data: {
			dataUrl: opts.dataUrl,
			title: opts.title || '',
			alt: opts.alt || '',
			postId: opts.postId || 0,
			pngId: opts.pngId || 0,
		},
	} );
}
