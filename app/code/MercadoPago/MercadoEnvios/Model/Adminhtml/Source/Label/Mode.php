<?php
namespace MercadoPago\MercadoEnvios\Model\Adminhtml\Source\Label;

class Mode
    implements \Magento\Framework\Option\ArrayInterface
{

    public function toOptionArray()
    {
        return [['value' => 'pdf' , 'label' => 'PDF'],['value' => 'zpl2' , 'label' => 'ZIP']];
    }

}
