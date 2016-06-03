<?php
namespace MercadoPago\MercadoEnvios\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 * Class ConfigObserver
 *
 * @package MercadoPago\Core\Observer
 */
class ShipmentData
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
        $observerData = $observer->getData();

        $orderId = $observerData['orderId'];
        $shipmentData = $observerData['shipmentData'];
        $order = $this->coreModel->_getOrder($orderId);

        $method = $order->getShippingMethod();

        if ($this->shipmentHelper->isMercadoEnviosMethod($method)) {
            $methodId = $shipmentData['shipping_option']['shipping_method_id'];
            $name = $shipmentData['shipping_option']['name'];
            $order->setShippingMethod('mercadoenvios_' . $methodId);

            $estimatedDate = $this->_timezone->formatDate($shipmentData['shipping_option']['estimated_delivery']['date']);
            $estimatedDate = __('(estimated date %s)', $estimatedDate);
            $shippingDescription = 'MercadoEnvíos - ' . $name . ' ' . $estimatedDate;
            $order->setShippingDescription($shippingDescription);
            try {
                $order->save();
                $this->shipmentHelper->log('Order ' . $order->getIncrementId() . ' shipping data set ', $shipmentData, \Zend_Log::INFO);
            } catch (\Exception $e) {
                $this->shipmentHelper->log("error when update shipment data: " . $e);
                $this->shipmentHelper->log($e);
            }
        }
    }

}
