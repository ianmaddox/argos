<?php
/**
 * AJAX utility class
 *
 * To be used statically.
 *
 * Provides three response types: redirect, response, and errors.  Each has its own send*() method.
 * The send() method allows combinations of these response types, but if given a redirect command,
 * a client will ignore all other input and forward the browser to the given URL.
 *
 * All calls to the send*() methods are fatal.
 *
 * The getRequest() method expects JSON data on either GET or POST, key name 'request'
 *
 * @package framework
 * @subpackage util
 */
class util_ajax {
	const METHOD_POST = 'post';

	const METHOD_GET = 'get';

	private $data = array();

	/**
	 * Method constructor.  Sole action is to throw an exception if this object is instantiated instead
	 * of being called statically.
	 */
	public function __construct() {
		trigger_error('Cannot instantiate static class', E_USER_ERROR);
	}

	/**
	 * Determine whether a given inbound request is an AJAX call.
	 *
	 * @return bool
	 */
	public static function isAjaxRequest() {
		if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
			return true;
		}
		return false;
	}

	/**
	 * Standard AJAX response method.
	 * Array safe.  This method will JSON encode the data and send it to the client.
	 *
	 * @param mixed $content
	 */
	public function setResponse($content) {
		self::send($content);
	}

	/**
	 * AJAX client redirection response.
	 *
	 * @param string $redirectUrl
	 */
	public function setRedirect($redirectUrl) {
		self::send(array(), array(), $redirectUrl);
	}

	/**
	 * AJAX error notifier.
	 * Used as an explicit channel for error data.  Localized text in array format expected.
	 *
	 * @param array $errors An array of errors
	 * @param string $type The http header type to throw down
	 * @param string $errorMsg An error message to attach to the HTTP response
	 */
	public function setError($errors, $type = null, $errorMsg = null) {
		if(!is_null($type) && !is_null($errorMsg)) {
			header('HTTP/1.1 ' . $type . ' ' . $errorMsg);
		}
		self::send(array(), $errors);
	}

	/**
	 * AJAX response handler.
	 * All AJAX responses are handled here.  If new response types need to be added, they should
	 * become part of the $data array.
	 *
	 * All calls to this method are fatal.
	 *
	 * @param array $content
	 * @param array $errors
	 * @param string $redirectUrl
	 */
	public static function send($content = array(), $errors = array(), $redirectUrl = '') {
		$content = is_array($content) ? $content : array($content);
		$errors = is_array($errors) ? $errors : array($errors);

		$data = array(
			'content' => $content,
			'errors' => $errors,
			'redirect' => $redirectUrl
		);

		if(!self::isAjaxRequest()) {
			trigger_error("Sending JSON data in response to a non-AJAX call.", E_USER_WARNING);
		}

		ob_clean();
		header('Content-Type: application/json');
		echo json_encode($data);
		flush();
		exit;
	}

}
