/**
 * AfconWave — WooCommerce Blocks checkout integration script.
 * Registers the AfconWave payment method on the Block-based Cart/Checkout.
 */
( function ( wc, wp ) {
    const { registerPaymentMethod } = wc.wcBlocksRegistry;
    const { decodeEntities }        = wp.htmlEntities;
    const { createElement }         = wp.element;

    // Data is hydrated by get_payment_method_data() in the PHP class.
    // The key pattern is always `{gateway_id}_data`.
    const settings = wc.wcSettings.getSetting( 'afconwave_data', {} );

    const Label   = () => createElement( 'span', null, decodeEntities( settings.title       || 'AfconWave' ) );
    const Content = () => createElement( 'span', null, decodeEntities( settings.description || '' ) );

    registerPaymentMethod( {
        name: 'afconwave',
        label: createElement( Label, null ),
        content: createElement( Content, null ),
        edit: createElement( Content, null ),
        canMakePayment: () => true,
        ariaLabel: decodeEntities( settings.title || 'AfconWave' ),
        supports: { features: settings.supports || [ 'products' ] },
    } );
} )( window.wc, window.wp );
