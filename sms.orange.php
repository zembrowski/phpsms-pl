<?php
/*
 * Copyright (c) <2009-2010> Krzysztof Zembrowski <krzysztof@zembrowski.pl>
 * Klasa: orange v0.1 beta
 *
 * Pozwala zarejestrowanym użytkownikom orange.pl wysyłać
 * wiadomości SMS do wszystkich sieci przy użyciu bramki mBox
 *
 * Wersja bez COOKIE_JAR. Nie tworzy żadnych plików
 *
 * Wymagania:
 * - konto użytkownika orange.pl
 * - PHP5, cURL, Xpaths
 *
 * To do:
 * - error handling
 * - speed up!
 */
 
$login = 'login'; // nazwa użytkownika orange.pl
$password = 'password'; // hasło do orange.pl
$number = '500000000'; // numer odbiorcy
$content = 'Hej, to dziala! Dzieki.'; // treść wiadomości

// I said: DO IT! Ok, you can try.
try {
	$o = new orange();
	if ( $o -> login ($login, $password) ) echo $o -> send ($number, $content);
} catch (Exception $e) {
	echo '[ERROR] ' . $e -> getMessage();
}

//Advanced setup
/*try {
	$o = new orange();
	$o -> Debug = true;
	$o -> time('start');
	if ($o -> login ($login, $password)) echo $o -> split ($number, $content);
	echo $o -> time('end');
} catch (Exception $e) {
	echo '[ERROR] ' . $e -> getMessage();
}*/

class orange
{
	/**
	 * Przechowuje identyfikatory i informacje
	 *
	 * @var $_curl
	 * @var $_cookie
	 * @var[array] $_time
	 */
	protected $_curl;
	protected $_cookie;
	protected $_time = array();

