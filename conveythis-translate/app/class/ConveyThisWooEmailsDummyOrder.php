<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * In-memory order used to render customer emails for the warm-up, modelled on
 * WooCommerce's own email preview order. It is never saved: the ID stays 0 and save()
 * does nothing. Loaded only when a warm-up runs, after WooCommerce.
 */
class ConveyThisWooEmailsDummyOrder extends WC_Order
{
    /** Masked like any order number; chosen so it does not occur elsewhere in an email. */
    const ORDER_NUMBER = '424242';

    public function __construct($order = 0) {
        parent::__construct($order);
        foreach ($this->item_types_to_group as $group) {
            $this->items[$group] = array();
        }
        $this->meta_data = array();
    }

    public function get_order_number() {
        return self::ORDER_NUMBER;
    }

    public function save() {
        return $this->get_id();
    }

    public function save_meta_data() {
    }

    public static function build() {
        $order = new self();

        // A real product name if the shop has one: its translation is usually ready already.
        $name = 'Product';
        $products = function_exists('wc_get_products') ? wc_get_products(array('status' => 'publish', 'limit' => 1)) : array();
        if (!empty($products) && is_object($products[0])) {
            $name = $products[0]->get_name();
        }
        $item = new WC_Order_Item_Product();
        $item->set_props(array('name' => $name, 'quantity' => 2, 'subtotal' => 20, 'total' => 20));
        $order->add_item($item);

        $title = '';
        if (function_exists('WC') && WC()->payment_gateways()) {
            foreach (WC()->payment_gateways()->get_available_payment_gateways() as $gateway) {
                $title = $gateway->get_title();
                break;
            }
        }
        $order->set_payment_method_title($title !== '' ? $title : 'Direct bank transfer');

        // Every masked field filled in, so the placeholders match a complete real order.
        $address = array(
            'first_name' => 'Alex',
            'last_name'  => 'Sample',
            'company'    => 'Sample Company',
            'address_1'  => '1 Sample Street',
            'address_2'  => 'Suite 2',
            'city'       => 'Sampletown',
            'state'      => 'CA',
            'postcode'   => '90001',
            'country'    => 'US',
            'email'      => 'alex@example.com',
            'phone'      => '+1 555 0100',
        );
        $order->set_address($address, 'billing');
        $order->set_address($address, 'shipping');
        $order->set_customer_note('Please leave the parcel at the door.');
        $order->set_date_created(time());
        $order->set_currency(get_woocommerce_currency());
        $order->set_total(20);
        $order->set_status('processing');
        return $order;
    }
}
