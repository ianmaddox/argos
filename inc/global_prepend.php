<?php

/**
 * This file bootstraps all PHP calls for every PHP site that uses the framework.
 * It should only contain code that is required for page loads across all sites.
 * This script is responsible for attempting to load the local_prepend script
 * if one is found.
 *
 * @package framework
 * @subpackage inc
 */
// Define the allmighty ARGOS_HOME.  This must be done before all other defines and calls.
define('ARGOS_HOME', !empty($_SERVER['ARGOS_HOME']) ? $_SERVER['ARGOS_HOME'] : (!empty($_ENV['ARGOS_HOME']) ? $_ENV['ARGOS_HOME'] : false));
set_error_handler('errorHandler');

// The autoloaders must be initialized before any classes are referenced
spl_autoload_register('argosAutoload', false, true);
require_once 'AWSSDKforPHP/sdk.class.php';
spl_autoload_register(array('CFLoader', 'autoloader'));

define('DIR', DIRECTORY_SEPARATOR);

/**
 * Cache engine constants
 */
define('CACHE_MEMORY', core_cache::ENGINE_APC);
define('CACHE_DB', core_cache::ENGINE_DB);
define('CACHE_NETWORK', core_cache::ENGINE_MEMCACHED);

define('CACHE_DEFAULT', CACHE_NETWORK);
define('CACHE_NONE', core_cache::ENGINE_NONE);

/**
 * Time constants to make seconds-based time math easier to read and maintain.
 */
define('SEC_SECOND', 1);
define('SEC_MINUTE', SEC_SECOND * 60);
define('SEC_HOUR', SEC_MINUTE * 60);
define('SEC_DAY', SEC_HOUR * 24);
define('SEC_WEEK', SEC_DAY * 7);
define('SEC_MONTH', SEC_DAY * 30);
define('SEC_YEAR', SEC_DAY * 365);
define('SEC_LEAPYEAR', SEC_DAY * 366);

/**
 * Payment constants to ease references to vendors
 */
define('PAYMENT_PAYPAL', ecomm_vendor::VENDOR_PAYPAL);
define('PAYMENT_CC', ecomm_vendor::VENDOR_CC_AUTHORIZENET);
define('PAYMENT_CC_RECUR', ecomm_vendor::VENDOR_CC_AUTHORIZENET);

/**
 * Credit card name constants
 */
define('CARD_VISA', 'Visa');
define('CARD_AMEX', 'Amex');
define('CARD_DISCOVER', 'Discover');
define('CARD_MASTERCARD', 'MasterCard');

$site = false;

/**
 * Check if we are in CLI mode, for scripts etc.
 */
if(util_cli::isCliMode()) {
	$_SERVER['UNIQUE_ID'] = uniqid();
	if(!ARGOS_HOME) {
		trigger_error("Environment variable ARGOS_HOME is not defined.  Cannot continue.", E_USER_ERROR);
	}
	util_cli::setIncludePath();
	$site = util_cli::getSiteName();
}

cfg::loadConfig($site);
awsInit();

# Composer dependencies
require_once(ARGOS_HOME.'/packages/composer/vendor/autoload.php');

/**
 * Call the local_prepend here to allow overriding default cache engines and such.
 * $site is expected to be set for any site with specific settings.
 */

if(file_exists(cfg::get('dirHome') . '/inc/local_prepend.php')) {
	require_once(cfg::get('dirHome') . '/inc/local_prepend.php');
}

function errorHandler($errno, $errstr, $errfile = 'UNDEFINED', $errline = 0) {
	if (!(error_reporting() & $errno)) {
		// This error code is not included in error_reporting
		return false;
	}

	$fatal = false;

	// E_ERROR and E_PARSE should never pass through here, but let's be ready if they do.
	$prefixes = array(
		E_ERROR => 'Error',
		E_USER_ERROR => 'User_Error',
		E_WARNING => 'Warning',
		E_USER_WARNING => 'User_Warning',
		E_PARSE => 'Parse_Error',
		E_NOTICE => 'Notice',
		E_USER_NOTICE => 'User_Notice',
		E_STRICT => 'Strict',
		E_DEPRECATED => 'Deprecated',
		E_USER_DEPRECATED => 'User_Deprecated',
		E_RECOVERABLE_ERROR => 'Recoverable_Error'
	);

	if(!isset($prefixes[$errno])) {
		return false;
	}

	$message = "{$_SERVER['UNIQUE_ID']} PHP {$prefixes[$errno]}: {$errstr} in {$errfile} on line {$errline}";
	error_log($message, 0);

	if(($errno & (E_ERROR | E_USER_ERROR | E_PARSE)) == $errno) {
		// Bomb out on fatal errors and throw the appropriate header
		headers_sent() || header(':', true, 500);
		die();
	}
	return true;
}

/**
 * Autoload class and interface files so we don't have to use redundant
 * require/include tags everywhere.
 *
 * @param string $class
 */
function argosAutoload($class) {
	$file = classToFile($class);
	if(!file_exists($file)) {
		return false;
	}
	return include_once($file);
}

/**
 * Converts a class name to the proper file name
 *
 * @param string $class
 * @return string $classFname
 */
