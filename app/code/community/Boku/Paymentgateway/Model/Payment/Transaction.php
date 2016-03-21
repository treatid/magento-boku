<?php
/**
 * Overall Boku transaction record.
 * Initially populated by Boku_Paymentgateway_Model_Payment_Standard when an order is submitted
 *  Updated again by Boku_Paymentgateway_ApiController when the transaction is complete
 * 
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author 		MDH <mdh@treatid.me.uk>
 */

Class Boku_Paymentgateway_Model_Payment_Transaction extends Boku_Paymentgateway_Model_Mapped_Abstract{

	private $_order = null;

	/**
	 * Initialize resource model
	 */
	protected function _construct(){
		$this->_init(self::APP_ROOT.'/payment_transaction');
		return parent::_construct();
	}

	/**
	 * @return null|Mage_Sales_Model_Order
	 */
	public function getOrder(){
		if (is_null($this->_order)){
			$id = $this->getOrderId();
			if (empty($id)){
				$model = Mage::getModel('sales/order')->load($this->getQuoteId(), 'quote_id');
				if ($model->getId()){
					$model->addStatusHistoryComment('Boku TRX-ID: '.$this->getTrxId());
					$this->setOrderId($model->getId())->save();
					$this->_order = $model;
				}
			}else{
				$this->_order = Mage::getModel('sales/order')->load($id);
				if (!$this->_order->getId()) $this->_order = null;
			}
		}
		return $this->_order;
	}
	/**
	 * @return null|Mage_Sales_Model_Quote
	 */
	public function getQuote(){
		$model = Mage::getModel('sales/quote')->load($this->getQuoteId());
		return $model->getId() ? $model : null;
	}

	/**
	 * @param array $data - {amount, currency, total}
	 * @return Mage_Sales_Model_Order_Payment|null
	 */
	public function addPayment(&$data){
		return $this->_addPayment($data);
	}
	/**
	 * @param array $data - {amount, currency}
	 * @return Mage_Sales_Model_Order_Payment|null
	 */
	public function addRefund(&$data){
		return $this->_addPayment($data, true);
	}
	/**
	 * Creates order payment/transaction records and updates order paid/refunded values
	 *  Fails if the currency differs from the order currency
	 *
	 * @param array $data - {amount, currency[, total]}
	 * @param boolean $refund
	 * @return Mage_Sales_Model_Order_Payment|null
	 */
	protected function _addPayment(&$data, $refund = false){
		$helper = Mage::helper(self::APP_ROOT);
		$trx_id = $this->getId();
		$order = $this->getOrder();
		try{
			if (empty($order))
				throw new Exception('Order not found for trx_id:'.$trx_id);
			if (($currency = $data['currency']) != $order->getOrderCurrencyCode())
				throw new Exception('Currency invalid: '.$currency.' != '.$order->getOrderCurrencyCode());
			$order_to_base_rate = 1/$order->getBaseToOrderRate();
			$amount = $data['amount'];
			$payment = Mage::getModel('sales/order_payment')->setData(array(
				'method'=>self::APP_ROOT,
				'amount_ordered'=>$order->getGrandTotal(),
				'base_amount_ordered'=>$order->getBaseGrandTotal(),
			));
			if ($refund)
				$payment->addData(array(
					'amount_refunded'=>$amount,
					'base_amount_refunded'=>$amount * $order_to_base_rate,
				));
			else
				$payment->addData(array(
					'amount_paid'=>$amount,
					'base_amount_paid'=>$amount * $order_to_base_rate,
				));

			$order->addPayment($payment);
			$payment->save();
			$transaction = Mage::getModel('sales/order_payment_transaction');
			$transaction
				->setOrderPaymentObject($payment)
				->setTxnId($trx_id)
				->setTxnType($refund ? $transaction::TYPE_REFUND : $transaction::TYPE_PAYMENT)
				->setAdditionalInformation('source', 'Boku '.($refund ? 'refund' : 'payment').' confirmed')
				->save()
				->close();
			if ($refund){
				$order_refund_total = $order->getTotalRefunded() + $amount;
				$order
					->setTotalRefunded($order_refund_total)
					->setBaseTotalRefunded($order_refund_total * $order_to_base_rate)
					->save();
			}else{
				$total = $data['total'];
				if ($order->getTotalPaid() < $total)
					$order
						->setTotalPaid($total)
						->setBaseTotalPaid($total * $order_to_base_rate)
						->save();
			}
			if ($amount > 0)
				$order->addStatusHistoryComment(($refund ? 'Refund' : 'Payment').' received: '.$currency.' '.$amount, false)
					->setIsVisibleOnFront(false)
					->setIsCustomerNotified(false)
					->save();
		}catch(Exception $e){
			$helper->logErr(__METHOD__.' - '.$e->getMessage());
			if (isset($transaction)) $transaction->delete();
			if (isset($payment)) $payment->delete();
			return null;
		}
		return $order->getPayment();
	}

	/**
	 * Changes the order status and cancels if appropriate
	 * If an api billingresult occurs before all api events have been received then
	 *  we may create a dummy payment of 0 to update the order payment values.
	 * If the total order value has been paid we will optionally generate an invoice.
	 *
	 * @return boolean
	 */
	public function complete($data = null){
		if ($this->getHandled()) return true;

		if (!empty($data)){
			$this->_map($data);
			if (array_key_exists('reference_currency', $data)){
				$rc = $this->getReferenceCurrency();
				if (!empty($rc) && $data['reference_currency'] != $rc)
					unset($data['reference_currency']);
				elseif (!isset($data['exchange']) && isset($data['paid']) && isset($data['reference_paid']))
					try{$data['exchange'] = ((float) $data['paid']) / $data['reference_paid'];}catch(Exception $e){}
			}
			if (array_key_exists('timestamp', $data)){
				$data['result_timestamp'] = $data['timestamp'];
				unset($data['timestamp']);
			}
			if (array_key_exists('country', $data))
				$data['country'] = strtoupper($data['country']);
			$this->addData($data)->save();
		}

		$result_code = $this->getResultCode();
		if (is_null($result_code)) return false;
		if (is_null($order = $this->getOrder())) return false;

		$helper = Mage::helper(self::APP_ROOT);

		//Add dummy payment if necessary
		$currency = $this->getCurrency();
		$paid = $helper->getFloatPrice($this->getPaid(), $currency);
		if ($paid > $order->getTotalPaid()){
			$payment_data = array(
				'amount'=>0,
				'currency'=>$currency,
				'total'=>$paid,
			);
			$payment = $this->addPayment($payment_data);
		}
try{
		switch ($result_code){
			case 0:
				switch ($order->getState()){
					case $order::STATE_NEW:
					case $order::STATE_PENDING_PAYMENT:
						$order->setState($order::STATE_PROCESSING)->save();
				}
				$order->addStatusHistoryComment('Boku Transaction Completed Successfully');

				if ($helper->getConfig('auto_invoice')
					&& ($order->getTotalPaid() - $order->getTotalRefunded()) == $order->getGrandTotal()
					&& $order->canInvoice())
					$helper->generateInvoice($order);
				break;
			default:
				$order->addStatusHistoryComment($this->getResultMsg());
				if ($order->canCancel() && $order->getTotalPaid() == 0){
					$order->cancel()->save();
					$this->setCancelled(true)->save();
				}else{
					switch ($order->getState()){
						case $order::STATE_NEW:
						case $order::STATE_PENDING_PAYMENT:
							$order->setState($order::STATE_PROCESSING)->save();
					}
				}
				break;
		}
		$this->setHandled(true)->save();
}catch(Exception $e){$helper->logErr(__METHOD__.' - '.$e->getMessage()); return false;}
		return true;
	}

	/**
	 * runs complete on any incomplete transactions
	 *
	 * @param string $trx_id - if null then looks at all transactions
	 */
	public function completeOutstanding($trx_id = null){
		$helper = Mage::helper(self::APP_ROOT);
		$c = $this->getCollection()
			->addFieldToFilter('handled', 0)
			->addFieldToFilter('result_code', array('notnull' => true))
			->addFieldToFilter('order_id', array('notnull' => true));
		if (!is_null($trx_id))
			$c->addFieldToFilter('trx_id', $trx_id);
		$failed = 0;
		foreach($c->load() as $model){
			$helper->setStore($model->getStoreId());
			if (!$model->complete()) $failed++;
		}
		if ($failed) $helper->log(__METHOD__.': '.$failed.' failed.');
	}

	/**
	 * Initiates the payment process.
	 * If successful we create a new boku transaction record.
	 *
	 * @param Mage_Sales_Model_Quote $quote
	 */
	public static function initiate(Mage_Sales_Model_Quote $quote = null){
		$helper = Mage::helper(self::APP_ROOT);
		if (empty($quote)) $quote = $helper->getQuote();
		try{
			$response = self::_initiate($quote);
			if (empty($response) || $response['result-code'] != 0)
				throw new Exception('Invalid Response');

			$trx_id = $response['trx-id'];
			if (empty($trx_id) || empty($response['buy-url']))
				throw new Exception($response['result-msg']);

			if (!is_null(self::getTransaction($response['trx-id'])))
				throw new Exception('Unexpected duplicate trx-id:'.$trx_id);

			$quote_id = $quote->getId();
			$currency = $quote->getQuoteCurrencyCode();
			$data = array(
				'trx-id'=>$trx_id,
				'test'=>$helper->getConfig('mode'),
				'store_id'=>$helper->getStore()->getId(),
				'quote_id'=>$quote_id,
				'country'=>$helper->getCountryCode(),
				'currency'=>$currency,
				'amount'=>$helper->getIntegerPrice($quote->getGrandTotal(), $currency),
				'reference_currency'=>$quote->getBaseCurrencyCode(),
				'timestamp'=>time(),
			);
			$transaction = self::getTransaction($data, true);
			if (is_null($transaction))
				throw new Exception('Create transaction trx-id:'.$trx_id.' failed');

			$helper->getSession()->addData(array(
				'trx-id'=>$trx_id,
				'quote_id'=>$quote_id,
				'buy-url'=>$response['buy-url'],
			));
		}catch(Exception $e){
			$helper->logErr(__METHOD__.': '.$e->getMessage().(!empty($response) ? "\n".json_encode($response, JSON_PRETTY_PRINT) : ''));
			Mage::throwException($helper->__('Failed to initiate the Boku payment transaction.'));
		}
	}

	/**
	 * Initiates a payment by calling the "prepare" Boku API
	 * 
	 * @param Mage_Sales_Model_Quote $quote
	 * @return null|array
	 */
	protected static function _initiate(Mage_Sales_Model_Quote $quote){
		$helper = Mage::helper(self::APP_ROOT);
		$num = $quote->getItemsCount();
		$currency = $quote->getQuoteCurrencyCode();
		if (!($order_id = $quote->getReservedOrderId()))
			$order_id = reserveOrderId()->getReservedOrderId();

		$params = array(
			'merchant-id'=>$helper->getConfig('merchant_id'),
			'service-id'=>$helper->getConfig('service_id'),
			'country'=>$helper->getCountryCode(),
			'consumer-id'=>$quote->getId(),
			'desc'=>$num.' '.$helper->__('item'.($num > 0 ? 's' : '')),
			'currency'=>$currency,
			'price-inc-salestax'=>$helper->getIntegerPrice($quote->getGrandTotal(), $currency),
			'callback-url'=>$helper->getCallbackUrl(),
			'fwdurl'=>$helper->getSuccessUrl(),
			'fail-fwdurl'=>$helper->getFailUrl(),
			'param'=>$order_id,
		);
		$phone = $helper->getPhone();
		if (!empty($phone)) $params['msisdn'] = str_replace(' ', '', $phone);
		$smn = $helper->getConfig('sub_merchant_name');
		if (!empty($smn)) $params['sub-merchant-name'] = $smn;
		if ($helper->getConfig('mode')) $params['test'] = 1;

		$url = $helper->getConfig('url/prepare');
		$client = new Zend_Http_Client($helper->buildUrl($url, $params));
		try {
			$response = $client->request();
			if ($response->isSuccessful()){
				if (!Boku_Paymentgateway_Model_Xml::isValidXml($response->getBody()))
					throw new Exception('Bad XML fetched from '.$url);
				$xml = new Boku_Paymentgateway_Model_Xml($response->getBody());
				return $xml->asArray();
			}else
				throw new Exception('Http Failed: '.$response->getMessage());
		}catch(Exception $e){$helper->logErr(__METHOD__.': '.$e->getMessage());}
		return null;
	}

	/**
	 * Gets the $data['trx-id'] transaction record
	 * 
	 * @param array|string $data
	 * @param boolean $create_if_none (default:false)
	 * @return Boku_Paymentgateway_Model_Payment_Transaction|null
	 */
	public static function getTransaction(&$data, $create_if_none = false){
		if (is_array($data)){
			if (!array_key_exists('trx-id', $data)) return null;
			$id = $data['trx-id'];
		}else
			$id = $data;
		$model = new self();
		$id = $model->load($id)->getId();
		if (empty($id)){
			if ($create_if_none && is_array($data)){
				try{
					$model->setData($data)->save();
				}catch(Exception $e){
					$model = null;
				}
			}else
				$model = null;
		}
		return $model;
	}
}