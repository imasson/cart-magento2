<?php
namespace MercadoPago\MercadoEnvios\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 * Class ConfigObserver
 *
 * @package MercadoPago\Core\Observer
 */
class ShipmentParams
    implements ObserverInterface
{
    /**
     *
     */
    const LOG_NAME = 'mercadopago';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \MercadoPago\Core\Helper\
     */
    protected $coreHelper;
    protected $coreModel;
    protected $shipmentHelper;

    /**
     * Config configResource
     *
     * @var $configResource
     */
    protected $configResource;
    protected $_timezone;

    /**
     * ConfigObserver constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \MercadoPago\Core\Helper\Data                      $coreHelper
     * @param \Magento\Config\Model\ResourceModel\Config         $configResource
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \MercadoPago\Core\Helper\Data $coreHelper,
        \Magento\Config\Model\ResourceModel\Config $configResource,
        \MercadoPago\Core\Model\Core $coreModel,
        \MercadoPago\MercadoEnvios\Helper\Data $shipmentHelper,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timeZone
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->configResource = $configResource;
        $this->coreHelper = $coreHelper;
        $this->coreModel = $coreModel;
        $this->shipmentHelper = $shipmentHelper;
        $this->_timezone = $timeZone;
    }

    /**
     * Updates configuration values based every time MercadoPago configuration section is saved
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getOrder();
        $method = $order->getShippingMethod();
        $shippingCost = $order->getBaseShippingAmount();
        $paramsME = [];
        if ($this->shipmentHelper->isMercadoEnviosMethod($method)) {
            $shippingAddress = $order->getShippingAddress();
            $zipCode = $shippingAddress->getPostcode();
            $defaultShippingId = substr($method, strpos($method, '_') + 1);

            $paramsME = [
                'mode'                    => 'me2',
                'zip_code'                => $zipCode,
                'default_shipping_method' => intval($defaultShippingId),
                'dimensions'              => $this->shipmentHelper->getDimensions($this->shipmentHelper->getAllItems($order->getAllItems()))
            ];
            if ($shippingCost == 0) {
                $paramsME['free_methods'] = [['id' => intval($defaultShippingId)]];
            }
        }
        if (!empty($shippingCost)) {
            $paramsME['cost'] = (float)$order->getBaseShippingAmount();
        }
        $observer->getParams()->setParams($paramsME);
        //$this->shipmentHelper->log('REQUEST SHIPMENT ME: ', $paramsME, \Zend_Log::INFO);

        return $observer;
    }

}
