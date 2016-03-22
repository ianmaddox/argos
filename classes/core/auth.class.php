<?php
class core_auth {
	const TYPE_GOOGLE = 'google';
	const TYPE_GENERIC = 'generic';

	/** @var string $context The context/site of the user */
	private static $context;

	/** @var core_auth $authenticator The authenticator instance */
	private static $authenticator;

	/** @var array $data The data from the authenticator */
	private $data;

	/**
	 * If none of the factory methods are called, build the class based on
	 * the information in the session
	 *
	 * @param string $context The context/site of the user
	 * @param mixed $userID The User ID
	 * @param string $source The authenticator
	 */
	public function __construct($context, $userID = false, $source = self::TYPE_GOOGLE) {
		if(!empty($context)) {
			// Set the context
			self::$context = $context;

			// Instantiate an authentication source
			self::$authenticator = $this->getAuthInstance($source);

			$_SESSION[self::$context]['userID'] = ($userID !== false) ? $userID : $this->getUserID();
		} else {
			return false;
		}
	}

	/**
	 * Return the cookie data we have
	 *
	 * @param string $context The context/site of the user
	 */
	public static function getCookieData($context) {
		if(!empty($context) && isset($_COOKIE[$context])) {
			return json_decode($_COOKIE[$context]);
		} else {
			return false;
		}
	}

	/**
	 * Gets an instance of the vendor object
	 *
	 * @param string $source
	 * @return core_auth
	 */
	private function getAuthInstance($source) {
		if($source != self::TYPE_GOOGLE && $source != self::TYPE_GENERIC) {
			trigger_error('Invalid authentication source specified: ' . $source, E_USER_ERROR);
		}
		$class = 'core_auth_' . $source;

		return new $class;
	}

	/**
	 * Authenticate the given credentials.
	 *
	 * @param string $username The username
	 * @param string $password The user's password
	 * @return boolean
	 */
	public function authenticate($username = null, $password = null) {
		$results = self::$authenticator->authenticate(self::$context, $username, $password);

		if($results) {
			$_SESSION[self::$context]['username'] = $username;
			$_SESSION[self::$context]['token'] = self::$authenticator->get('token');

			return true;
		} else {
			$this->data['response'] = self::$authenticator->getResponseData();

			return false;
		}
	}

	/**
	 * Get the user's ID
	 *
	 * @return integer
	 */
	public function getUserID() {
		return (isset($_SESSION[self::$context]['userID'])) ? $_SESSION[self::$context]['userID'] : 0;
	}

	/**
	 * Get the username
	 *
	 * @return string
	 */
	public function getUserName() {
		return (isset($_SESSION[self::$context]['username'])) ? $_SESSION[self::$context]['username'] : false;
	}

	/**
	 * Get the context
	 *
	 * @return string $context
	 */
	public function getContext() {
		return self::$context;
	}

	/**
	 * Getter.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function get($key) {
		return isset($this->data[$key]) ? $this->data[$key] : false;
	}

	/**
	 * Log the user out.  Destroys their session
	 */
	public function logout() {
		session_destroy();
		session_start();
	}

	/** Helpers */

	/**
	 * Hash a password using a standard algo
	 * @param string The password to be hashed
	 * @return string hashed password
	 */
	private function createPassHash($pass) {
		// Hash the password salting it with the first two characters
		$hash1 = crypt($pass, substr($pass, 0, 2));

		// Hash the password again using a static salt
		$hash2 = crypt($hash1, 'ys');
		return addslashes($hash2);
	}

}
