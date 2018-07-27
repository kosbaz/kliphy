<?php
	date_default_timezone_set("Asia/Tehran");
	$pluginData[flynet][type] = 'payment';
	$pluginData[flynet][name] = 'Flynet.ir (پرداخت موبایلی)';
	$pluginData[flynet][uniq] = 'flynet';
	$pluginData[flynet][description] = '';
	$pluginData[flynet][author][name] = 'فلای نت';
	$pluginData[flynet][author][url] = 'http://flynet.ir';
	$pluginData[flynet][author][email] = 'info@flynet.ir';

	$pluginData[flynet][field][config][1][title] = 'لطفا API خود را در فیلد زیر وارد نمایید ';
	$pluginData[flynet][field][config][1][name] = 'pin';


    function send_flynet($url,$api,$amount,$product_name){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_POSTFIELDS,"api=$api&amount=$amount&product_name=$product_name");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
    function get_flynet($url,$api,$trans_id,$mobile){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_POSTFIELDS,"api=$api&mobile=$mobile&trans_id=$trans_id");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
	function gateway__flynet($data)
	{
		global $config,$smarty,$db;
		$api = $data[pin];
        $amount = $data[amount];
        $redirect = $data[callback];
		$order_id		= $data[invoice_id];

	    $url = 'http://flynet.ir/webservice/gateway.php';


		$product_title		= $db->fetch("
			SELECT product_title
			FROM product,payment,card
			WHERE card.card_product = product.product_id AND card_payment_id = payment.payment_id AND payment_rand = $order_id
		");

		$product_name = substr($product_title['product_title'],0,33);



        $result = send_flynet($url,$api,$amount,$product_name);

		if ($result > 0 && is_numeric($result))
		{
			$update[payment_rand]	= $result;
			$sql = $db->queryUpdate('payment', $update, 'WHERE `payment_rand` = "'.$order_id.'" LIMIT 1;');
			$db->execute($sql);
			$data[title] = 'پرداخت فاکتور';
			$data[message] = file_get_contents('http://flynet.ir/webservice/code.php');

			$data[message] = str_replace('$redirect',$redirect,$data[message]);
			$data[message] = str_replace('$result',$result,$data[message]);

			$smarty->assign('data', $data);
			$smarty->display('message.tpl');
			exit;
		}
		else
		{
			//-- نمایش خطا
			$data[title] = 'خطای سیستم';
			$data[message] = '<font color="red">در ارتباط با درگاه flynet مشکلی به وجود آمده است. لطفا مطمئن شوید کد API خود را به درستی در قسمت مدیریت وارد کرده اید.</font> شماره خطا: '.$result.'<br /><a href="index.php" class="button">بازگشت</a>';
			$smarty->assign('data', $data);
			$smarty->display('message.tpl');
			exit;

		}
	}

	function callback__flynet($data)
	{
		global $db,$get,$smarty;
        $api = $data[pin];
        $url = 'http://flynet.ir/webservice/get-result.php';
        $trans_id = $_POST['trans_id'];
        $mobile = $_POST['mobile'];
        $result = get_flynet($url,$api,$trans_id,$mobile);

		if($result == 1)
		{
			$today = strtotime('Today');
			$sql 		= 'SELECT * FROM `payment` WHERE payment_time > '.$today.' AND `payment_rand` = "'.$trans_id.'" LIMIT 1;';
			$payment 	= $db->fetch($sql);
			if ($payment)
			{
				if ($payment[payment_status] == 1)
				{
				    $output[status] = 1;
					$output[res_num] = NULL;
                    $output[ref_num] = $trans_id;
					$output[payment_id] = $payment[payment_id];

				}
				else
				{
					$output[status]	= 0;
					$output[message]= 'چنین سفارشی تعریف نشده است.';
				}
			}
			else
			{
				$output[status]	= 0;
				$output[message]= 'اطلاعات پرداخت کامل نیست.';
			}
		}
		else
		{
			$output[status]	= 0;
			$output[message]= 'پرداخت موفقيت آميز نبود';
		}
		return $output;
	}
