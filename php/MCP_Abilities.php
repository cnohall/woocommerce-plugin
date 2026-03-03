<?php

/**
 * WooCommerce MCP (Model Context Protocol) abilities for the Blockonomics plugin.
 *
 * Registers abilities via the WordPress Abilities API so that AI clients
 * (e.g. Claude Desktop / Claude Code) can query Blockonomics payment data
 * through the WooCommerce MCP server (WooCommerce 10.3+).
 *
 * @see https://developer.woocommerce.com/docs/features/mcp/
 */
class Blockonomics_MCP_Abilities {

    public static function register_category() {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        wp_register_ability_category(
            'blockonomics',
            array(
                'label' => __( 'Blockonomics', 'blockonomics-bitcoin-payments' ),
            )
        );
    }

    public static function register() {
        // WordPress Abilities API is only available in WP 6.8+ / WC 10.3+
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        wp_register_ability(
            'blockonomics/get-payment-status',
            array(
                'label'       => __( 'Get Blockonomics Payment Status', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Returns the current crypto payment record(s) for a WooCommerce order — amount expected, amount paid, transaction ID, and payment status.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id' => array(
                            'type'        => 'integer',
                            'description' => 'WooCommerce order ID.',
                        ),
                    ),
                    'required' => array( 'order_id' ),
                ),
                'output_schema' => array(
                    'type'  => 'object',
                    'properties' => array(
                        'payments' => array(
                            'type'  => 'array',
                            'items' => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'crypto'           => array( 'type' => 'string' ),
                                    'address'          => array( 'type' => 'string' ),
                                    'payment_status'   => array( 'type' => 'integer', 'description' => '0=pending, 1=unconfirmed, 2=confirmed' ),
                                    'expected_satoshi' => array( 'type' => 'integer' ),
                                    'expected_fiat'    => array( 'type' => 'number' ),
                                    'currency'         => array( 'type' => 'string' ),
                                    'paid_satoshi'     => array( 'type' => 'integer' ),
                                    'paid_fiat'        => array( 'type' => 'number' ),
                                    'txid'             => array( 'type' => 'string' ),
                                ),
                            ),
                        ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'permission_check' ),
                'execute_callback'    => array( __CLASS__, 'get_payment_status' ),
                'meta' => array( 'mcp' => array( 'public' => true ) ),
            )
        );

        wp_register_ability(
            'blockonomics/get-order-by-address',
            array(
                'label'       => __( 'Get Order by Crypto Address', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Looks up a Blockonomics payment record by the crypto address assigned to an order.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'address' => array(
                            'type'        => 'string',
                            'description' => 'The crypto address (Bitcoin, BCH, or USDT) assigned to the order.',
                        ),
                    ),
                    'required' => array( 'address' ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id'         => array( 'type' => 'integer' ),
                        'crypto'           => array( 'type' => 'string' ),
                        'address'          => array( 'type' => 'string' ),
                        'payment_status'   => array( 'type' => 'integer' ),
                        'expected_satoshi' => array( 'type' => 'integer' ),
                        'expected_fiat'    => array( 'type' => 'number' ),
                        'currency'         => array( 'type' => 'string' ),
                        'paid_satoshi'     => array( 'type' => 'integer' ),
                        'paid_fiat'        => array( 'type' => 'number' ),
                        'txid'             => array( 'type' => 'string' ),
                        'error'            => array( 'type' => 'string' ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'permission_check' ),
                'execute_callback'    => array( __CLASS__, 'get_order_by_address' ),
                'meta' => array( 'mcp' => array( 'public' => true ) ),
            )
        );

        wp_register_ability(
            'blockonomics/get-order-by-txid',
            array(
                'label'       => __( 'Get Order by Transaction ID', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Looks up a Blockonomics payment record by the on-chain transaction ID.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'txid' => array(
                            'type'        => 'string',
                            'description' => 'The on-chain transaction ID.',
                        ),
                    ),
                    'required' => array( 'txid' ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'order_id'         => array( 'type' => 'integer' ),
                        'crypto'           => array( 'type' => 'string' ),
                        'address'          => array( 'type' => 'string' ),
                        'payment_status'   => array( 'type' => 'integer' ),
                        'expected_satoshi' => array( 'type' => 'integer' ),
                        'expected_fiat'    => array( 'type' => 'number' ),
                        'currency'         => array( 'type' => 'string' ),
                        'paid_satoshi'     => array( 'type' => 'integer' ),
                        'paid_fiat'        => array( 'type' => 'number' ),
                        'txid'             => array( 'type' => 'string' ),
                        'error'            => array( 'type' => 'string' ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'permission_check' ),
                'execute_callback'    => array( __CLASS__, 'get_order_by_txid' ),
                'meta' => array( 'mcp' => array( 'public' => true ) ),
            )
        );

        wp_register_ability(
            'blockonomics/get-enabled-cryptos',
            array(
                'label'       => __( 'Get Enabled Blockonomics Cryptos', 'blockonomics-bitcoin-payments' ),
                'description' => __( 'Returns the list of crypto currencies currently enabled in the Blockonomics plugin settings.', 'blockonomics-bitcoin-payments' ),
                'category'    => 'blockonomics',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => new stdClass(), // no parameters required
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'enabled_cryptos' => array(
                            'type'        => 'array',
                            'items'       => array( 'type' => 'string' ),
                            'description' => 'Crypto codes that are enabled, e.g. ["btc", "usdt"].',
                        ),
                        'store_uid_configured' => array(
                            'type'        => 'boolean',
                            'description' => 'Whether a Blockonomics store UID is configured (widget checkout mode).',
                        ),
                    ),
                ),
                'permission_callback' => array( __CLASS__, 'permission_check' ),
                'execute_callback'    => array( __CLASS__, 'get_enabled_cryptos' ),
                'meta' => array( 'mcp' => array( 'public' => true ) ),
            )
        );
    }

    /**
     * All abilities require manage_woocommerce capability.
     */
    public static function permission_check() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Returns all payment records for a given order_id.
     */
    public static function get_payment_status( $args ) {
        global $wpdb;

        $order_id = intval( $args['order_id'] );
        $table    = $wpdb->prefix . 'blockonomics_payments';

        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d", $order_id ),
            ARRAY_A
        );

        if ( $rows === false ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'blockonomics-bitcoin-payments' ) );
        }

        return array( 'payments' => $rows ?: array() );
    }

    /**
     * Returns the payment record for a given crypto address.
     */
    public static function get_order_by_address( $args ) {
        global $wpdb;

        $table = $wpdb->prefix . 'blockonomics_payments';
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE address = %s", sanitize_text_field( $args['address'] ) ),
            ARRAY_A
        );

        if ( $row === false ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'blockonomics-bitcoin-payments' ) );
        }

        if ( empty( $row ) ) {
            return array( 'error' => __( 'No order found for this address.', 'blockonomics-bitcoin-payments' ) );
        }

        return $row;
    }

    /**
     * Returns the payment record for a given transaction ID.
     */
    public static function get_order_by_txid( $args ) {
        global $wpdb;

        $table = $wpdb->prefix . 'blockonomics_payments';
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE txid = %s", sanitize_text_field( $args['txid'] ) ),
            ARRAY_A
        );

        if ( $row === false ) {
            return new WP_Error( 'db_error', __( 'Database query failed.', 'blockonomics-bitcoin-payments' ) );
        }

        if ( empty( $row ) ) {
            return array( 'error' => __( 'No order found for this txid.', 'blockonomics-bitcoin-payments' ) );
        }

        return $row;
    }

    /**
     * Returns enabled crypto codes and whether a store UID is configured.
     */
    public static function get_enabled_cryptos( $args ) {
        $raw     = get_option( 'blockonomics_enabled_cryptos', '' );
        $enabled = array_filter( array_map( 'trim', explode( ',', $raw ) ) );

        return array(
            'enabled_cryptos'      => array_values( $enabled ),
            'store_uid_configured' => ! empty( get_option( 'blockonomics_store_uid', '' ) ),
        );
    }
}
