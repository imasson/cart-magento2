<?php
namespace MercadoPago\Core\Model\Custom;

class Payment
    extends \Magento\Payment\Model\Method\AbstractMethod
{
    protected $_code = 'mercadopago_custom';
}
