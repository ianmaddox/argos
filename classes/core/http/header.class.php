<?php

/**
 * The http header class provides a clean interface for accessing http header
 * properties during requests. Also provided is a method for injecting new
 * header values in order to unit test and override default behavior if desired.
 *
 * @package framework
 * @subpackage http
 */

class core_http_header {

	/** @var array $request Request header properties */
	private $request = array();

	/** @var array $response Response header properties */
	private $response = array();

	public function __construct() {

		$this->request = getallheaders();

		// Default response headers
		$this->response = array(
			'Content-Type' => 'text/html; charset=UTF-8',
			'Cache-Control' => 'private, max-age=0',
			'Pragma' => 'no-cache',
			'Expires' => '-1',
			'Last-Modified' => gmdate("D, d M Y H:i:s")
		);
	}

	/**
	 * Outputs response headers.
	 *
	 * @return void
	 */
	public function send() {
		foreach($this->response as $headerKey => $headerValue) {
			header($headerKey.": ".$headerValue);
		}
	}
}