<?php
/*
Modified April 15th 2020 by Blockchain Remittance Ltd.
Adapted to handle calls to RemiBit API.
*/


/**
 * osCommerce Online Merchant
 *
 * @copyright (c) 2016 osCommerce; https://www.oscommerce.com
 * @license MIT; https://www.oscommerce.com/license/mit.txt
 */

use OSC\OM\HTML;
use OSC\OM\Mail;
use OSC\OM\OSCOM;
use OSC\OM\Registry;

class remibit
{
    var $code, $title, $description, $enabled;

    function __construct()
    {
        $this->api_version = '1.0';
        $this->code = 'remibit';
        $this->title = OSCOM::getDef('module_payment_remibit_text_title');
        $this->public_title = OSCOM::getDef('module_payment_remibit_text_public_title');
        $this->description = OSCOM::getDef('module_payment_remibit_text_description');
        $this->sort_order = defined('MODULE_PAYMENT_REMIBIT_SORT_ORDER') ? MODULE_PAYMENT_REMIBIT_SORT_ORDER : 0;
        $this->enabled = defined('MODULE_PAYMENT_REMIBIT_STATUS') && (MODULE_PAYMENT_REMIBIT_STATUS == 'True') ? true : false;
        $this->order_status = defined('MODULE_PAYMENT_REMIBIT_ORDER_STATUS_ID') && ((int)MODULE_PAYMENT_REMIBIT_ORDER_STATUS_ID > 0) ? (int)MODULE_PAYMENT_REMIBIT_ORDER_STATUS_ID : 0;

        if ($this->enabled === true) {
            if (!tep_not_null(MODULE_PAYMENT_REMIBIT_TRANSACTION_KEY) || !tep_not_null(MODULE_PAYMENT_REMIBIT_LOGIN_ID)
                || !tep_not_null(MODULE_PAYMENT_REMIBIT_LOGIN_ID) || !tep_not_null(MODULE_PAYMENT_REMIBIT_MD5_HASH)
            ) {
                $this->description = '<div class="secWarning">' . OSCOM::getDef('module_payment_remibit_error_admin_configuration') . '</div>' . $this->description;

                $this->enabled = false;
            }
        }
    }

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
        return array('id' => $this->code,
            'module' => $this->public_title);
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
        global $order;
        $currency = $_SESSION['currency'];
        if (!isset($_POST['x_response_code']) && !isset($_POST['x_invoice_num'])) {
            $order_id = $_SESSION['cartID'];
            $timeStamp = time();
            $order_total = $this->format_raw($order->info['total']);

            $transactionKey = MODULE_PAYMENT_REMIBIT_TRANSACTION_KEY;

            if (function_exists('hash_hmac')) {
                $hash_d = hash_hmac('md5', sprintf('%s^%s^%s^%s^%s',
                    MODULE_PAYMENT_REMIBIT_LOGIN_ID,
                    $order_id,
                    $timeStamp,
                    $order_total,
                    $currency
                ), $transactionKey);
            } else {
                $hash_d = bin2hex(mhash(MHASH_MD5, sprintf('%s^%s^%s^%s^%s',
                    MODULE_PAYMENT_REMIBIT_LOGIN_ID,
                    $order_id,
                    $timeStamp,
                    $order_total,
                    $currency
                ), $transactionKey));
            }

            $params = array(
                'x_login'           => MODULE_PAYMENT_REMIBIT_LOGIN_ID,
                'x_amount'          => $order_total,
                'x_invoice_num'     => $order_id,
                'x_relay_response'  => 'TRUE',
                'x_fp_sequence'     => $order_id,
                'x_fp_hash'         => $hash_d,
                'x_show_form'       => 'PAYMENT_FORM',
                'x_tran_key'        => MODULE_PAYMENT_REMIBIT_TRANSACTION_KEY,
                'x_version'         => $this->api_version,
                'x_type'            => 'AUTH_CAPTURE',
                'x_relay_url'       => OSCOM::link('ext/modules/payment/remibit/callback.php', '', 'SSL', false, false),
                'x_currency_code'   => $currency,
                'x_fp_timestamp'    => $timeStamp,
                'x_first_name'      => $order->billing['firstname'],
                'x_last_name'       => $order->billing['lastname'],
                'x_company'         => $order->billing['company'],
                'x_address'         => $order->billing['street_address'],
                'x_city'            => $order->billing['city'],
                'x_state'           => $order->billing['state'],
                'x_zip'             => $order->billing['postcode'],
                'x_country'         => $order->billing['country']['title'],
                'x_phone'           => $order->customer['telephone'],
                'x_email'           => $order->customer['email_address'],
                'x_tax'             => $this->format_raw($order->info['tax']),
                'x_cancel_url'      => OSCOM::link('checkout_payment.php', '', 'SSL'),
                'x_cancel_url_text' => 'Cancel Payment',
                'x_test_request'    => 'FALSE',
                'x_ship_to_first_name' => $order->delivery['firstname'],
                'x_ship_to_last_name' => $order->delivery['lastname'],
                'x_ship_to_company'   => $order->delivery['company'],
                'x_ship_to_address'   => $order->delivery['street_address'],
                'x_ship_to_city'      => $order->delivery['city'],
                'x_ship_to_state'     => $order->delivery['state'],
                'x_ship_to_zip'       => $order->delivery['postcode'],
                'x_ship_to_country'   => $order->delivery['country']['title'],
                 'x_freight'          => $this->format_raw($order->info['shipping_cost'])
            );

            $post_string = array();

            foreach ($params as $key => $value) {
                $post_string[] = "<input type='hidden' name='$key' value='$value'/>";
            }

            $gateway_url = MODULE_PAYMENT_REMIBIT_GATEWAY_URL;

            $this->sendTransactionToGateway($gateway_url, $post_string);
        }
    }

    function after_process()
    {
        global $insert_id;

        $OSCOM_Db = Registry::get('Db');

        if(!$this->validate()){
            OSCOM::redirect('checkout_payment.php', 'payment_error=' . $this->code . '&error=invalid_payment', 'SSL');
        } else {
            $sql_data_array = array('orders_id' => $insert_id,
                'orders_status_id' => MODULE_PAYMENT_REMIBIT_TRANSACTION_ORDER_STATUS_ID,
                'date_added' => 'now()',
                'customer_notified' => '0',
                'comments' => 'Payment successful<br/>Ref Number/Transaction ID: '.$_REQUEST['x_trans_id']);

            $OSCOM_Db->save('orders_status_history', $sql_data_array);
        }
    }

    function validate()
    {
        $hashData = implode('^', [
            $_POST['x_trans_id'],
            $_POST['x_test_request'],
            $_POST['x_response_code'],
            $_POST['x_auth_code'],
            $_POST['x_cvv2_resp_code'],
            $_POST['x_cavv_response'],
            $_POST['x_avs_code'],
            $_POST['x_method'],
            $_POST['x_account_number'],
            $_POST['x_amount'],
            $_POST['x_company'],
            $_POST['x_first_name'],
            $_POST['x_last_name'],
            $_POST['x_address'],
            $_POST['x_city'],
            $_POST['x_state'],
            $_POST['x_zip'],
            $_POST['x_country'],
            $_POST['x_phone'],
            $_POST['x_fax'],
            $_POST['x_email'],
            $_POST['x_ship_to_company'],
            $_POST['x_ship_to_first_name'],
            $_POST['x_ship_to_last_name'],
            $_POST['x_ship_to_address'],
            $_POST['x_ship_to_city'],
            $_POST['x_ship_to_state'],
            $_POST['x_ship_to_zip'],
            $_POST['x_ship_to_country'],
            $_POST['x_invoice_num'],
        ]);

        $digest = strtoupper(HASH_HMAC('sha512', "^" . $hashData . "^", hex2bin(MODULE_PAYMENT_REMIBIT_SIGNATURE_KEY)));
        if ($_POST['x_response_code'] != '' && (strtoupper($_POST['x_SHA2_Hash']) == $digest)) {
            return true;
        } else {
            return false;
        }
    }

    function get_error()
    {
        global $HTTP_GET_VARS;

        $error_message = module_payment_remibit_error_gereral;

        switch ($HTTP_GET_VARS['error']) {

            case 'currency':
                $error_message = module_payment_remibit_error_currentcy;
                break;

            default:
                $error_message = module_payment_remibit_error_gereral;
                break;
        }

        $error = array('title' => module_payment_remibit_error_title,
            'error' => $error_message);

        return $error;
    }

    function check()
    {
        return defined('MODULE_PAYMENT_REMIBIT_STATUS');
    }

    function install($parameter = null)
    {
        $OSCOM_Db = Registry::get('Db');

        $params = $this->getParams();

        if (isset($parameter)) {
            if (isset($params[$parameter])) {
                $params = array($parameter => $params[$parameter]);
            } else {
                $params = array();
            }
        }

        foreach ($params as $key => $data) {
            $sql_data_array = array('configuration_title' => $data['title'],
                'configuration_key' => $key,
                'configuration_value' => (isset($data['value']) ? $data['value'] : ''),
                'configuration_description' => $data['desc'],
                'configuration_group_id' => '6',
                'sort_order' => '0',
                'date_added' => 'now()');

            if (isset($data['set_func'])) {
                $sql_data_array['set_function'] = $data['set_func'];
            }

            if (isset($data['use_func'])) {
                $sql_data_array['use_function'] = $data['use_func'];
            }

            $OSCOM_Db->save('configuration', $sql_data_array);
        }
    }

    function remove()
    {
        return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
    }

    function keys()
    {
        $keys = array_keys($this->getParams());

        if ($this->check()) {
            foreach ($keys as $key) {
                if (!defined($key)) {
                    $this->install($key);
                }
            }
        }

        return $keys;
    }

    function getParams()
    {
        $OSCOM_Db = Registry::get('Db');

        if (!defined('MODULE_PAYMENT_REMIBIT_TRANSACTION_ORDER_STATUS_ID')) {
            $Qcheck = $OSCOM_Db->get('orders_status', 'orders_status_id', ['orders_status_name' => 'RemiBit [Transactions]'], null, 1);

            if ($Qcheck->fetch() === false) {
                $Qstatus = $OSCOM_Db->get('orders_status', 'max(orders_status_id) as status_id');

                $status_id = $Qstatus->valueInt('status_id') + 1;

                $languages = tep_get_languages();

                foreach ($languages as $lang) {
                    $OSCOM_Db->save('orders_status', [
                        'orders_status_id' => $status_id,
                        'language_id' => $lang['id'],
                        'orders_status_name' => 'RemiBit [Transactions]',
                        'public_flag' => 0,
                        'downloads_flag' => 0
                    ]);
                }
            } else {
                $status_id = $Qcheck->valueInt('orders_status_id');
            }
        } else {
            $status_id = MODULE_PAYMENT_REMIBIT_TRANSACTION_ORDER_STATUS_ID;
        }

        $params = array('MODULE_PAYMENT_REMIBIT_STATUS' => array('title' => 'Enable RemiBit Payment Method',
            'desc' => 'Do you want to accept RemiBit payment Method',
            'value' => 'True',
            'set_func' => 'tep_cfg_select_option(array(\'True\', \'False\'), '),
            'MODULE_PAYMENT_REMIBIT_LOGIN_ID' => array('title' => 'Login ID',
                'desc' => 'The Login ID used for the RemiBit service',
                'type' => 'password'),

            'MODULE_PAYMENT_REMIBIT_TRANSACTION_KEY' => array('title' => 'Transaction Key',
                'desc' => 'The Transaction Key used for the RemiBit service',
                'type' => 'password'),

            'MODULE_PAYMENT_REMIBIT_SIGNATURE_KEY' => array('title' => 'Signature Key',
                'desc' => 'Signature Key',
                'type' => 'password'),

            'MODULE_PAYMENT_REMIBIT_MD5_HASH' => array('title' => 'MD5 Hash Value',
                'desc' => 'The MD5 Hash value to verify transactions with',
                'type' => 'password'),

            'MODULE_PAYMENT_REMIBIT_TRANSACTION_ORDER_STATUS_ID' => array('title' => 'Transaction Order Status',
                'desc' => 'Include transaction information in this order status level',
                'value' => $status_id,
                'use_func' => 'tep_get_order_status_name',
                'set_func' => 'tep_cfg_pull_down_order_statuses('),

            'MODULE_PAYMENT_REMIBIT_GATEWAY_URL' => array('title' => 'Endpoint URL',
                'desc' => 'API URL',
                'value' => 'https://app.remibit.com/pay',
                'type' => 'text'),
        );

        return $params;
    }

