<?php
/**
 * Boku standard checkout module
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */

class Boku_Paymentgateway_Model_Prices
{
	const APP_ROOT = 'boku';

	protected $price_list_timeout = 300;

	/**
	 * @param ISO-4217 $currency
	 * @return int
	 */
	public function getCurrencyPrecision($currency){
		$prices = $this->getPrices($currency);
		if (is_array($prices)){
			$prices = $this->getCache();
			$country = Mage::helper(self::APP_ROOT)->getCountryCode();
			try {
				return $prices['country'][$country][$currency]['currency']['currency-decimal-places'];
			} catch (Exception $e){}
		}
		$formatted_price = Mage::app()->getLocale()->currency($currency)->toCurrency(0, array('display'=>Zend_Currency::NO_SYMBOL));
		$pieces = preg_split('/[^0-9]/' , $formatted_price);
		return strlen($pieces[count($pieces) - 1]);
	}
	/**
	 * @param float $value
	 * @param ISO-4217 $currency
	 * @return int
	 */
	public function getIntegerPrice($value, $currency){
		return (int) ($value * pow(10, $this->getCurrencyPrecision($currency)));
	}
	/**
	 * Convert a value expressed in the fractional currency unit to the normal float value
	 *
	 * @param int $price
	 * @param string (ISO 4217) $currency
	 * @return float
	 */
	public function getFloatPrice($price, $currency){
		return $price / pow(10, $this->getCurrencyPrecision($currency));
	}

	/**
	 * Is the required price-point possible
	 * returns false or an array of networks for which it is available
	 * 
	 * @param int $value (default: basket total)
	 * @param ISO-4217 $currency (default: store currency)
	 * @param ISO-3166-1-alpha-2 $country (default: see getPrices)
	 * @return boolean|array
	 */
	public function isAvailable($value = null, $currency = null, $country = null){
		$helper = Mage::helper(self::APP_ROOT);

		if (empty($currency)) $currency = $helper->getCurrencyCode();
		if (empty($currency)) return false;
		if (empty($country)) $country = $helper->getCountryCode();
		if (empty($value)) $value = $helper->getQuote()->getGrandTotal();
		$int_price = $this->getIntegerPrice($value, $currency);

		$prices = $this->getPrices($currency, $country);
		$available = false;
		$pp_found = false;
		if (is_array($prices)){
			foreach($prices as $k=>&$v){
				if (array_key_exists('increment', $v)){
					if ($int_price >= $v['min-price'] && $int_price <= $v['max-price']
						&& ($v['increment'] == 0 || !(($int_price - $v['min-price']) % $v['increment']))){
						$available = (bool) $v['status'];
						$pp_found = true;
						break;
					}
				}elseif (array_key_exists('amount', $v)){
					if ($int_price == $v['amount']){
						$available = !$v['status'] ? false : (isset($v['network']) ? $v['network'] : true);
						$pp_found = true;
						break;
					}
				}
			}
		}
		if (!$available && !$pp_found)
			$available = $this->getAvailableNetworks($currency, $country, $int_price);
		if (is_array($available))
			$helper->getSession()->setNetworks($available);
		else
			$helper->getSession()->unsNetworks();

		if (!$available)
			$helper->log(__METHOD__."($value,$currency,$country) is".($available ? '' : ' NOT').' available for your Boku service '.$helper->getConfig('service_id'), ($available ? Zend_Log::INFO : Zend_Log::WARN));
		return $available;
	}

	/**
	 * Gets array of price-point data for particular country/currency
	 * 
	 * @param ISO-4217 $currency (default: store currency)
	 * @param ISO-3166-1-alpha-2 $country (default: store country)
	 * @return array|null
	 */
	public function getPrices($currency = null, $country = null, $fetch = false){
		$helper = Mage::helper(self::APP_ROOT);

		if (empty($currency)) $currency = $helper->getCurrencyCode();
		if (empty($country)) $country = $helper->getCountryCode();

		if (!$fetch){
			$prices = $this->getCache();
			try {
				$prices = $prices['country'][$country][$currency]['price-points'];
				$fetch = !is_array($prices);
			} catch (Exception $e){$fetch = true;}
		}

		if ($fetch){
			$prices = $this->fetchPrices($currency, $country);
			$this->addToCache($prices);
			try {
				if (!empty($country)){
					$prices = $prices['country'][$country][$currency]['price-points'];
					if (!is_array($prices)) $prices = null;
				}else{
					$p = null;
					foreach($prices['country'] as $c)
						if (array_key_exists($currency, $c) && is_array($c[$currency])){
							$p = $c[$currency]['price-points']; break;
						}
					$prices = $p;
				}
			} catch (Exception $e){$prices = null;}
		}
		if (is_null($prices))
			$helper->log(__METHOD__."($currency,$country): No price data found", Zend_Log::WARN);
		return $prices;
	}

