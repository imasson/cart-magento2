define(
    [
        'jquery',
        'Magento_Payment/js/view/payment/iframe',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
        'Magento_Checkout/js/model/quote',
        'meli',
        'tinyj',
        'MPcustom',
        'tiny'
    ],
    function ($, Component, additionalValidators, setPaymentInformationAction, fullScreenLoader, $t, quote) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'MercadoPago_Core/payment/custom-method'
            },
            placeOrderHandler: null,
            validateHandler: null,
            redirectAfterPlaceOrder: false,


            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },

            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },

            context: function () {
                return this;
            },

            isShowLegend: function () {
                return true;
            },

            getCode: function () {
                return 'mercadopago_custom';
            },

            getTokenCodeArray: function (code) {
                return "payment[" + this.getCode() + "][" + code + "]";
            },

            isActive: function () {
                return true;
            },

            isOCPReady: function () {
                return ((this.getCustomer() != false) && (this.getCustomer().cards.length > 0));
            },

            initApp: function () {
                if (window.checkoutConfig.payment[this.getCode()] != undefined) {
                    window.PublicKeyMercadoPagoCustom = window.checkoutConfig.payment[this.getCode()]['public_key'];
                    MercadoPagoCustom.enableLog(window.checkoutConfig.payment[this.getCode()]['logEnabled']);
                    MercadoPagoCustom.getInstance().init();
                    if (this.isOCPReady()) {
                        MercadoPagoCustom.getInstance().initOCP();
                    }
                }
            },

            initDiscountApp: function () {
                if (this.isCouponEnabled()){
                    MercadoPagoCustom.getInstance().initDiscount();
                }
            },

            isCouponEnabled: function () {
                if (window.checkoutConfig.payment[this.getCode()] != undefined) {
                    return (window.checkoutConfig.payment[this.getCode()]['discount_coupon']);
                }
            },

            getAvailableCards: function () {
                if (window.checkoutConfig.payment[this.getCode()] != undefined) {
                    var _customer = window.checkoutConfig.payment[this.getCode()]['customer'];
                    if (!_customer) return [];

                    var Card = function(value, name, firstSix, securityCodeLength, secureThumbnail) {
                        this.cardName = name;
                        this.value = value;
                        this.firstSix = firstSix;
                        this.securityCodeLength = securityCodeLength;
                        this.secureThumbnail = secureThumbnail;
                    };

                    var availableCards = [];
                    _customer.cards.forEach(function(card) {
                        availableCards.push(new Card(card['id'],
                            card['payment_method']['name']+ ' ended in ' + card['last_four_digits'],
                            card['first_six_digits'],
                            card['security_code']['length'] ),
                            card['payment_method']['secure_thumbnail']);
                    });
                    return availableCards;
                }
                return [];
            },
            setOptionsExtraValues: function (option, item) {
                jQuery(option).attr('first_six_digits', item.firstSix);
                jQuery(option).attr('security_code_length', item.securityCodeLength);
                jQuery(option).attr('secure_thumb', item.secureThumbnail);
            },
            getCustomerAttribute: function (attribute) {
                if (window.checkoutConfig.payment[this.getCode()] != undefined) {
                    return window.checkoutConfig.payment[this.getCode()]['customer'][attribute];
                }
                return '';
            },
            getBannerUrl: function () {
                if (window.checkoutConfig.payment[this.getCode()] != undefined) {
                    return window.checkoutConfig.payment[this.getCode()]['bannerUrl'];
                }
                return '';
            },

            getGrandTotal: function () {
                return quote.totals().base_grand_total;
            },

            getBaseUrl: function () {
                if (window.checkoutConfig.payment[this.getCode()] != undefined) {
                    return window.checkoutConfig.payment[this.getCode()]['base_url'];
                }
                return '';
            },
            getRoute: function () {
                if (window.checkoutConfig.payment[this.getCode()] != undefined) {
                    return window.checkoutConfig.payment[this.getCode()]['route'];
                }
                return '';
            },
            getCountry: function () {
                if (window.checkoutConfig.payment[this.getCode()] != undefined) {
                    return window.checkoutConfig.payment[this.getCode()]['country'];
                }
                return '';
            },
            getSuccessUrl: function () {
                if (window.checkoutConfig.payment[this.getCode()] != undefined) {
                    return window.checkoutConfig.payment[this.getCode()]['success_url'];
                }
                return '';
            },
            getCustomer: function () {
                if (window.checkoutConfig.payment[this.getCode()] != undefined) {
                    return window.checkoutConfig.payment[this.getCode()]['customer'];
                }
                return '';
            },
            getLoadingGifUrl: function () {
                if (window.checkoutConfig.payment[this.getCode()] != undefined) {
                    return window.checkoutConfig.payment[this.getCode()]['loading_gif'];
                }
                return '';
            },

            /**
             * Get url to logo
             * @returns {String}
             */
            getLogoUrl: function () {
                if (window.checkoutConfig.payment['mercadopago_standard'] != undefined) {
                    return window.checkoutConfig.payment[this.getCode()]['logoUrl'];
                }
                return '';
            },

            /**
             * @override
             */
            getData: function () {
                var dataObj = {
                    'method': this.item.method,
                    'additional_data': {
                        'payment[method]': this.getCode(),
                        'card_expiration_month': TinyJ('#cardExpirationMonth').val(),
                        'card_expiration_year': TinyJ('#cardExpirationYear').val(),
                        'card_holder_name': TinyJ('#cardholderName').val(),
                        'doc_type': TinyJ('#docType').val(),
                        'doc_number': TinyJ('#docNumber').val(),
                        'installments': TinyJ('#installments').val(),
                        'total_amount':  TinyJ('#mercadopago_checkout_custom').getElem('.total_amount').val(),
                        'amount': TinyJ('#mercadopago_checkout_custom').getElem('.amount').val(),
                        'site_id': this.getCountry(),
                        'token': TinyJ('.token').val(),
                        'payment_method_id': TinyJ('#mercadopago_checkout_custom').getElem('.payment_method_id').val(),
                        'one_click_pay': TinyJ('#one_click_pay_mp').val(),
                        'issuer_id': TinyJ('#issuer').val()
                    }
                };
                if (window.checkoutConfig.payment[this.getCode()] != undefined) {
                    if (window.checkoutConfig.payment[this.getCode()]['discount_coupon']) {
                        dataObj.additional_data['mercadopago-discount-amount'] = TinyJ('#mercadopago_checkout_custom').getElem('.mercadopago-discount-amount').val();
                        dataObj.additional_data['coupon_code'] = TinyJ('#mercadopago_checkout_custom').getElem('#input-coupon-discount').val();
                    }
                }
                if (this.isOCPReady()) {
                    dataObj.additional_data['customer_id'] = TinyJ('#customer_id').val();
                }
                return dataObj;
            },
            afterPlaceOrder : function () {
                window.location = this.getSuccessUrl();
            },
            validate : function () {
                return this.validateHandler();
            }


        });
    }
);