// format prices without currency formatting
    function format_raw($number, $currency_code = '', $currency_value = '')
    {
        global $currencies;

        if (empty($currency_code) || !$currencies->is_set($currency_code)) {
            $currency_code = $_SESSION['currency'];
        }

        if (empty($currency_value) || !is_numeric($currency_value)) {
            $currency_value = $currencies->currencies[$currency_code]['value'];
        }

        return number_format(tep_round($number * $currency_value, $currencies->currencies[$currency_code]['decimal_places']), $currencies->currencies[$currency_code]['decimal_places'], '.', '');
    }

    function getOrderTotalsSummary()
    {
        global $order_total_modules;

        $order_total_array = array();

        if (is_array($order_total_modules->modules)) {
            foreach ($order_total_modules->modules as $value) {
                $class = substr($value, 0, strrpos($value, '.'));
                if ($GLOBALS[$class]->enabled) {
                    for ($i = 0, $n = sizeof($GLOBALS[$class]->output); $i < $n; $i++) {
                        if (tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->output[$i]['text'])) {
                            $order_total_array[] = array('code' => $GLOBALS[$class]->code,
                                'title' => $GLOBALS[$class]->output[$i]['title'],
                                'text' => $GLOBALS[$class]->output[$i]['text'],
                                'value' => $GLOBALS[$class]->output[$i]['value'],
                                'sort_order' => $GLOBALS[$class]->sort_order);
                        }
                    }
                }
            }
        }

        return $order_total_array;
    }

    function sendTransactionToGateway($url, $parameters)
    {
        $loading = ' <div style="width: 100%; height: 100%;top: 50%; padding-top: 10px;padding-left: 10px;  left: 50%; transform: translate(40%, 40%)"><div style="width: 150px;height: 150px;border-top: #CC0000 solid 5px; border-radius: 50%;animation: a1 2s linear infinite;position: absolute"></div> </div> <style>*{overflow: hidden;}@keyframes a1 {to{transform: rotate(360deg)}}</style>';

        $html_form = '<form action="' . $url . '" method="post" id="authorize_payment_form">' . implode('', $parameters) . '<input type="submit" id="submit_authorize_payment_form" style="display: none"/>' . $loading . '</form><script>document.getElementById("submit_authorize_payment_form").click();</script>';

        echo $html_form;
        die();
    }
}
?>
