<?php
namespace MercadoPago\MercadoEnvios\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 * Class ConfigObserver
 *
 * @package MercadoPago\Core\Observer
 */
class TrackingPopup
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
    protected $_request;

    /**
     * @var \Magento\Shipping\Model\InfoFactory
     */
    protected $_shippingInfoFactory;
    protected $_actionFlag;

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
        \Magento\Framework\App\Request\Http $request,
        \Magento\Shipping\Model\InfoFactory $shippingInfoFactory,
        \Magento\Framework\App\ActionFlag $actionFlag

    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->configResource = $configResource;
        $this->coreHelper = $coreHelper;
        $this->coreModel = $coreModel;
        $this->shipmentHelper = $shipmentHelper;
        $this->_timezone = $timeZone;
        $this->_request = $request;
        $this->_shippingInfoFactory = $shippingInfoFactory;
        $this->_actionFlag = $actionFlag;


    }

    /**
     * Updates configuration values based every time MercadoPago configuration section is saved
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $shippingInfoModel = $this->_shippingInfoFactory->create()->loadByHash($this->_request->getParam('hash'));

        if ($url = $this->shipmentHelper->getTrackingUrlByShippingInfo($shippingInfoModel)) {
            $controller = $observer->getControllerAction();
            $controller->getResponse()->setRedirect($url);
            $this->_actionFlag->set('', \Magento\Framework\App\Action\Action::FLAG_NO_DISPATCH, true);

            //$controller->setFlag('', 'no-dispatch', true);
        }

        return $observer;
    }

}
