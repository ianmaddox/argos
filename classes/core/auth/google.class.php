<?php
/**
 * Gets an authentication token for a Google service. Puts the token in a
 * session variable and re-uses it as needed, instead of fetching a new token
 * for every call.
 */
class core_auth_google implements interface_auth {
	/** @var array $results The results of the authenticate attempt */
	private $data = array();

	/** @var array $errors The array of human readable Google errors */
	private $errors = array(
		"Unknown" => "The error is unknown or unspecified; the request contained invalid input or was malformed.",
		"NotVerified" => "The account email address has not been verified. The user will need to access their Google account directly to resolve the issue before logging in using a non-Google application.",
		"TermsNotAgreed" => "The user has not agreed to terms. The user will need to access their Google account directly to resolve the issue before logging in using a non-Google application.",
		"AccountDeleted" => "The user account has been deleted.",
		"CaptchaRequired" => "A CAPTCHA is required.",
		"AccountDisabled" => "The user account has been disabled.",
		"ServiceDisabled" => "The user's access to the specified service has been disabled. (The user account may still be valid.)",
		"BadAuthentication" => "The login request used a username or password that is not recognized.",
		"ServiceUnavailable" => "The service is not available; try again later.");

	public function __construct() {

	}

	/**
	 * Authenticate the user against Google's server
	 *
	 * @param string $context Name of the calling application
	 * @param string $username Google email account
	 * @param string $password Password for Google email account
	 * @return boolean
	 */
	public function authenticate($context, $username = null, $password = null) {
		if(!is_null($username) && !is_null($password)) {
			$fields = "accountType=" . urlencode('HOSTED_OR_GOOGLE')
				. "&Email=" . urlencode($username)
				. "&Passwd=" . urlencode($password)
				. "&source=" . urlencode($context)
				. "&service=" . urlencode('cl');

			// Get an authorization token
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "https://www.google.com/accounts/ClientLogin");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, TRUE);

			$response = curl_exec($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);

			// Find the return values
			preg_match_all('/(?P<key>\w+)=(?P<value>.*)\n*/im',$response , $result, PREG_SET_ORDER);
			foreach($result as $return) {
				$value = ($return['key'] == 'Error') ? $this->errors[$return['value']] : $return['value'];
				$key = ($return['key'] == 'Auth') ? 'token' : strtolower($return['key']);

				$this->data[$key] = $value;
			}

			if($info['http_code'] != '200') {
				return false;
			} else {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get a value from the results
	 *
	 * @param string $key
	 * @return string
	 */
	public function get($key) {
		return isset($this->data[$key]) ? $this->data[$key] : false;
	}

	/**
	 * Get all values from the results
	 *
	 * @return array
	 */
	public function getResponseData() {
		return $this->data;
	}
}