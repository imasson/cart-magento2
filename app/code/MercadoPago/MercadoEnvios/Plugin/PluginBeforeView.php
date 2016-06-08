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
    
    public function afterGetShipment(\Magento\Shipping\Block\Adminhtml\View $subject){
        if ($subject->getRequest()->getFullActionName() == 'adminhtml_order_shipment_view') {
//            $subject->addButton(
//                'custom_button',
//                [
//                    'label'   => 'Print shipping label',
//                    'onclick' => 'window.open(\' ' . $this->shipmentHelper->getTrackingPrintUrl($subject->getRequest()->getParam('shipment_id')) . '\')',
//                    'class'   => 'go'
//                ]
//            );
//            $subject->addButton(
//                'print',
//                [
//                    'label' => __('Print'),
//                    'class' => 'save',
//                    'onclick' => 'setLocation(\'' . 'xxxxxxxx'. '\')'
//                ]
//           );
       }

   //     return null;
   }

}