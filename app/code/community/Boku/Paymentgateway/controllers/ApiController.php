<?php
/**
 * Boku callback controller
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */
class Boku_Paymentgateway_ApiController extends Mage_Core_Controller_Front_Action
{
	const APP_ROOT = 'boku';

	static $STATUS = array(
		-1	=> 'Unknown error code',
		0	=> 'OK',
		5	=> 'Failed - unknown trx',
		20	=> 'Missing or Invalid "cmd=" value',
		28	=> 'Invalid signature',
		29	=> 'Unsupported Price Point',
		31	=> 'Invalid Or Missing Price',
		32	=> 'Bad Bind Credentials',
		33	=> 'Invalid Or Missing Currency Code',
		34	=> 'Invalid Or Missing Service-Id',
		35	=> 'Internal Error',
		36	=> 'Invalid or Missing Country Code',
		37	=> 'Invalid Dynamic Pricing Mode',
		38	=> 'Invalid Dynamic-match',
		39	=> 'Invalid or missing Dynamic-deviation',
		40	=> 'Invalid or missing Dynamic-deviation-policy',
		41	=> 'No payment solution available',
		42	=> 'Country not available on requested service',
		43	=> 'Invalid Request',
		51	=> 'Expired timestamp',
		52	=> 'Incorrect field format',
		53	=> 'Invalid field value',
		60	=> 'Invalid "row-ref" value',
		91	=> 'Missing or invalid user parameter(s)',
		93	=> 'Unsupported network',
		99	=> 'Boku undergoing maintenance',
	);
	/**
	 * These are the minimum sets of parameters required for each callback.
	 */
	static $REQUIRED_PARAMS = array(
		'common'=>array(
			'trx-id',
			'timestamp',
			'action',
		),
		'billingresult'=>array(
			'result-code',
			'currency',
			'paid',
			'amount',
		),
		'event'=>array(
			'event-code',
			'currency',
			'paid',
			'message-cost',
		),
		'chargeback'=>array(
			'currency',
			'chargebackamount',
			'paid',
			'reason-id',
			'refundsource',
		),
	);

	/**
	 * Handler for all callbacks from Boku
	 *
	 * @input URL parameters
	 * @output XML
	 */
	public function indexAction(){
		$helper = Mage::helper(self::APP_ROOT);
		$helper->setStore(null);
		$data = Mage::app()->getRequest()->getParams();
		$response_data = $this->verifyCallback($data);

		if ($response_data['status_code'] == 0){
			switch($data['action']){
				case 'billingresult':
				case 'event':
				case 'chargeback':
					try{
						$transaction = $helper->getTransaction($data);
						if (empty($transaction))
							throw new Exception('Transaction data not found for trx-id='.$data['trx-id'], 5);
					}catch(Exception $e){
						$code = $e->getCode();
						if (empty($code)) $code = -1;
						$response_data = array_merge($response_data, array(
							'status_code'=>$code,
							'status'=>self::$STATUS[$code],
						));
						$data['notes'] = $e->getMessage();
						$helper->logErr(__METHOD__.' - '.$e->getMessage());
						break;
					}
					try{
						$this->{$data['action'].'Handler'}($data, $response_data);
					}catch(Exception $e){
						$helper->log(__CLASS__.'::'.$data['action'].' - '.$e->getMessage());
					}
					break;
				default:
					$response_data = array_merge($response_data, array(
						'status_code'=>53,
						'status'=>self::$STATUS[53],
						'field'=>'action',
					));
			}
		}
		$data['status_code'] = $response_data['status_code'];
		$data['status'] = $response_data['status'];
		$this->logCallback($data);
		$r = $this->getResponse();
		$r->setHeader('HTTP/1.1 200 OK', '', true);
		$r->setHeader('Content-Type', 'text/xml; charset=utf-8', true);
		$r->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate', true);
		$r->setHeader('Expires', 'Expires: Sat, 1 Jan 2000 00:00:00 GMT', true);
		$r->setHeader('Pragma', 'no-cache', true);
		$r->setBody($this->getCallbackResponseXml($response_data)->asXML());
	}

