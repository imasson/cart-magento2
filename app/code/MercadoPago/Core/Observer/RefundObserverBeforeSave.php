<?php

namespace MercadoPago\Core\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 * Class RefundObserverBeforeSave
 *
 * @package MercadoPago\Core\Observer
 */

class RefundObserverBeforeSave implements ObserverInterface
{

    protected $_session;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;

    /**
     * @var \MercadoPago\Core\Helper\Data
     */
    protected $_dataHelper;

    /**
     * RefundObserverBeforeSave constructor.
     * @param \Magento\Backend\Model\Session $session
     * @param \Magento\Framework\App\Action\Context $context
     * @param \MercadoPago\Core\Helper\Data $dataHelper
     */
    public function __construct(\Magento\Backend\Model\Session $session,
                                \Magento\Framework\App\Action\Context $context,
                                \MercadoPago\Core\Helper\Data $dataHelper)
    {
        $this->_session = $session;
        $this->_messageManager = $context->getMessageManager();
        $this->_dataHelper = $dataHelper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $creditMemo = $observer->getData('creditmemo');
        $order = $creditMemo->getOrder();
        $this->creditMemoRefundBeforeSave($order, $creditMemo);
    }

    /**
     * @param $order \Magento\Sales\Model\Order
     * @param $creditMemo \Magento\Sales\Model\Order\Creditmemo
     */
    protected function creditMemoRefundBeforeSave ($order, $creditMemo)
    {
        if ($order->getExternalRequest()) {
            return; // si la peticion de crear un credit memo viene de mercado pago, no hace falta mandar el request nuevamente
        }

        $orderStatus = $order->getData('status');
        $orderPaymentStatus = $order->getPayment()->getData('additional_information')['status'];
        $payment = $order->getPayment();
        $paymentID = $order->getPayment()->getData('additional_information')['id'];
        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
        $orderStatusHistory = $order->getAllStatusHistory();
        $isCreditCardPayment = ($order->getPayment()->getData('additional_information')['installments'] != null ? true : false);

        $paymentDate = null;
        foreach ($orderStatusHistory as $status) {
            if (strpos($status->getComment(), 'The payment was approved') !== false) {
                $paymentDate = $status->getCreatedAt();
                break;
            }
        }

        $isTotalRefund = $payment->getAmountPaid() == $payment->getAmountRefunded();

        $isValidBasicData = $this->checkRefundBasicData ($paymentMethod, $paymentDate);

        $isValidaData = $this->checkRefundData ($isCreditCardPayment,
            $orderStatus,
            $orderPaymentStatus,
            $paymentDate,
            $order);

        if ($isValidBasicData && $isValidaData) {
            $this->sendRefundRequest($order, $creditMemo, $paymentMethod, $isTotalRefund, $paymentID);
        }

    }

    /**
     * @param $paymentMethod
     * @param $paymentDate
     *
     * @return bool
     */
    protected function checkRefundBasicData ($paymentMethod, $paymentDate) {
        $refundAvailable = $this->_dataHelper->isRefundAvailable();

        if ($paymentDate == null) {
            $this->_messageManager->addErrorMessage(__('No payment is recorded. You can\'t make a refund on a unpaid order'));
            return false;
        }

        if (!($paymentMethod == 'mercadopago_standard' || $paymentMethod == 'mercadopago_custom')) {
            $this->_messageManager->addErrorMessage(__('Order payment wasn\'t made by Mercado Pago. The refund will be made through Magento'));
            return false;
        }

        if (!$refundAvailable) {
            $this->_messageManager->addErrorMessage(__('Mercado Pago refunds are disabled. The refund will be made through Magento'));
            return false;
        }

        return true;
    }

