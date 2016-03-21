<?php
/**
 * Boku Tests
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */

class Boku_Paymentgateway_Model_Test
{
	const APP_ROOT = 'boku';

	/**
	 * Attempts to fetch price-list data from Boku
	 * returns info/results for process 
	 * 
	 * @return array
	 */
	public function testConnection(){
		$helper = Mage::helper(self::APP_ROOT);

		$price_url = $helper->getConfig('url/pricelist');
		$merchant_id = $helper->getConfig('merchant_id');
		$service_id = $helper->getConfig('service_id');
		$api_key = $helper->getConfig('api_security_key');
		$out = array("Attempting to fetch price-point data from $price_url");
		try{
			if (empty($merchant_id) || empty($service_id) || empty($api_key)){
				if (empty($merchant_id)) $out[] = 'Merchant Id not set';
				if (empty($service_id)) $out[] = 'Service Id not set';
				if (empty($api_key)) $out[] = 'API Security Key not set';
				throw new Exception('Boku payment settings incomplete.');
			}

			$currency = $helper->getCurrencyCode();
			$country = $helper->getCountryCode();
			$params = array(
				'merchant-id'=>$merchant_id,
				'service-id'=>$service_id,
				'currency'=>$currency,
				'country'=>$country,
				'reference-currency'=>$helper->getBaseCurrencyCode(),
			);
			$client = new Zend_Http_Client($helper->buildUrl($price_url, $params));
			$response = $client->request();
			if ($response->isSuccessful()){
				if (!Boku_Paymentgateway_Model_Xml::isValidXml($response->getBody()))
					throw new Exception('Bad XML fetched from '.$price_url);
				$prices = new Boku_Paymentgateway_Model_Xml($response->getBody());
				$prices = $prices->asArray();
				if ($prices['response-code'] != 0){
					$msg = $prices['response-message'];
					switch($prices['response-code']){
						case 28: $msg .= ' (This is probably caused by incorrect values for your Merchant Id, Service Id or API Security Key)'; break;
						case 33: $msg .= ' ('.$currency.')'; break;
						case 34: if (!empty($service_id)) $msg .= ' ('.$service_id.')'; break;
						case 36: if (!empty($country)) $msg .= ' ('.$currency.')'; break;
					}
					throw new Exception($msg);
				}
				$out[] = "Connection Successful";
			}else
				throw new Exception('Http Failed: '.$response->getMessage());
		}catch(Exception $e){
			$out[] = $e->getMessage();
		}
		return $out;
	}

}
