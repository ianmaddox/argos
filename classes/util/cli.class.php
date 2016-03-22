<?php

/**
 * Description of cliclass
 *
 * @author ianmaddox
 *
 * @package framework
 * @subpackage util
 */
class util_cli {
	private function __construct() {

	}

	/**
	 * Determine whether the script is executing in CLI mode
	 *
	 * @return boolean
	 */
	public static function isCliMode() {
		return PHP_SAPI == 'cli';
	}

	/**
	 * Returns an array of command line arguments.  Parses each arg as separate
	 * unless a flag with a single dash is followed by a value with no dashes.
	 * For example "-f file.txt".  Flags and arguments are returned as keys in
	 * an array.  Their values (if any) are returned as the array's values.
	 * Null is returned for each element with no value.
	 */
	public static function getArgs() {
		$rawArgs = $_SERVER['argv'];
		array_shift($rawArgs);
		$args = array();
		$skipNext = false;

		// Grab each arg and process it.
		foreach($rawArgs as $caret => $arg) {
			// Processing single-dash flags may mean skipping the next value because it has already been snatched up.
			if($skipNext) {
				$skipNext = false;
				continue;
			}

			// Split the value from the flag or arg.
			$kvp = explode('=', $arg, 2);
			$key = $kvp[0];
			$val = isset($kvp[1]) ? $kvp[1] : true;

			// Allow for single-dash, single letter flags followed by a space then their value
			if($key{0} == '-'
			&& isset($key{1}) && $key{1} != '-'
			&& isset($rawArgs[$caret + 1])
			&& $rawArgs[$caret + 1]{0} != '-') {
				$val = $rawArgs[$caret + 1];
				$skipNext = true;
			}
			$args[$key] = $val;
		}

		return $args;
	}

	/**
	 * Determine the root directory of the site in CLI mode, prefixed by a DIRECTORY_SEPARATOR
	 *
	 * @return string
	 */
	public static function getSiteDir() {
		list($path) = get_included_files();
		if(stripos($path, 'phpunit') ) {
			return '/phpunit';
		} elseif(strpos($path,ARGOS_HOME) !== 0) {
			trigger_error("Could not determine site directory.  ARGOS_HOME path not found.",E_USER_WARNING);
			return false;
		}

		$siteDir = substr($path,strlen(ARGOS_HOME));
		$start = strpos($siteDir,DIRECTORY_SEPARATOR);
		$end = strPos($siteDir,DIRECTORY_SEPARATOR,$start + 1);

		$siteDir = substr($siteDir,$start,$end);

		return $siteDir;
	}

	/**
	 * Determine the active site name
	 *
	 * @return string
	 */
	public static function getSiteName() {
		$dir = self::getSiteDir();
		$caret = strpos($dir,'.');
		$caret = $caret ? $caret : strlen($dir);
		$site = substr($dir,1,$caret - 1);

		return $site;
	}

	/**
	 * Alter the script's include path for CLI scripts to they have the same
	 * context as an apache page.
	 */
	public function setIncludePath() {
		if(isset($_ENV['cli_include_set'])) {
			return;
		}
		$_ENV['cli_include_set'] = true;

		$rootDir = ARGOS_HOME . self::getSiteDir();
		$path = get_include_path();
		set_include_path(
			$rootDir . DIRECTORY_SEPARATOR . 'classes' .
			PATH_SEPARATOR . $rootDir . DIRECTORY_SEPARATOR . 'inc' .
			PATH_SEPARATOR . $rootDir .
			PATH_SEPARATOR . $path
			);
	}
}