    /**
     * @param $isCreditCardPayment
     * @param $orderStatus
     * @param $orderPaymentStatus
     * @param $paymentDate
     * @param $order
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function checkRefundData ($isCreditCardPayment,
                                        $orderStatus,
                                        $orderPaymentStatus,
                                        $paymentDate,
                                        $order)
    {

        $maxDays = $this->_dataHelper->getMaximumDaysRefund();
        $maxRefunds = $this->_dataHelper->getMaximumPartialRefunds();

        $isValidaData = true;

        if (!$isCreditCardPayment) {
            $this->_messageManager->addErrorMessage(__('You can only refund orders paid by credit card'));
            $isValidaData = false;
        }

        if (!($orderStatus == 'processing' || $orderStatus == 'completed')) {
            $this->_messageManager->addErrorMessage(__('You can only make refunds on orders whose status is Processing or Completed'));
            $isValidaData = false;
        }

        if (!($orderPaymentStatus == 'approved')) {
            $this->_messageManager->addErrorMessage(__('You can only make refunds on orders whose payment status Approved'));
            $isValidaData = false;
        }

        if (!($this->daysSince($paymentDate) < $maxDays)) {
            $this->_messageManager->addErrorMessage(__('Refunds are accepted up to ') .
                $maxDays . __(' days after payment approval. The current order exceeds the limit set'));
            $isValidaData = false;
        }

        if (!(count($order->getCreditmemosCollection()->getItems()) < $maxRefunds)) {
            $isValidaData = false;
            $this->_messageManager->addErrorMessage(__('You can only make ' . $maxRefunds . ' partial refunds on the same order'));
        }

        if (!$isValidaData) {
            $this->throwRefundException();
        }

        return $isValidaData;
    }

    /**
     * @param $order \Magento\Sales\Model\Order
     * @param $creditMemo \Magento\Sales\Model\Order\Creditmemo
     * @param $paymentMethod string
     * @param $isTotalRefund boolean
     * @param $paymentID int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function sendRefundRequest ($order, $creditMemo, $paymentMethod, $isTotalRefund, $paymentID) {
        $clientId = $this->_dataHelper->getClientId();
        $clientSecret = $this->_dataHelper->getClientSecret();

        $mp = $this->_dataHelper->getApiInstance($clientId, $clientSecret);
        $response = null;
        $amount = $creditMemo->getGrandTotal();
        $access_token = $this->_dataHelper->getAccessToken();
        if ($paymentMethod == 'mercadopago_standard') {
            if ($isTotalRefund) {
                $response = $mp->refund_payment($paymentID);
                $order->setMercadoPagoRefundType('total');
            } else {
                $order->setMercadoPagoRefundType('partial');
                $metadata = [
                    "reason" => '',
                    "external_reference" => $order->getIncrementId(),
                ];
                $params = [
                    "amount" => $amount,
                    "metadata" => $metadata,
                ];
                $response = $mp->post("/collections/$paymentID/refunds?access_token=$access_token", $params);
            }
        } else {
            if ($isTotalRefund) {
                $response = $mp->post("/v1/payments/$paymentID/refunds?access_token=$access_token", null);
            } else {
                $params = [
                    "amount" => $amount,
                ];
                $response = $mp->post("/v1/payments/$paymentID/refunds?access_token=$access_token", $params);
            }
        }

        if ($response['status'] == 201 || $response['status'] == 200) {
            $order->setMercadoPagoRefund(true);
            $this->_messageManager->addSuccessMessage(__('Refund made by Mercado Pago'));
        } else {
            $this->_messageManager->addErrorMessage(__('Failed to make the refund by Mercado Pago'));
            $this->_messageManager->addErrorMessage($response['status'] . ' ' . $response['response']['message']);
            $this->throwRefundException();
        }
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function throwRefundException () {
        throw new \Magento\Framework\Exception\LocalizedException(new \Magento\Framework\Phrase('Mercado Pago - Refund not made'));
    }

    /**
     * @param $date
     *
     * @return float days since argument and NOW
     */
    private function daysSince($date)
    {
        $now = strtotime (date('Y-m-d', time()));
        $date = strtotime ($date);
        return (abs($now - $date) / 86400);
    }
}