	/**
	 * Fetches a list of available phone networks for given country, currency and price.
	 * Also adds the price info to the cached price list.
	 * If the price is not available then it is also added to the cache as unavailable.
	 *
	 * @param ISO-4217 $currency
	 * @param ISO-3166-1-alpha-2 $country
	 * @param int $price
	 * @return array|null
	 */
	private function getAvailableNetworks($currency, $country, $price){
		$helper = Mage::helper(self::APP_ROOT);
		$data = $this->fetchPrices($currency, $country, $price);
		$networks = false;
		try{
			if (!empty($data)){
				$p = &$data['country'][$country][$currency]['price-points'][0];
				$networks = $p['network'];
			}else
				$p = array(
					'status'=>0,
					'min-price'=>$price,
					'max-price'=>$price,
					'increment'=>0,
				);
			$pl = array();
			$pl['country'][$country][$currency]['price-points'][] = $p;
			$this->addToCache($pl);
		}catch(Exception $e){}
		return $networks;
	}

	/**
	 * Recursive merge of 2 arrays. a1 is modified and returned
	 * if a2 key is an integer then append to a1
	 * elseif a2 and a1 values are both arrays then merge
	 * else replace
	 *
	 * @param array &$a1
	 * @param array &$a2
	 * @return array
	 */
	private function &arrayMerge(&$a1, &$a2){
		foreach ($a2 as $k=>&$v)
			if (is_integer($k))
				$a1[] = $a2[$k];
			elseif (is_array($v) && isset($a1[$k]) && is_array($a1[$k]))
				$this->arrayMerge($a1[$k], $v);
			else $a1[$k] = $v;
		return $a1;
	}

	/**
	 * fetches price data from the cache
	 * if the cache has timed out then clears the cache and returns null
	 *
	 * @return array|null
	 */
	private function getCache(){
		$session = Mage::helper(self::APP_ROOT)->getSession();
		$plt = $session->getPriceListTimestamp();
		if (is_integer($plt)){
			if ((time() - $plt) <= $this->price_list_timeout)
				return $session->getPriceList();
			$this->clearCache();
		}
		return null;
	}
	/**
	 * adds price data to the cache
	 * if the cache was empty then sets the timestamp
	 *
	 * @param array
	 */
	private function addToCache(&$d){
		if (!is_array($d)) return;
		$session = Mage::helper(self::APP_ROOT)->getSession();
		$pl = $session->getPriceList();
		if (!is_integer($session->getPriceListTimestamp()) || !is_array($pl))
			$session->setPriceListTimestamp(time());
		$session->setPriceList(is_array($pl) ? $this->arrayMerge($pl, $d) : $d);
	}
	private function clearCache(){
		$session = Mage::helper(self::APP_ROOT)->getSession();
		$session->unsPriceList();
		$session->unsPriceListTimestamp();
	}

