<?php
defined( 'ABSPATH' ) or exit;
add_action( 'plugins_loaded', 'wc_filter_orders_by_bitcoin_address' );

class WC_Filter_Orders_By_Address {
	protected static $instance;
	public function __construct() {
		if ( is_admin() ) {
			// add bulk order filter for exported / non-exported orders
			add_action( 'restrict_manage_posts', array( $this, 'filter_orders_by_address') , 20 );
			add_filter( 'request',               array( $this, 'filter_orders_by_address_query' ) );		
		}
	}
	public function filter_orders_by_address() {
		global $typenow;
		if ( 'shop_order' === $typenow ) {
			?>
			<input type='name' placeholder='Filter by bitcoin address' name='filter_by_address'>
			<?php
		}
	}
	public function filter_orders_by_address_query( $vars ) {
		global $typenow;
		if ( 'shop_order' === $typenow && isset( $_GET['filter_by_address'] ) && ! empty( $_GET['filter_by_address'] ) ) {
			$vars['meta_key']   = 'btc_address';
			$vars['meta_value'] = wc_clean( $_GET['filter_by_address'] );
		}
		return $vars;
	}
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

function wc_filter_orders_by_bitcoin_address() {
    return WC_Filter_Orders_By_Address::instance();
}
