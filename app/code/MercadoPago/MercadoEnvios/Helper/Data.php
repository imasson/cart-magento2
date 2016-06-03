<?php
namespace MercadoPago\MercadoEnvios\Helper;


/**
 * Class Data
 *
 * @package MercadoPago\Core\Helper
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Data
    extends \Magento\Framework\App\Helper\AbstractHelper
{

    const XML_PATH_ATTRIBUTES_MAPPING = 'carriers/mercadoenvios/attributesmapping';
    const ME_LENGTH_UNIT = 'cm';
    const ME_WEIGHT_UNIT = 'gr';
    const ME_SHIPMENT_URL = 'https://api.mercadolibre.com/shipments/';
    const ME_SHIPMENT_LABEL_URL = 'https://api.mercadolibre.com/shipment_labels';
    const ME_SHIPMENT_TRACKING_URL = 'https://api.mercadolibre.com/sites/';

    protected $_mapping;
    protected $_products = [];

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $_productFactory;

    protected $_mpLogger;

    protected $_helperItem;
    protected $_helperCarrier;

    public static $enabled_methods = ['mla', 'mlb', 'mlm'];


    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \MercadoPago\MercadoEnvios\Helper\ItemData $helperItem,
        \MercadoPago\MercadoEnvios\Helper\CarrierData $helperCarrier,
        \MercadoPago\Core\Logger\Logger $mpLogger
    )
    {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_productFactory = $productFactory;
        $this->_helperItem = $helperItem;
        $this->_helperCarrier = $helperCarrier;
        $this->_mpLogger = $mpLogger;

    }


    /**
     * @param $quote Mage_Sales_Model_Quote
     */
    public function getDimensions($items)
    {
        $width = 0;
        $height = 0;
        $length = 0;
        $weight = 0;
        $bulk = 0;
        foreach ($items as $item) {
            $tempWidth = $this->_helperCarrier->_getShippingDimension($item, 'width');
            $tempHeight = $this->_helperCarrier->_getShippingDimension($item, 'height');
            $tempLength = $this->_helperCarrier->_getShippingDimension($item, 'length');
            $tempWeight = $this->_helperCarrier->_getShippingDimension($item, 'weight');
            $qty = $this->_helperItem->itemGetQty($item);
            $bulk += ($tempWidth * $tempHeight * $tempLength) * $qty;
            $width += $tempWidth * $qty;
            $height += $tempHeight * $qty;
            $length += $tempLength * $qty;
            $weight += $tempWeight * $qty;
        }
        $height = ceil($height);
        $width = ceil($width);
        $length = ceil($length);
        $weight = ceil($weight);

        $this->_helperCarrier->validateCartDimension($height, $width, $length, $weight);
        $bulk = ceil(pow($bulk, 1/3));

        return $bulk . 'x' . $bulk . 'x' . $bulk . ',' . $weight;

    }

    /**
     * Retrieves Quote
     *
     * @param integer $quoteId
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function getQuote()
    {
//        if (Mage::app()->getStore()->isAdmin()) {
//            $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
//        } else {
//            $quote = Mage::getModel('checkout/cart')->getQuote();
//        }

        return $this->_checkoutSession->getQuote();

    }

    public function isMercadoEnviosMethod($method)
    {
        $shippingMethod = substr($method, 0, strpos($method, '_'));

        return ($shippingMethod == \MercadoPago\MercadoEnvios\Model\Carrier\MercadoEnvios::CODE);
    }



    public function getFreeMethod($request)
    {
        $freeMethod = $this->scopeConfig->getValue('carriers/mercadoenvios/free_method',\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if (!empty($freeMethod)) {
            if (!$this->scopeConfig->isSetFlag('carriers/mercadoenvios/free_shipping_enable',\Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
                return $freeMethod;
            } else {
                if ($this->scopeConfig->getValue('carriers/mercadoenvios/free_shipping_subtotal',\Magento\Store\Model\ScopeInterface::SCOPE_STORE) <= $request->getPackageValue()) {
                    return $freeMethod;
                }
            }
        }

        return null;
    }

    public function isCountryEnabled()
    {
        return (in_array($this->scopeConfig->getValue('payment/mercadopago/country',\Magento\Store\Model\ScopeInterface::SCOPE_STORE), self::$enabled_methods));
    }

    public function getTrackingUrlByShippingInfo($_shippingInfo)
    {
        $tracking = Mage::getModel('sales/order_shipment_track');
        $tracking = $tracking->getCollection()
            ->addFieldToFilter(
                ['entity_id', 'parent_id', 'order_id'],
                [
                    ['eq' => $_shippingInfo->getTrackId()],
                    ['eq' => $_shippingInfo->getShipId()],
                    ['eq' => $_shippingInfo->getOrderId()],
                ]
            )
            ->setPageSize(1)
            ->setCurPage(1)
            ->load();

        foreach ($_shippingInfo->getTrackingInfo() as $track) {
            $lastTrack = array_pop($track);
            if (isset($lastTrack['title']) && $lastTrack['title'] == MercadoPago_MercadoEnvios_Model_Observer::CODE) {
                $item = array_pop($tracking->getItems());
                if ($item->getId()) {
                    return $item->getDescription();
                }
            }
        }

        return '';
    }

    public function getTrackingPrintUrl($shipmentId)
    {
        if ($shipmentId) {
            if ($shipment = Mage::getModel('sales/order_shipment')->load($shipmentId)) {
                if ($shipment->getShippingLabel()) {
                    $params = [
                        'shipment_ids'  => $shipment->getShippingLabel(),
                        'response_type' => Mage::getStoreConfig('carriers/mercadoenvios/shipping_label'),
                        'access_token'  => Mage::helper('mercadopago')->getAccessToken()
                    ];

                    return self::ME_SHIPMENT_LABEL_URL . '?' . http_build_query($params);
                }
            }
        }

        return '';
    }

    public function getShipmentInfo($shipmentId)
    {
        $client = new \Zend_Http_Client(self::ME_SHIPMENT_URL . $shipmentId);
        $client->setMethod(\Zend_Http_Client::GET);
        $client->setParameterGet('access_token', Mage::helper('mercadopago')->getAccessToken());

        try {
            $response = $client->request();
        } catch (\Exception $e) {
            $this->log($e);
            throw new \Exception($e);
        }

        return json_decode($response->getBody());
    }

    public function getServiceInfo($serviceId, $country)
    {
        $client = new \Zend_Http_Client(self::ME_SHIPMENT_TRACKING_URL . $country . '/shipping_services');
        $client->setMethod(\Zend_Http_Client::GET);
        try {
            $response = $client->request();
        } catch (\Exception $e) {
            $this->log($e);
            throw new \Exception($e);
        }

        $response = json_decode($response->getBody());
        foreach ($response as $result) {
            if ($result->id == $serviceId) {
                return $result;
            }
        }

        return '';
    }

    public function log($message, $array = null, $level = \Zend_Log::ERR, $file = "mercadoenvios.log")
    {
        $actionLog = $this->scopeConfig->getValue('carriers/mercadoenvios/log',\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if (!$actionLog) {
            return;
        }
        //if extra data is provided, it's encoded for better visualization
        if (!is_null($array)) {
            $message .= " - " . json_encode($array);
        }

        //set log
        $this->_mpLogger->setName($file);
        $this->_mpLogger->log($level,$message);
    }

    /**
     * Return items for further shipment rate evaluation. We need to pass children of a bundle instead passing the
     * bundle itself, otherwise we may not get a rate at all (e.g. when total weight of a bundle exceeds max weight
     * despite each item by itself is not)
     *
     * @return array
     */
    public function getAllItems($allItems)
    {
        $items = [];
        foreach ($allItems as $item) {
            /* @var $item Mage_Sales_Model_Quote_Item */
            if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                // Don't process children here - we will process (or already have processed) them below
                continue;
            }

            if ($item->getHasChildren() && $item->isShipSeparately()) {
                foreach ($item->getChildren() as $child) {
                    if (!$child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                        $items[] = $child;
                    }
                }
            } else {
                // Ship together - count compound item as one solid
                $items[] = $item;
            }
        }

        return $items;
    }
}