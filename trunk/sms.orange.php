<?php
/*
 * Klasa: orange v0.2.1 beta
 *
 * (c) <2009-2014> Written by: Krzysztof Tomasz Zembrowski
 * MIT License: http://www.opensource.org/licenses/mit-license.php
 *
 * Part of `phpsms-pl`
 * http://code.google.com/p/phpsms-pl/
 *
 * Pozwala zarejestrowanym użytkownikom orange.pl wysyłać
 * wiadomości SMS do wszystkich sieci przy użyciu bramki MultiBox
 *
 * Wersja bez COOKIE_JAR. Nie tworzy plików.
 *
 * Wymagania:
 * + konto użytkownika orange.pl
 * + PHP5, cURL, Xpaths
 *
 * To do:
 * - compare left SMS before and after
 * ? login request result as token (impossible due to false redirect)
 */
 
$login = 'login'; // nazwa użytkownika orange.pl
$password = 'password'; // hasło do orange.pl
$number = '500000000'; // numer odbiorcy
$content = 'Hej, to działa. Dzięki!'; // treść wiadomości

// I said: DO IT! Ok, you can try.
try {
	$o = new orange();
	if ( $o -> login ($login, $password) ) echo $o -> send ($number, $content);
} catch (Exception $e) {
	echo '[ERROR] ' . $e -> getMessage();
}

//Advanced setup
/*try {
	$o = new Orange();
	$o -> Debug = true;
	// Useful for debugging
	//error_reporting(E_ALL);
	//ini_set('display_errors', 1);
	//ini_set('date.timezone', 'Europe/Warsaw');
	$o -> time('start');
	if ($o -> login ($login, $password)) echo $o -> send ($number, $content);
	# Dziel dłuższe wiadomości
	//if ($o -> login ($login, $password)) echo $o -> split ($number, $content);
	echo $o -> time('end');
} catch (Exception $e) {
	echo '[ERROR] ' . $e -> getMessage();
}*/

class Orange
{
	/**
	 * Przechowuje identyfikatory i informacje
	 *
	 * @var $_curl
	 * @var $_cookie
	 * @var $_before - EXPERIMENTAL
	 * @var[array] $_time
	 */
	protected $_curl;
	protected $_cookie;
	protected $_before;
	protected $_time = array();

	/**
	 * Istotne ustawienia
	 *
	 * @var string $useragent - "Browser" User Agent w przesłanym nagłówku
	 * @var string $*url - odnośniki
	 * @var string $length - maksymalna długość wiadomości, jeśli większa - dzielona na części o podanej długości
	 */
	private $useragent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/537.75.14';
	private $loginformurl = 'https://www.orange.pl/zaloguj.phtml';
	private $loginposturl = '?_DARGS=/ocp/gear/infoportal/portlets/login/login-box.jsp';
	private $smsformurl = 'https://www.orange.pl/portal/map/map/message_box?mbox_edit=new&mbox_view=newsms';
	private $smsposturl = 'https://www.orange.pl/portal/map/map/message_box?_DARGS=/gear/mapmessagebox/smsform.jsp';
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
			CURLOPT_HEADER => true,
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
		
		$error = curl_error($this->_curl);
		
		if (!empty($error) && $this->Debug) throw new Exception('[cURL] ' . $error . '; URL: ' . $url .'; POST ' . var_dump($post));

		if (!empty($result)) $this->cookies($result);

		return $result;

