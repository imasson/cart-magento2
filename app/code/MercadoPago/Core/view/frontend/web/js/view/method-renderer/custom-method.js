define(
    [
        'Magento_Payment/js/view/payment/iframe',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/model/full-screen-loader',
        'meli',
        'tinyj'
    ],
    function ($, Component, additionalValidators, setPaymentInformationAction, fullScreenLoader) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'MercadoPago_Core/payment/custom-method'
            },
            placeOrderHandler: null,
            validateHandler: null,

            setPlaceOrderHandler: function(handler) {
                this.placeOrderHandler = handler;
            },

            setValidateHandler: function(handler) {
                this.validateHandler = handler;
            },

            context: function() {
                return this;
            },

            isShowLegend: function() {
                return true;
            },

            getCode: function() {
                return 'mercadopago_custom';
            },

            isActive: function() {
                return true;
            },

            /**
             * @override
             */
            placeOrder: function () {
                var self = this;
                //if (this.validateHandler() && additionalValidators.validate()) {
                //    fullScreenLoader.startLoader();
                //    this.isPlaceOrderActionAllowed(false);
                //    $.when(setPaymentInformationAction(this.messageContainer, {
                //        'method': self.getCode()
                //    })).done(function () {
                //        self.placeOrderHandler().fail(function () {
                //            fullScreenLoader.stopLoader();
                //        });
                //    }).fail(function () {
                //        fullScreenLoader.stopLoader();
                //        self.isPlaceOrderActionAllowed(true);
                //    });
                //}
            }

        });
    }
);