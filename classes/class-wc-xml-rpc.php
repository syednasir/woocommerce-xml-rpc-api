<?php
/**
 * WooCommerce XML-RPC API
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce XML-RPC API to newer
 * versions in the future. If you wish to customize WooCommerce XML-RPC API for your
 * needs please refer to https://github.com/skyverge/woocommerce-xml-rpc-api for more information.
 *
 * @package     WC-XML-RPC-API
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Abstract class for implementing XML-RPC
 *
 * Thanks to MZAweb for the inspiration (http://wordpress.stackexchange.com/a/39480/19669)
 *
 * @since 0.1
 */
abstract class WC_XML_RPC {


	/** @var array public API calls available */
	protected $calls = array();

	/** @var string XML-RPC method namespace prefix */
	private $namespace = 'wc';


	/**
	 * Setups ReflectionClass and adds public method of the instantiating class to the list of available WP XML-RPC methods
	 *
	 * @since 0.1
	 */
	public function __construct() {

		$reflector = new ReflectionClass( $this );

		foreach ( $reflector->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {

			if ( $method->isUserDefined() && $method->getDeclaringClass()->name != get_class() )
				$this->calls[] = $method->name;
		}

		add_filter( 'xmlrpc_methods', array( $this, 'add_methods' ) );
	}


	/**
	 * Add available API methods to the list of available WP XML-RPC methods, using the namespace 'wc.<method_name>'
	 *
	 * @since 0.1
	 */
	public function add_methods( $methods ) {

		foreach ( $this->calls as $call ) {

			$methods[ "{$this->namespace}.{$call}" ] = array( $this, 'handle_request' );
		}

		return $methods;
	}


	/**
	 * Handles XML-RPC requests by:
	 *
	 * 1) verifying the WP username/password are valid
	 * 2) verifying the WP username is allowed to edit_posts
	 * 3) checking if the requested method name is a valid & callable method in the instantiating class
	 * 4) calling the requested method
	 *
	 * == Required ==
	 * + 'username' - WP username with at least contributor role
	 * + 'password' - password the WP username
	 *
	 * @param array $params XML-RPC parameters
	 * @throws Exception invalid username/password, or insufficient permissions
	 * @return string|IXR_Error success message, optional return data, or IXR_Error
	 */
	public function handle_request( $params) {
		global $wp_xmlrpc_server;

		$username = ( isset( $params['username'] ) ) ? $params['username'] : null;
		$password = ( isset( $params['password'] ) ) ? $params['password'] : null;

		// verify credentials
		if ( ! $wp_xmlrpc_server->login( $username, $password ) )
			return $wp_xmlrpc_server->error;

		// check for edit_posts capability (requires contributor role) which seems safer than requiring
		// manage_woocommerce_orders which requires Administrator role
		if ( ! current_user_can( 'edit_posts' ) )
			return new IXR_Error( 403, __( 'You are not allowed access to details about orders.' ) );

		// get the called method
		$called_method = $this->get_called_method();

		if ( ! is_callable( array( $this, $called_method ) ) )
			return new IXR_Error( 405, sprintf( __( 'Method "%s" not allowed', 'wc-xml-rpc-api' ), $called_method ) );

		try {

			// follow WP core xml-rpc pattern
			do_action( 'xmlrpc_call', "{$this->namespace}.{$called_method}" );

			// call method with passed XML-RPC params
			return call_user_func_array( array( $this, $called_method ), array( $params ) );

		} catch ( Exception $e ) {

			return new IXR_Error( $e->getCode(), $e->getMessage() );
		}
	}


	/**
	 * Helper method to get the name of the called XML-RPC method
	 *
	 * @since 0.1
	 */
	private function get_called_method() {
		global $wp_xmlrpc_server;

		$call = $wp_xmlrpc_server->message->methodName;

		list( $namespace, $called_method ) = explode( '.', $call );

		return $called_method;
	}


} // end \WC_XML_RPC class
