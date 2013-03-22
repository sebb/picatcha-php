<?php

namespace Picatcha;

class Picatcha {

	const API_SERVER = "api.picatcha.com";

	/**
	 * @var string
	 */
	private $public_key;

	/**
	 * @var string
	 */
	private $private_key;

	/**
	 * @param string $public_key  A public key for Picatcha
	 * @param string $private_key Private key
	 */
	public function __construct($public_key, $private_key) {

		$this->public_key  = $public_key;
		$this->private_key = $private_key;
	}

	/**
	 * Submits an HTTP POST to a Picatcha server
	 *
	 * @param string  $host
	 *   Host to send the request
	 * @param string  $path
	 *   Path to send the request
	 * @param array   $data
	 *   Data to send with the request
	 * @param integer $port
	 *   Port to send the request (default: 80)
	 *
	 * @return array
	 *   response
	 */
	private function http_post($host, $path, $data, $port = 80) {

		$http_request = "POST $path HTTP/1.0\r\n";
		$http_request .= "Host: $host\r\n";
		$http_request .= "User-Agent: Picatcha/PHP\r\n";
		$http_request .= "Content-Length: " . strlen($data) . "\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
		$http_request .= "\r\n";
		$http_request .= $data;

		$response = '';
		$fs       = @fsockopen($host, $port, $errno, $errstr, 10);
		if (false == $fs) {
			die('Could not open socket');
		}

		fwrite($fs, $http_request);

		// 1160 is the size of one TCP-IP packet.
		while (!feof($fs)) {
			$response .= fgets($fs, 1160);
		}
		fclose($fs);
		$response = explode("\r\n\r\n", $response, 2);

		return $response;
	}

	/**
	 * Gets the challenge HTML (javascript and non-javascript version).
	 *
	 * This is called from the browser, and the resulting Picatcha HTML widget
	 * is embedded within the HTML form it was called from.
	 *
	 * @param string $error The error given by Picatcha (default: null)
	 * @param string $format
	 * @param string $style
	 * @param string $link
	 * @param string $IMG_SIZE
	 * @param int    $NOISE_LEVEL
	 * @param int    $NOISE_TYPE
	 * @param string $lang
	 * @param string $langOverride
	 * @param bool   $use_ssl
	 * @throws \Exception
	 * @return string The HTML to be embedded in the user's form
	 */
	public function get_html($error = null, $format = '2', $style = '#2a1f19', $link = '1', $IMG_SIZE = '75', $NOISE_LEVEL = 0, $NOISE_TYPE = 0, $lang = 'en', $langOverride = '0', $use_ssl = false) {

		if (empty($this->public_key)) {

			throw new \Exception("To use Picatcha you must get an API key from http://picatcha.com");
		}

		if ($use_ssl) {
			$api_server = "https://" . self::API_SERVER;
		} else {
			$api_server = "http://" . self::API_SERVER;
		}

		$elm_id        = 'picatcha';
		$customization = json_encode(array(
										 'format'       => (string) $format,
										 'color'        => (string) $style,
										 'link'         => (string) $link,
										 'image_size'   => (string) $IMG_SIZE,
										 'lang'         => (string) $lang,
										 'langOverride' => (string) $langOverride,
										 'noise_level'  => (string) $NOISE_LEVEL,
										 'noise_type'   => (string) $NOISE_TYPE,
									 ));

		$html = "
<script type=\"text/javascript\" src=\"{$api_server}/static/client/picatcha.js\"></script>
<link href=\"{$api_server}/static/client/picatcha.css\" rel=\"stylesheet\" type=\"text/css\">

<script type=\"text/javascript\">
	Picatcha.PUBLIC_KEY = '{$this->public_key}';
	Picatcha.setCustomization({$customization});
	jQuery(window).load(function(){ Picatcha.create('{$elm_id}', {}); });
</script>";

		if (!empty($error)) {
			$html .= "<div id=\"{$elm_id}_error\">{$error}</div>";
		}
		$html .= "<div id=\"{$elm_id}\"></div>";

		return $html;
	}

	/**
	 * Calls an HTTP POST function to verify if the user's choices were correct
	 *
	 * @param string $remoteip     Remote IP
	 * @param string $user_agent   User agent
	 * @param string $challenge    Challenge token
	 * @param array  $response     Response
	 * @param int    $timeout
	 * @param array  $extra_params Extra variables to post to the server
	 * @return \Picatcha\PicatchaResponse
	 * @throws \Exception
	 */
	public function check_answer($remoteip, $user_agent, $challenge, $response, $timeout = 90, $extra_params = array()) {

		if (empty($this->private_key)) {

			throw new \Exception("To use Picatcha you must get an API key from http://picatcha.com");
		}

		if (empty($remoteip)) {

			throw new \Exception("For security reasons, you must pass the remote ip to Picatcha");
		}

		if (empty($user_agent)) {

			throw new \Exception("You must pass the user agent to Picatcha");
		}

		// Discard spam submissions.
		if (empty($challenge) || empty($response)) {

			return new PicatchaResponse(false, 'incorrect-answer');
		}

		$params = array(
			'k'  => $this->private_key,
			'ip' => $remoteip,
			'ua' => $user_agent,
			't'  => $challenge,
			'r'  => $response,
			'to' => $timeout,
		) + $extra_params;

		$data     = json_encode($params);
		$response = $this->http_post(self::API_SERVER, "/v", $data);
		$res      = json_decode($response[1], false);

		$picatcha_response = new PicatchaResponse(false);
		if ($res->s) {
			$picatcha_response->setIsValid(true);
		} else {
			$picatcha_response->setError($res->e);
		}

		return $picatcha_response;
	}

	/**
	 * Gets a URL where the user can sign up for Picatcha.
	 *
	 * If your application has a configuration page where you enter a key,
	 * you should provide a link using this function.
	 *
	 * @param string $domain
	 *   The domain where the page is hosted
	 * @param string $appname
	 *   The name of your application
	 */
	public function get_signup_url($domain = null, $appname = null) {

		return "http://picatcha.com/";
	}
}
