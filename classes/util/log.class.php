<?php
/**
 * Logging utility class
 *
 * To be used statically.
 *
 * @package framework
 * @subpackage util
 */
class util_log {

	/**
	 * Method constructor.  Sole action is to throw an exception if this object is instantiated instead
	 * of being called statically.
	 */
	public function __construct()
	{
		trigger_error('Cannot instantiate static class', E_USER_ERROR);
	}

	/**
	 * Provides a means of logging concise event messages for later processing.  The messages
	 * should be kept short and the context used should be consistent for a single source yet unique
	 * among all other contexts.
	 *
	 * @param string $context provides a keyword on which to search
	 * @param array $data is the payload to log
	 * @param int the number of layers of backtrace to skip to get to the appropriate file and line info.
	 */
	public static function write($context, $data, $backtracePassthru = 0)
	{
		$payloadArr = array();
		$backtrace = debug_backtrace();

		$ptr = (int)$backtracePassthru;
		$line = isset($backtrace[$ptr]['line']) ? $backtrace[$ptr]['line'] : 'Unknown';
		$file = isset($backtrace[$ptr]['file']) ? $backtrace[$ptr]['file'] : 'Unknown';

		// Gracefully handle string data input
		if(!is_array($data)) {
			$payloadArr = array($data);
		} else {

			// If an associative array is provided, maintain the context given with key value pairs
			foreach($data as $key => $val) {
				$key = str_replace(' ','_',$key);

				if(is_int($key)) {
					$payloadArr[] = "$val";
				} else {
					$key = str_replace("=", "\=", $key);
					$payloadArr[] = "$key=\"$val\"";
				}
			}
		}

		// Collapse the final content down to a string
		$payload = implode(' | ', $payloadArr);

		// Escape characters that might make parsing later more difficult
		$context = str_replace("'", "\'", $context);
		$payload = str_replace("'", "\'", $payload);

		// Write the output to the PHP error logger.
		// This outlet may be subject to change due to technical requirements, but the interface
		// should remain the same.
		$message = "{$_SERVER['UNIQUE_ID']} PHP Info: Context:'$context' Data:'$payload' in {$file} on line {$line} ";
		error_log($message, 0);
	}

}
