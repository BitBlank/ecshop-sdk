# 加入插件文件

复制 `source/ecshop/includes/modules/payment/bapp.php` 到ecshop的相应目录中的 `source/ecshop/includes/modules/payment/`  
复制 `source/source/ecshop/languages/zh_cn/payment/bapp.php` 到ecshop的相应目录中的 `source/ecshop/languages/en_us/payment/`  
复制 `source/source/ecshop/languages/zh_tw/payment/bapp.php` 到ecshop的相应目录中的 `source/ecshop/languages/en_us/payment/`  
复制 `source/source/ecshop/languages/en_us/payment/bapp.php` 到ecshop的相应目录中的 `source/ecshop/languages/en_us/payment/`  

# 处理回调

修改 `source/ecshop/respond.php`

请在 「if (empty($pay_code))」前添加代码（参考添加代码 Start-End）

```
...

/* 添加代码 Start */
if (empty($pay_code))
{
	try {
	    $jsonStr = file_get_contents('php://input');
	    $notifyData = json_decode($jsonStr);
	    if ($notifyData['bapp_id']) {
	        $pay_code = 'bapp';
	    }
    } catch (\Exception $e) {
    }
}
/* 添加代码 End */

if (empty($pay_code))
...

````

请在 「$payment = new $pay_code();」 后添加代码（参考添加代码 Start-End）

```
$sql = "SELECT COUNT(*) FROM " . $ecs->table('payment') . " WHERE pay_code = '$pay_code' AND enabled = 1";
if ($db->getOne($sql) == 0)
{
    $msg = $_LANG['pay_disabled'];
}
else
{
    $plugin_file = dirname(__FILE__).'/includes/modules/payment/' . $pay_code . '.php';
    if (file_exists($plugin_file))
    {
        include_once($plugin_file);

        $payment = new $pay_code();

        /* 添加代码 Start */
        if ($_SERVER['REQUEST_METHOD'] == "POST" && $pay_code == 'bapp') {
		    if ($payment->respond()) {
		        echo 'SUCCESS';
		    } else {
		        echo 'FAIL';
		    }
		    exit();
		}
        /* 添加代码 End */

        $msg     = (@$payment->respond()) ? $_LANG['pay_success'] : $_LANG['pay_fail'];
    }
    else
    {
        $msg = $_LANG['pay_not_exist'];
    }
}
```

# 设置商户信息

进入「管理后台」-「系统设置」-「支付方式」-「B.app」-「安装」

填入 `App Key` 与 `App Secret` 信息，并确定保存
