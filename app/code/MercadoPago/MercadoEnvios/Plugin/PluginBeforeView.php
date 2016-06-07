<?php
namespace MercadoPago\MercadoEnvios\Plugin;

class PluginBeforeView
{

    protected $_shipmentHelper;
    protected $_shipmentFactory;

    /**
     * PluginBeforeView constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \MercadoPago\Core\Helper\Data                      $coreHelper
     * @param \Magento\Config\Model\ResourceModel\Config         $configResource
     */
    public function __construct(
        \MercadoPago\MercadoEnvios\Helper\Data $shipmentHelper,
        \Magento\Sales\Model\ResourceModel\Order\ShipmentFactory $shipmentFactory
    )
    {
        $this->shipmentHelper = $shipmentHelper;
        $this->_shipmentFactory = $shipmentFactory;

    }
    
    public function afterGetButtonList(\Magento\Backend\Block\Widget\Context $subject, $buttonList) {

        if ($subject->getRequest()->getFullActionName() == 'adminhtml_order_shipment_view') {
            $buttonList->add(
                'custom_button',
                [
                    'label'   => 'Print shipping label',
                    'onclick' => 'window.open(\' ' . $this->shipmentHelper->getTrackingPrintUrl($subject->getRequest()->getParam('shipment_id')) . '\')',
                    'class'   => 'go'
                ]
            );
        }

        return $buttonList;
    }

}