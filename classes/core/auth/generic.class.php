<?php
class core_auth_generic implements interface_auth {

	public function __construct() {

	}

	/**
	 * Authenticate the user against Google's server
	 *
	 * @param string $context Name of the calling application
	 * @param string $userID Google email account
	 * @param string $password Password for Google email account
	 * @return boolean
	 */
	public function authenticate($context, $userID = null, $password = null) {

	}
}