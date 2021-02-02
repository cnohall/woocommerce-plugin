<?php

/**
 * This class is responsible testing the setup that communicates with the Blockonomics API
 */
class Test_Setup
{
    const BASE_URL = 'https://www.blockonomics.co';
    const NEW_ADDRESS_URL = 'https://www.blockonomics.co/api/new_address';
    const SET_CALLBACK_URL = 'https://www.blockonomics.co/api/update_callback';
    const GET_CALLBACKS_URL = 'https://www.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';
    const TEMP_API_KEY_URL = 'https://www.blockonomics.co/api/temp_wallet';

    const BCH_BASE_URL = 'https://bch.blockonomics.co';
    const BCH_NEW_ADDRESS_URL = 'https://bch.blockonomics.co/api/new_address';
    const BCH_SET_CALLBACK_URL = 'https://bch.blockonomics.co/api/update_callback';
    const BCH_GET_CALLBACKS_URL = 'https://bch.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';

    public function __construct()
    {
        $this->api_key = $this->get_api_key();
    }

    // Runs when the Test Setup button is clicked
    // Returns any errors or false if no errors
    public function testSetup()
    {
        // Fetch the crypto to test based on the plugin settings
        $crypto = $this->get_test_setup_crypto();
        $api_key = get_option("blockonomics_api_key");

        //If BCH enabled and API Key is not set: give error
        if (!$api_key && $crypto === 'bch'){
            $error_str = __('Set the API Key or disable BCH', 'blockonomics-bitcoin-payments');
            return $error_str;
        }

        $error_str = $this->check_callback_urls_or_set_one($crypto);
        if (!$error_str)
        {
            //Everything OK ! Test address generation
            $response = $this->new_address($callback_secret, $crypto, true);
            if ($response->response_code!=200){
                $error_str = $response->response_message;
            }
        }
        if($error_str) {
            // Append troubleshooting article to all errors
            $error_str = $error_str . '<p>' . __('For more information, please consult <a href="http://help.blockonomics.co/support/solutions/articles/33000215104-unable-to-generate-new-address" target="_blank">this troubleshooting article</a>', 'blockonomics-bitcoin-payments'). '</p>';
            return $error_str;
        }
        // No errors
        return false;
    }

    public function get_test_setup_crypto() {
        $bch_enabled  = get_option('blockonomics_bch');
        if ($bch_enabled  == '1'){
            return 'bch';
        }else{
            return 'btc';
        }
    }

    public function check_callback_urls_or_set_one($crypto) 
    {
        $response = $this->get_callbacks($crypto);
        $response_body = json_decode(wp_remote_retrieve_body($response));
        //chek the current callback and detect any potential errors
        $error_str = $this->check_callback_response($response, $response_body, $crypto);
        if(!$error_str){
            //if needed, set the callback.
            $error_str = $this->set_callback($response_body, $crypto);
        }
        return $error_str;
    }

    public function get_callbacks($crypto)
    {
        if ($crypto == 'btc'){
            $url = Test_Setup::GET_CALLBACKS_URL;
        }else{
            $url = Test_Setup::BCH_GET_CALLBACKS_URL;
        }
        $response = $this->get($url, $this->api_key);
        return $response;
    }

    public function check_callback_response($response, $response_body, $crypto){
        $error_str = '';
        $error_crypto = strtoupper($crypto).' error: ';
        //TODO: Check This: WE should actually check code for timeout
        if (!wp_remote_retrieve_response_code($response)) {
            $error_str = __($error_crypto.'Your server is blocking outgoing HTTPS calls', 'blockonomics-bitcoin-payments');
        }
        elseif (wp_remote_retrieve_response_code($response)==401)
            $error_str = __($error_crypto.'API Key is incorrect', 'blockonomics-bitcoin-payments');
        elseif (wp_remote_retrieve_response_code($response)!=200)
            $error_str = $error_crypto.$response->data;
        elseif (!isset($response_body) || count($response_body) == 0)
        {
            $error_str = __($error_crypto.'You have not entered an xPub', 'blockonomics-bitcoin-payments');
        }
        return $error_str;
    }

