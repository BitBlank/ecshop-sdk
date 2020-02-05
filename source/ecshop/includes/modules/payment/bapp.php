<?php

if (!defined('IN_ECS')) {
    die('Hacking attempt');
}

$payment_lang = ROOT_PATH . 'languages/' . $GLOBALS['_CFG']['lang'] . '/payment/bapp.php';

if (file_exists($payment_lang)) {
    global $_LANG;

    include_once($payment_lang);
}

if (isset($set_modules) && $set_modules == TRUE) {
    $i = isset($modules) ? count($modules) : 0;

    /* 代码 */
    $modules[$i]['code'] = basename(__FILE__, '.php');

    /* 描述对应的语言项 */
    $modules[$i]['desc'] = 'bapp_desc';

    /* 是否支持货到付款 */
    $modules[$i]['is_cod'] = '0';

    /* 是否支持在线支付 */
    $modules[$i]['is_online'] = '1';

    /* 作者 */
    $modules[$i]['author'] = 'Bapp';

    /* 网址 */
    $modules[$i]['website'] = 'https://b.app';

    /* 版本号 */
    $modules[$i]['version'] = '1.0.0';

    /* 配置信息 */
    $modules[$i]['config'] = array(
        array('name' => 'bapp_appkey', 'type' => 'text', 'value' => ''),
        array('name' => 'bapp_appsecret', 'type' => 'text', 'value' => ''),
        array('name' => 'bapp_return', 'type' => 'text', 'value' => $GLOBALS['ecs']->url() . 'respond.php'),

    );
    return;
}

class bapp
{
    function __construct()
    {
        $this->bapp();
    }

    function bapp()
    {

    }

    function get_code($order, $payment)
    {
        $amount = (int)($order['order_amount'] * 100);
        $reqParam = [
            'order_id' => $order['log_id'],
            'amount' => $amount,
            'body' => $order['order_sn'],
            'notify_url' => return_url(basename(__FILE__, '.php')),
            'return_url' => return_url(basename(__FILE__, '.php')) . "&order_id=" . $order['log_id'] . "&body=" . $order['order_sn'],
            'extra' => '',
            'order_ip' => $this->get_user_ip(),
            'amount_type' => 'CNY',
            'time' => time() * 1000,
            'app_key' => $payment['bapp_appkey']
        ];
        $sign = $this->get_sign($payment['bapp_appsecret'], $reqParam);
        $reqParam['sign'] = $sign;
        $res = $this->http_request('https://bapi.app/api/v2/pay', 'POST', $reqParam);
        if ($res && $res['code'] == 200) {
            $payUrl = $res['data']['pay_url'];
            return '<div style="text-align:center"><a href="' . $payUrl . '" style="background-color:#F6931A;color:#ffffff;margin:5px;padding:6px 12px;text-decoration:none;border-radius:4px;font-size:18px;font-weight:bold"><img width="14px" height="14px" style="margin-right:8px" src="https://cdn.fwtqo.cn/static/img/20190613_48.png">' . $GLOBALS['_LANG']['pay_button'] . '</a></div>';
        } else if ($res) {
            return '<div style="text-align:center">' . $res['msg'] . '</div>';
        }
        return '<div style="text-align:center"><strong>Network error</strong></div>';
    }

    function respond()
    {
        $payment = get_payment('bapp');
        if ($_SERVER['REQUEST_METHOD'] != "POST") {
            $body = $_REQUEST['body'];
            $orderId = $_REQUEST['order_id'];
            $reqParam = [
                'order_id' => $orderId,
                'time' => time() * 1000,
                'app_key' => $payment['bapp_appkey']
            ];
            $sign = $this->get_sign($payment['bapp_appsecret'], $reqParam);
            $reqParam['sign'] = $sign;
            $res = $this->http_request('https://bapi.app/api/v2/order', 'GET', $reqParam);
            if ($res && $res['code'] == 200 && $res['data'] && $res['data']['order_state'] == 1 && $res['data']['body'] == $body) {
                return true;
            }
            return false;
        }

        $appSecret = $payment['bapp_appsecret'];
        $jsonStr = file_get_contents('php://input');
        $notifyData = (array)json_decode($jsonStr);
        $calcSign = $this->get_sign($appSecret, $notifyData);

        //检查签名
        if ($calcSign != $notifyData['sign']) {
            echo 'SIGN ERROR';
            exit;
        }

        //检查订单状态
        if ($notifyData['order_state'] != 1) {
            echo 'ORDER STATE ERROR';
            exit;
        }

        order_paid($notifyData['order_id']);
        echo 'SUCCESS';
        exit;
    }

    function get_user_ip()
    {
        $userIp = ($_SERVER["HTTP_VIA"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : $_SERVER["REMOTE_ADDR"];
        return ($userIp) ? $userIp : $_SERVER["REMOTE_ADDR"];
    }

    function get_sign($appSecret, $orderParam)
    {
        $signOriginStr = '';
        ksort($orderParam);
        foreach ($orderParam as $key => $value) {
            if (empty($key) || $key == 'sign') {
                continue;
            }
            $signOriginStr = "$signOriginStr$key=$value&";
        }
        return strtolower(md5($signOriginStr . "app_secret=$appSecret"));
    }

    function http_request($url, $method = 'GET', $params = [])
    {
        $curl = curl_init();
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
            $jsonStr = json_encode($params);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonStr);
        } else if ($method == 'GET') {
            $url = $url . "?" . http_build_query($params, '', '&');
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        $output = curl_exec($curl);
        if (curl_errno($curl) > 0) {
            return [];
        }
        curl_close($curl);
        $json = json_decode($output, true);
        return $json;
    }
}

?>