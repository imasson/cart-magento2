<?php

namespace MercadoPago\Core\Helper;

use Magento\Framework\View\LayoutFactory;
use MercadoPago\SDK;


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
     *path to mercadopago custom ticket active
     */
    const XML_PATH_MERCADOPAGO_TICKET_ACTIVE = 'payment/mercadopago_customticket/active';


    /**
     *path to access token config
     */
    const XML_PATH_ACCESS_TOKEN = 'payment/mercadopago_custom/access_token';
    /**
     *path to public config
     */
    const XML_PATH_PUBLIC_KEY = 'payment/mercadopago_custom/public_key';
    /**
     *path to mercadopago custom active config
     */
    const XML_PATH_MERCADOPAGO_CUSTOM_ACTIVE = 'payment/mercadopago_custom/active';


    /**
     *path to client id config
     */
    const XML_PATH_CLIENT_ID = 'payment/mercadopago_standard/client_id';
    /**
     *path to client secret config
     */
    const XML_PATH_CLIENT_SECRET = 'payment/mercadopago_standard/client_secret';


    /**
     *path to mercadopago country config
     */
    const XML_PATH_COUNTRY = 'payment/mercadopago/country';
    /**
     *path to payment calculator available
     */
    const XML_PATH_CALCULATOR_AVAILABLE = 'payment/mercadopago/calculalator_available';
    /**
     *path to the list of pages on which to display the calculator
     */
    const XML_PATH_CALCULATOR_PAGES = 'payment/mercadopago/show_in_pages';
    /**
     *path to mercadopago refund available config
     */
    const XML_PATH_REFUND_AVAILABLE = 'payment/mercadopago/refund_available';
    /**
     *path to maximum days refund config
     */
    const XML_PATH_MAXIMUM_DAYS_REFUND = 'payment/mercadopago/maximum_days_refund';
    /**
     *path to maximum partial refunds config
     */
    const XML_PATH_MAXIMUM_PARTIAL_REFUNDS = 'payment/mercadopago/maximum_partial_refunds';
    /**
     *path to order status refunded config
     */
    const XML_PATH_ORDER_STATUS_REFUNDED = 'payment/mercadopago/order_status_refunded';
    /**
     *path to use successpage mp config
     */
    const XML_PATH_USE_SUCCESSPAGE_MP = 'payment/mercadopago/use_successpage_mp';
    /**
     *path to sponsor id config
     */
    const XML_PATH_SPONSOR_ID = 'payment/mercadopago/sponsor_id';

    const XML_PATH_CONSIDER_DISCOUNT = 'payment/mercadopago/consider_discount';


    /**
     *Platform constants
     */
    const TYPE = 'magento';
    const PLATFORM_V1_WHITELABEL = 'v1-whitelabel';
    const PLATFORM_DESKTOP = 'Desktop';

    /**
     * payment calculator
     */
    const STATUS_ACTIVE = 'active';
    const PAYMENT_TYPE_CREDIT_CARD = 'credit_card';

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
     * @var \Magento\Framework\Setup\ModuleContextInterface
     */
    protected $_moduleContext;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $_moduleList;

    /**
     * @var \Magento\Backend\Block\Store\Switcher
     */
    protected $_switcher;
    protected $_composerInformation;
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
     * @param \Magento\Framework\Setup\ModuleContextInterface      $moduleContext
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
        \Magento\Framework\Setup\ModuleContextInterface $moduleContext,
        \MercadoPago\Core\Logger\Logger $logger,
        \Magento\Sales\Model\ResourceModel\Status\Collection $statusFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Backend\Block\Store\Switcher $switcher,
        \Magento\Framework\Composer\ComposerInformation $composerInformation,
        \Magento\Framework\Module\ModuleListInterface $moduleList
    )
    {
        parent::__construct($context, $layoutFactory, $paymentMethodFactory, $appEmulation, $paymentConfig, $initialConfig);
        $this->_messageInterface = $messageInterface;
        $this->_mpLogger = $logger;
        $this->_moduleContext = $moduleContext;
        $this->_statusFactory = $statusFactory;
        $this->_orderFactory = $orderFactory;
        $this->_switcher = $switcher;
        $this->_composerInformation = $composerInformation;
        $this->_moduleList = $moduleList;
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
     * @return \MercadoPago\Core\Lib\Api
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getApiInstance()
    {
        if (!$this->_config) {
            \MercadoPago\Sdk::initialize();
            $this->_config = \MercadoPago\Sdk::config();
        }
        $params = func_num_args();
        if (empty($params)) {
            return;
        }

        $type = self::TYPE . ' ' . (string)$this->_moduleList->getOne('MercadoPago_Core')['setup_version'];
        if ($params == 1) {
            $this->_config->set('ACCESS_TOKEN', func_get_arg(0));
            \MercadoPago\Sdk::addCustomTrackingParam('x-tracking-id', 'platform:' . self::PLATFORM_V1_WHITELABEL . ',type:' . $type . ',so;');
        } else {
            $this->_config->set('CLIENT_ID', func_get_arg(0));
            $this->_config->set('CLIENT_SECRET', func_get_arg(1));
            \MercadoPago\Sdk::addCustomTrackingParam('x-tracking-id', 'platform:' . self::PLATFORM_DESKTOP . ',type:' . $type . ',so;');
        }

    }

    /**
     * AccessToken valid?
     *
     * @param $accessToken
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function isValidAccessToken($accessToken)
    {
        $this->getApiInstance($accessToken);
        $response = \MercadoPago\Sdk::get("/v1/payment_methods");
        if ($response['code'] == 401 || $response['code'] == 400) {
            return false;
        }
        $this->_config->set('ACCESS_TOKEN', $accessToken);

        return true;
    }

    /**
     * ClientId and Secret valid?
     *
     * @param $clientId
     * @param $clientSecret
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function isValidClientCredentials($clientId, $clientSecret)
    {
        $this->getApiInstance($clientId, $clientSecret);
        $accessToken = $this->_config->get('ACCESS_TOKEN');

        return !empty($accessToken);
    }

    /**
     * Return the access token proved by api
     *
     * @param null $scopeCode
     *
     * @return bool|mixed
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAccessToken($scopeCode = null)
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
     * Calculate and set order MercadoPago specific subtotals based on data values
     *
     * @param $data
     * @param $order
     */
    public function setOrderSubtotals($data, $order)
    {
        $couponAmount = $this->_getMultiCardValue($data, 'coupon_amount');
        $transactionAmount = $this->_getMultiCardValue($data, 'transaction_amount');
        
        if (isset($data['total_paid_amount'])) {
            $paidAmount = $this->_getMultiCardValue($data, 'total_paid_amount');
        } else {
            $paidAmount = $data['transaction_details']['total_paid_amount'];
        }

        $shippingCost = $this->_getMultiCardValue($data, 'shipping_cost');
        $originalAmount = $transactionAmount + $shippingCost;

        if ($couponAmount
            && $this->scopeConfig->isSetFlag(self::XML_PATH_CONSIDER_DISCOUNT,\Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
            $order->setDiscountCouponAmount($couponAmount * -1);
            $order->setBaseDiscountCouponAmount($couponAmount * -1);
        }

        //if a discount was applied  should be considered to get financing cost
        $paidAmount += $couponAmount;
        $financingCost = $paidAmount - $originalAmount;

        if ($shippingCost > 0) {
            $order->setBaseShippingAmount($shippingCost);
            $order->setShippingAmount($shippingCost);
        }


        if (\Zend_Locale_Math::round($financingCost, 4) > 0) {
            $order->setFinanceCostAmount($financingCost);
            $order->setBaseFinanceCostAmount($financingCost);
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


    // @todo

    /**
     * Return success url
     *
     * @return string
     */
    public function getSuccessUrl()
    {
        if ($this->scopeConfig->getValue(self::XML_PATH_USE_SUCCESSPAGE_MP, \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
            $url = 'mercadopago/checkout/page';
        } else {
            $url = 'checkout/onepage/success';
        }

        return $url;
    }

    public function isRefundAvailable()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_REFUND_AVAILABLE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getMaximumDaysRefund()
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MAXIMUM_DAYS_REFUND, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getMaximumPartialRefunds()
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MAXIMUM_PARTIAL_REFUNDS, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getClientId()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CLIENT_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getClientSecret()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CLIENT_SECRET, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getPublicKey()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_PUBLIC_KEY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return boolean
     */
    public function isAvailableCalculator()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CALCULATOR_AVAILABLE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return mixed
     */
    public function getPagesToShow()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CALCULATOR_PAGES, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * return the list of payment methods or null
     *
     * @param mixed|null $accessToken
     *
     * @return mixed
     */
    public function getMercadoPagoPaymentMethods($accessToken)
    {
        $this->getApiInstance($accessToken);
        try {
            $response = \MercadoPago\sdk::get("/v1/payment_methods");
            if ($response['code'] == 401 || $response['code'] == 400) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
        
        return $response['body'];
    }

    public function getModuleVersion()
    {
        $magentoPackages = $this->_composerInformation->getInstalledMagentoPackages();

        return $magentoPackages['mercadopago/magento2-plugin']['version'];
    }

    /**
     * Summary: Get client id from access token.
     * Description: Get client id from access token.
     *
     * @param String $at
     *
     * @return String client id.
     */
    public static function getClientIdFromAccessToken($at)
    {
        $t = explode('-', $at);
        if (count($t) > 0) {
            return $t[1];
        }

        return '';
    }

    public function getAnalyticsData($order)
    {
        $additionalInfo = $order->getPayment()->getData('additional_information');
        $analyticsData = [];
        if ($order->getPayment()->getData('method')) {
            $methodCode = $order->getPayment()->getData('method');
            $analyticsData = [
                'payment_id'    => isset($additionalInfo['payment_id_detail']) ? $order->getPayment()->getData('additional_information')['payment_id_detail'] : '',
                'payment_type'  => 'credit_card',
                'checkout_type' => 'custom'
            ];
            if ($methodCode == \MercadoPago\Core\Model\Custom\Payment::CODE) {
                $analyticsData['public_key'] = $this->scopeConfig->getValue('payment/mercadopago_custom/public_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            } elseif ($methodCode == \MercadoPago\Core\Model\Standard\Payment::CODE) {
                $analyticsData['analytics_key'] = $this->scopeConfig->getValue(\MercadoPago\Core\Helper\Data::XML_PATH_CLIENT_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                $analyticsData['checkout_type'] = 'basic';
                $analyticsData['payment_type'] = isset($additionalInfo['payment_type_id']) ? $order->getPayment()->getData('additional_information')['payment_type_id'] : 'credit_card';
            } else {
                $analyticsData['analytics_key'] = $this->getClientIdFromAccessToken($this->scopeConfig->getValue(\MercadoPago\Core\Helper\Data::XML_PATH_ACCESS_TOKEN, \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
                $analyticsData['payment_type'] = 'ticket';
            }
        }
        return $analyticsData;
    }
}
