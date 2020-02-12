<?php

class Pay_Helper_Transaction
{

    public static function processTransaction($transactionId, $dry_run = false)
    {
        /**
         * @var $module paynl_paymentmethods
         */
        $module = Module::getInstanceByName(Tools::getValue('module'));

        $token     = Configuration::get('PAYNL_TOKEN');
        $serviceId = Configuration::get('PAYNL_SERVICE_ID');

        $apiInfo = new Pay_Api_Info();

        $apiInfo->setApiToken($token);
        $apiInfo->setServiceId($serviceId);
        $apiInfo->setTransactionId($transactionId);

        $result = $apiInfo->doRequest();


        $transactionAmount = $result['paymentDetails']['paidCurrenyAmount'];

        $stateId = $result['paymentDetails']['state'];

        if ($stateId == 95) {
            // authorized transactions have no paidamount
            $transactionAmount = $result['paymentDetails']['currenyAmount'];
        }

        $stateText = self::getStateText($stateId);

        //de transactie ophalen
        try {
            $transaction = self::getTransaction($transactionId);
        } catch (Pay_Exception $ex) {
            // transactie is niet gevonden... quickfix, we voegen hem opnieuw toe
            self::addTransaction($transactionId, $result['paymentDetails']['paymentOptionId'],
                $result['paymentDetails']['amount'], $result['paymentDetails']['paidCurrency'],
                str_replace('CartId: ', '', $result['statsDetails']['extra1']), 'Inserted after not found');

            $transaction = self::getTransaction($transactionId);
        }

        $cartId = $orderId = $transaction['order_id'];

        $orderPaid = self::orderPaid($orderId);

        if ($dry_run) {
            $real_order_id = Order::getOrderByCartId($orderId);

            return array(
                'orderId'       => $orderId,
                'state'         => $stateText,
                'real_order_id' => $real_order_id,
            );
        }

        if ($orderPaid == true && $stateText != 'PAID') {
            throw new Pay_Exception_Notice('Order already paid');
        }

        if ($stateText == $transaction['status']) {
            throw new Pay_Exception_Notice('Status already processed');
        }

        if ($stateText == 'PAID') {
            $id_order_state = Configuration::get('PAYNL_SUCCESS');

            /** @var CartCore $cart */
            $cart     = new Cart($cartId);
            $customer = new Customer($cart->id_customer);

            /** @var CurrencyCore $objCurrency */
            $objCurrency = Currency::getCurrencyInstance((int) $cart->id_currency);

            $orderTotal = $cart->getOrderTotal();
            $orderTotalBase = Tools::convertPrice($orderTotal, $objCurrency, false);

            $extraFeeBase   = $module->getExtraCosts($transaction['option_id'], $orderTotalBase);
            $extraFee = Tools::convertPrice($extraFeeBase, $objCurrency, true);

            if (isset($cart->additional_shipping_cost)) {
                $cart->additional_shipping_cost += $extraFee;
            }

            $cart->save();

            $paymentMethodName = $module->getPaymentMethodName($transaction['option_id']);
            $paidAmount = $transactionAmount / 100;

            $module->validateOrderPay((int)$cart->id, $id_order_state, $paidAmount, $extraFee, $paymentMethodName, null,
                array('transaction_id' => $transactionId), (int)$objCurrency->id, false, $customer->secure_key);

        } elseif ($stateText == 'CANCEL') {
            // Only cancel if validateOnStart is true

            $real_order_id = Order::getOrderByCartId($cartId);

            if ( ! self::shouldCancel($transactionId)) {
                throw new Pay_Exception_Notice('Not cancelling because an order should not have been made by this method');
            }

            if ($real_order_id) {
                /**
                 * @var $objOrder OrderCore
                 */
                $objOrder          = new Order($real_order_id);
                $history           = new OrderHistory();
                $history->id_order = (int)$objOrder->id;
                $history->changeIdOrderState((int)Configuration::get('PAYNL_CANCEL'), $objOrder);
                $history->addWithemail();
            }
        }

        self::updateTransactionState($transactionId, $stateText);

        $real_order_id = Order::getOrderByCartId($cartId);
        return array(
            'orderId'       => $orderId,
            'real_order_id' => $real_order_id,
            'state'         => $stateText,
        );
    }

    /**
     * Get the status by statusId
     *
     * @param int $statusId
     *
     * @return string The status
     */
    public static function getStateText($stateId)
    {
        switch ($stateId) {
            case 80:
            case -51:
                return 'CHECKAMOUNT';
            case 100:
            case 95:
                return 'PAID';
            default:
                if ($stateId < 0) {
                    return 'CANCEL';
                } else {
                    return 'PENDING';
                }
        }
    }

    public static function getTransaction($transaction_id)
    {
        $db = Db::getInstance();

        $sql = "SELECT * FROM " . _DB_PREFIX_ . "pay_transactions WHERE transaction_id = '" . $db->escape($transaction_id) . "'";

        $row = $db->getRow($sql);
        if (empty($row)) {
            throw new Pay_Exception('Transaction not found');
        }

        return $row;
    }

    public static function addTransaction($transaction_id, $option_id, $amount, $currency, $order_id, $startData)
    {
        $db = Db::getInstance();

        $data = array(
            'transaction_id' => $transaction_id,
            'option_id'      => (int)$option_id,
            'amount'         => (int)$amount,
            'currency'       => $currency,
            'order_id'       => $order_id,
            'status'         => 'NEW',
            'start_data'     => $db->escape(json_encode($startData)),
        );

        $db->insert('pay_transactions', $data);
    }

    /**
     * Check if the order is already paid, it is possible that an order has more than 1 transaction.
     * So we heck if another transaction for this order is already paid
     *
     * @param integer $order_id
     */
    public static function orderPaid($order_id)
    {
        $db = Db::getInstance();

        $sql = "SELECT * FROM " . _DB_PREFIX_ . "pay_transactions WHERE order_id = '" . $db->escape($order_id) . "' AND status = 'PAID'";

        $row = $db->getRow($sql);
        if (empty($row)) {
            return false;
        } else {
            return true;
        }
    }

    private static function updateTransactionState($transactionId, $statusText)
    {
        $db = Db::getInstance();

        $db->update('pay_transactions', array('status' => $statusText),
            "transaction_id = '" . $db->escape($transactionId) . "'");
    }

    private static function shouldCancel($transaction_id)
    {
        /**
         * @var $module paynl_paymentmethods
         */
        $module = Module::getInstanceByName(Tools::getValue('module'));

        $transaction = self::getTransaction($transaction_id);

        return $module->validateOnStart($transaction['option_id']);
    }

}
