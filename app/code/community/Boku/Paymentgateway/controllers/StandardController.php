<?php
/**
 * Boku payment gateway controller
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */
class Boku_Paymentgateway_StandardController extends Mage_Checkout_Controller_Action
{
	const APP_ROOT = 'boku';

	public function prepareAction(){
		$this->loadLayout();
		$this->renderLayout();
	}

	/**
	 * Called by Boku for failed transactions
	 * Cancels the order, detatches the order and transaction from the quote
	 *  restores the quote to the basket.
	 */
	public function cancelAction(){
		$helper = Mage::helper(self::APP_ROOT);
		$boku_session = $helper->getSession();
		$trx_id = $boku_session->getData('trx-id');
		$boku_session->clear();
		$data = Mage::app()->getRequest()->getParams();
		try{
			if (isset($data['trx-id'])){
				if (empty($trx_id))
					$trx_id = $data['trx-id'];
				else if ($data['trx-id'] != $trx_id)
					throw new Exception('trx-id:'.$data['trx-id'].' not same as session:'.$trx_id);
			}else if (empty($trx_id))
				throw new Exception('No trx-id param');
			if (is_null($transaction = $helper->getTransaction($trx_id))) throw new Exception('Transaction '.$trx_id.' record not found.');
			if ($transaction->getCancelled()) throw new Exception('Transaction '.$trx_id.' already cancelled.');

			$order = $transaction->getOrder();
			$quote = $transaction->getQuote();
			$transaction->setCancelled(true)->setQuoteId(null)->save();

			if (!is_null($quote)){
				$quote->setIsActive(true)->setReservedOrderId(null)->save();
				if (!is_null($order))
					$order->setQuoteId(null)->save();
				$session = Mage::getSingleton('checkout/session');
				$session->setQuoteId($quote->getId());
				$session->setFirstTimeChk('0');
				Mage::getSingleton('checkout/type_onepage')->getCheckout()->unsLastQuoteId();
			}

			if (!is_null($order)){
//				$order->addStatusHistoryComment('Boku Transaction Cancelled');
				if ($order->canCancel())
					$order->cancel()->save();
			}
		}catch(Exception $e){
			$helper->logErr(__METHOD__.': '.$e->getMessage());
		}
		$this->loadLayout();
		$this->renderLayout();
	}

	/**
	 * Called by Boku for successful transactions
	 *  changes the order status to STATE_PENDING_PAYMENT (if STATE_NEW)
	 */
	public function successAction(){
		$helper = Mage::helper(self::APP_ROOT);
		$boku_session = $helper->getSession();
		$trx_id = $boku_session->getData('trx-id');
		$boku_session->clear();
		$data = Mage::app()->getRequest()->getParams();
		try{
			if (!isset($data['trx-id'])) throw new Exception('No trx-id param');
			if (empty($trx_id))
				$trx_id = $data['trx-id'];
			else if ($data['trx-id'] != $trx_id)
				throw new Exception('trx-id:'.$data['trx-id'].' not same as session:'.$trx_id);
			if (is_null($transaction = $helper->getTransaction($trx_id))) throw new Exception('Transaction '.$trx_id.' record not found.');
			if ($transaction->getCancelled()) throw new Exception('Transaction '.$trx_id.' already cancelled.');
			if (is_null($order = $transaction->getOrder())) throw new Exception('Order not found for '.$trx_id);

			if ($order->getState() == $order::STATE_NEW)
				$order->setState($order::STATE_PENDING_PAYMENT)->save();
			$order->addStatusHistoryComment('Boku Transaction Initiated');

			$msg = $helper->getConfig('message/success');
			if (!empty($msg)) Mage::getSingleton('checkout/session')->addNotice($msg);
		}catch(Exception $e){
			$helper->logErr(__METHOD__.': '.$e->getMessage());
		}
		$this->loadLayout();
		$this->renderLayout();
	}

	/**
	 * Not used in normal activity. Can be called to force completion of any outstanding callback handling.
	 */
	public function completeAction(){
		Mage::helper(self::APP_ROOT)->completeOutstanding();
	}
}
