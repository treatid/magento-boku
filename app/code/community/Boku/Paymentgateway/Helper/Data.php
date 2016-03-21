<?php
/**
 * Data helper
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author 		MDH <mdh@treatid.me.uk>
 */

class Boku_Paymentgateway_Helper_Data extends Mage_Core_Helper_Abstract
{
	const APP_ROOT = 'boku';
	const CONFIG_ROOT = 'payment/boku';

	/**
	 * Are we in the admin system ?
	 *
	 * @return boolean
	 */
	public static function isAdmin(){
		return Mage::app()->getStore()->isAdmin() || Mage::getDesign()->getArea() == 'adminhtml';
	}

	/**
	 * get the country ISO code
	 * If there is a quote in the basket and it has a billing address then uses this
	 *  otherwise it defaults to the store's country.
	 *
	 * @return string
	 */
	public static function getCountryCode(){
		if (($q = self::getQuote()) && ($m = $q->getBillingAddress()) && ($m = $m->getCountryId()))
			return $m;
		return self::getStore()->getConfig('general/country/default');
	}

	/**
	 * get the base currency ISO code
	 *
	 * @return string
	 */
	public static function getBaseCurrencyCode(){
		return self::getStore()->getBaseCurrencyCode();
	}

	/**
	 * get the current quote/order's currency ISO code
	 *
	 * @return string
	 */
	public static function getCurrencyCode(){
		return self::getQuote()->getQuoteCurrencyCode();
	}

	/**
	 * Get a currency's decimal places
	 *
	 * @param string (ISO 4217) $currency
	 * @return int
	 */
	public static function getCurrencyPrecision($currency){
		return Mage::getSingleton(self::APP_ROOT.'/prices')->getCurrencyPrecision($currency);
	}

	/**
	 * Get the currency value expressed in the fractional currency unit
	 *
	 * @param float $price
	 * @param string (ISO 4217) $currency
	 * @return int
	 */
	public static function getIntegerPrice($price, $currency){
		return Mage::getSingleton(self::APP_ROOT.'/prices')->getIntegerPrice($price, $currency);
	}

	/**
	 * Convert a value expressed in the fractional currency unit to the normal float value
	 *
	 * @param int $price
	 * @param string (ISO 4217) $currency
	 * @return float
	 */
	public static function getFloatPrice($price, $currency){
		return Mage::getSingleton(self::APP_ROOT.'/prices')->getFloatPrice($price, $currency);
	}

	/**
	 * convert from one currency to another
	 *
	 * @param number $val
	 * @param string $currency_from - ISO code for $val currency - default store currency
	 * @param string $currency_to - ISO code for return currency - default base currency
	 * @return number
	 */
	public static function convertCurrency($val, $currency_from = null, $currency_to = null){
		if (empty($currency_from))	$currency_from = self::getCurrencyCode();
		if (empty($currency_to))	$currency_to = self::getBaseCurrencyCode();
		$currency_from = strtoupper($currency_from);
		$currency_to = strtoupper($currency_to);
		if ($currency_from != $currency_to)
			$val = Mage::helper('directory')->currencyConvert($val, $currency_from, $currency_to);
		return $val;
	}
	public static function convertToBaseCurrency($val, $currency = null){
		return self::convertCurrency($val, $currency);
	}

	public static function getCheckout(){
		return Mage::getModel('checkout/cart');
	}

	/**
	 * Get the current quote
	 *
	 * @return Mage_Sales_Model_Quote
	 */
	public static function getQuote(){
		return self::isAdmin() ? Mage::getSingleton('adminhtml/session_quote')->getQuote() : self::getCheckout()->getQuote();
	}

	/**
	 * @param string $trx_id
	 * @return Boku_Paymentgateway_Model_Payment_Transaction | null
	 */
	public static function getTransaction($trx_id){
		return Mage::getSingleton(self::APP_ROOT.'/payment_transaction')->getTransaction($trx_id);
	}

	/**
	 * @return string | null
	 */
	public static function getPhone(){
		if (($m = self::getQuote()) && ($m = $m->getBillingAddress()) && ($m = $m->getTelephone()))
			return $m;
		return null;
	}

