<?php
namespace MercadoPago\Core\Controller\Notifications;

/**
 * Class Standard
 *
 * @package MercadoPago\Core\Controller\Notifications
 */
class Standard
    extends \Magento\Framework\App\Action\Action

{
    /**
     * @var \MercadoPago\Core\Model\Standard\PaymentFactory
     */
    protected $_paymentFactory;

    /**
     * @var \MercadoPago\Core\Helper\
     */
    protected $coreHelper;

    /**
     * @var \MercadoPago\Core\Model\Core
     */
    protected $coreModel;

    /**
     *log file name
     */
    const LOG_NAME = 'standard_notification';


    /**
     * Standard constructor.
     *
     * @param \Magento\Framework\App\Action\Context           $context
     * @param \MercadoPago\Core\Model\Standard\PaymentFactory $paymentFactory
     * @param \MercadoPago\Core\Helper\Data                   $coreHelper
     * @param \MercadoPago\Core\Model\Core                    $coreModel
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \MercadoPago\Core\Model\Standard\PaymentFactory $paymentFactory,
        \MercadoPago\Core\Helper\Data $coreHelper,
        \MercadoPago\Core\Model\Core $coreModel
    )
    {
        $this->_paymentFactory = $paymentFactory;
        $this->coreHelper = $coreHelper;
        $this->coreModel = $coreModel;
        parent::__construct($context);
    }

    /**
     * Controller Action
     */
    public function execute()
    {
        $request = $this->getRequest();
        //notification received
        $this->coreHelper->log("Standard Received notification", self::LOG_NAME, $request->getParams());

        $id = $request->getParam('id');
        $topic = $request->getParam('topic');

        if (!empty($id) && $topic == 'merchant_order') {
            $response = $this->coreModel->getMerchantOrder($id);
            $this->coreHelper->log("Return merchant_order", self::LOG_NAME, $response);
            if ($response['status'] == 200 || $response['status'] == 201) {
                $merchantOrder = $response['response'];

                if (count($merchantOrder['payments']) > 0) {
                    $data = $this->_getDataPayments($merchantOrder);
                    $statusFinal = $this->_getStatusFinal($data['status']);
                    $shipmentData = (isset($merchantOrder['shipments'][0])) ? $merchantOrder['shipments'][0] : [];
                    $this->coreHelper->log("Update Order", self::LOG_NAME);
                    $this->coreHelper->setStatusUpdated($data);
                    $this->coreModel->updateOrder($data);

                    if (!empty($shipmentData)) {
                        $this->_eventManager->dispatch(
                            'mercadopago_standard_notification_before_set_status',
                            ['shipmentData' => $shipmentData, 'orderId' => $merchantOrder['external_reference']]
                        );
                    }

                    if ($statusFinal != false) {
                        $data['status_final'] = $statusFinal;
                        $this->coreHelper->log("Received Payment data", self::LOG_NAME, $data);
                        $setStatusResponse = $this->coreModel->setStatusOrder($data);
                        $this->getResponse()->setBody($setStatusResponse['text']);
                        $this->getResponse()->setHttpResponseCode($setStatusResponse['code']);
                    } else {
                        $this->getResponse()->setBody("Status not final");
                        $this->getResponse()->setHttpResponseCode(\MercadoPago\Core\Helper\Response::HTTP_OK);
                    }
                    if (!empty($shipmentData)) {
                        $this->_eventManager->dispatch('mercadopago_standard_notification_received',
                            ['payment'        => $data,
                             'merchant_order' => $merchantOrder]
                        );
                    }
                    return;
                }
            }
        } else {
            $this->coreHelper->log("Merchant Order not found", self::LOG_NAME, $request->getParams());
            $this->getResponse()->setBody("Merchant Order not found");
            $this->getResponse()->setHttpResponseCode(\MercadoPago\Core\Helper\Response::HTTP_NOT_FOUND);
        }

        $this->coreHelper->log("Http code", self::LOG_NAME, $this->getResponse()->getHttpResponseCode());
    }


    /**
     * Check if status is final in case of multiple card payment
     *
     * @param $dataStatus
     *
     * @return bool|mixed|string
     */
    protected function _getStatusFinal($dataStatus)
    {
        $status_final = "";
        $statuses = explode('|', $dataStatus);
        foreach ($statuses as $status) {
            $status = str_replace(' ', '', $status);
            if ($status_final == "") {
                $status_final = $status;
            } else {
                if ($status_final != $status) {
                    $status_final = false;
                }
            }
        }

        return $status_final;
    }

    /**
     * Collect data from notification content
     *
     * @param $merchantOrder
     *
     * @return array
     */
    protected function _getDataPayments($merchantOrder)
    {
        $data = array();
        foreach ($merchantOrder['payments'] as $payment) {
            $response = $this->coreModel->getPayment($payment['id']);
            $payment = $response['response']['collection'];
            $data = $this->_formatArrayPayment($data, $payment);
        }

        return $data;
    }


    /**
     * Collect data from notification content to update order info
     *
     * @param $data
     * @param $payment
     *
     * @return mixed
     */
    protected function _formatArrayPayment($data, $payment)
    {
        $this->coreHelper->log("Format Array", self::LOG_NAME);

        $fields = array(
            "status",
            "status_detail",
            "id",
            "payment_method_id",
            "transaction_amount",
            "total_paid_amount",
            "coupon_amount",
            "installments",
            "shipping_cost",
        );

        foreach ($fields as $field) {
            if (isset($payment[$field])) {
                if (isset($data[$field])) {
                    $data[$field] .= " | " . $payment[$field];
                } else {
                    $data[$field] = $payment[$field];
                }
            }
        }

        if (isset($payment["last_four_digits"])) {
            if (isset($data["trunc_card"])) {
                $data["trunc_card"] .= " | " . "xxxx xxxx xxxx " . $payment["last_four_digits"];
            } else {
                $data["trunc_card"] = "xxxx xxxx xxxx " . $payment["last_four_digits"];
            }
        }

        if (isset($payment['cardholder']['name'])) {
            if (isset($data["cardholder_name"])) {
                $data["cardholder_name"] .= " | " . $payment["cardholder"]["name"];
            } else {
                $data["cardholder_name"] = $payment["cardholder"]["name"];
            }
        }

        if (isset($payment['statement_descriptor'])) {
            $data['statement_descriptor'] = $payment['statement_descriptor'];
        }

        $data['external_reference'] = $payment['external_reference'];
        $data['payer_first_name'] = $payment['payer']['first_name'];
        $data['payer_last_name'] = $payment['payer']['last_name'];
        $data['payer_email'] = $payment['payer']['email'];

        return $data;
    }

}