<?php 
/**
 * Plugin Name: Woocommerce Order Profit into DataLayer
 * Description: This plugin adds an the order total gross profit value to the DataLayer as a discrete event name. Cost price will be retrieved from a custom field of each product.
 * Author: Erik Molenaar
 * Author URI: https://erikmolenaar.nl
 * Version: 1.0
 */

// Exit if accessed directly
if ( ! defined ( 'ABSPATH' ) ) {
	die;
}

// Function added to order-received page
add_action( 'woocommerce_thankyou', 'emnl_wc_prder_profit_into_datalayer' );
function emnl_wc_prder_profit_into_datalayer( $order_id ) {

	// Get order object
	$order = new WC_Order( $order_id );

	// Get order created datetime
	$order_created_datetime = $order->get_date_created(); 

	// Check if a succesful, paid order was retrieved
	if ( $order_created_datetime ) {

		// Convert datetimes to timestamp
		$order_created_datetime_timezone = $order_created_datetime->getTimezone(); 
		$order_created_timestamp = $order_created_datetime->getTimestamp(); 

		// Get current datetime and its timestamp
		$current_datetime = new WC_DateTime(); 
		$current_datetime->setTimezone( $order_created_datetime_timezone );
		$current_timestamp = $current_datetime->getTimestamp();
		
		// Get age of order creation and payment
		$order_created_age_in_seconds = $current_timestamp - $order_created_timestamp;

		// Get order paid (if available)
		$order_paid_datetime = $order->get_date_paid(); 

		// At this point we presume there is no order paid datetime available
		$order_paid_age_in_seconds = false;
		
		if ( $order_paid_datetime ) {

			$order_paid_datetime_timezone = $order_paid_datetime->getTimezone(); 
			$order_paid_timestamp = $order_paid_datetime->getTimestamp(); 
			$order_paid_age_in_seconds = $current_timestamp - $order_paid_timestamp;
			
		}

		// Set allowed age 
		$allowed_age_minutes = 39;

		// Check allowed age (of order creation or order payment)
		if ( ( $order_created_age_in_seconds <= ( $allowed_age_minutes * 60 ) ) || ( $order_paid_age_in_seconds && ( $order_paid_age_in_seconds <= ( $allowed_age_minutes * 60 ) ) ) ) {

			// Get order totals
			$order_total = $order->get_total();
			$order_shipping = $order->get_shipping_total();
			$order_tax = $order->get_total_tax();

			// Check if values exist
			if ( $order_total ) {

				// Set order shipping to 0 if empty
				if ( ! is_numeric ( $order_shipping ) ) {
					$order_shipping = 0;	
				}

				// Set order tax to 0 if empty
				if ( ! is_numeric ( $order_tax ) ) {
					$order_tax = 0;	
				}

				// Calculating order total without tax and shipping costs
				$order_total = $order_total - $order_shipping - $order_tax ;
				
				// Get order items
				$items = $order->get_items();

				// Check if items were succesfully retrieved
				if ( $items ) {

					// At this point, there is no cost price (yet). We'll add up from here
					$order_cost_price = 0;

					// Loop thru every product item getting its product costs
					foreach ( $items as $item ) {

						// Getting product ID
						$product_id  = $item['product_id'];

						// For variations, get the variation product ID instead
						if ( $item['variation_id'] > 0 ) { 
							$product_id  = $item['variation_id'];
						}

						// Check if there is a product ID to work with
						if ( $product_id !== false ) {

							$product_cost_price_cf = 'uniliving_inkoopprijs';

							// Getting custom field value for cost price of this product ID
							$product_cost_price = get_post_meta( $product_id, $product_cost_price_cf, true );

							// Check if a cost price was retrieved and it is numeric
							if ( $product_cost_price && is_numeric( $product_cost_price ) ) {

								// Adding the product cost price to order cost price total for this product and its ordered qty
								$order_cost_price = $order_cost_price + ( $product_cost_price * $item['quantity'] );

							}

						}

					}

					// Calculate order profit. If negative set to 0.
					$order_profit = max( $order_total - $order_cost_price, 0 );

					$eventname_1 = 'EpsilonSigmaAlpha';
					$eventname_2 = 'TimeoutValue';
				
					// Build dataLayer HTML output
					$html  = '<script type="text/javascript">';
					$html .=	'window.dataLayer = window.dataLayer || [];';
					$html .=	'window.dataLayer.push({';
					$html .=		'\'event\': \'' . $eventname_1 .'\',';
					$html .=		'\'' . $eventname_2 . '\' : \'' . $order_profit . '\'';
					$html .=	'});';
					$html .= '</script>';
					
					// Echo dataLayer HTML
					echo $html;

				}

			}

		}

	}

}