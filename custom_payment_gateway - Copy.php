<?php
/*
Plugin Name: Birb Payment Gateway
Description: Birb Payment Gateway
Author: Birb
Author URI: https://birb.com
*/
// define('birbBaseUrl', 'http://localhost:5000/');
 define('birbBaseUrl', 'https://payment.birbpay.com/');

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}


/**
 * Birb Payment Gateway.
 *
 * Provides a Birb Payment Gateway, mainly for testing purposes.
 */
// $birbBaseUrl ='http://45.32.253.204:5000/';


function register_session()
{
	if (!session_id())
		session_start();
}
add_action('init', 'register_session', 1);


add_action('plugins_loaded', 'init_custom_gateway_class');
add_filter('woocommerce_get_price_html', 'custom_price_html', 100, 2);

function getBirbPrice(){
	$wp_options = get_option('woocommerce_custom_settings');
	$price = get_option('birb_price');
	$timeatamp = get_option('birb_timestamp');
	
	if ($wp_options['show_in_birb'] == 'yes') {
		if ($timeatamp > time() && floatval($price) >0) {
			return floatval($price);
		} else {

			$method = "GET";
			$url = birbBaseUrl . 'payment/rate';
			$header = array("Content-Type: application/json");
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => $method,
				CURLOPT_POSTFIELDS => null,
				CURLOPT_HTTPHEADER => $header,
			));
			$response = curl_exec($curl);
			$result = json_decode($response, true);
			$p = null;
			
			if ($result['status'] == true) {
				$p = $result['rate'];

				if(floatval($p)){
					update_option('birb_timestamp', time() + 60 * 60 * 5);
					update_option('birb_price', $p);
				}
				
			}
		}
		$price = get_option('birb_price');
		if(floatval($price) >0){
			return floatval($price);
		}
	}

	return null;
}
function custom_price_html($defaultprice, $product)
{
	
		$price =getBirbPrice();
		if ($price) {
			$pp = $product->get_price();
			$coin = number_format(($pp / floatval($price)), 6);
			return $defaultprice . ' (' . $coin . ' Birb)';
		}
	return $defaultprice;
}
function getBirbPriceHtml($defaultprice)
{
	
		$price =getBirbPrice();
		if ($price) {
			
			$coin = number_format(($defaultprice / floatval($price)), 6);
			return   ' (' . $coin . ' Birb)';
		}
		return '';
}


add_filter( 'woocommerce_cart_item_price', 'filter_cart_item_price', 10, 3 );
function filter_cart_item_price( $price_html, $cart_item, $cart_item_key ) {
	

        if ( WC()->cart->display_prices_including_tax() ) {
            $product_price = wc_get_price_including_tax( $cart_item['data'], $args );
        } else {
            $product_price = wc_get_price_excluding_tax( $cart_item['data'], $args );
        }
		
   
     return $price_html.getBirbPriceHtml($product_price);
}

// add_filter( 'woocommerce_cart_item_subtotal', 'woocommerce_cart_item_subtotal_birb' ); // added
add_filter( 'woocommerce_cart_subtotal', 'woocommerce_cart_subtotal_birb' ); // added
add_filter( 'woocommerce_cart_total', 'woocommerce_cart_total_birb' ); // added


function woocommerce_cart_subtotal_birb( $price ) {
	$sub =WC()->cart->subtotal;
    
    return $price . getBirbPriceHtml($sub);
}
function woocommerce_cart_total_birb( $price ) {
	$sub =WC()->cart->total;
    
    return $price . getBirbPriceHtml($sub);
}
add_filter( 'woocommerce_cart_item_subtotal', 'bbloomer_if_coupon_slash_item_subtotal', 99, 3 );
 
function bbloomer_if_coupon_slash_item_subtotal( $subtotal, $cart_item, $cart_item_key ){
global $woocommerce;
$newsubtotal = ( $cart_item['data']->get_price() * $cart_item['quantity'] ); 


 
return $subtotal. getBirbPriceHtml($newsubtotal);
}