	/**
	 * Istotne ustawienia
	 *
	 * @var string $useragent - "Browser" User Agent w przesłanym nagłówku
	 * @var string $*url - odnośniki
	 * @var string $length - maksymalna długość wiadomości, jeśli większa - dzielona na części o podanej długości
	 */
	private $useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.0; pl; rv:1.9.2.8) Gecko/20090722 Firefox/3.6.8';
	private $loginurl = 'http://www.orange.pl/portal/map/map/signin';
	private $smsurl = 'http://www.orange.pl/portal/map/map/message_box?mbox_view=newsms';
	private $actionurl = 'http://www.orange.pl/portal/map/map/message_box?_DARGS=/gear/mapmessagebox/smsform.jsp';
	private $length = '640';
	
	/**
	 * Debug (default: Off)
	 * 
	 * @var boolean $Debug - verbose mode
	 */
	 
	public $Debug = false;

	/**
	 * Ułatwienie dostępu do curl
	 *
	 * @param string $url - adres strony
	 * @param array $post - opcjonalnie tablica parametrów
	 * @return string - zwraca pobraną treść
	 */
	private function curl ($url, $post = NULL)
	{
		$this->_curl = curl_init();

		$var = array(
			CURLOPT_URL => $url,
			CURLOPT_HEADER => (empty($this->_cookie))?true:false,
			CURLOPT_USERAGENT => $this->useragent,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false);
			
		if (!empty($this->_cookie)) $var[CURLOPT_COOKIE] = $this->_cookie;

		if ( !is_null($post) ) {
		$tmp = '';
			foreach ($post as $option => $value) {
				$tmp .= $option. '=' .urlencode($value). '&';
			}

			$var[CURLOPT_POST] = true;
			$var[CURLOPT_POSTFIELDS] = $tmp;
		}

		curl_setopt_array($this->_curl, $var);

		$result = curl_exec($this->_curl);
		
		if (empty($this->_cookie)) {
			preg_match_all('/^Set-Cookie:\s+(.*);/mU', $result, $match);
			$this->_cookie = implode(';', array_unique($match[1]));
		}

		return $result;

		curl_close($this->_curl);
	}

	/**
	 * Autentyfikacja w orange.pl
	 *
	 * @param string $login - nazwa użytkownika
	 * @param string $password - hasło
	 */
	public function login ($login, $password)
	{
		$data = array(
		'_dyncharset' => 'UTF-8',
		'/amg/ptk/map/core/formhandlers/AdvancedProfileFormHandler.loginErrorURL' => $this->loginurl,
		'_D:/amg/ptk/map/core/formhandlers/AdvancedProfileFormHandler.loginErrorURL=' => '',
		'/amg/ptk/map/core/formhandlers/AdvancedProfileFormHandler.loginSuccessURL' => 'http://www.orange.pl/portal/map/map/pim',
		'_D:/amg/ptk/map/core/formhandlers/AdvancedProfileFormHandler.loginSuccessURL' => '',
		'/amg/ptk/map/core/formhandlers/AdvancedProfileFormHandler.value.login' => $login,
		'_D:/amg/ptk/map/core/formhandlers/AdvancedProfileFormHandler.value.login' => '',
		'/amg/ptk/map/core/formhandlers/AdvancedProfileFormHandler.value.password' => $password,
		'_D:/amg/ptk/map/core/formhandlers/AdvancedProfileFormHandler.value.password' => '',
		'/amg/ptk/map/core/formhandlers/AdvancedProfileFormHandler.login.x' => rand(0,50),
		'/amg/ptk/map/core/formhandlers/AdvancedProfileFormHandler.login.y' => rand(0,25),
		'_D:/amg/ptk/map/core/formhandlers/AdvancedProfileFormHandler.login' => '',
		'_DARGS' => '/gear/static/signInLoginBox.jsp'
		);

		$sent = $this->curl($this->loginurl, $data);

		return $this->check($sent);
	}

	/**
	 * Wysyła wiadomość sms po sprawdzeniu jej długości
	 *
	 * @param int $to - numer odbiorcy
	 * @param string $body - treść wiadomości
	 * @return string - wiadomość z wynikiem; w razie błędu zawartość HTML strony wynikowej do przeanalizowania
	 */
	public function send ($to, $body, $summary = true)
	{
		if (strlen($body) <= 0 || strlen($body) > 640) { throw new Exception('Wiadomość musi być dłuższa niż 0 znaków, natomiast krótsza niż 640 znaków');}

		$data = array(
		'_dyncharset' => 'UTF-8',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.type' => 'sms',
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.type' => '',
		'enabled' => 'true',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.token' => $this->token(),
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.token' => '',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.errorURL' => '/portal/map/map/message_box?mbox_view=newsms',
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.errorURL' => '',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.successURL' => '/portal/map/map/message_box?mbox_view=messageslist',
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.successURL' =>'',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.to' => $to,
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.to' => '',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.body' => $body,
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.body' => '',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.create' => 'Wyślij',
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.create' => '',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.create.x' => rand(0,50),
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.create.y' => rand(0,25),
		'_DARGS' => '/gear/mapmessagebox/smsform.jsp'
		);

		curl_setopt($this->_curl, CURLOPT_REFERER, $this->smsurl);

		$sent = $this->curl($this->actionurl, $data);

		if ( $this->check($sent) && $summary) {
			$result = $this->result('Wiadomość została wysłana prawidłowo.');
			$result .= $this->left($sent);
			return $result;
		} else {
			if ($this->Debug) return $sent;
		}
	}
	
	/**
	 * Dzieli długą wiadomość SMS na części i wysyła
	 *
	 * @param int $to - numer odbiorcy
	 * @param string $body - treść wiadomości
	 * @return string - rezulatat
	 */
	public function split ($to, $body)
	{
		$result = '';
		
		$m = str_split($body, $this->length);
		
		for ($n=0; $n<count($m); $n++) {
			$sent = $this->send($to,$m[$n],false);
			$result .= $this->result('Wysłano wiadomość '.($n+1).' z '.count($m));
			flush();
		}
		
		$result .= $this->left($sent);
		
		return $result;
	}
	
	/**
	 * Pobiera token z formularza
	 *
	 * @return string - zwraca token
	 */
	private function token ()
	{
		$doc = new DOMDocument();
		@$doc->loadHTML($this->curl($this->smsurl));
		$xpath = new DOMXPath($doc);
		$token = $xpath->evaluate('//input[@name="/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.token"]')->item(0)->getAttribute('value');

		return $token;
	}

	/**
	 * Funkcja sprawdzająca czy nie ma błędów
	 *
	 * @param string $content - strona wynikowa
	 * @return boolean - jeśli błąd false
	 */
	private function check ($content)
	{
		$doc = new DOMDocument();
		@$doc->loadHTML($content);
		$xpath = new DOMXPath($doc);
		$error = $xpath->evaluate('//div[@class="box-error"]/p');
		if ($error->length > 0 && !preg_match('/cookie/',$error->item(0)->nodeValue)) { //any other idea how to fix cookie error?
			for ($n=0; $n < $error->length; $n++) { throw new Exception($error->item($n)->nodeValue); }
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Sprawdza liczbę wiadomości SMS do wykorzystania w obecnym miesiącu
	 *
	 * @param string $content - treść HTML do przeanalizowania
	 * @return string - tekst
	 */
	private function left ($content)
	{
		$doc = new DOMDocument();
		@$doc->loadHTML($content);
		$xpath = new DOMXPath($doc);
		$left = $xpath->evaluate('//div[@id="syndication"]//p[@class="item"]/span[@class="value"]')->item(0)->nodeValue;
		$result = $this->result('W tym miesiącu pozostało do wykorzystania: '.$left.' wiadomości SMS.');
		return $result;
	}

	/**
	 * Funkcja opracowująca wynik tekstowy
	 * (experimental)
	 *
	 * @param string $content - treść
	 * @return string - tekst
	 */
	private function result ($content)
	{
		$result = $content." \n";
		
		return $result;
	}
	
	/**
	 * Funkcja obliczająca czas wykonywania skryptu
	 * (experimental)
	 * 
	 * @param string $type [start/end]
	 * @param boolean $result - czy zwrócić czas wykonywania
	 * @return string - czas wykonywania
	 */
	 public function time ($type, $result = false)
	 {
	 	$this->_time[$type] = microtime(true);
	 	if ($type == 'end' && ($result || $this->Debug)) return $this->_time['end']-$this->_time['start'];
	 }
}