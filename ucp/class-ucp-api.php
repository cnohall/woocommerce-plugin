<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * UCP API Handler.
 */
class UCP_API
{

    const NAMESPACE = 'ucp/v1';

    public function register_routes()
    {
        register_rest_route(self::NAMESPACE, '/discovery', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_discovery'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/search', array(
            'methods' => 'POST',
            'callback' => array($this, 'search_products'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/checkout', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_checkout'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/discounts', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_discounts'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE, '/checkout/(?P<id>[a-zA-Z0-9%=_-]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_checkout'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'validate_callback' => function ($param, $request, $key) {
                        return is_string($param);
                    }
                ),
            ),
        ));

        register_rest_route(self::NAMESPACE, '/checkout/(?P<id>[a-zA-Z0-9%=_-]+)/complete', array(
            'methods' => 'POST',
            'callback' => array($this, 'complete_checkout'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'validate_callback' => function ($param, $request, $key) {
                        return is_string($param);
                    }
                ),
            ),
        ));
    }

    public function get_discovery($request)
    {
        return new WP_REST_Response(array(
            'protocol' => 'ucp',
            'version' => '0.1.0',
            'capabilities' => array(
                'shopping.search' => array(
                    'version' => '2026-01-11',
                    'endpoint' => '/ucp/v1/search',
                    'schema' => 'https://ucp.dev/schemas/shopping/search.json',
                    'method' => 'POST',
                ),
                'shopping.checkout' => array(
                    'version' => '2026-01-11',
                    'endpoint' => '/ucp/v1/checkout',
                    'schema' => 'https://ucp.dev/schemas/shopping/checkout.json',
                    'method' => 'POST',
                ),
                'shopping.discounts' => array(
                    'version' => '2026-01-23',
                    'endpoint' => '/ucp/v1/discounts',
                    'method' => 'GET',
                ),
            ),
            'store_info' => array(
                'name' => get_bloginfo('name'),
                'currency' => get_woocommerce_currency(),
            ),
        ), 200);
    }

    public function get_discounts($request)
    {
        $args = array(
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );

        $coupons = get_posts($args);
        $available_coupons = array();

        foreach ($coupons as $post) {
            $coupon = new WC_Coupon($post->ID);
            $available_coupons[] = array(
                'code' => $coupon->get_code(),
                'amount' => $coupon->get_amount(),
                'type' => $coupon->get_discount_type(),
                'description' => $post->post_excerpt ? $post->post_excerpt : 'Discount code: ' . $coupon->get_code(),
            );
        }

        return new WP_REST_Response(array(
            'discounts' => $available_coupons,
            'count' => count($available_coupons)
        ), 200);
    }

    public function search_products($request)
    {
        $params = $request->get_json_params();
        $query = isset($params['query']) ? sanitize_text_field($params['query']) : '';

        if (empty($query)) {
            $final_ids = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
            ));
        } else {
            $queries = array($query);
            if (substr($query, -1) === 's') {
                $queries[] = substr($query, 0, -1);
            }

            $cat_product_ids = get_posts(array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'name',
                        'terms' => $queries,
                    )
                )
            ));

            $search_keyword = $query;
            if (count($queries) > 1) {
                $search_keyword = $queries[1];
            }

            $search_product_ids = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => 10,
                's' => $search_keyword,
                'fields' => 'ids',
            ));

            $all_ids = array_unique(array_merge($cat_product_ids, $search_product_ids));
            $final_ids = array_slice($all_ids, 0, 10);
        }

        if (empty($final_ids)) {
            return new WP_REST_Response(array('items' => array()), 200);
        }

        $products = wc_get_products(array(
            'include' => $final_ids,
            'limit' => -1,
        ));

        $mapper = new UCP_Mapper();

        $items = array();
        foreach ($products as $product) {
            $items[] = $mapper->map_product_to_item($product);
        }

        return new WP_REST_Response(array(
            'items' => $items,
        ), 200);
    }

    public function create_checkout($request)
    {
        $params = $request->get_json_params();
        $store_api = new UCP_Store_API();

        try {
            $items = $params['items'] ?? array();
            $result = $store_api->create_checkout($items);

            return new WP_REST_Response($result, 201);

        } catch (Exception $e) {
            return new WP_Error('checkout_error', $e->getMessage(), array('status' => 500));
        }
    }

    public function update_checkout($request)
    {
        $id_encoded = $request->get_param('id');
        $params = $request->get_json_params();
        $store_api = new UCP_Store_API();

        try {
            $result = $store_api->update_checkout($id_encoded, $params);

            return new WP_REST_Response($result, 200);

        } catch (Exception $e) {
            return new WP_Error('update_error', $e->getMessage(), array('status' => 500));
        }
    }

    public function complete_checkout($request)
    {
        $id_encoded = $request->get_param('id');
        $store_api = new UCP_Store_API();

        try {
            $result = $store_api->complete_checkout($id_encoded);

            return new WP_REST_Response($result, 200);

        } catch (Exception $e) {
            return new WP_Error('complete_error', $e->getMessage(), array('status' => 500));
        }
    }
}
