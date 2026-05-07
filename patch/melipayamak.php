<?php

/**
 * Melipayamak REST reference (Payamak-Panel). Production code: {@see \GrowthOrchestrator\Sms\MelipayamakSmsTransport}.
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