	/**
	 * Fetches price data from Boku
	 * If $price is not provided then it will fetch price-list otherwise print-info.
	 *
	 * @param ISO-4217 $currency
	 * @param ISO-3166-1-alpha-2 $country
	 * @param int $price (default: null)
	 * @return array|null
	 */
	public function fetchPrices($currency, $country, $price = null){
		$helper = Mage::helper(self::APP_ROOT);
		$price_url = $helper->getConfig(is_null($price) ? 'url/pricelist' : 'url/priceinfo');
		$service_id = $helper->getConfig('service_id');
		$params = array(
			'merchant-id'=>$helper->getConfig('merchant_id'),
			'service-id'=>$service_id,
			'country'=>$country,
			'currency'=>$currency,
			'reference-currency'=>$helper->getBaseCurrencyCode(),
//			'show-all-networks'=>'true',
		);
		if (!is_null($price))
		$params = array_merge($params, array(
			'price'=>$price,
			'show-all-networks'=>'true',
		));

		$client = new Zend_Http_Client($helper->buildUrl($price_url, $params));
		try{
			$response = $client->request();
			if ($response->isSuccessful()){
				if (!Boku_Paymentgateway_Model_Xml::isValidXml($response->getBody()))
					throw new Exception('Bad XML fetched from '.$price_url);
				$prices = new Boku_Paymentgateway_Model_Xml($response->getBody());
				$prices = $prices->asArray();
				if ($prices['response-code'] != 0){
					$msg = $prices['response-message'];
					switch($prices['response-code']){
						case 33: $msg .= ' ('.$currency.')'; break;
						case 34: $msg .= ' ('.$service_id.')'; break;
						case 36: $msg .= ' ('.$country.')'; break;
					}
					throw new Exception($msg);
				}
				self::collapsePriceArray($prices);
				if (!array_key_exists('country', $prices))
					throw new Exception('No price-point data retrieved');
				self::sectionCurrencies($prices);
				return $prices;
			}else
				throw new Exception('Http Failed: '.$response->getMessage());
		}catch(Exception $e){$helper->logErr(__METHOD__."($currency,$country,$price): ".$e->getMessage());}
		return null;
	}

	/**
	 * Shifts price data into currency sections and removes some repeated data.
	 *
	 * @param array &$data (by reference)
	 * @return array
	 */
	private static function &sectionCurrencies(&$data){
		unset($data['format']);
		unset($data['reference-currency']);
		foreach($data['country'] as &$country){
			$first = true;
			foreach(array_keys($country) as $k){
				$pp = $country[$k];
				unset($country[$k]);
				$currency = $pp['currency'];
				unset($pp['currency']);
				if ($first)
					$country[$currency]['currency'] = array(
						'currency-decimal-places'=>$pp['currency-decimal-places'],
						'reference-currency'=>$pp['reference-currency'],
						'exchange'=>$pp['exchange'],
					);
				unset($pp['currency-decimal-places']);
				unset($pp['reference-currency']);
				unset($pp['exchange']);
				$country[$currency]['price-points'][] = $pp;
				$first = false;
			}
		}
		return $data;
	}

	/**
	 * Simplifies and cleans up the output from Boku_Paymentgateway_Model_Xml::asArray()
	 * Intended to work with Boku price-list and price-info responses.
	 *
	 * @param array &$data (by reference)
	 * @return array
	 */
	private static function &collapsePriceArray(&$data){
		//strip useless data
		if (isset($data['@string'])) unset($data['@string']);

		if (count($data) > 1 && isset($data['@code'])){
			$na = array();
			$code = '@'.$data['@code'];
			unset($data['@code']);
			foreach($data as $k=>&$v) $na[$k] = $v;
			$data = array($code=>$na);
		}

		//do recursion
		foreach($data as $k=>&$v)
			if (is_array($v))
				self::collapsePriceArray($v);

		//
		foreach($data as $k=>&$v){
			if (!(is_array($v) && is_numeric($k))) continue;
			$na = 0;
			foreach($v as $vk=>&$vv)
				if (substr((string) $vk, 0, 1) == '@'){
					if (++$na > 1) break;
					$ak = $vk;
					$av = $vv;
				}
			if ($na == 1 && !is_array($av) && !array_key_exists($av, $data)){
				unset($v[$ak]);
				$data[$av] = $v;
				unset($data[$k]);
			}
		}

		//remove @ from start of attribute keys
		foreach($data as $k=>&$v)
			if (substr((string) $k, 0, 1) == '@'){
				$data[substr((string) $k, 1)] = $v;
				unset($data[$k]);
			}

		//collapse single element arrays into their parent
		foreach($data as $k=>&$v)
			if (is_array($v) && count($v) == 1){
				foreach($v as $vk=>&$vv){
					if (is_numeric($vk) || $vk == 'name'
						|| (($vk == 'continuous-price' || $vk == 'discrete-price') && !array_key_exists('status', $vv)))
						$v = $vv;
					elseif (is_numeric($k) && !array_key_exists($vk, $data)){
						$data[$vk] = $vv;
						unset($data[$k]);
					}
					break;
				}
			}
		return $data;
	}
}
