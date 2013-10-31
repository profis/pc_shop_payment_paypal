<?php
function pc_shop_payment_paypal_install($controller) {
	global $core, $logger;
	$logger->debug('pc_shop_payment_paypal_install()');
	
	$payment_option_model = new PC_shop_payment_option_model();
	$payment_option_model->absorb_debug_settings($logger);
	$payment_option_model->insert(array('code' => 'paypal'), array(
		'lt' => array(
			'name' => 'Per paypal sistemą'
		),
		'en' => array(
			'name' => 'Using paypal system'
		),
		'ru' => array(
			'name' => 'Используя систему paypal'
		)
	), array('ignore' => true));
	
	$core->Set_config_if('paypal_email', '', 'pc_shop_payment_paypal');
	$core->Set_config_if('paypal_signature', '', 'pc_shop_payment_paypal');
	
	return true;
}

function pc_shop_payment_paypal_uninstall($controller) {
	global $core, $logger;
	$logger->debug('pc_shop_payment_paypal_uninstall()');
	
	$payment_option_model = new PC_shop_payment_option_model();
	$payment_option_model->absorb_debug_settings($logger);
	$payment_option_model->delete(array('where' => array('code' => 'paypal')));
	
	return true;
}