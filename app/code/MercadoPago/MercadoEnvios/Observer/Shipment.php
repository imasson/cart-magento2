<?php
namespace MercadoPago\MercadoEnvios\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 * Class ConfigObserver
 *
 * @package MercadoPago\Core\Observer
 */
class Shipment
    implements ObserverInterface
{

    /**
     *
     */
    const LOG_NAME = 'mercadopago';
    const CODE = 'MercadoEnvios';

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
    protected $_shipmentFactory;
    protected $_shipment;
    protected $_trackFactory;
    protected $_transaction;

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
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timeZone,
        \Magento\Sales\Model\ResourceModel\Order\ShipmentFactory $shipmentFactory,
        \Magento\Sales\Model\Order\ShipmentFactory $shipment,
        \Magento\Sales\Model\Order\Shipment\TrackFactory $trackFactory,
        \Magento\Framework\DB\Transaction $transaction
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->configResource = $configResource;
        $this->coreHelper = $coreHelper;
        $this->coreModel = $coreModel;
        $this->shipmentHelper = $shipmentHelper;
        $this->_timezone = $timeZone;
        $this->_shipmentFactory = $shipmentFactory;
        $this->_shipment = $shipment;
        $this->_trackFactory = $trackFactory;
        $this->_transaction = $transaction;
    }

    /**
     * Updates configuration values based every time MercadoPago configuration section is saved
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $merchant_order = $observer->getMerchantOrder();
        if (!count($merchant_order['shipments']) > 0) {
            return;
        }
        $data = $observer->getPayment();
        $order = $this->coreModel->_getOrder($data["external_reference"]);

        //if order has shipments, status is updated. If it doesn't the shipment is created.
        if ($merchant_order['shipments'][0]['status'] == 'ready_to_ship') {
            if ($order->hasShipments()) {
                $shipment = $this->_shipmentFactory->create()->load($order->getId(), 'order_id');
            } else {
                $shipment = $this->_shipment->create($order);
                //$this->_shipmentFactory->prepareItems($shipment, $order);
                $order->setIsInProcess(true);
            }
            $shipment->setShippingLabel($merchant_order['shipments'][0]['id']);

            $shipmentInfo = $this->shipmentHelper->getShipmentInfo($merchant_order['shipments'][0]['id']);
            $this->coreHelper->log("Shipment Info", 'mercadopago-notification.log', $shipmentInfo);
            $serviceInfo = $this->shipmentHelper->getServiceInfo($merchant_order['shipments'][0]['service_id'], $merchant_order['site_id']);
            $this->coreHelper->log("Service Info by service id", 'mercadopago-notification.log', $serviceInfo);
            if ($shipmentInfo && isset($shipmentInfo->tracking_number)) {
                $tracking['number'] = $shipmentInfo->tracking_number;
                $tracking['description'] = str_replace('#{trackingNumber}', $shipmentInfo->tracking_number, $serviceInfo->tracking_url);
                $tracking['title'] = self::CODE;

                $existingTracking = $this->_trackFactory->create()->load($shipment->getOrderId(),'order_id');

                if ($existingTracking->getId()) {
                    $track = $shipment->getTrackById($existingTracking->getId());
                    $track->setNumber($tracking['number'])
                        ->setDescription($tracking['description'])
                        ->setTitle($tracking['title'])
                        ->save();
                } else {
                    $track = $this->_trackFactory->create()->addData($tracking);
                    $track->setCarrierCode(\MercadoPago\MercadoEnvios\Model\Carrier\MercadoEnvios::CODE);
                    $shipment->addTrack($track);

                    $shipment->save();
                }

                $this->coreHelper->log("Track added", 'mercadopago-notification.log', $track);
            }

            $this->_transaction
                ->addObject($order)
                ->save();
        }
    }

}
