<?php
/*
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2014 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */
class paynl_paymentmethodsExchangeModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
            $transactionId = Tools::getValue('order_id');
            $action = Tools::getValue('action');

            Pay_Helper::$identifier = 'exc' . uniqid();
            Pay_Helper::payLog('Exchange incoming: ' . $action, $transactionId);

            try{
                if(strpos($action, 'refund') !== false){
                	die('TRUE| Ignoring refund');
                }
                if(strpos($action, 'pending') !== false){
	                die('TRUE| Ignoring pending');
                }
                $result = Pay_Helper_Transaction::processTransaction($transactionId);
            } catch (Pay_Exception_Notice $ex) {
                Pay_Helper::payLog('Exchange exception notice: ' . $ex->getMessage(), $transactionId);
                echo "TRUE| ";
                echo $ex->getMessage();
                die();
            } catch (Exception $ex) {
                Pay_Helper::payLog('Exchange exception: ' . $ex->getMessage(), $transactionId);
                echo "FALSE| ";
                echo $ex->getMessage();
                die();
            }
            $response = 'TRUE| Status updated to ' . $result['state'] . ' for cartId: ' . $result['orderId'] . ' orderId: ' . @$result['real_order_id'];
            Pay_Helper::payLog('Exchange response: ' . $response, $transactionId);

            echo $response;
            die();
	}
}
