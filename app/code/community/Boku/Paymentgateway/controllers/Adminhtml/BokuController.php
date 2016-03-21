<?php
/**
 * Admin controller
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */
class Boku_Paymentgateway_Adminhtml_BokuController extends Mage_Adminhtml_Controller_Action
{
	public function testAction(){
		$this->loadLayout();
		$this->renderLayout();
	}
}