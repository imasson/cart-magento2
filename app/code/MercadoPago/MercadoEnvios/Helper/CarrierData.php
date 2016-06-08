<?php
namespace MercadoPago\MercadoEnvios\Helper;

class CarrierData
    extends \Magento\Framework\App\Helper\AbstractHelper
{

    const XML_PATH_ATTRIBUTES_MAPPING = 'carriers/mercadoenvios/attributesmapping';
    const ME_LENGTH_UNIT = 'cm';
    const ME_WEIGHT_UNIT = 'gr';

    protected $_products = [];
    protected $_mapping;
    protected $_productFactory;
    protected $_mpLogger;

    protected $_maxWeight = ['mla' => '25000', 'mlb' => '30000', 'mlm' => ''];
    protected $_individualDimensions = ['height' => ['mla' => ['min' => '0', 'max' => '70'], 'mlb' => ['min' => '2', 'max' => '105'], 'mlm' => ['min' => '0', 'max' => '80']],
                                        'width'  => ['mla' => ['min' => '0', 'max' => '70'], 'mlb' => ['min' => '11', 'max' => '105'], 'mlm' => ['min' => '0', 'max' => '80']],
                                        'length' => ['mla' => ['min' => '0', 'max' => '70'], 'mlb' => ['min' => '16', 'max' => '105'], 'mlm' => ['min' => '0', 'max' => '120']],
                                        'weight' => ['mla' => ['min' => '0', 'max' => '25000'], 'mlb' => ['min' => '0', 'max' => '30000'], 'mlm' => ['min' => '0', 'max' => '70000']],
    ];
    protected $_globalMaxDimensions = ['mla' => '210',
                                       'mlb' => '200',
                                       'mlm' => '347',
    ];

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \MercadoPago\Core\Logger\Logger $mpLogger
    )
    {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_productFactory = $productFactory;
        $this->_mpLogger = $mpLogger;

    }

    /**
     * @param $item Mage_Sales_Model_Quote_Item
     */
    public function _getShippingDimension($item, $type)
    {
        $attributeMapped = $this->_getConfigAttributeMapped($type);
        if (!empty($attributeMapped)) {
            if (!isset($this->_products[$item->getProductId()])) {
                $this->_products[$item->getProductId()] = $this->_productFactory->create()->load($item->getProductId());
            }
            $product = $this->_products[$item->getProductId()];
            $result = $product->getData($attributeMapped);
            $result = $this->getAttributesMappingUnitConversion($type, $result);
            $this->validateProductDimension($result, $type, $item);

            return $result;
        }

        return 0;
    }

    protected function validateProductDimension($dimension, $type, $item)
    {
        $country = $this->scopeConfig->getValue('payment/mercadopago/country');
        if (empty((int)$dimension) || $dimension > $this->_individualDimensions[$type][$country]['max'] || $dimension < $this->_individualDimensions[$type][$country]['min']) {
            $this->log('Invalid dimension product: PRODUCT ', $item->getData());
            throw new \Magento\Framework\Exception\LocalizedException('Invalid dimensions product');
        }
    }

    public function validateCartDimension($height, $width, $length, $weight)
    {
        $country = $this->scopeConfig->getValue('payment/mercadopago/country');
        if (!isset($this->_globalMaxDimensions[$country])) {
            return;
        }
        if (($height + $width + $length) > $this->_globalMaxDimensions[$country]) {
            $this->log('Invalid dimensions in cart:', ['width' => $width, 'height' => $height, 'length' => $length, 'weight' => $weight,]);
            //Mage::register('mercadoenvios_msg', __('Package exceed maximum dimensions'));
            throw new \Magento\Framework\Exception\LocalizedException('Invalid dimensions cart');
        }
    }

    protected function _getConfigAttributeMapped($type)
    {
        return (isset($this->getAttributeMapping()[$type]['code'])) ? $this->getAttributeMapping()[$type]['code'] : null;
    }

    public function getAttributeMapping()
    {
        if (empty($this->_mapping)) {
            $mapping = $this->scopeConfig->getValue(self::XML_PATH_ATTRIBUTES_MAPPING);
            $mapping = unserialize($mapping);
            $mappingResult = [];
            foreach ($mapping as $key => $map) {
                $mappingResult[$key] = ['code' => $map['attribute_code'], 'unit' => $map['unit']];
            }
            $this->_mapping = $mappingResult;
        }

        return $this->_mapping;
    }

    /**
     * @param $attributeType string
     * @param $value         string
     *
     * @return string
     */
    public function getAttributesMappingUnitConversion($attributeType, $value)
    {
        $this->_getConfigAttributeMapped($attributeType);

        if ($attributeType == 'weight') {
            //check if needs conversion
            if ($this->_mapping[$attributeType]['unit'] != self::ME_WEIGHT_UNIT) {
                $unit = new \Zend_Measure_Weight((float)$value);
                $unit->convertTo(\Zend_Measure_Weight::GRAM);

                return $unit->getValue();
            }

        } elseif ($this->_mapping[$attributeType]['unit'] != self::ME_LENGTH_UNIT) {
            $unit = new \Zend_Measure_Length((float)$value);
            $unit->convertTo(\Zend_Measure_Length::CENTIMETER);

            return $unit->getValue();
        }

        return $value;
    }

    public function log($message, $array = null, $level = \Monolog\Logger::ALERT, $file = "mercadoenvios.log")
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
}