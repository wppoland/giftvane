/**
 * Block cart and checkout: add "12.50 left after this order" to the gift card
 * coupon chip. The text comes from the Store API cart response
 * (extensions.giftvane.remaining, keyed by card code), computed on the server
 * from the live balance.
 */
( function () {
	const checkout = window.wc && window.wc.blocksCheckout;
	const data = window.wp && window.wp.data;

	if ( ! checkout || ! checkout.registerCheckoutFilters || ! data ) {
		return;
	}

	const normalize = ( code ) => String( code ).toUpperCase().replace( /[^A-Z0-9-]/g, '' );

	checkout.registerCheckoutFilters( 'giftvane', {
		coupons: ( coupons ) => {
			const cart = data.select( 'wc/store/cart' ).getCartData();
			const left = ( cart && cart.extensions && cart.extensions.giftvane && cart.extensions.giftvane.remaining ) || {};

			return coupons.map( ( coupon ) => {
				const text = left[ normalize( coupon.code ) ];

				return text ? { ...coupon, label: coupon.label + ' (' + text + ')' } : coupon;
			} );
		},
	} );
} )();