    public function set_callback ($response_body, $crypto){
        if (count($response_body) == 1)
        {
            $error_crypto = strtoupper($crypto).' error: ';
            $error_str = '';
            $response_callback = '';
            $response_address = '';

            if(isset($response_body[0])){
                $response_callback = isset($response_body[0]->callback) ? $response_body[0]->callback : '';
                $response_address = isset($response_body[0]->address) ? $response_body[0]->address : '';
            }
            $callback_secret = get_option('blockonomics_callback_secret');
            $api_url = WC()->api_request_url('WC_Gateway_Blockonomics');
            $callback_url = add_query_arg('secret', $callback_secret, $api_url);

            // Remove http:// or https:// from urls
            $api_url_without_schema = preg_replace('/https?:\/\//', '', $api_url);
            $callback_url_without_schema = preg_replace('/https?:\/\//', '', $callback_url);
            $response_callback_without_schema = preg_replace('/https?:\/\//', '', $response_callback);

            if(!$response_callback || $response_callback == null)
            {
                //No callback URL set, set one 
                $this->update_callback($callback_url, $crypto, $response_address);
            }

            elseif($response_callback_without_schema != $callback_url_without_schema)
            {
                $base_url = get_bloginfo('wpurl');
                $base_url = preg_replace('/https?:\/\//', '', $base_url);
                // Check if only secret differs
                if(strpos($response_callback, $base_url) !== false)
                {
                    //Looks like the user regenrated callback by mistake
                    //Just force Update_callback on server
                    $this->update_callback($callback_url, $crypto, $response_address);
                }
                else
                {
                    $error_str = __($error_crypto."You have an existing callback URL. Refer instructions on integrating multiple websites", 'blockonomics-bitcoin-payments');
                }
            }
        }
        return $error_str;
    }

    public function update_callback($callback_url, $crypto, $xpub)
    {
        if ($crypto == 'btc'){
            $url = Test_Setup::SET_CALLBACK_URL;
        }else{
            $url = Test_Setup::BCH_SET_CALLBACK_URL;
        }
        $body = json_encode(array('callback' => $callback_url, 'xpub' => $xpub));
        $response = $this->post($url, $this->api_key, $body);
        return json_decode(wp_remote_retrieve_body($response));
    }

    public function new_address($secret, $crypto, $reset=false)
    {
        if($reset)
        {
            $get_params = "?match_callback=$secret&reset=1";
        } 
        else
        {
            $get_params = "?match_callback=$secret";
        }
        if($crypto == 'btc'){
            $url = Test_Setup::NEW_ADDRESS_URL.$get_params;
        }else{
            $url = Test_Setup::BCH_NEW_ADDRESS_URL.$get_params;            
        }
        $response = $this->post($url, $this->api_key, '', 8);
        if (!isset($responseObj)) $responseObj = new stdClass();
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        if (wp_remote_retrieve_body($response))
        {
          $body = json_decode(wp_remote_retrieve_body($response));
          $responseObj->{'response_message'} = isset($body->message) ? $body->message : '';
          $responseObj->{'address'} = isset($body->address) ? $body->address : '';
        }
        return $responseObj;
    }


    public function get_api_key()
    {
        $api_key = get_option("blockonomics_api_key");
        if (!$api_key)
        {
            $api_key = get_option("blockonomics_temp_api_key");
        }
        return $api_key;
    }

    public function get_temp_api_key($callback_url)
    {
        $url = Test_Setup::TEMP_API_KEY_URL;
        $body = json_encode(array('callback' => $callback_url));
        $response = $this->post($url, '', $body);
        $responseObj = json_decode(wp_remote_retrieve_body($response));
        $responseObj->{'response_code'} = wp_remote_retrieve_response_code($response);
        return $responseObj;
    }

    private function get($url, $api_key = '')
    {
        $headers = $this->set_headers($api_key);

        $response = wp_remote_get( $url, array(
            'method' => 'GET',
            'headers' => $headers
            )
        );

        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo __('Something went wrong', 'blockonomics-bitcoin-payments').': '.$error_message;
        }else{
            return $response;
        }
    }

    private function post($url, $api_key = '', $body = '', $timeout = '')
    {
        $headers = $this->set_headers($api_key);

        $data = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body
            );
        if($timeout){
            $data['timeout'] = $timeout;
        }
        
        $response = wp_remote_post( $url, $data );
        if(is_wp_error( $response )){
           $error_message = $response->get_error_message();
           echo __('Something went wrong', 'blockonomics-bitcoin-payments').': '.$error_message;
        }else{
            return $response;
        }
    }

    private function set_headers($api_key)
    {
        if($api_key){
            return 'Authorization: Bearer ' . $api_key;
        }else{
            return '';
        }
    }
}