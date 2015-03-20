<?php

class PC_shop_paypal_payment_method extends PC_shop_payment_method {
	
	const DATE_FROMAT = 'Y-m-d\TH:i:s\Z';
	const API_URL = 'https://api-3t.paypal.com/nvp';
	const WEB_URL = 'https://www.paypal.com/cgi-bin/webscr';
	const TEST_API_URL = 'https://api-3t.sandbox.paypal.com/nvp';
	const TEST_WEB_URL = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
	const API_VERSION = '72.0';
	
	private $apiUrl = self::API_URL;
	private $webUrl = self::WEB_URL;
	private $testMode = false;
	private $authEmail = null;
	private $authUser = null;
	private $authPass = null;
	private $authSignature = null;
	
	private $checkoutUrl;
	private $returnUrl;
	private $cancelUrl;
	private $callbackUrl;
	
	private $logoImageUrl;
	private $headerImageUrl;
	private $brandName;
	
	private $token;
	
	protected $_debug_api = false;
	
	public function Init($payment_data, $order_data = array(), $shop_site = null) {
		parent::Init($payment_data, $order_data, $shop_site);
		if ($this->_is_test()) {
			$this->apiUrl = self::TEST_API_URL;
			$this->webUrl = self::TEST_WEB_URL;
		}
	}
	
	protected function _load_lib() {
		
	}
	
	
	
	public function make_online_payment() {
		$params = array(
			'notify_url' => $this->_get_callback_url(),
			//"cmd" =>"_cart",
			"cmd" =>"_xclick",
			"upload" =>"1",
			'bussines' => $this->_payment_data['login'],
			'address_override' => 0,
			//'first_name' => '',
			//'last_name' => '',
			'email' => $this->_order_data['email'],
			//'address1' => '',
			'RETURNURL' => $this->_get_accept_url(),
			'CANCELURL' => $this->_get_cancel_url(),
			'PAYMENTREQUEST_0_NOTIFYURL' => $this->_get_callback_url(),
			'CALLBACKTIMEOUT' => 3,
			'REQCONFIRMSHIPPING' => 0,
			
			'ADDROVERRIDE' => 0,
			'ALLOWNOTE' => 0,
			
			'invoice' => $this->_order_data['id'],
			'currency_code' => $this->_order_data['currency'],
			'PAYMENTREQUEST_0_CURRENCYCODE' => $this->_order_data['currency'],

		);
		if (isset($this->cfg['pc_shop_payment_paypal'])) {
			if (v($this->cfg['pc_shop_payment_paypal']['brand_name'])) { 
				$params['BRANDNAME'] = $this->cfg['pc_shop_payment_paypal']['brand_name']; 
			}
			if (v($this->cfg['pc_shop_payment_paypal']['logo_image'])) { 
				$params['LOGOIMG'] = $this->cfg['pc_shop_payment_paypal']['logo_image']; 
			}
			if (v($this->cfg['pc_shop_payment_paypal']['header_image'])) { 
				$params['HDRIMG'] = $this->cfg['pc_shop_payment_paypal']['header_image']; 
			}
		}
		//print_pre($this->_order_data);
		$cc = 0; $sum = 0; $desc = '';
		
		foreach ($this->_order_data['items'] as $li) {
			$sum += $li['price'] * $li['quantity'];
			if (!$desc) {
				$desc = $li['name'];
			}
			//$params['L_PAYMENTREQUEST_0_ITEMCATEGORY'.$cc] = 'Physical';
			$params['L_PAYMENTREQUEST_0_NAME'.$cc] = $desc = $li['name'];;
			if (v($li['description'])) {
				//$params['L_PAYMENTREQUEST_0_DESC'.$cc] = $li['description'];
			}
			$params['L_PAYMENTREQUEST_0_AMT'.$cc] = floatval($li['price']);
			$params['L_PAYMENTREQUEST_0_QTY'.$cc] = intval($li['quantity']);
			//$params['L_PAYMENTREQUEST_0_NUMBER'.$cc] = intval($li['id']);
			$cc++;
		}
		
		if ($cc > 1) {
			$desc = '';
		}
		$params['MAXAMT'] = $sum + 10;
		//$params['PAYMENTREQUEST_0_DESC'] = $desc;
		$params['PAYMENTREQUEST_0_PAYMENTACTION'] = 'Sale';
		//$params['PAYMENTREQUEST_0_PAYMENTACTION'] = 'Authorization';
		$params['PAYMENTREQUEST_0_AMT'] = $this->_order_data['total_price'];
		$params['PAYMENTREQUEST_0_ITEMAMT'] = $sum;
		$params['PAYMENTREQUEST_0_NOTIFYURL'] = $this->_get_callback_url();
		//$params['PAYMENTREQUEST_0_NOTIFY_URL'] = $this->_get_callback_url();
		
		if ($this->_order_data['total_price'] > $sum) {
			$params['PAYMENTREQUEST_0_SHIPPINGAMT'] = $this->_order_data['total_price'] - $sum;
		}
		else {
			$params['NOSHIPPING'] = 1;
		}
		
		
//		if ($order['cycle'] > 0) {
//			$params['L_BILLINGTYPE0'] = 'RecurringPayments';
//			$params['L_BILLINGAGREEMENTDESCRIPTION0'] = $desc;
//		}
		//print_pre($params);
		
		$resp = $this->apiCall('SetExpressCheckout', $params);

		//exit;
		//header("Location: {$this->webUrl}?" . http_build_query($p));
		
		if (isset($resp['ACK']) && $resp['ACK'] == 'Success') {
			$this->token = $resp['TOKEN'];
			$url = $this->webUrl.'?cmd=_express-checkout&token='.$this->token;
			$order_model = new PC_shop_order_model();
			$order_model->update(array('token' => $resp['TOKEN']), $this->_order_data['id']);
			header("Location: $url");
			exit;
		} else {
			//$this->token = null;
			//$this->setError("SetExpressCheckout failed");
			//trace($resp);
			//trace($this->conf);
		}
		
		return array(null, null);
	}
	
