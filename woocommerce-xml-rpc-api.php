<?php
/**
 * Plugin Name: WooCommerce XML-RPC API
 * Plugin URI: https://github.com/skyverge/woocommerce-xml-rpc-api
 * Description: Adds XML-RPC methods that allow you to update Orders and Products for your WooCommerce store
 * Author: SkyVerge
 * Author URI: http://www.skyverge.com
 * Version: 0.1
 * Text Domain: wc-xml-rpc-api
 * Domain Path: /languages/
 *
 * Copyright: (c) 2013 SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-XML-RPC-API
 * @author    SkyVerge
 * @category  API
 * @copyright Copyright (c) 2013, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// load abstract class
require( 'classes/class-wc-xml-rpc.php' );

/**
 * # WooCommerce XML-RPC API
 *
 * ## Plugin Overview
 *
 * This plugin adds new XML-RPC methods to set various information for WooCommerce Orders and Products.
 *
 * ## Methods
 *
 * + `wc.updateOrderTracking` - adds/updates tracking information that is displayable to the customer using the WooCommerce Order Tracking extension
 * + `wc.updateOrderStatus` - updates the order status
 *
 * @since 0.1
 * @extends WC_XML_RPC
 */
class WC_XML_RPC_API extends WC_XML_RPC {


	/** plugin version number */
	const VERSION = '0.1';

	/** message to return on successful API call */
	const SUCCESS_MESSAGE = 'OK';


	/**
	 * Update the tracking information for an order using these parameters:
	 *
	 * == Required ==
	 * + 'username' - WP username with at least contributor role
	 * + 'password' - password the WP username
	 * + 'order_number' - the order ID or custom order number of the order to update tracking for
	 * + 'tracking_provider' - a valid WooCommerce Shipment Tracking provider ID (e.g. fedex,ups,usps)
	 * + 'tracking_number' - the tracking number for the order
	 *
	 * == Optional ==
	 * + 'date_shipped' - the date the order was shipped, in YYYY-MM-DD format
	 * + 'custom_tracking_provider' - a custom tracking provider to use instead. Make sure 'tracking_provider' is set to a blank string
	 * + 'custom_tracking_link' - the link the customer can use to track their order for the custom provider
	 * + 'order_status' - a valid order status slug to update the order to
	 *
	 *
	 * @since 0.1
	 * @param array $data XML-RPC parameters
	 * @throws Exception missing or invalid information
	 * @return string success message
	 */
	public function updateOrderTracking( $data ) {

		// set params in key = required? format
		$params = array(
			'order_number'             => true,
			'tracking_provider'        => true,
			'tracking_number'          => true,
			'date_shipped'             => false,
			'custom_tracking_provider' => false,
			'custom_tracking_link'     => false,
			'order_status'             => false,
		);

		// get posted data
		list( $order_number, $tracking_provider, $tracking_number, $date_shipped, $custom_tracking_provider, $custom_tracking_link, $order_status ) = $this->get_data( $params, $data );

		// get the WC_Order object
		$order = $this->get_order( $order_number );

		// test mode, don't make any changes, just return success
		if ( isset ( $params['test_mode'] ) && $params['test_mode'] ) return self::SUCCESS_MESSAGE;

		// update the tracking data
		update_post_meta( $order->id, '_tracking_provider', woocommerce_clean( $tracking_provider ) );
		update_post_meta( $order->id, '_tracking_number',   woocommerce_clean( $tracking_number ) );

		// update optional tracking data
		if ( $date_shipped )             update_post_meta( $order->id, '_date_shipped', strtotime( $date_shipped ) );
		if ( $custom_tracking_provider ) update_post_meta( $order->id, '_custom_tracking_link', woocommerce_clean( $custom_tracking_link ) );
		if ( $custom_tracking_link )     update_post_meta( $order->id, '_custom_tracking_provider', woocommerce_clean( $custom_tracking_provider ) );

		// optionally update the order status
		if ( $order_status ) $this->update_order_status( $order, $order_status );

		// return success
		return self::SUCCESS_MESSAGE;
	}