	public static function getSession(){
		return Mage::getSingleton(self::APP_ROOT.'/'.(self::isAdmin() ? 'adminSession' : 'session'));
	}

	/**
	 * Creates order payment/transaction records and updates order paid values
	 * amount and total need to be in the same currency as the order and transaction
	 *
	 * @param array $data - {trx_id, amount, currency, total}
	 * @return Mage_Sales_Model_Order_Payment|null
	 */
	public static function createPayment($data){
		try{
			$trx_id = $data['trx_id'];
			if (is_null($transaction = self::getTransaction($trx_id)))
				throw new Exception('Transaction '.$trx_id.' not found');
			return $transaction->addPayment($data);
		}catch(Exception $e){
			self::logErr(__METHOD__.' - '.$e->getMessage());
			return null;
		}
	}

	/**
	 * Creates order payment/transaction records and updates order refund values
	 * amount needs to be in the same currency as the order and transaction
	 *
	 * @param array $data - {trx_id, amount, currency}
	 * @return Mage_Sales_Model_Order_Payment|null
	 */
	public static function createRefund($data){
		try{
			$trx_id = $data['trx_id'];
			if (is_null($transaction = self::getTransaction($trx_id)))
				throw new Exception('Transaction '.$trx_id.' not found');
			return $transaction->addRefund($data);
		}catch(Exception $e){
			self::logErr(__METHOD__.' - '.$e->getMessage());
			return null;
		}
	}

	/**
	 * @param Mage_Sales_Model_Order $order
	 * @return Mage_Sales_Model_Order_Invoice|null
	 */
	public static function generateInvoice($order){
		try{
			$invoice = $order->prepareInvoice()
				->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::NOT_CAPTURE)
				->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
				->addComment('Auto-Generated by Boku')
				->register();
			Mage::getModel('core/resource_transaction')
				->addObject($invoice)
				->addObject($invoice->getOrder())
				->save();
			$invoice->sendEmail(true);
		}catch(Exception $e2){self::logErr(__METHOD__.' - '.$e->getMessage());}
		return isset($invoice) ? $invoice : null;
	}

	/**
	 * Runs outstanding actions related to Boku callbacks
	 * Because of the asynchronous nature of Boku callbacks we might need a cron job to run this
	 *  Otherwise we could force completion when we view an order in the admin
	 * 
	 * @param string $trx_id - if null then looks at all transactions
	 */
	public static function completeOutstanding($trx_id = null){
		foreach(array('event','chargeback','transaction') as $c)
			Mage::getSingleton(self::APP_ROOT.'/payment_'.$c)->completeOutstanding($trx_id);
	}

	/**
	 * Used for creating or verifying the signature field in Boku api calls
	 * 
	 * @param array $data
	 * @return string
	 */
	public static function getCompressedParameterString(&$data){
		ksort($data);
		$t = '';
		foreach($data as $k=>$v){
			$v = trim((string) $v);
			if ($v != '') $t .= trim($k).$v;
		}
		return $t;
	}

	/**
	 * Add the Boku sig field to the params and add or updates the timestamp field
	 * These fields are required for all Boku api calls.
	 *
	 * @param array $params
	 */
	public static function addSignature(&$params){
		$api_key = self::getConfig('api_security_key');
		$params['timestamp'] = str_pad(time(), 10, '0', STR_PAD_LEFT);
		$t = self::getCompressedParameterString($params);
		$params['sig'] = md5($t.$api_key);
	}

	/**
	 * Build a Boku URL - adds the timestamp and sig parameters
	 *
	 * @param string $url
	 * @param array $params
	 * @return string
	 */
	public static function buildURL($url, $params = array()){
		if (($i = strpos($url, '?')) != false){
			$query = substr($url, $i + 1);
			$url = substr($url, 0, $i);
			foreach(explode('&', $query) as $p){
				$pa = explode('=', $p);
				$k = urldecode($pa[0]);
				if (!array_key_exists($k, $params))
					$params[$k] = urldecode($pa[1]);
			}
		}
		self::addSignature($params);
		$url .= '?';
		foreach($params as $k=>$v)
			$url .= urlencode($k).'='.urlencode($v).'&';
		return $url;
	}

