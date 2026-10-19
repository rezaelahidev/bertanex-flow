<?php

/**
 * Melipayamak REST reference (Payamak-Panel). Production code: {@see \BertanexFlow\Sms\MelipayamakSmsTransport}.
 *
 * @see https://rest.payamak-panel.com/api/SendSMS/SendSMS
 */

// GitHub نمونه کدهای
$username = 'username';
$password = 'password';
$api = new MelipayamakApi($username,$password);
$sms = $api->sms();
$to = '09123456789';
$from = '5000...';
$text = 'تست وب سرویس ملی پیامک';
$response = $sms->send($to,$from,$text);

//بدون نیاز به پکیج گیت هاب Procedural PHP نمونه کدهای 
$data=array('username' =>"", 'password'=>"", 'to' =>"0912...,0912...", 'from' => "", "text" =>"تست");
$post_data = http_build_query($data);
$handle = curl_init('https://rest.payamak-panel.com/api/SendSMS/SendSMS');
curl_setopt($handle, CURLOPT_HTTPHEADER, array(
    'content-type' => 'application/x-www-form-urlencoded'
));
curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($handle, CURLOPT_POST, true);
curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
$response = curl_exec($handle);
var_dump($response);





$melipayamak = new MelipayamakSmsConfig();
$sms = new MelipayamakSmsTransport($melipayamak);

$sms->deliver('09104457956', 'code=199 کد تایید: 889 عمدباکس: معرفی روزانه یکصد محصول عمده جدید', []);

//بدون نیاز به پکیج گیت هاب Procedural PHP نمونه کدهای 
// ini_set("soap.wsdl_cache_enabled", "0");
// $client = new \SoapClient("https://api.payamak-panel.com/post/SharedService.asmx?wsdl",array("encoding"=>"UTF-8"));
// $data = array(
// 	"username"=>"omdbox",
//     "password"=>"09f364f7-6f06-4da5-ac5f-c228c2fd0ec2",
	
// );
// $Result= $client->GetSharedServiceBody($data)->GetSharedServiceBodyResult;
// var_dump($Result);
// exit;
// ini_set("soap.wsdl_cache_enabled","0");
// $sms = new \SoapClient("http://api.payamak-panel.com/post/Send.asmx?wsdl",array("encoding"=>"UTF-8"));
// $data = array(
//     "username"=>"omdbox",
//     "password"=>"09f364f7-6f06-4da5-ac5f-c228c2fd0ec2",
//     "text"=>'code=199 کد تایید: 889 عمدباکس: معرفی روزانه یکصد محصول عمده جدید',
//     "to"=>"09104457956",
//     "bodyId"=> 418445);
// $send_Result = $sms->SendByBaseNumber($data)->SendByBaseNumberResult;
// echo $send_Result;