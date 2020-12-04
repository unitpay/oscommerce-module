<?php
error_reporting(E_ERROR | E_PARSE);

class unitpay
{
    var $code, $title, $description, $enabled;

    // class constructor
    function unitpay()
    {

        //global $order;

        $this->signature = 'unitpay';
        $this->api_version = '112';

        $this->code = 'unitpay';
        $this->title = MODULE_PAYMENT_UNITPAY_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_UNITPAY_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_UNITPAY_SORT_ORDER;
        $this->enabled = true;

    }

    // class methods
    function update_status()
    {
        return false;
    }

    function javascript_validation()
    {
        return false;
    }

    function selection()
    {
        return array('id' => $this->code, 'module' => $this->title);
    }

    function pre_confirmation_check()
    {
        return false;
    }

    function confirmation()
    {
        return false;
    }

    function process_button()
    {

        return false;
    }

    function before_process()
    {
        return false;
    }

    function after_process()
    {
        global $insert_id, $cart, $order;

        $domain = MODULE_PAYMENT_UNITPAY_DOMAIN;
        $public_key = MODULE_PAYMENT_UNITPAY_PUBLIC_KEY;
        $secret_key = MODULE_PAYMENT_UNITPAY_SECRET_KEY;
        $sum = number_format($order->info['total'], 2, '.', '');

        $currency = $order->info['currency'];
        $account = $insert_id;
        $desc = 'Заказ №' . $insert_id;
        $signature = hash('sha256', join('{up}', array(
            $account,
            $currency,
            $desc,
            $sum,
            $secret_key
        )));

        $cashItems = $this->cashItems($order, $currency);
        $email = $order->customer["email_address"];
        $phone = $order->customer["telephone"];

        $payment_url = 'https://' . $domain . '/pay/' . $public_key . '?' . 'sum=' . $sum . '&account=' . $account . '&signature=' . $signature . '&currency=' . $currency . '&desc=' . $desc . '&customerEmail=' . $email .'&customerPhone=' . $phone . '&cashItems=' .$cashItems;

        $cart->reset(true);
        tep_session_unregister('sendto');
        tep_session_unregister('billto');
        tep_session_unregister('shipping');
        tep_session_unregister('payment');
        tep_session_unregister('comments');
        tep_redirect($payment_url);
    }

    function cashItems($order, $currency) {
        $items = array_map(function ($item) use($currency) {
            return array(
                'name' => $item["name"],
                'count' => $item["qty"],
                'price' => $item["price"],
                'currency' => $currency,
                'type' => 'commodity',
                'nds' => $this->taxRates($item['tax']),
            );
        }, $order->products);

        if($order->info['shipping_cost'] > 0) {
            $items[] = array(
                'name' => "Услуги доставки",
                'count' => 1,
                'price' => $order->info['shipping_cost'],
                'currency' => $currency,
                'type' => 'service',
                //'nds' => $this->taxRates($order->info['tax']),
            );
        }

        return base64_encode(json_encode($items));
    }

    function taxRates($rate){
        switch (intval($rate)){
            case 10:
                $vat = 'vat10';
                break;
            case 20:
                $vat = 'vat20';
                break;
            case 0:
                $vat = 'none';
                break;
            default:
                $vat = 'none';
        }

        return $vat;
    }

    function output_error()
    {
        return false;
    }

    function check()
    {
        return true;
    }

    function install()
    {

        global $cfgModules, $language;
        $module_language_directory = $cfgModules->get('payment', 'language_directory');
        include_once($module_language_directory.$language."/modules/payment/unitpay.php");

        $pay_status_id = $this->createOrderStatus("Paid[Unitpay]");
        $error_status_id = $this->createOrderStatus("Error[Unitpay]");

        //config params
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values (
            '".MODULE_PAYMENT_UNITPAY_DOMAIN_TITLE."', 
            'MODULE_PAYMENT_UNITPAY_DOMAIN', 
            '', 
            '', 
            '6', '0', now())"
        );

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values (
            '".MODULE_PAYMENT_UNITPAY_PUBLIC_KEY_TITLE."', 
            'MODULE_PAYMENT_UNITPAY_PUBLIC_KEY', 
            '', 
            '', 
            '6', '0', now())"
        );

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values (
            '".MODULE_PAYMENT_UNITPAY_SECRET_KEY_TITLE."', 
            'MODULE_PAYMENT_UNITPAY_SECRET_KEY', 
            '', 
            '', 
            '6', '0', now())"
        );

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values (
            '".MODULE_PAYMENT_UNITPAY_SORT_ORDER_TITLE."', 
            'MODULE_PAYMENT_UNITPAY_SORT_ORDER', 
            '0', 
            '', 
            '6', '0', now())");

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values (
            '".MODULE_PAYMENT_UNITPAY_ORDER_PAY_STATUS_TITLE."', 
            'MODULE_PAYMENT_UNITPAY_ORDER_PAY_STATUS_ID', 
            '".$pay_status_id."',  
            '', 
            '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values (
            '".MODULE_PAYMENT_UNITPAY_ORDER_ERROR_STATUS_TITLE."', 
            'MODULE_PAYMENT_UNITPAY_ORDER_ERROR_STATUS_ID', 
            '".$error_status_id."',  
            '', 
            '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values (
            '".MODULE_PAYMENT_UNITPAY_CALLBACK_TITLE."', 
            'MODULE_PAYMENT_UNITPAY_CALLBACK', 
            '" . HTTP_SERVER . DIR_WS_CATALOG . "unitpay.php',
            '', 
            '6', '0', now())");

    }

    function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION .
                " where configuration_key in ('" . implode("', '", $this->keys()) . "')");

    }

    function keys()
    {
        return array(
            'MODULE_PAYMENT_UNITPAY_DOMAIN',
            'MODULE_PAYMENT_UNITPAY_PUBLIC_KEY',
            'MODULE_PAYMENT_UNITPAY_SECRET_KEY',
            'MODULE_PAYMENT_UNITPAY_SORT_ORDER',
            'MODULE_PAYMENT_UNITPAY_ORDER_PAY_STATUS_ID',
            'MODULE_PAYMENT_UNITPAY_ORDER_ERROR_STATUS_ID',
            'MODULE_PAYMENT_UNITPAY_CALLBACK',
        );
    }

    function createOrderStatus( $title ){
        $q = tep_db_query("select orders_status_id from ".TABLE_ORDERS_STATUS." where orders_status_name = '".$title."' limit 1");
        if (tep_db_num_rows($q) < 1) {
            $q = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
            $row = tep_db_fetch_array($q);
            $status_id = $row['status_id'] + 1;
            $languages = tep_get_languages();
            $qf = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
            if (tep_db_num_rows($qf) == 1) {
                foreach ($languages as $lang) {
                    tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name, public_flag) values ('" . $status_id . "', '" . $lang['id'] . "', " . "'" . $title . "', 1)");
                }
            } else {
                foreach ($languages as $lang) {
                    tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id . "', '" . $lang['id'] . "', " . "'" . $title . "')");
                }
            }
        }else{
            $status = tep_db_fetch_array($q);
            $status_id = $status['orders_status_id'];
        }
        return $status_id;
    }

}
?>