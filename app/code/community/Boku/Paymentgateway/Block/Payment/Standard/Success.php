<?php
/**
 * After successful Boku buy-url submission (via Prepare block)
 *
 * @category	Payment gateway
 * @package		boku_paymentgateway
 * @copyright	Copyright (c) Boku (http://www.boku.com/)
 * @author		MDH <mdh@treatid.me.uk>
 */

class Boku_Paymentgateway_Block_Payment_Standard_Success extends Boku_Paymentgateway_Block_Payment_Standard_Result
{
	protected function getRedirectUrl(){
		return Mage::helper(self::APP_ROOT)->getUrl('checkout/onepage/success');
	}
}