	private function apiCall($method, $params) {
		if (!$method) {
			return null;
		}
		
		if ($params && !is_array($params)) {
			$p = self::urlParamsToArray($params);
		} else {
			$p = $params;
		}
		//print_pre($this->_payment_data);
		$p['METHOD']	= $method;
		$p['USER']		= $this->_payment_data['login'];
		$p['PWD']		= $this->_payment_data['payment_key'];
		$p['SIGNATURE']	= $this->cfg['pc_shop_payment_paypal']['paypal_signature'];
		$p['VERSION']	= self::API_VERSION;

		$p = $this->urlParamsToString($p);
		$resp = $this->httpRequest($this->apiUrl.($p ? "?{$p}" : ''));
		$f = $this->urlParamsToArray($resp['body']);
		return $f;
	}
	
	
	
	public function accept() {
		$payment_succesful = false;
		$response = array();
		try {
			if (isset($_REQUEST['token']) && $_REQUEST['token']
			&& isset($_REQUEST['PayerID']) && $_REQUEST['PayerID']) {
				$token = urldecode($_REQUEST['token']);

				$resp = $this->apiCall('GetExpressCheckoutDetails', array('TOKEN' => $token));
				if (isset($resp['ACK']) && $resp['ACK'] == 'Success' && $token == $resp['TOKEN']) {
					$order_model = new PC_shop_order_model();
					$order_data = $order_model->get_one(array(
						'where' => array(
							'token' => $resp['TOKEN']
						)
					));
					if ($order_data) {
						$this->_order_data = $order_data;
						$this->order_id = $this->_order_data['id'];
						$do_express_params = array(
							'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
							'PAYMENTREQUEST_0_AMT' => $order_data['total_price'],
							'PAYMENTREQUEST_0_CURRENCYCODE' => $order_data['currency'],
							'PAYERID' => $resp['PAYERID'],
							'TOKEN' => $resp['TOKEN'],
							'PAYMENTREQUEST_0_NOTIFYURL' => $this->_get_callback_url()
						);

						$resp = $this->apiCall('DoExpressCheckoutPayment', $do_express_params);

						if (isset($resp['ACK']) && $resp['ACK'] == 'Success' && $token == $resp['TOKEN']) {
							$order_model->update(array('transaction_id' => $resp['PAYMENTINFO_0_TRANSACTIONID']), $order_data['id']);
							$response = $resp;
						}
					}
					
				}
			}
			$this->_response = $response;
			
			if (v($response['PAYMENTINFO_0_PAYMENTSTATUS']) == 'Completed') {
				$payment_succesful = $this->_is_payment_successful();
			}
			elseif(v($response['PAYMENTINFO_0_PAYMENTSTATUS']) == 'Pending') {
				return self::STATUS_WAITING;
			}
			else {
				return self::STATUS_FAILED;
			}
			
		} catch (Exception $e) {
			$message = $e->getMessage();
			if ($message == self::_STATUS_IS_PAID) {
				return self::STATUS_ALREADY_PURCHASED;
			}
			else {
				$this->_error = get_class($e) . ': ' . $message;
				return self::STATUS_ERROR;
			}
		}
		if ($payment_succesful) {
			return self::STATUS_SUCCESS;
		}
		return $payment_succesful;
	}
	
	
	public function callback() {
		$logf = 2;

		$req_ = array('cmd' => '_notify-validate');
		$req = array_merge($req_, $_POST);
		
		if (!$this->_is_test() && $req['test_ipn']) {
			return false;
		}
		
		if ($req['receiver_email'] != $this->cfg['pc_shop_payment_paypal']['paypal_email']) {
			return false;
		}
		
		$resp = $this->httpRequest($this->webUrl, $req);
		
		//$item_name = self::gvv('item_name', $_POST);
		//$item_number = self::gvv('item_number', $_POST);
		$payment_status = self::gvv('payment_status', $_POST);
		$payment_amount = self::gvv('payment_gross', $_POST);
		$payment_currency = self::gvv('mc_currency', $_POST);
		$txn_id = self::gvv('txn_id', $_POST);
	
		//self::debugLog($resp, $logf);
	
		if ($resp['http_code'] != 200) { // HTTP ERROR
			return null;
		}

		if ($resp['body'] == 'VERIFIED') {
			// Also might check that txn_id has not been previously processed
			// Also might check that payment_amount/payment_currency are correct
			
			$order_model = new PC_shop_order_model();
			$this->_order_data = $order_model->get_one(array(
				'where' => array(
					'transaction_id' => $txn_id,
					'payment_option' => 'paypal'
				)
			));
			
			if (empty($this->_order_data)) {
				//DB does not contains info about a currently received IPN...
				return false;
			}
			
			if ($payment_status == 'Completed') {
				if ($this->_is_order_paid() == 1) {
					return false;
				}
				if (!$this->_get_order_total_price() || $this->_get_order_total_price() != $payment_amount) {
					return false;
				}
				if (!$this->_get_order_currency() || $this->_get_order_currency() != $payment_currency) {
					return false;
				}
				//$order_model->update(array('is_paid' => 1), $this->_order_data['id']);
				return true;
			} else if ($payment_status == 'Reversed') {
				$order_model->update(array('is_paid' => 0), $this->_order_data['id']);
				return false;
			}
		}
		else if ($resp['body'] == 'INVALID') {
			return null;
		}
		
		return null;
	}
	
	
	protected function _get_response_payment_status() {
		return $this->_response['PAYMENTINFO_0_PAYMENTSTATUS'] == 'Completed';
	}
	
	protected function _get_response_order_id() {
		$order_model = new PC_shop_order_model();
		return $order_model->get_one(array(
			'where' => array(
				'transaction_id' => $this->_response['txn_id'],
				'payment_option' => 'paypal'
			),
			'value' => 'id'
		));
	}
	
	protected function _get_response_test() {
		if (isset($this->_response['test_ipn'])) {
			return true;
		}
		return false;
	}
	
	protected function _get_response_amount() {
		return $this->_response['PAYMENTINFO_0_AMT'];
	}
	
	protected function _get_response_currency() {
		return $this->_response['PAYMENTINFO_0_CURRENCYCODE'];
	}
	
}
