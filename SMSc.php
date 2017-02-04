<?php
class SMSc {
	
	private $config; //array
	
	private $lastError; // array('error' => '', 'errno' => '');
	
	private $response; //array
	
	public function __construct(array $config = array()) {
		if (!$config) {
			$this->config = include(__DIR__ . '/config.php');
		} else {
			$this->config = $config;
		}
	}
	
	public function checkPhone($number) {
		return preg_match('/^\+\d{10,}$/', $number);
	}
	
	public function send(array $message) {
		
		if ($this->config['charset_in'] != $this->config['charset_out']) {
			$message['text'] = iconv($this->config['charset_in'], $this->config['charset_out'], $message['text']);
			if ($message['text'] === false) {
				$this->error(-1);
				return false;
			}
		}
		
		
		if (is_array($message['to'])) {
			$true_numbers = array_filter($message['to'], array($this, 'checkPhone'));

			$false_numbers = array_diff($message['to'], $true_numbers);
			
			if ($false_numbers) {
				$this->lastError = array(	 
					'error' => 'Неправильные форматы номеров: ' . htmlspecialchars(implode(', ', $false_numbers)),
					'errno' => 7
				);
				return false;
			} else {
				$message['to'] = implode(',', $message['to']);
			}	
		} else {
			if (!$this->checkPhone($message['to'])) {
				$this->error(7);
				return false;
			}
		}
		
		$data = array(
			'login'		=> $this->config['login'],
			'psw'		=> $this->config['password'],
			'phones'	=> $message['to'],
			'mes'		=> $message['text'],
			'charset'	=> $this->config['charset_out'],
			'cost'		=> 3, // запрос баланса
			'fmt'		=> 3 //для получения ответа в формате json 	
		);
		
		
		$ch = curl_init($this->config['url_service'] . 'sys/send.php');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		
		$response = curl_exec($ch);
		curl_close($ch);
		
		if ($response === false) {
			$this->error(-3);
			return false;
		}
		
		
		/* $response 
		
		// if all good
			{
				"id": <id>,
				"cnt": <n>
			}
			
		// if error 
			{
				"error": "описание",
				"error_code": N
			}
		*/
		
		
		$data_response = json_decode ($response, true); //array
		
		if (json_last_error() != JSON_ERROR_NONE) {
			$this->error(-4);
			return false;
		}
		
		
		
		if (array_key_exists('error_code', $data_response)) {
			$this->error($data_response['error_code']);
			return false;
		}
		
		
		$this->response = $data_response;
		
		return true;
	}
	
	private function error($code) {
		
		$erros = array(
			-4 => 'Ошибка в json данных.',
			-3 => 'Не удалось выполнить запрос к серверу.',
			-2 => 'При отправке SMS произошла ошибка, попробуйте повторить операцию позже.',
			-1 => 'Не удалось перекодировать строку.',
			0 => 'Не известная ошибка.',
			1 => 'Ошибка в параметрах.',
			2 => 'Неверный логин или пароль.',
			3 => 'Недостаточно средств на счете Клиента.',
			4 => 'IP-адрес временно заблокирован из-за частых ошибок в запросах.',
			5 => 'Неверный формат даты.',
			6 => 'Сообщение запрещено (по тексту или по имени отправителя).',
			7 => 'Неверный формат номера телефона.',
			8 => 'Сообщение на указанный номер не может быть доставлено.',
			9 => 'Отправка более одного одинакового запроса на передачу SMS-сообщения либо более пяти одинаковых запросов на получение стоимости сообщения в течение минуты.'
		);
		
		if (!array_key_exists($code, $erros)) {
			$code = 0;
		}
		
		if (!in_array($code, array(7,8))) {
			file_put_contents(__DIR__ . '/log.txt', $code . ' ' . $erros[$code] . "\r\n", FILE_APPEND);
			
			$code = -2;
		}
		
		$this->lastError = array('error' => $erros[$code], 'errno' => $code);
	}
	
	public function getResponse() {
		if (isset($this->response)) {
			return $this->response;
		} else {
			return false;
		}
	}
	
	
	
	
	public function getLastError() {
		if (isset($this->lastError)) {
			return $this->lastError;
		} else {
			return false;
		}
	}
}