function classToFile($class) {
	// Strip out potentially dangerous characters that could lead to directory traversal.
	$class = str_replace(array('.', '/'), '', $class);

	$classFname = escapeshellcmd($class);
	// Replace the underscores in the classname to directory seperators
	$classFname = str_replace('_', '/', $classFname);

	if(strpos($classFname, 'site/') === 0) {
		// Local includes live in the site dir in the classes folder
		$classFname = cfg::get('dirHome') . '/classes/' . substr($classFname, strlen('site/'));
	} else {
		// Otherwise it's from the framework
		$classFname = ARGOS_HOME . '/common/classes/' . $classFname;
	}

	return $classFname . '.class.php';
}

/**
 * Verifies that class we are requesting actually exists as a file.
 *
 * @param string $class
 * @return bool
 */
function isClassValid($class) {
	$fileName = classToFile($class);

	return file_exists($fileName);
}

/**
 * A form of debug
 *
 * @param mixed $val
 * @param string $name
 * @param bool $keepgoing
 * @return type
 */
function dumpout($val, $name = '', $keepgoing = false) {
	$formatted = PHP_SAPI != 'cli';
	echo _getDebug($val, $name, $formatted);
	flush();

	if(!$keepgoing) {
		die("\n");
	}

	return $val;
}

/**
 * Prints out a value type and content into the error log
 *
 * @param mixed $val The value to print out
 * @param string $name The name to show for the value
 * @param bool $keepgoing Should we keep going or die
 * @return mixed
 */
function logit($val, $name = '', $keepgoing = true) {
	util_log::write("DEBUG", "\n".trim(_getDebug($val, $name, false)), 1);

	if(!$keepgoing) {
		die("\n");
	}

	return $val;
}

/**
 * Format the output for debug
 *
 * @param mixed $val
 * @param string $name
 * @param bool $formatted
 * @return string $out
 */
function _getDebug($val, $name, $formatted) {
	$tf = array('false', 'true');
	$name = $name ? "\$$name :: " : "";
	$type = gettype($val);
	$quot = $formatted && $type == 'string' ? "<font color=red>\"</font>" : "";
	$val = $type == 'boolean' ? $tf[$val] : $val;
	$type = $type == 'array' ? "$type (" . count($val) . ")" : $type;
	$type = $type == 'string' ? "$type (" . strlen($val) . ")" : $type;
	$val = print_r($val, true);
	$find = array('    (', '    )', '        ');
	$repl = array('(', ')', '    ');
	$val = str_replace($find, $repl, $val);

	$val = !$formatted ? $val : htmlspecialchars($val);

	$out = '';
	if($formatted) {
		$out .= "<pre style='text-align:left'><font color=red>";
	}

	$out .= "\n$name$type";

	if($formatted) {
		$out .= "</font>";
	}

	$out .= "\n$quot{$val}$quot";

	if($formatted) {
		$out .= "</pre>";
	} else {
		$out .= "\n";
	}

	return $out;
}

/**
 * Checks if the file exists
 *
 * @param string $file
 * @return boolean
 */
function file_exists_path($file) {
	$paths = explode(PATH_SEPARATOR, get_include_path());
	foreach($paths as $path) {
		if(file_exists($path . '/' . $file)) {
			return true;
		}
	}
	return false;
}

/**
 * A simple activity notification script usable at the command line
 * Call twiddle with an integer value for normal iteration.
 * Call it with no arguments to clean up before echoing other output.
 *
 * @param int $interval sets an interval to limit the frequency of twiddler updates
 *
 * @staticvar array $twid
 * @staticvar int $i
 * @staticvar int $t
 * @staticvar boolean $d
 * @param int $interval sets an interval to limit the frequency of twiddler updates
 */
function twiddle($interval = null) {
	static $twid = array();
	static $i = 0;
	static $t = 0;
	static $d = false;

	if(!$twid) {
		$twids = array(array('|', '/', '-', '\\'), array('.', ':', '\'', ':'), array('+', '-', '+', '|'));
		$twid = $twids[array_rand($twids)];
	}
	// If no interval was passed in then just return
	if($interval === null) {
		echo $d;
		$twid = false;
		$d = '';
		return;
	}
	$interval = ceil($interval);
	if($i++ % $interval == 0) {
		echo ($t > 0 ? $d : '') . $twid[$t++ % count($twid)];
		$d = chr(8);
	}
}

function awsInit($config = 'aws-ec2') {
	$conf = cfg::get($config);

	CFCredentials::set(array(
	   // Credentials for the development environment.
   	'dev' => array(

	      // Amazon Web Services Key. Found in the AWS Security Credentials. You can also pass
   	   // this value as the first parameter to a service constructor.
      	'key' => $conf['publicKey'],

	      // Amazon Web Services Secret Key. Found in the AWS Security Credentials. You can also
   	   // pass this value as the second parameter to a service constructor.
      	'secret' => $conf['privateKey'],

	      // This option allows you to configure a preferred storage type to use for caching by
   	   // default. This can be changed later using the set_cache_config() method.
      	//
	      // Valid values are: `apc`, `xcache`, or a file system path such as `./cache` or
   	   // `/tmp/cache/`.
      	'default_cache_config' => 'apc',

	      // Determines which Cerificate Authority file to use.
   	   //
      	// A value of boolean `false` will use the Certificate Authority file available on the
	      // system. A value of boolean `true` will use the Certificate Authority provided by the
   	   // SDK. Passing a file system path to a Certificate Authority file (chmodded to `0755`)
      	// will use that.
	      //
   	   // Leave this set to `false` if you're not sure.
      	'certificate_authority' => false
	   ),

	   // Specify a default credential set to use if there are more than one.
   	'@default' => 'dev'
	));
}
