<?php

/**
 * Encryption utility class.  Standardizes basic internal encryption/decryption
 *
 * @package Framework
 */
class util_crypt {

	private $td;

	public function __construct() {
	}

	/**
	 * Initialize the encryption class.  This method expects the crypto key to be in ARGOS_HOME/etc/cryptkey
	 * The crypt key must be no longer than 24 characters or an error will be thrown during encryption/decryption.
	 */
	private function init() {
		$cache = new core_cache(CACHE_MEMORY);
		$cacheKey = __CLASS__.'//cryptkey';
		if(!$key = $cache->get($cacheKey)) {
			util_log::write(__METHOD__,'Reading cryptkey from keymaster');
			$key = file_get_contents(get_cfg_var('cryptkey'));
			if(!empty($key)) {
				$cache->set($cacheKey, $key, SEC_YEAR);
			}
		}
		if(empty($key)) {
			trigger_error('Cannot find encryption key.  Please check keymaster URL found in vhost file.', E_USER_ERROR);
		}
		$this->td = mcrypt_module_open(MCRYPT_3DES, '', MCRYPT_MODE_ECB, '');
		$size = mcrypt_get_iv_size(MCRYPT_3DES, MCRYPT_MODE_ECB);
		$iv = str_repeat('0', $size);
		$result = mcrypt_generic_init($this->td, $key, $iv);
		if($result < 0) {
			trigger_error("Error initializing mcrypt.  Got code '$result'.", E_USER_ERROR);
		}
	}

	/**
	 * Shut down the crypto
	 */
	private function deinit() {
		mcrypt_generic_deinit($this->td);
		mcrypt_module_close($this->td);
	}

	/**
	 * Encrypt a given string and return a base64 encoded result
	 * @param string $data
	 * @return string
	 */
	public function encrypt($data) {
		$this->init();
		$encrypted = mcrypt_generic($this->td, $data);
		$this->deinit();
		return base64_encode($encrypted);
	}

	/**
	 * Decrypt a given string that was produced by util_crypt::encrypt().
	 * Note that this will return binary garbage if the string was manipulated or the key is wrong.
	 *
	 * @param string $encrypted
	 * @return string
	 */
	public function decrypt($encrypted) {
		$this->init();
		$encryptedRaw = base64_decode($encrypted);
		$decrypted = rtrim(($encryptedRaw !== false ? mdecrypt_generic($this->td, $encryptedRaw) : ''), "\0");
		$this->deinit();
		return $decrypted;
	}

}
