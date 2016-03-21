<?php
/**
 * Boku standard checkout module
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */

class Boku_Paymentgateway_Model_Payment_Standard extends Mage_Payment_Model_Method_Abstract
{
	const APP_ROOT = 'boku';

	protected $_code = self::APP_ROOT;
	protected $_formBlockType = 'boku/payment_standard_form';

	protected $_canUseForMultishipping  = true;
	protected $_isGateway				= true;
	protected $_isInitializeNeeded		= true;

// NOT POSSIBLE YET
	protected $_canUseInternal			= false;

	/**
	 * This makes sure that the config settings have values
	 * Note: there is no guarantee that the values are correct !
	 *
	 * @see Mage_Payment_Model_Method_Abstract::canUseCheckout()
	 * @return bool
	 */
	public function canUseCheckout(){
		$helper = Mage::helper(self::APP_ROOT);
		$merchant_id = $helper->getConfig('merchant_id');
		$api_security_key = $helper->getConfig('api_security_key');
		$service_id = $helper->getConfig('service_id');
		return !(empty($merchant_id) || empty($api_security_key) || empty($service_id));
	}

	/**
	 * Check whether payment method is applicable to quote
	 * Note may get called multiple times during the payment process (by separate ajax requests)
	 *
	 * @param $currency
	 * @return bool
	 */
	public function canUseForCurrency($currency){
		$helper = Mage::helper(self::APP_ROOT);
		$model = $helper->getQuote();
		return Mage::getSingleton(self::APP_ROOT.'/prices')
			->isAvailable($model->getGrandTotal(), $model->getQuoteCurrencyCode(), $helper->getCountryCode());
	}

	/**
	 * Called by Mage_Sales_Model_Order_Payment::place
	 * is used instead of authorize and capture functions when $_isInitializeNeeded is true
	 *
	 * @param string $action
	 * @param Varien_Object $state
	 */
	public function initialize($action, $state){
		if ($action == Boku_Paymentgateway_Model_System_Config::PAYMENT_ACTION_AUTH)
			Mage::getSingleton(self::APP_ROOT.'/payment_transaction')->initiate();
		return $this;
	}

	/**
	 * Return Order placed redirect url
	 * Called by Mage_Checkout_Model_Type_Onepage::saveOrder
	 *
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl(){
		return Mage::helper(self::APP_ROOT)->getUrl(self::APP_ROOT.'/standard/prepare');
	}
}