		curl_close($this->_curl);
	}

	/**
	 * Przetwarza ciasteczka
	 *
	 * @param string $content - zawartość strony
	 */
	private function cookies ($content)
	{
		preg_match_all('/^Set-Cookie:\s+(.*);/mU', $content, $match);
		if (empty($this->_cookie)) {
			$this->_cookie = implode(';', array_unique($match[1]));
		} else {
			$old = explode(";",$this->_cookie);
			$new = array_unique($match[1]);
			$cookies = array_merge($old, $new);
			$this->_cookie = implode(';', array_unique($cookies));
		}
	}

	/**
	 * Autentyfikacja w orange.pl
	 *
	 * @param string $login - nazwa użytkownika
	 * @param string $password - hasło
	 */
	public function login ($login, $password)
	{
		// Vist the orange ones to get cookies from them
		$visit = $this->curl($this->loginformurl);
		
		$data = array(
		'_dyncharset' => 'UTF-8',
        '_dynSessConf' => '-2354258262419359049',
        '/tp/core/profile/login/ProfileLoginFormHandler.loginErrorURL' => $this->smsformurl,
        '_D:/tp/core/profile/login/ProfileLoginFormHandler.loginErrorURL' => '',
        '/tp/core/profile/login/ProfileLoginFormHandler.loginSuccessURL' => '',
        '_D:/tp/core/profile/login/ProfileLoginFormHandler.loginSuccessURL' => '',
        '/tp/core/profile/login/ProfileLoginFormHandler.firstEnter' => true,
        '_D:/tp/core/profile/login/ProfileLoginFormHandler.firstEnter' => '',
        '/tp/core/profile/login/ProfileLoginFormHandler.value.login' => $login,
        '_D:/tp/core/profile/login/ProfileLoginFormHandler.value.login' => '',
        '/tp/core/profile/login/ProfileLoginFormHandler.value.password' => $password,
        '_D:/tp/core/profile/login/ProfileLoginFormHandler.value.password' => '',
        '/tp/core/profile/login/ProfileLoginFormHandler.rememberMe' => true,
        '_D:/tp/core/profile/login/ProfileLoginFormHandler.rememberMe' => '',
        '/tp/core/profile/login/ProfileLoginFormHandler.login.x' => rand(0,60),
        '/tp/core/profile/login/ProfileLoginFormHandler.login.y' => rand(0,30),
        '_D:/tp/core/profile/login/ProfileLoginFormHandler.login' => '',
        '_DARGS' => '/ocp/gear/infoportal/portlets/login/login-box.jsp'
		);

		curl_setopt($this->_curl, CURLOPT_REFERER, $this->loginformurl);

		$sent = $this->curl($this->loginformurl . $this->loginposturl, $data);

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
		'_dynSessConf' => '3180938745375173535',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.type' => 'sms',
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.type' => '',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.errorURL' => '/portal/map/map/message_box?mbox_view=newsms',
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.errorURL' => '',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.successURL' => '/portal/map/map/message_box?mbox_view=sentmessageslist',
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.successURL' =>'',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.to' => $to,
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.to' => '',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.body' => $body,
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.body' => '',
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.token' => $this->token(),
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.token' => '',
		'enabled' => true,
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.create.x' => rand(0,50),
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.create.y' => rand(0,25),
		'/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.create' => 'Wyślij',
		'_D:/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.create' => '',
		'_DARGS' => '/gear/mapmessagebox/smsform.jsp'
		);

		curl_setopt($this->_curl, CURLOPT_REFERER, $this->smsformurl);

		$sent = $this->curl($this->smsposturl, $data);

		if ( $this->check($sent) && $summary) {
			$result = $this->left($sent);
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
		$content = $this->curl($this->smsformurl);
		$doc = new DOMDocument();
		@$doc->loadHTML($content);
		$xpath = new DOMXPath($doc);
		$token = $xpath->evaluate('//input[@name="/amg/ptk/map/messagebox/formhandlers/MessageFormHandler.token"]')->item(0)->getAttribute('value');
		
		//$this->left($content,'before');
		
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
	 * @param string $type - EXPERIMENTAL - rodzaj sprawdzenia: przed, czy po
	 * @return string - tekst
	 */
	private function left ($content, $type = 'after')
	{
		$doc = new DOMDocument();
		@$doc->loadHTML($content);
		$xpath = new DOMXPath($doc);
		$left = $xpath->evaluate('//div[@id="syndication"]//p[@class="item"]/span[@class="value"]')->item(0)->nodeValue;
		
		return $this->result('Wiadomość została wysłana prawidłowo. W tym miesiącu pozostało do wykorzystania: '.$left.' wiadomości SMS.');
		
		# Experimental / NOT WORKING
		/*if ($type == 'before') $this->_before = $left;
		else {
			# Compare _before and after
			if ($left = $this->_before) return $this->result('Liczba dostępnych wiadomości ('.$left.') nie uległa zmianie. Oznacza to, że wystąpił błąd podczas wysyłania formularza z wiadomością.');
			# Sent!
			if ($left < $this->_before) return $this->result('Wiadomość została wysłana prawidłowo. W tym miesiącu pozostało do wykorzystania: '.$left.' wiadomości SMS.');
		}*/
		
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