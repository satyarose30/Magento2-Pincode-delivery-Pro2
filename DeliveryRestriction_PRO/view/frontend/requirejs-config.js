/**
 * Custom_DeliveryRestriction — RequireJS config
 * Registers a KnockoutJS mixin on the Magento checkout shipping step
 * to show live pincode availability feedback as the customer types.
 */
var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/shipping': {
                'Custom_DeliveryRestriction/js/checkout-zip-validator': true
            }
        }
    }
};
