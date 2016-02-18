<?php
namespace Mercadopago\Core\Controller\Notifications;


class Standard
    extends \Magento\Framework\App\Action\Action

{
    protected $_paymentFactory;

    /**
     * @var \MercadoPago\Core\Helper\
     */
    protected $coreHelper;

    /**
     * @var \MercadoPago\Core\Model\Core
     */
    protected $coreModel;

    const LOG_NAME = 'notification';


    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \MercadoPago\Core\Model\Standard\PaymentFactory $paymentFactory,
        \MercadoPago\Core\Helper\Data $coreHelper,
        \MercadoPago\Core\Model\Core $coreModel
    )
    {
        $this->_paymentFactory = $paymentFactory;
        $this->coreHelper = $coreHelper;
        $this->coreModel = $coreModel;
        parent::__construct($context);
    }

    public function execute()
    {
        $request = $this->getRequest();
        //notification received
        $this->coreHelper->log("Standard Received notification", self::LOG_NAME, $request->getParams());

        //$core = Mage::getModel('mercadopago/core');

        $id = $request->getParam('id');
        $topic = $request->getParam('topic');

        if (!empty($id) && $topic == 'merchant_order') {
            $response = $this->coreModel->getMerchantOrder($id);
            $this->coreHelper->log("Return merchant_order", self::LOG_NAME, $response);
            if ($response['status'] == 200 || $response['status'] == 201) {
                $merchant_order = $response['response'];

                if (count($merchant_order['payments']) > 0) {
                    $data = $this->_getDataPayments($merchant_order);
                    $status_final = $this->getStatusFinal($data['status']);
                    $this->coreHelper->log("Update Order", self::LOG_NAME);
                    $this->coreModel->updateOrder($data);

                    if ($status_final != false) {
                        $data['status_final'] = $status_final;
                        $this->coreHelper->log("Received Payment data", self::LOG_NAME, $data);
                        $setStatusResponse = $this->coreModel->setStatusOrder($data);
                        $this->getResponse()->setBody($setStatusResponse['text']);
                        $this->getResponse()->setHttpResponseCode($setStatusResponse['code']);
                    }

                    return;
                }
            }
        }

        $this->coreHelper->log("Merchant Order not found", self::LOG_NAME, $request->getParams());
        $this->getResponse()->setBody("Merchant Order not found");
        //$this->getResponse()->setHttpResponseCode(MercadoPago_Core_Helper_Response::HTTP_NOT_FOUND);
    }

}