	/**
	 * Update the order status for an order using these parameters:
	 *
	 * == Required ==
	 * + 'username' - WP username with at least contributor role
	 * + 'password' - password the WP username
	 * + 'order_number' - the order ID or custom order number of the order to update tracking for
	 * + 'order_status' - the new status to set the order to
	 *
	 * == Optional ==
	 * + 'message' - an optional message to include with the order status update
	 *
	 * @since 0.1
	 * @param array $data XML-RPC parameters
	 * @throws Exception missing or invalid information
	 * @return string success message
	 */
	public function updateOrderStatus( $data ) {

		// set params in key => required? format
		$params = array(
			'order_number' => true,
			'order_status' => true,
			'message'      => false,
		);

		// get posted data
		list( $order_number, $order_status, $message ) = $this->get_data( $params, $data );

		// get the WC_Order object
		$order = $this->get_order( $order_number );

		// test mode, don't make any changes, just return success
		if ( isset ( $params['test_mode'] ) && $params['test_mode'] ) return self::SUCCESS_MESSAGE;

		// update status
		$this->update_order_status( $order, $order_status, $message );

		// return success
		return self::SUCCESS_MESSAGE;
	}


	/**
	 * Helper method to update an order's status
	 *
	 * @since 0.1
	 * @param object $order WC_Order object
	 * @param string $status the new status to update the order to
	 * @param string $message an optional message to include with the status update
	 * @throws Exception invalid order status
	 */
	private function update_order_status( $order, $status, $message = '' ) {

		// make sure order status is valid
		$valid_order_statuses = get_terms( 'shop_order_status', array( 'fields' => 'names', 'hide_empty' => 0 ) );

		if ( ! in_array( $status, $valid_order_statuses ) )
			throw new Exception( sprintf( __( '"%s" is not a valid order status.', 'wc-xml-rpc-api' ), $status ), 500 );

		// update order status if different than the current status
		if ( $status != $order->status )
			$order->update_status( $status, $message );
	}


	/**
	 * Get an order by order ID / custom order number.  Returns the order if
	 * one can be found, or false
	 *
	 * @since 0.1
	 * @param string|int $order_number customer order number or Order ID
	 * @throws Exception
	 * @return object WC_Order order object if found, otherwise false
	 */
	private function get_order( $order_number ) {

		// try to get order by order ID
		$order = new WC_Order( $order_number );

		// found order by ID
		if ( is_object( $order ) )
			return $order;

		// try to search for the order by custom order number
		$posts = get_posts( array(
			'numberposts' => 1,
			'meta_key'    => '_order_number',
			'meta_value'  => $order_number,
			'post_type'   => 'shop_order',
			'post_status' => 'publish',
			'fields'      => 'ids'
		) );

		// get the single order ID
		list( $order_id ) = ( empty( $posts ) ) ? null : $posts;

		// last try
		if ( null !== $order_id )
			return new WC_Order( $order_id );

		throw new Exception( sprintf( __( 'Order with number "%s" not found', 'wc-xml-rpc-api' ), $order_number ), 404 );
	}



	/**
	 * Helper method to parse XML-RPC parameters and check for required or invalid parameters
	 *
	 * @since 0.1
	 * @param array $keys associative array in the format: param name => required?
	 * @param array $params XML-RPC parameters
	 * @throws Exception if a required parameter is missing
	 * @return array parameters in a simple array for easy use with list()
	 */
	private function get_data( $keys, $params ) {

		$data = array();

		foreach ( $keys as $key => $required ) {

			if ( $required ) {

				if ( ! isset( $params[ $key ] ) )
					throw new Exception( sprintf( __( 'Missing required parameter, "%s"', 'wc-xml-rpc-api' ), $key ), 500 );

				$data[] = $params[ $key ];

			} else {

				$data[] = ( isset( $params[ $key ] ) ) ? $params[ $key ] : '';
			}
		}

		return $data;
	}


} // end \WC_XML_RPC_API class


/**
 * The WC_XML-RPC_API global object
 * @name $wc_xml-rpc_api
 * @global WC_XML-RPC_API $GLOBALS['wc_xml_rpc_api']
 */
$GLOBALS['wc_xml_rpc_api'] = new WC_XML_RPC_API();
