<?php

require (__DIR__ . '/php/sms/SMSc.php');

$sms = new SMSc();

if (!$sms->checkPhone($tel)) {
  echo 'Не верный номер телефона.';
  exit;
}

if (!$sms->send($data)) {
	print_r($sms->getLastError());
} else {
  $response = $sms->getResponse();
  print_r($response);
	echo 'good';
}