	/**
	 * Callback from Boku at the end of the payment process
	 * Note:assumes that the currency is the same as original prepare call
	 * 
	 * @param array $data
	 * @param array $response
	 * @return array $response
	 */
	protected function &billingresultHandler($data, &$response){
		$helper = Mage::helper(self::APP_ROOT);
		$transaction = $helper->getTransaction($data);
		if (is_numeric($transaction->getResultCode()))
			$helper->logErr('Unexpected extra billingresult for trx-id:'.$data['trx-id']);
		else
			$transaction->complete($data);
		return $response;
	}

	/**
	 * Callback from Boku made for each payment
	 * Note:assumes that the currency is the same as original prepare call
	 * 
	 * @param array $data
	 * @param array $response
	 * @return array $response
	 */
	protected function &eventHandler(&$data, &$response){
		Mage::getSingleton(self::APP_ROOT.'/payment_event')->create($data);
		return $response;
	}

	/**
	 * Callback from Boku for refunds
	 * Note:assumes that the currency is the same as original prepare call
	 * 
	 * @param array $data
	 * @param array $response
	 * @return array $response
	 */
	protected function &chargebackHandler(&$data, &$response){
		Mage::getSingleton(self::APP_ROOT.'/payment_chargeback')->create($data);
		return $response;
	}

	/**
	 * Creates a new callback record for the $data['trx-id'] transaction
	 * 
	 * @param array $data
	 */
	protected function logCallback(&$data){
		if ($data['status_code'] == 5) $data['trx-id'] = null;
		return Mage::getModel(self::APP_ROOT.'/payment_callback')->addData($data)->save();
	}

	/**
	 * Does general verification for all callbacks from Boku
	 * Aborts as soon as a failure is encountered.
	 *
	 * @param array $params
	 * @return array(status_code=>int, status=>string[, field=>string])
	 */
	protected function verifyCallback(&$params){
		$helper = Mage::helper(self::APP_ROOT);
		try{
			$remote_ip = Mage::helper('core/http')->getRemoteAddr();
			$valid_ips = $helper->getConfig('callback_ips');
			if (!empty($valid_ips) && !in_array($remote_ip, explode(';', $valid_ips)))
				throw new Exception('Invalid source IP for callback: '.$remote_ip, -1);

		//Verify the validity of the callback signature
			if (!$helper->verifySignature($params)) throw new Exception('sig', 28);

		//Verify that primary fields exist
			foreach (self::$REQUIRED_PARAMS['common'] as $field)
				if (!array_key_exists($field, $params)) throw new Exception($field, $field == 'trx-id' ? 5 : 43);

		//Verify callback specific fields exist
			foreach (self::$REQUIRED_PARAMS[$params['action']] as $field)
				if (!array_key_exists($field, $params)) throw new Exception($field, 43);

			$response = array('status_code'=>0, 'status'=>self::$STATUS[0]);

		}catch(Exception $e){
			$helper->logErr(__METHOD__.': '.$e->getMessage().' '.self::$STATUS[$e->getCode()]."\n".json_encode($params, JSON_PRETTY_PRINT));
			$status = isset(self::$STATUS[$e->getCode()]) ? self::$STATUS[$e->getCode()] : 'Unknown Status';
			$response = array('status_code'=>$e->getCode(), 'status'=>$status, 'field'=>$e->getMessage());
		}
		$response['trx-id'] = array_key_exists('trx-id', $params) ? $params['trx-id'] : 'Unknown';
		return $response;
	}

	/**
	 * Generates SimpleXML data for response.
	 *
	 * @param array $data
	 * @return SimpleXML
	 */
	protected function getCallbackResponseXml($data){
		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8" standalone="yes" ?><callback-ack></callback-ack>');
		$xml->addChild('trx-id', $data['trx-id']);
		$s = $xml->addChild('status', $data['status']);
		$s->addAttribute('code', $data['status_code']);
		if ($data['status_code'] == 53)
			$s->addAttribute('invalidfield', $data['field']);
		return $xml;
	}
}