function init_custom_gateway_class()
{

	class WC_Gateway_Custom extends WC_Payment_Gateway
	{

		public $domain;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct()
		{

			$this->domain = 'custom_payment';

			$this->id                 = 'custom';
			$this->icon               = apply_filters('woocommerce_custom_gateway_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __('Birb', $this->domain);
			$this->method_description = __('Allows payments with Birb gateway.', $this->domain);

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title        = $this->get_option('title');
			$this->description  = $this->get_option('description');
			$this->instructions = $this->get_option('instructions', $this->description);
			$this->order_status = $this->get_option('order_status', 'completed');

			// Actions
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_thankyou_custom', array($this, 'thankyou_page'));

			// Customer Emails
			add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
		}

		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields()
		{

			$this->form_fields = array(
				'enabled' => array(
					'title'   => __('Enable/Disable', $this->domain),
					'type'    => 'checkbox',
					'label'   => __('Enable Birb Payment', $this->domain),
					'default' => 'yes'
				),
				'show_in_birb' => array(
					'title'   => __('Show Price in Birb', $this->domain),
					'type'    => 'checkbox',
					'label'   => __('Show Price in Birb', $this->domain),
					'default' => 'no'
				),
				'merchant_apikey' => array(
					'title'       => __('API KEY', $this->domain),
					'type'        => 'text',
					'description' => __('This controls the title which the user sees during checkout.', $this->domain),
					'default'     => __('Enter Merchant API KEY', $this->domain),
					'desc_tip'    => true,
				),
				'merchant_secretkey' => array(
					'title'       => __('SECRET KEY', $this->domain),
					'type'        => 'text',
					'description' => __('This controls the title which the user sees during checkout.', $this->domain),
					'default'     => __('Enter Merchant SECRET KEY', $this->domain),
					'desc_tip'    => true,
				),
				'wallet_address' => array(
					'title'       => __('Wallet Address', $this->domain),
					'type'        => 'text',
					'description' => __('This controls the title which the user sees during checkout.', $this->domain),
					'default'     => __('Enter Merchant WALLET ADDRESS', $this->domain),
					'desc_tip'    => true,
				),
				'title' => array(
					'title'       => __('Title', $this->domain),
					'type'        => 'text',
					'description' => __('This controls the title which the user sees during checkout.', $this->domain),
					'default'     => __('Birb Payment', $this->domain),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __('Description', $this->domain),
					'type'        => 'textarea',
					'description' => __('Payment method description that the customer will see on your checkout.', $this->domain),
					'default'     => __('Payment Information', $this->domain),
					'desc_tip'    => true,
				),
			);
		}


		/**
		 * Output for the order received page.
		 */
		public function thankyou_page()
		{
			//die('werty');
			if ($this->instructions)
				echo wpautop(wptexturize($this->instructions));
		}

		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions($order, $sent_to_admin, $plain_text = false)
		{
			if ($this->instructions && !$sent_to_admin && 'custom' === $order->payment_method && $order->has_status('on-hold')) {
				echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
			}
		}

		public function payment_fields()
		{
			if ($description = $this->get_description()) {
				echo wpautop(wptexturize($description));
			}
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment($order_id)
		{
			if ($_POST['payment_method'] == 'custom') {
				global $wpdb;
				$option_value = get_option('woocommerce_custom_settings');
	
	
	
				$wp_options = $wpdb->get_results("SELECT option_value FROM wp_options WHERE (option_name = 'woocommerce_custom_settings' AND autoload = 'yes')");
				// var_dump($wp_options);
				
				$login_url = birbBaseUrl . 'api/deep/login/';
				$apikey = $option_value['merchant_apikey'];
				$secretkey = $option_value['merchant_secretkey'];
				$wallet_address = $option_value['wallet_address'];
				$wallet_address = $wallet_address;
				$login_data['apiKey'] = $apikey;
				$login_data['apiSecret'] = $secretkey;
				$data['email'] = $_POST['billing_email'];
				// var_dump($login_data);
				// var_dump($wallet_address);
				// die;
				$order = wc_get_order($order_id);
				$_SESSION['success_url'] = $this->get_return_url($order) . '&';
				$method = "POST";
				$header = array("Content-Type: application/json");
				$curl = curl_init();
				curl_setopt_array($curl, array(
					CURLOPT_URL => $login_url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 30,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => $method,
					CURLOPT_POSTFIELDS => json_encode($login_data),
					CURLOPT_HTTPHEADER => $header,
				));
				$response = curl_exec($curl);
				$result = json_decode($response, true);
				// var_dump($result);
				if ($result['status'] == 1) {
					$token = @$result['data']['platform']['token'];
					// var_dump($result);
					return birbCreateOrder($data, $token, $order_id, $wallet_address);
				} else {
					return $response;
				}
			}
			if ($_POST['payment_method'] != 'custom')
				return;

			if (!isset($_POST['sender_add']) || empty($_POST['sender_add']))
				wc_add_notice(__('Please add your sender_add number', $this->domain), 'error');

			if (!isset($_POST['transaction']) || empty($_POST['transaction']))
				wc_add_notice(__('Please add your transaction ID', $this->domain), 'error');

			$order = wc_get_order($order_id);
		}
	}
}

add_filter('woocommerce_payment_gateways', 'add_custom_gateway_class');
function add_custom_gateway_class($methods)
{
	$methods[] = 'WC_Gateway_Custom';
	return $methods;
}

function birbCreateOrder($data, $token, $order_id, $wallet_address)
{
	global $woocommerce;
	$order = wc_get_order($order_id);
	$data['storeOrderId'] = $order_id;
	$data['merchant_address'] = $wallet_address;
	$data['email'] = $_POST['billing_email'];
	$data['total'] = WC()->cart->total;
	$data['cancelUrl'] = $order->get_cancel_order_url() . '&';
	$data['successUrl'] = $_SESSION['success_url'];
	$order_items = $order->get_items();
	$items = [];
	foreach ($order_items as $item_id => $item) {
		// Get the product name
		$product_name = $item['name'];
		// Get the item quantity
		$item_quantity = $order->get_item_meta($item_id, '_qty', true);
		// Get the item line total
		$item_total = $order->get_item_meta($item_id, '_line_total', true);

		// Displaying this data (to check)
		$items[$item_id] = ["name" => $product_name, "qty" => $item_quantity, 'price' => $item_total];
	}
	$data['fullOrder'] = $items;
	$data['get_total'] = $order->get_total();;
	// var_dump($items);
	return birbSend($data, $token);
}

function birbSend($data, $token)
{
	global $wpdb;
	$wp_options = $wpdb->get_results("SELECT option_value FROM wp_options WHERE (option_name = 'woocommerce_custom_settings' AND autoload = 'yes')");
	$option_value = unserialize($wp_options[0]->option_value);
	//$api_senddata_url = $option_value['api_senddata_url'];
	$api_senddata_url = birbBaseUrl . 'payment/order/';
	//$api_return_url = $option_value['api_return_url'];
	$api_return_url = birbBaseUrl . 'payment/confirm/';
	$order_id = $data['storeOrderId'];
	$token = $token;
	$_SESSION['token'] = $token;
	$_SESSION['_oderiD'] = $order_id;
	$url = $api_senddata_url;
	$header = array(
		"Authorization: Token " . $token,
		"Content-Type: application/json",
	);
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	$response = curl_exec($ch);
	if (curl_errno($ch) != CURLE_OK) {
		curl_close($ch);
		$toReturn = new stdClass();
		$toReturn->status = false;
		$toReturn->data = 'Request failed';
		return $toReturn;
	} else {
		if (birbisJson($response)) {
			$res_data = json_decode($response);
			$_SESSION['res_data'] = $res_data;
			$id = $res_data->data->_id;
			$url = $api_return_url . $id;
			return array(
				'result'    => 'success',
				'redirect'  => $url
			);
		}
	}
}

add_action('template_redirect', 'correct_redirect', 10);
function correct_redirect()
{
	$token = @$_SESSION['token'];
	$res_data = @$_SESSION['res_data'];
	$order_id = @$_SESSION['_oderiD'];
	// var_dump($order_id);
	// die;
	global $wpdb;
	$wp_options = $wpdb->get_results("SELECT option_value FROM wp_options WHERE (option_name = 'woocommerce_custom_settings' AND autoload = 'yes')");
	$option_value = unserialize($wp_options[0]->option_value);
	$merchant_add = array('address' => $option_value['wallet_address']);
	//$tr_url = @$option_value['api_check_tr'];
	$tr_url = birbBaseUrl . 'transaction/';
	$id = @$res_data->data->_id;
	$url = $tr_url . $id;
	$header = array(
		"Authorization: Token " . $token,
		"Content-Type: application/json",
	);
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($merchant_add));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	$response = curl_exec($ch);
	$oder_meta = json_decode($response, true);
	if ($oder_meta['status'] == 1) {
		$order_id = $res_data->data->orderInfo[0]->storeOrderId;
		wc_add_order_item_meta($order_id, '_custom_oder_meta', $response);
		$order = wc_get_order($order_id);
		if($oder_meta['verify']){
			$order->update_status('completed');
		} else {
			$order->update_status('Pending payment');
		}
		WC()->cart->empty_cart();
		$_SESSION = array();
		return succsessOrder($order_id);
	}
}


function birbisJson($string)
{
	json_decode($string);
	return (json_last_error() == JSON_ERROR_NONE);
}

function cancelOrder($order_id)
{
	$order = wc_get_order($order_id);
	$redirect_url = $order->get_cancel_order_url() . '&';
	global $woocommerce;
	$status = $order->get_status();
	WC()->cart->empty_cart();
	return array(
		'result'    => 'success',
		'redirect'  => $redirect_url
	);
}

function succsessOrder($order_id)
{
	$order = wc_get_order($order_id);
	$redirect_url = $_SESSION['success_url'];
	WC()->cart->empty_cart();
	return array(
		'result'    => 'success',
		'redirect'  => $redirect_url
	);
}

add_action('woocommerce_admin_order_data_after_billing_address', 'custom_checkout_field_display_admin_order_meta', 10, 1);
function custom_checkout_field_display_admin_order_meta($order)
{
	$method = get_post_meta($order->id, '_payment_method', true);
	if ($method != 'custom')
		return;
	$sender_add = get_post_meta($order->id, 'sender_add', true);
	$transaction = get_post_meta($order->id, 'transaction', true);
	$order_meta_data = wc_get_order_item_meta($order->id, '_custom_oder_meta', true);
	$oder_meta = json_decode($order_meta_data, true);

	if ($oder_meta && array_key_exists('newTrans', $oder_meta['data'])) {
		$order_data = $oder_meta['data']['newTrans'];
	} elseif ($oder_meta && array_key_exists('0', $oder_meta['data'])) {
		$order_data = $oder_meta['data'][0];
	} else {
		$order_data = $oder_meta['data'];
	}


	$t_id = @$order_data['_id'];
	$to = @$order_data['to'];
	$from = @$order_data['fromaddress'];
	$hash = @$order_data['hash'];
	$coin = @$order_data['coin'];
	echo '<h2> Transaction Details(Birb) </h2>';
	if ($coin) {
		echo '<p style="word-break: break-all;"><strong>' . __('Transaction Hash') . ':</strong> ' . $hash . '</p>';
		echo '<p style="word-break: break-all;"><strong>' . __('Mercahnt Address') . ':</strong> ' . $to . '</p>';
		echo '<p style="word-break: break-all;"><strong>' . __('Customer Address') . ':</strong> ' . $from . '</p>';
		//echo '<p style="word-break: break-all;"><strong>'.__( 'Transaction Hash').':</strong> ' . $hash . '</p>';
		echo '<p style="word-break: break-all;"><strong>' . __('Coins') . ':</strong> ' . round($coin, 6) . '</p>';
		echo '<p style="word-break: break-all;"><strong>' . __('Transaction Hash Verify') . ': </strong><a href="https://www.bscscan.com/tx/' . $hash . '" style="color:#0058ff" target="_blank">Verify</a></p>';

		$order->update_status('completed');
		if (WC()->cart) {
			WC()->cart->empty_cart();
		}

		$_SESSION = array();
	}
}


add_action('woocommerce_thankyou', 'auspicious_view_order_and_thankyou_page', 20);
add_action('woocommerce_view_order', 'auspicious_view_order_and_thankyou_page', 20);

function auspicious_view_order_and_thankyou_page($order_id)
{
	$order_meta_data = wc_get_order_item_meta($order_id, '_custom_oder_meta', true);
	$oder_meta = json_decode($order_meta_data, true);
	if ($oder_meta && array_key_exists('newTrans', $oder_meta['data'])) {
		$order_data = $oder_meta['data']['newTrans'];
	} elseif ($oder_meta &&  array_key_exists('0', $oder_meta['data'])) {
		$order_data = $oder_meta['data'][0];
	} else {
		$order_data = $oder_meta['data'];
	}
	$coin = @$order_data['coin'];
	$from = @$order_data['fromaddress'];
	$hash = @$order_data['hash'];
	if (is_user_logged_in() && $coin) { ?>
		<h2>Transaction Details(Birb)</h2>
		<table class="woocommerce-table shop_table gift_info">
			<tbody>
				<tr>
					<th>Total Birbs</th>
					<td><?php echo round($coin, 6); ?></td>
				</tr>
				<!-- <tr>
					<th>Customer Address</th>
					<td><?php //echo $from 
						?></td>
				</tr> -->
				<tr>
					<th>Transaction Verify</th>
					<td><a href="https://www.bscscan.com/tx/<?php echo $hash; ?>" style="color:#0058ff" target="_blank">Verify</a></td>
				</tr>

			</tbody>
		</table>
<?php }
}
