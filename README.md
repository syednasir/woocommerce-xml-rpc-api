# WooCommerce XML-RPC API

A simple XML-RPC API for WooCommerce

## Description


Provides an easy to use XML-RPC API to update order information in WooCommerce. Right now there are two API calls available,
with more planned in the future. If you have a specific API call that you need, please open up an issue (or even better,
a pull request :) so that it can be added.

## Installation

1. Upload the entire `/woocommerce-xml-rpc-api` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. That's it!

## How to Use

1. Setup a new WordPress user with at least the `contributor` role (all API calls require at least the `edit_posts` capability)
2. Setup your favorite XML-RPC client, [XML RPC Client](https://itunes.apple.com/us/app/xml-rpc-client/id424424203?mt=12) on OSX is a good one.
3. Use `http://www.your-website.com/xmlrpc.php` as the endpoint URI.
4. Send a request to one of the methods listed in the next section with the proper parameters.

##  List of Methods

### `wc.updateOrderTracking`
Update the tracking information for an order. This method works in combination with the [WooCommerce Shipment Tracking](http://www.woothemes.com/products/shipment-tracking/) extension.

#### Required
* `username` - WP username with at least contributor role
* `password` - WP password
* `order_number` - the order ID or custom order number of the order
* `tracking_provider` - a valid WooCommerce Shipment Tracking provider ID (e.g. `fedex`,`ups`,`usps`)
* `tracking_number` - the tracking number for the order

#### Optional
* `date_shipped` - the date the order was shipped, in YYYY-MM-DD format
* `custom_tracking_provide` - a custom tracking provider to use instead. Make sure that the `tracking_provider` parameter is set to a blank string
* `custom_tracking_link` - the link the customer can use to track their order for the custom provider
* `order_status` - a valid order status slug to update the order to

### `wc.updateOrderStatus`
Update the status of an order

#### Required
* `username` - WP username with at least contributor role
* `password` - WP password
* `order_number` - the order ID or custom order number of the order
* `order_status` - a valid order status slug to update the order to (e.g. `completed`)

#### Optional
* `message` - a message to include with the order status update


## Changelog

### 0.1
 * First version
