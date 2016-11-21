<?php
namespace MercadoPago\Core\Helper;

use Magento\Framework\View\LayoutFactory;


/**
 * Class Data
 *
 * @package MercadoPago\Core\Helper
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Data
    extends \Magento\Payment\Helper\Data
{

    /**
     *path to access token config
     */
    const XML_PATH_ACCESS_TOKEN = 'payment/mercadopago_custom/access_token';
    /**
     *path to public config
     */
    const XML_PATH_PUBLIC_KEY = 'payment/mercadopago_custom/public_key';
    /**
     *path to client id config
     */
    const XML_PATH_CLIENT_ID = 'payment/mercadopago_standard/client_id';
    /**
     *path to client secret config
     */
    const XML_PATH_CLIENT_SECRET = 'payment/mercadopago_standard/client_secret';

    const PLATFORM_V1_WHITELABEL = 'v1-whitelabel';
    const PLATFORM_DESKTOP = 'Desktop';
    const TYPE = 'magento';

    /**
     * @var \MercadoPago\Core\Helper\Message\MessageInterface
     */
    protected $_messageInterface;

    /**
     * MercadoPago Logging instance
     *
     * @var \MercadoPago\Core\Logger\Logger
     */
    protected $_mpLogger;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Status\Collection
     */
    protected $_statusFactory;

    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $_moduleList;

    /**
     * @var bool flag indicates when status was updated by notifications.
     */
    protected $_statusUpdatedFlag = false;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;
    protected $_config;

    /**
     * Data constructor.
     *
     * @param Message\MessageInterface                             $messageInterface
     * @param \Magento\Framework\App\Helper\Context                $context
     * @param LayoutFactory                                        $layoutFactory
     * @param \Magento\Payment\Model\Method\Factory                $paymentMethodFactory
     * @param \Magento\Store\Model\App\Emulation                   $appEmulation
     * @param \Magento\Payment\Model\Config                        $paymentConfig
     * @param \Magento\Framework\App\Config\Initial                $initialConfig
     * @param \Magento\Framework\Module\ModuleListInterface        $moduleList
     * @param \MercadoPago\Core\Logger\Logger                      $logger
     * @param \Magento\Sales\Model\ResourceModel\Status\Collection $statusFactory
     */
    public function __construct(
        \MercadoPago\Core\Helper\Message\MessageInterface $messageInterface,
        \Magento\Framework\App\Helper\Context $context,
        LayoutFactory $layoutFactory,
        \Magento\Payment\Model\Method\Factory $paymentMethodFactory,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Payment\Model\Config $paymentConfig,
        \Magento\Framework\App\Config\Initial $initialConfig,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \MercadoPago\Core\Logger\Logger $logger,
        \Magento\Sales\Model\ResourceModel\Status\Collection $statusFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory
    )
    {
        parent::__construct($context, $layoutFactory, $paymentMethodFactory, $appEmulation, $paymentConfig, $initialConfig);
        $this->_messageInterface = $messageInterface;
        $this->_mpLogger = $logger;
        $this->_moduleList = $moduleList;
        $this->_statusFactory = $statusFactory;
        $this->_orderFactory = $orderFactory;
    }

    /**
     * @return bool return updated flag
     */
    public function isStatusUpdated()
    {
        return $this->_statusUpdatedFlag;
    }

    /**
     * Set flag status updated
     *
     * @param $notificationData
     */
    public function setStatusUpdated($notificationData)
    {
        $order = $this->_orderFactory->create()->loadByIncrementId($notificationData["external_reference"]);
        $status = $notificationData['status'];
        $currentStatus = $order->getPayment()->getAdditionalInformation('status');
        if (($status == $currentStatus)) {
            $this->_statusUpdatedFlag = true;
        }
    }

    /**
     * Log custom message using MercadoPago logger instance
     *
     * @param        $message
     * @param string $name
     * @param null   $array
     */
    public function log($message, $name = "mercadopago", $array = null)
    {
        //load admin configuration value, default is true
        $actionLog = $this->scopeConfig->getValue('payment/mercadopago/logs', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if (!$actionLog) {
            return;
        }
        //if extra data is provided, it's encoded for better visualization
        if (!is_null($array)) {
            $message .= " - " . json_encode($array);
        }

        //set log
        $this->_mpLogger->setName($name);
        $this->_mpLogger->debug($message);
    }

    /**
     * Return MercadoPago Api instance given AccessToken or ClientId and Secret
     *
     * @return \MercadoPago_Core_Lib_Api
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getApiInstance()
    {
        $params = func_num_args();
        if ($params > 2 || $params < 1) {
            throw new \Magento\Framework\Exception\LocalizedException("Invalid arguments. Use CLIENT_ID and CLIENT SECRET, or ACCESS_TOKEN");
        }
        if ($params == 1) {
            $api = new \MercadoPago_Core_Lib_Api(func_get_arg(0));
            //$api->set_platform(self::PLATFORM_OPENPLATFORM);
        } else {
            $api = new \MercadoPago_Core_Lib_Api(func_get_arg(0), func_get_arg(1));
           // $api->set_platform(self::PLATFORM_STD);
        }
        if ($this->scopeConfig->getValue('payment/mercadopago_standard/sandbox_mode')) {
            $api->sandbox_mode(true);
        }

        $api->set_type(self::TYPE);

        //$api->set_so((string)$this->_moduleContext->getVersion()); //TODO tracking

        return $api;

    }

    public function initApiInstance()
    {
        if (!$this->_config) {
            \MercadoPago\MercadoPagoSdk::initialize();
            $this->_config = \MercadoPago\MercadoPagoSdk::config();
        }

        $params = func_num_args();
        if (empty($params)) {
            return;
        }

        $type = self::TYPE . ' ' . (string)$this->_moduleList->getOne('MercadoPago_Core')['setup_version'];
        if ($params == 1) {
            $this->_config->set('ACCESS_TOKEN', func_get_arg(0));
            \MercadoPago\MercadoPagoSdk::addCustomHeader('x-tracking-id', 'platform:' . self::PLATFORM_V1_WHITELABEL . ',type:' . $type . ',so;');
        } else {
            $this->_config->set('CLIENT_ID', func_get_arg(0));
            $this->_config->set('CLIENT_SECRET', func_get_arg(1));
            \MercadoPago\MercadoPagoSdk::addCustomHeader('x-tracking-id', 'platform:' . self::PLATFORM_DESKTOP . ',type:' . $type . ',so;');
        }
    }

    public function isValidAccessToken($accessToken)
    {
        $this->initApiInstance();
        $response = \MercadoPago\MercadoPagoSdk::restClient()->get("/v1/payment_methods", ['url_query' => ['access_token' => $accessToken]]);
        if ($response['code'] == 401 || $response['code'] == 400) {
            return false;
        }
        $this->_config->set('ACCESS_TOKEN', $accessToken);

        return true;
    }

    public function isValidClientCredentials($clientId, $clientSecret)
    {
        $this->initApiInstance($clientId, $clientSecret);
        $accessToken = $this->_config->get('ACCESS_TOKEN');

        return !empty($accessToken);
    }

    /**
     * Return the access token proved by api
     *
     * @return mixed
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAccessToken()
    {
        $clientId = $this->scopeConfig->getValue(self::XML_PATH_CLIENT_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $clientSecret = $this->scopeConfig->getValue(self::XML_PATH_CLIENT_SECRET, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($this->isValidClientCredentials($clientId, $clientSecret)) {
            return $this->_config->get('ACCESS_TOKEN');
        } else {
            return false;
        }
    }

    /**
     * Return order status mapping based on current configuration
     *
     * @param $status
     *
     * @return mixed
     */
    public function getStatusOrder($status)
    {
        switch ($status) {
            case 'approved': {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_approved', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            }
            case 'refunded': {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_refunded', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            }
            case 'in_mediation': {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_in_mediation', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            }
            case 'cancelled': {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_cancelled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            }
            case 'rejected': {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_rejected', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            }
            case 'chargeback': {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_chargeback', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            }
            default: {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_in_process', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            }
        }

        return $status;
    }

    /**
     * Get the assigned state of an order status
     *
     * @param string $status
     */
    public function _getAssignedState($status)
    {
        $collection = $this->_statusFactory
            ->joinStates()
            ->addFieldToFilter('main_table.status', $status);

        $collectionItems = $collection->getItems();

        return array_pop($collectionItems)->getState();
    }

    /**
     * Return raw message for payment detail
     *
     * @param $status
     * @param $payment
     *
     * @return \Magento\Framework\Phrase|string
     */
    public function getMessage($status, $payment)
    {
        $rawMessage = __($this->_messageInterface->getMessage($status));
        $rawMessage .= __('<br/> Payment id: %1', $payment['id']);
        $rawMessage .= __('<br/> Status: %1', $payment['status']);
        $rawMessage .= __('<br/> Status Detail: %1', $payment['status_detail']);

        return $rawMessage;
    }

    /**
     * Calculate and set order MercadoPago specific subtotals based on data values
     *
     * @param $data
     * @param $order
     */
    public function setOrderSubtotals($data, $order)
    {
        if (isset($data['total_paid_amount'])) {
            $balance = $this->_getMultiCardValue($data, 'total_paid_amount');
        } else {
            $balance = $data['transaction_details']['total_paid_amount'];
        }

        if (isset($data['shipping_cost'])) {
            $shippingCost = $this->_getMultiCardValue($data, 'shipping_cost');
            $order->setBaseShippingAmount($shippingCost);
            $order->setShippingAmount($shippingCost);
        } else {
            $shippingCost = 0;
        }

        $order->setGrandTotal($balance);
        $order->setBaseGrandTotal($balance);
        if ($shippingCost > 0) {
            $order->setBaseShippingAmount($shippingCost);
            $order->setShippingAmount($shippingCost);
        }

        $couponAmount = $this->_getMultiCardValue($data, 'coupon_amount');
        $transactionAmount = $this->_getMultiCardValue($data, 'transaction_amount');
        if ($couponAmount) {
            $order->setDiscountCouponAmount($couponAmount * -1);
            $order->setBaseDiscountCouponAmount($couponAmount * -1);
            $balance = $balance - ($transactionAmount - $couponAmount + $shippingCost);
        } else {
            $balance = $balance - $transactionAmount - $shippingCost;
        }

        if (\Zend_Locale_Math::round($balance, 4) > 0) {
            $order->setFinanceCostAmount($balance);
            $order->setBaseFinanceCostAmount($balance);
        }

        $order->save();
    }

    /**
     * Modify payment array adding specific fields
     *
     * @param $payment
     *
     * @return mixed
     */
    public function setPayerInfo(&$payment)
    {
        $payment["trunc_card"] = "xxxx xxxx xxxx " . $payment['card']["last_four_digits"];
        $payment["cardholder_name"] = $payment['card']["cardholder"]["name"];
        $payment['payer_first_name'] = $payment['payer']['first_name'];
        $payment['payer_last_name'] = $payment['payer']['last_name'];
        $payment['payer_email'] = $payment['payer']['email'];

        return $payment;
    }

    /**
     * Return sum of fields separated with |
     *
     * @param $fullValue
     *
     * @return int
     */
    protected function _getMultiCardValue($data, $field)
    {
        $finalValue = 0;
        if (!isset($data[$field])) {
            return $finalValue;
        }
        $amountValues = explode('|', $data[$field]);
        $statusValues = explode('|', $data['status']);
        foreach ($amountValues as $key => $value) {
            $value = (float)str_replace(' ', '', $value);
            if (str_replace(' ', '', $statusValues[$key]) == 'approved') {
                $finalValue = $finalValue + $value;
            }
        }

        return $finalValue;
    }

    /**
     * Return success url
     *
     * @return string
     */
    public function getSuccessUrl()
    {
        if ($this->scopeConfig->getValue('payment/mercadopago/use_successpage_mp')) {
            $url = 'mercadopago/success/page';
        } else {
            $url = 'checkout/onepage/success';
        }

        return $url;
    }

    public function isRefundAvailable () {
        return $this->scopeConfig->getValue('payment/mercadopago/refund_available');
    }

    public function getMaximumDaysRefund () {
        return (int) $this->scopeConfig->getValue('payment/mercadopago/maximum_days_refund');
    }

    public function getMaximumPartialRefunds () {
        return (int) $this->scopeConfig->getValue('payment/mercadopago/maximum_partial_refunds');
    }
    
    public function getClientId () {
        return $this->scopeConfig->getValue('payment/mercadopago_standard/client_id');
    }
    
    public function getClientSecret() {
        return $this->scopeConfig->getValue('payment/mercadopago_standard/client_secret');
    }

    public function getPublicKey() {
        return $this->scopeConfig->getValue('payment/mercadopago_custom_checkout/public_key');
    }

    public function getOrderStatusRefunded() {
        return $this->scopeConfig->getValue('payment/mercadopago/order_status_refunded');
    }
}
