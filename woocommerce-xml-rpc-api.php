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

/**
 * The WC_XML-RPC_API global object
 * @name $wc_xml-rpc_api
 * @global WC_XML-RPC_API $GLOBALS['wc_xml_rpc_api']
 */
$GLOBALS['wc_xml_rpc_api'] = new WC_XML_RPC_API();


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
 */
class WC_XML_RPC_API {


	/** plugin version number */
	const VERSION = '0.1';

	/** message to return on successful API call */
	const SUCCESS_MESSAGE = 'OK';


	/**
	 * Initializes the plugin
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// add custom XML-RPC methods
		add_filter( 'xmlrpc_methods', array( $this, 'add_xml_rpc_methods' ) );
	}


	/**
	 * Add custom XML-RPC methods
	 *
	 * @param array $methods list of available XML-RPC methods
	 * @return array list of available XML-RPC met0hods
	 */
	public function add_xml_rpc_methods( $methods ) {

		// order tracking
		$methods['wc.updateOrderTracking'] = array( $this, 'update_order_tracking' );

		// order status
		$methods['wc.updateOrderStatus'] = array( $this, 'update_order_status' );

		return $methods;
	}


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
	 *
	 *
	 * @param array $params associative array of tracking info to set
	 * @throws Exception missing or invalid information
	 * @return string|IXR_Error
	 */
	public function update_order_tracking( $params ) {

		try {

			// authenticate the username/password and make sure the user can edit posts
			$this->authenticate_request( $params );

			// follow WP core xml-rpc pattern
			do_action( 'xmlrpc_call', 'wc.updateOrderTracking' );

			// required parameters
			if ( ! isset( $params['order_number'] ) )      throw new Exception( __( 'Missing required parameter, "order_number"', 'wc-xml-rpc-api' ), 500 );
			if ( ! isset( $params['tracking_provider'] ) ) throw new Exception( __( 'Missing required parameter, "tracking_provider"', 'wc-xml-rpc-api' ), 500 );
			if ( ! isset( $params['tracking_number'] ) )   throw new Exception( __( 'Missing required parameter, "tracking_number"', 'wc-xml-rpc-api' ), 500 );

			// required tracking information
			$order_number      = $params['order_number'];
			$tracking_provider = $params['tracking_provider'];
			$tracking_number   = $params['tracking_number'];

			// optional tracking information
			$date_shipped             = ( isset( $params['date_shipped'] ) ) ? $params['date_shipped'] : null;
			$custom_tracking_provider = ( isset( $params['custom_tracking_provider'] ) ) ? $params['custom_tracking_provider'] : null;
			$custom_tracking_link     = ( isset( $params['custom_tracking_link'] ) ) ? $params['custom_tracking_link'] : null;

			// get the WC_Order object
			$order = $this->get_order( $order_number );

			if ( ! is_object( $order ) )
				throw new Exception( sprintf( __( 'Order with number "%s" not found', 'wc-xml-rpc-api' ), $order_number ), 404 );

			// test mode, don't make any changes, just return success
			if ( isset ( $params['test_mode'] ) && $params['test_mode'] ) return self::SUCCESS_MESSAGE;

			// update the tracking data
			update_post_meta( $order->id, '_tracking_provider', woocommerce_clean( $tracking_provider ) );
			update_post_meta( $order->id, '_tracking_number',   woocommerce_clean( $tracking_number ) );

			// update optional tracking data
			if ( $date_shipped )             update_post_meta( $order->id, '_date_shipped', strtotime( $date_shipped ) );
			if ( $custom_tracking_provider ) update_post_meta( $order->id, '_custom_tracking_link', woocommerce_clean( $custom_tracking_link ) );
			if ( $custom_tracking_link )     update_post_meta( $order->id, '_custom_tracking_provider', woocommerce_clean( $custom_tracking_provider ) );

			// return success
			return self::SUCCESS_MESSAGE;

		} catch ( Exception $e ) {

			// return an XML-RPC error
			return new IXR_Error( $e->getCode(), $e->getMessage() );
		}
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
	 * @param array $params associative array of order info to set
	 * @throws Exception missing or invalid information
	 * @return string|IXR_Error
	 */
	public function update_order_status( $params ) {

		try {

			// authenticate the username/password and make sure the user can edit posts
			$this->authenticate_request( $params );

			// follow WP core xml-rpc pattern
			do_action( 'xmlrpc_call', 'wc.updateOrderStatus' );

			// required parameters
			if ( ! isset( $params['order_number'] ) ) throw new Exception( __( 'Missing required parameter, "order_number"', 'wc-xml-rpc-api' ), 500 );
			if ( ! isset( $params['order_status'] ) ) throw new Exception( __( 'Missing required parameter, "order_status"', 'wc-xml-rpc-api' ), 500 );

			// required order info
			$order_number = $params['order_number'];
			$order_status = $params['order_status'];

			// optional order info
			$message = ( ! empty( $params['message'] ) ) ? $params['message'] : '';

			// make sure order status is valid
			$valid_order_statuses = get_terms( 'shop_order_status', array( 'fields' => 'names', 'hide_empty' => 0 ) );
			if ( ! in_array( $order_status, $valid_order_statuses ) )
				throw new Exception( sprintf( __( '"%s" is not a valid order status.', 'wc-xml-rpc-api' ), $order_status ), 500 );

			// get the WC_Order object
			$order = $this->get_order( $order_number );
			if ( ! is_object( $order ) )
				throw new Exception( sprintf( __( 'Order with number "%s" not found', 'wc-xml-rpc-api' ), $order_number ), 404 );

			// test mode, don't make any changes, just return success
			if ( isset ( $params['test_mode'] ) && $params['test_mode'] ) return self::SUCCESS_MESSAGE;

			// update order status if different than the current status
			if ( $order_status != $order->status )
				$order->update_status( $order_status, $message );

			// return success
			return self::SUCCESS_MESSAGE;

		} catch ( Exception $e ) {

			// return an XML-RPC error
			return new IXR_Error( $e->getCode(), $e->getMessage() );
		}
	}


	/**
	 * Authenticate the XML-RPC request and ensure the user can edit posts
	 *
	 * == Required ==
	 * + 'username' - WP username with at least contributor role
	 * + 'password' - password the WP username
	 *
	 * @param array $params associative array
	 * @throws Exception invalid username/password, or insufficient permissions
	 */
	private function authenticate_request( $params) {
		global $wp_xmlrpc_server;

		$username = ( isset( $params['username'] ) ) ? $params['username'] : null;
		$password = ( isset( $params['password'] ) ) ? $params['password'] : null;

		// verify credentials
		if ( ! $wp_xmlrpc_server->login( $username, $password ) )
			throw new Exception( $wp_xmlrpc_server->error->message, $wp_xmlrpc_server->error->code );

		/**
		 * check for edit_posts capability (requires contributor role) which seems safer than requiring
		 * manage_woocommerce_orders which requires Administrator role
		 */
		if ( ! current_user_can( 'edit_posts' ) )
			throw new Exception( __( 'You are not allowed access to details about orders.' ), 403 );
	}


	/**
	 * Get an order by order ID / custom order number.  Returns the order if
	 * one can be found, or false
	 *
	 * @param string|int $order_number customer order number or Order ID
	 * @return object|bool WC_Order order object if found, otherwise false
	 */
	private function get_order( $order_number ) {

		// try to get order by order ID
		$order = get_post( $order_number );

		// found order by ID
		if ( ! is_null( $order ) )
			return new WC_Order( $order->ID );

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

		return false;
	}


} // end \WC_XML_RPC_API class