/*	function getRemoteIp() {
		$data = function_exists('apache_request_headers') ? apache_request_headers() : $_SERVER;

		foreach (array(
			'X-Forwarded-For',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
			) as $key)
			if (array_key_exists($key, $data) && filter_var($data[$key], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
				return $data[$key];
		return $_SERVER['REMOTE_ADDR'];
	}
*/
	/**
	 * checks the validity of the sig parameter in the Boku callback url
	 * $data can be full url, just the query part or array of query parameters
	 *
	 * @param mixed $data
	 * @return boolean
	 */
	public static function verifySignature($data){
		if (!is_array($data)){
			if (!is_string($data)) return false;
			if (strpos($data, 'sig=') === false) return false;
			$params = array();
			if (($i = strpos($data, '?')) !== false)
				$data = substr($data, $i + 1);
			foreach(explode('&', $data) as $p){
				$pa = explode('=', $p);
				$k = urldecode($pa[0]);
				if (!array_key_exists($k, $params))
					$params[$k] = trim(urldecode($pa[1]));
			}
		}elseif(!array_key_exists('sig', $params = $data))
			return false;
		$api_key = self::getConfig('api_security_key');
		$sig = $params['sig'];
		unset($params['sig']);
		$t = self::getCompressedParameterString($params);
		return strcasecmp($sig, md5($t.$api_key)) == 0;
	}

	public static function getUrl($route = null, $params = null){
		$store = self::getStore();
		if (empty($params))
			$params = array('_store'=>$store->getId());
		else
			$params['_store'] = $store->getId();
		return Mage::getUrl($route, $params);
	}

	/**
	 * Urls for Boku prepare call
	 * 
	 * @return string url
	 */
	public static function getCallbackUrl(){
		$url = self::getConfig('url/callback');
		if (empty($url))
			$url = self::getUrl(self::APP_ROOT.'/api');
		return $url;
	}
	public static function getSuccessUrl(){
		return self::getUrl(self::APP_ROOT.'/standard/success');
	}
	public static function getFailUrl(){
		return self::getUrl(self::APP_ROOT.'/standard/cancel');
	}

	/**
	 * @return Mage_Core_Model_Store | Mage_Core_Model_Website
	 */
	public static function getScopeObject(){
		$session = self::getSession();
		$obj = $session->getScopeObject();
		if (!empty($obj)) return $obj;
		$params = Mage::app()->getRequest()->getParams();
		if (isset($params['store']))
			$obj = Mage::app()->getStore($params['store']);
		elseif (isset($params['website']))
			$obj = Mage::app()->getWebsite($params['website']);
		else
			$obj = Mage::app()->getStore();
		$session->setScopeObject($obj);
		return $obj;
	}

	/**
	 * @return Mage_Core_Model_Store
	 */
	public static function getStore(){
		$obj = self::getScopeObject();
		if ($obj instanceof Mage_Core_Model_Website){
			$store = $obj->getDefaultStore();
			if (!empty($store)) $obj = $store;
		}
		if (!($obj instanceof Mage_Core_Model_Store))
			$obj = Mage::app()->getStore();
		return $obj;
	}

	/**
	 * Sets the current config scope to a particular store.
	 * clears the scope if $store_id == null
	 *
	 * @param int|string $store_id
	 */
	public static function setStore($store_id){
		if (is_null($store_id))
			self::getSession()->unsScopeObject();
		elseif (self::getStore()->getId() != $store_id)
			self::getSession()->setScopeObject(Mage::app()->getStore($store_id));
	}

	/**
	 * Get config data specific to this plugin and current store context
	 * 
	 * @return string
	 */
	public static function getConfig($key){
		return self::getStore()->getConfig(self::CONFIG_ROOT.'/'.$key);
	}

	public static function log($msg, $type = Zend_Log::INFO){
		Mage::log($msg, $type, self::APP_ROOT.'.log');
	}
	public static function logErr($msg){
		self::log($msg, Zend_Log::ERR);
	}
}