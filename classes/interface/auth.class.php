<?php
/**
 *
 * @package framework
 * @subpackage interface
 */
interface interface_auth {
	public function __construct();

	/**
	 * Authenticate the user against Google's server
	 *
	 * @param string $context Name of the calling application
	 * @param string $userID Google email account
	 * @param string $password Password for Google email account
	 * @return boolean
	 */
	public function authenticate($context, $userID = null, $password = null);

	/**
	 * Get a value from the results
	 *
	 * @param string $key
	 * @return string
	 */
	public function get($key);

	/**
	 * Get all values from the results
	 *
	 * @return array
	 */
	public function getResponseData();
}