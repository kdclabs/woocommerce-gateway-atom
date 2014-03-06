<?php
/*
Plugin Name: WooCommerce Atom Paynetz
Plugin URI: http://www.kdclabs.com/?p=149
Donate link: https://www.payumoney.com/webfront/index/kdclabs
Description: Atom Paynetz Gateway for WooCommerce. Atom Paynetz allows a merchant to accept payments along with real-time confirmation.
Version: 1.0.0
Author: _KDC-Labs
Author URI: http://www.kdclabs.com/
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('plugins_loaded', 'woocommerce_atomtech_init', 0);
define('IMGDIR', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function woocommerce_atomtech_init(){
	if(!class_exists('WC_Payment_Gateway')) return;

    if( isset($_GET['msg']) && !empty($_GET['msg']) ){
        add_action('the_content', 'showMessage');
    }
    function showMessage($content){
            return '<div class="'.htmlentities($_GET['type']).'">'.htmlentities(urldecode($_GET['msg'])).'</div>'.$content;
    }

    /**
     * Gateway class
     */
	class WC_atomtech extends WC_Payment_Gateway{
		public function __construct(){
			$this->id 					= 'atomtech';
			$this->icon         		= IMGDIR . 'logo.png';
			$this->method_title 		= 'Atom Paynetz';
			$this->method_description	= "Come discover the new way to pay.";
			$this->has_fields 			= false;
			
			$this->init_form_fields();
			$this->init_settings();
			if ( $this->settings['showlogo'] == "yes" ) {
				$this->icon 			= IMGDIR . 'logo.png';
			}			
			$this->title 			= $this->settings['title'];
			$this->atom_domain		= $this->settings['atom_domain'];
			$this->redirect_page_id = $this->settings['redirect_page_id'];
			if ( $this->settings['testmode'] == "yes" ) {
				$this->liveurl 			= 'http://203.114.240.183/paynetz/epi/fts';
				$this->atom_port		= "80";
				$this->login_id 		= "160";
				$this->password 		= "Test@123";
				$this->atom_product_id	= "NSE";
				$this->description 		= $this->settings['description'].
										"<br/><br/><u>Test Mode is <strong>ACTIVE</strong><br>Please select <strong>Net Banking</strong> as <em>Payment Mode</em>, so you can test the success / failure response.";
			} else {
				$this->liveurl 			= 'https://'.$this->settings['atom_domain'].'/paynetz/epi/fts';
				$this->atom_port		= "443";
				$this->login_id 		= $this->settings['login_id'];
				$this->password 		= $this->settings['password'];
				$this->description 		= $this->settings['description'];
			}					
					
			$this->msg['message'] 	= "";
			$this->msg['class'] 	= "";
					
			add_action('init', array(&$this, 'check_atomtech_response'));
			//update for woocommerce >2.0
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_atomtech_response' ) );
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				/* 2.0.0 */
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			} else {
				/* 1.6.6 */
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}
			
			add_action('woocommerce_receipt_atomtech', array(&$this, 'receipt_page'));
		}
    
		function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title' 		=> __('Enable/Disable', 'kdc'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Enable Atom Paynetz Module.', 'kdc'),
					'default' 		=> 'no',
					'description' 	=> 'Show in the Payment List as a payment option'
				),
      			'title' => array(
					'title' 		=> __('Title:', 'kdc'),
					'type'			=> 'text',
					'default' 		=> __('Online Payments', 'kdc'),
					'description' 	=> __('This controls the title which the user sees during checkout.', 'kdc'),
					'desc_tip' 		=> true
				),
      			'description' => array(
					'title' 		=> __('Description:', 'kdc'),
					'type' 			=> 'textarea',
					'default' 		=> __('Pay securely by Credit or Debit Card or Internet Banking through Atom Technologies Secure Servers.', 'kdc'),
					'description' 	=> __('This controls the description which the user sees during checkout.', 'kdc'),
					'desc_tip' 		=> true
				),
      			'atom_domain' => array(
					'title' 		=> __('Atom Domain', 'kdc'),
					'type' 			=> 'text',
					'description' 	=> __('Will be provided by Atom Paynetz Team after production movement', 'kdc'),
					'desc_tip' 		=> true
				),
      			'login_id' => array(
					'title' 		=> __('Login Id', 'kdc'),
					'type' 			=> 'text',
					'description' 	=> __('As provided by Atom Paynetz Team', 'kdc'),
					'desc_tip' 		=> true
				),
      			'password' => array(
					'title' 		=> __('Password', 'kdc'),
					'type' 			=> 'password',
					'description' 	=> __('As provided by Atom Paynetz Team', 'kdc'),
					'desc_tip' 		=> true
				),
      			'atom_product_id' 	=> array(
					'title' 		=> __('API Key', 'kdc'),
					'type' 			=> 'text',
					'description' 	=>  __('Will be provided by Atom Paynetz Team after production movement', 'kdc'),
					'desc_tip' 		=> true
                ),
				'showlogo' => array(
					'title' 		=> __('Show Logo', 'kdc'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Show the "PayU Money" logo in the Payment Method section for the user', 'kdc'),
					'default' 		=> 'yes',
					'description' 	=> __('Tick to show "PayU Money" logo'),
					'desc_tip' 		=> true
                ),
      			'testmode' => array(
					'title' 		=> __('TEST Mode', 'kdc'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Enable PayU Money TEST Transactions.', 'kdc'),
					'default' 		=> 'no',
					'description' 	=> __('Tick to run TEST Transaction on the PayU Money platform'),
					'desc_tip' 		=> true
                ),
      			'redirect_page_id' => array(
					'title' 		=> __('Return Page'),
					'type' 			=> 'select',
					'options' 		=> $this->atomtech_get_pages('Select Page'),
					'description' 	=> __('URL of success page', 'kdc'),
					'desc_tip' 		=> true
                )
			);
		}
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
		public function admin_options(){
			echo '<h3>'.__('Atom Paynetz', 'kdc').'</h3>';
			echo '<p>'.__('Your one step to online and face-to-face payment solutions').'</p>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			echo '</table>';
		}
        /**
         *  There are no payment fields for techpro, but we want to show the description if set.
         **/
		function payment_fields(){
			if($this->description) echo wpautop(wptexturize($this->description));
		}
		/**
		* Receipt Page
		**/
		function receipt_page($order){
			echo '<p>'.__('Thank you for your order, please click the button below to pay.', 'kdc').'</p>';
			echo $this->generate_atomtech_form($order);
		}
		/**
		* Generate payu button link
		**/
		function generate_atomtech_form($order_id){
			global $woocommerce;
			$order = new WC_Order( $order_id );
			
			$redirect_url = ($this->redirect_page_id=="" || $this->redirect_page_id==0)?get_site_url() . "/":get_permalink($this->redirect_page_id);
			//For wooCoomerce 2.0
			$redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );

			$order_number = "WPWC".$order_id;
			//$order_number = "WPWC".date("ymds").$order_id;
			
			if($order->billing_address_2 != "") {
				$address = $order->billing_address_1.", ".$order->billing_address_2;
			} else {
				$address = $order->billing_address_1;
			}
			$address = substr( $address, 0, 50);
			$alldata = 	$order->billing_email.
						$order->billing_first_name.
						$order->billing_last_name.
						$address.
						$order->billing_city.
						$order->billing_state.
						$order->billing_country.
						$order->order_total.
						$order_number;
			$privatekey = atomtech_encrypt( $this->username.":|:".$this->password, $this->atom_product_id );
			$checksum = atomtech_calculate_checksum( $alldata.date('Y-m-d'), $privatekey );
			$atomtech_args = array(
				'buyerEmail' 	=> $order->billing_email,
				'buyerPhone' 	=> $order->billing_phone,
				'buyerFirstName'=> $order->billing_first_name,
				'buyerLastName' => $order->billing_last_name,
				'buyerAddress' 	=> $address,
				'buyerCity' 	=> $order->billing_city,
				'buyerState' 	=> $order->billing_state,
				'buyerCountry' 	=> $order->billing_country,
				'buyerPinCode' 	=> $order->billing_postcode,
				'amount' 		=> $order->order_total,
				'orderid' 		=> $order_number,
				'privatekey' 	=> $privatekey,
				'mercid' 		=> $this->login_id,
				'checksum' 		=> $checksum,
				'currency' 		=> '356',
				'isocurrency' 	=> 'INR'
			);
			$atomtech_args_array = array();
			foreach($atomtech_args as $key => $value){
				$atomtech_args_array[] = '                <input type="hidden" name="'.$key.'" value="'.$value.'">'."\n";
			}

			return '	<form action="https://payments.atomtech.co.in/pay/index.php" method="post" id="atomtech_payment_form">
				' . implode('', $atomtech_args_array) . '
				<input type="submit" class="button-alt" id="submit_atomtech_payment_form" value="'.__('Pay via PayU', 'kdc').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'kdc').'</a>
					<script type="text/javascript">
					jQuery(function(){
					jQuery("body").block({
						message: "'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'kdc').'",
						overlayCSS: {
							background		: "#fff",
							opacity			: 0.6
						},
						css: {
							padding			: 20,
							textAlign		: "center",
							color			: "#555",
							border			: "3px solid #aaa",
							backgroundColor	: "#fff",
							cursor			: "wait",
							lineHeight		: "32px"
						}
					});
					jQuery("#submit_atomtech_payment_form").click();});
					</script>
				</form>';
		}
		/**
		* Process the payment and return the result
		**/
		function process_payment($order_id){
			global $woocommerce;
			$order = new WC_Order( $order_id );
			return array(
				'result' => 'success', 
				'redirect' => add_query_arg(
					'order', 
					$order->id, 
					add_query_arg(
						'key', 
						$order->order_key, 
						get_permalink(get_option('woocommerce_pay_page_id'))
					)
				)
        	);
		}
		/**
		* Check for valid payu server callback
		**/
		function check_atomtech_response(){
			global $woocommerce;
			if( isset($_REQUEST['TRANSACTIONID']) && isset($_REQUEST['TRANSACTIONSTATUS']) && isset($_REQUEST['ap_SecureHash']) ){
				$order_id = $_REQUEST['TRANSACTIONID'];
				if($order_id != ''){
					try{
						$order = new WC_Order( $order_id );
						$status = $_REQUEST['TRANSACTIONSTATUS'];
						$hash = $_REQUEST['ap_SecureHash'];
						$checkhash = sprintf( "%u", crc32 ($_REQUEST['TRANSACTIONID'].':'.$_REQUEST['APTRANSACTIONID'].':'.$_REQUEST['AMOUNT'].':'.$_REQUEST['TRANSACTIONSTATUS'].':'.$_REQUEST['MESSAGE'].':'.$this->login_id.':'.$this->username ) );
						$transauthorised = false;

						if( $order->status !== 'completed' ) {
							if( $hash == $checkhash ) {
								$status = strtolower( $status );
								if( $status == "200" ) {
									$transauthorised = true;
									$this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
									$this->msg['class'] = 'woocommerce-message';
									if($order->status == 'processing'){
										$order->add_order_note('Atom Paynetz ID: '.$_REQUEST['APTRANSACTIONID'].' ('.$_REQUEST['TRANSACTIONID'].')');
										if( isset($_POST['MESSAGE']) && $_POST['MESSAGE'] != "" ) {
											$order->add_order_note('<br/>Msg: '.$_REQUEST['MESSAGE']);
										}
									}else{
										$order->payment_complete();
										$order->add_order_note('Atom Paynetz payment successful.<br/>Atom Paynetz ID: '.$_REQUEST['APTRANSACTIONID'].' ('.$_REQUEST['TRANSACTIONID'].')');
										if( isset($_POST['MESSAGE']) && $_POST['MESSAGE'] != "" ) {
											$order->add_order_note('<br/>Msg: '.$_REQUEST['MESSAGE']);
										}
										$woocommerce -> cart -> empty_cart();
									}
								}else if($status=="pending"){
									$this->msg['message'] = "Thank you for shopping with us. Right now your payment status is pending. We will keep you posted regarding the status of your order through eMail";
									$this->msg['class'] = 'woocommerce-info';
									$order->add_order_note('Atom Paynetz payment status is pending<br/>Atom Paynetz ID: '.$_REQUEST['APTRANSACTIONID'].' ('.$_REQUEST['TRANSACTIONID'].')');
									if( isset($_POST['MESSAGE']) && $_POST['MESSAGE'] != "" ) {
										$order->add_order_note('<br/>Msg: '.$_REQUEST['MESSAGE']);
									}
									$order->update_status('on-hold');
									$woocommerce -> cart -> empty_cart();
								}else{
									$this->msg['class'] = 'woocommerce-error';
									$this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
									$order->add_order_note('Transaction ERROR: '.$_REQUEST['MESSAGE'].'<br/>Atom Paynetz ID: '.$_REQUEST['APTRANSACTIONID'].' ('.$_REQUEST['TRANSACTIONID'].')');
								}
							}else{
								$this->msg['class'] = 'error';
								$this->msg['message'] = "Security Error. Illegal access detected.";
							}
							if($transauthorised==false){
								$order->update_status('failed');
							}
						}
					}catch(Exception $e){
                        // $errorOccurred = true;
                        $msg = "Error";
					}
				}

                $redirect_url = ($this->redirect_page_id=="" || $this->redirect_page_id==0)?get_site_url() . "/":get_permalink($this->redirect_page_id);
                //For wooCoomerce 2.0
                $redirect_url = add_query_arg( array('msg'=> urlencode($this->msg['message']), 'type'=>$this->msg['class']), $redirect_url );

                wp_redirect( $redirect_url );
                exit;

			}
		
		}
		
		/*
        //Removed For WooCommerce 2.0
		function showMessage($content){
			return '<div class="box '.$this->msg['class'].'">'.$this->msg['message'].'</div>'.$content;
		}
		*/
		
		// get all pages
		function atomtech_get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
                	$has_parent = $page->post_parent;
                	while($has_parent) {
                    	$prefix .=  ' - ';
                    	$next_page = get_page($has_parent);
                    	$has_parent = $next_page->post_parent;
                	}
            	}
            	// add to page list array array
            	$page_list[$page->ID] = $prefix . $page->post_title;
        	}
        	return $page_list;
    		}
		}
		
		/**
		* CheckSum for Atom Paynetz
		**/
		function atomtech_calculate_checksum( $data, $secret_key ) {
			$checksum = md5( $data.$secret_key );
			return $checksum;
		}
		function atomtech_encrypt($data, $salt) {
			$key = hash( 'SHA256', $salt.'@'.$data );
        	return $key;
    	}	
		function atomtech_verify_checksum( $checksum, $all, $secret ) {
			$cal_checksum = atomtech_calculate_checksum( $secret, $all );
			$bool = 0;
			if( $checksum == $cal_checksum ) {
				$bool = 1;
			}
			return $bool;
		}	
		
		/**
		* Add the Gateway to WooCommerce
		**/
		function woocommerce_add_atomtech_gateway($methods) {
			$methods[] = 'WC_atomtech';
			return $methods;
		}

		add_filter('woocommerce_payment_gateways', 'woocommerce_add_atomtech_gateway' );
	}
