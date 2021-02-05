<?php
/**
 * Plugin Name: Filter WooCommerce Orders by Payment Method
 * Plugin URI: http://skyverge.com/
 * Description: Filters WooCommerce orders by the payment method used :)
 * Author: SkyVerge
 * Author URI: http://www.skyverge.com/
 * Version: 1.0.0
 * Text Domain: wc-filter-orders-by-payment
 *
 * Copyright: (c) 2017-2020 SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Filter-Orders-By-Payment
 * @author    SkyVerge, Cnohall
 * @category  Admin
 * @copyright Copyright (c) 2017-2020, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

// fire it up!
add_action( 'plugins_loaded', 'wc_filter_orders_by_payment' );


/** 
 * Main plugin class
 *
 * @since 1.0.0
 */
class WC_Filter_Orders_By_Address {

	const VERSION = '1.0.0';
	/** @var WC_Filter_Orders_By_Address single instance of this plugin */
	protected static $instance;

	/**
	 * Main plugin class constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		if ( is_admin() ) {

			// add bulk order filter for exported / non-exported orders
			add_action( 'restrict_manage_posts', array( $this, 'filter_orders_by_address') , 20 );
			add_filter( 'request',               array( $this, 'filter_orders_by_address_query' ) );		
		}
	}

	/** Plugin methods ***************************************/
	/**
	 * Add bulk filter for orders by payment method
	 *
	 * @since 1.0.0
	 */
	public function filter_orders_by_address() {
		$orders = get_option('blockonomics_orders');
		global $typenow;
		if ( 'shop_order' === $typenow ) {
			// get all payment methods, even inactive ones
			$gateways = WC()->payment_gateways->payment_gateways();
			?>
			<input type='name' placeholder='Filter by bitcoin address' name='filter_by_address'>
			<select name="_shop_order_payment_method" id="dropdown_shop_order_payment_method">
				<option value="">
					<?php esc_html_e( 'All Payment Methods', 'wc-filter-orders-by-payment' ); ?>
				</option>
				<?php foreach ( $gateways as $id => $gateway ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>" <?php echo esc_attr( isset( $_GET['_shop_order_payment_method'] ) ? selected( $id, $_GET['_shop_order_payment_method'], false ) : '' ); ?>>
					<?php echo esc_html( $gateway->get_title() ); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<?php
		}
	}

	/**
	 * Process bulk filter order payment method
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars query vars without filtering
	 * @return array $vars query vars with (maybe) filtering
	 */
	public function filter_orders_by_address_query( $vars ) {
		global $typenow;
		if ( 'shop_order' === $typenow && isset( $_GET['filter_by_address'] ) && ! empty( $_GET['filter_by_address'] ) ) {
			$vars['meta_key']   = 'btc_address';
			$vars['meta_value'] = wc_clean( $_GET['filter_by_address'] );
		}
		return $vars;
	}


	public function filter_orders_by_payment_method_query( $vars ) {
		global $typenow;

		if ( 'shop_order' === $typenow && isset( $_GET['_shop_order_payment_method'] ) && ! empty( $_GET['_shop_order_payment_method'] ) ) {
			$vars['meta_key']   = '_payment_method';
			$vars['meta_value'] = wc_clean( $_GET['_shop_order_payment_method'] );
		}

		return $vars;
	}



	/** Helper methods ***************************************/
	/**
	 * Main WC_Filter_Orders_By_Payment Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.0.0
	 * @see wc_filter_orders_by_payment()
	 * @return WC_Filter_Orders_By_Address
 	*/
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


}

/**
 * Returns the One True Instance of WC_Filter_Orders_By_Address
 *
 * @since 1.0.0
 * @return WC_Filter_Orders_By_Address
 */
function wc_filter_orders_by_payment() {
    return WC_Filter_Orders_By_Address::instance();
}



		// $address = trim($_POST['address']); 
		// echo 'You searched for an order on the address: '.$address.'<br>';
		// $matches = 0;
		// foreach ($orders as $order) {
		// 	foreach ($order as $details) {
		// 		echo "<a href='post.php?post=".$details['order_id']."&action=edit'>Order#: ".$details['order_id']."</a><br>";
		// 		if ($details['address'] == $address){
		// 			$matches = 1;

		// 		}
		// 	}
		// }
		// if ($matches == 0){
		// 	echo 'No orders matched';
		// }