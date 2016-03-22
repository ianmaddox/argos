<?php

/**
 * Description of cliclass
 *
 * @author ianmaddox
 *
 * @package framework
 * @subpackage util
 */
class util_cliArgs {
	// Flags for determining whether an input has an associated value
	const VALUE_REQUIRED = 'req';
	const VALUE_OPTIONAL = 'opt';
	const VALUE_NONE = 'noval';

	private $args = array();

	/**
	 * Fetch the processed list of CLI arguments and store them locally.
	 */
	public function __construct() {
		$this->args = util_cli::getArgs();
	}

	/**
	 * Test whether a particular flag (or one of an equivalent pair of flags) is set
	 *
	 * @param string $flag
	 * @param string $altFlag
	 * @return bool
	 */
	public function isFlagSet($flag, $altFlag = false) {
		if(isset($this->args[$flag]) || ($altFlag && isset($this->args[$altFlag]))) {
			return true;
		}
		return false;
	}

	/**
	 * Fetch the value of an option or its equivalent flag
	 *
	 * @param string $option
	 * @param string $altOption
	 * @return string|null
	 */
	public function getValue($option, $altOption = false, $default = null) {
		if(isset($this->args[$option])) {
			return $this->args[$option];
		}
		if($altOption && isset($this->args[$altOption])) {
			return $this->args[$altOption];
		}
		return $default;
	}

	/**
	 * Check any number of command line flags and return false if any are missing.
	 * Equivalent pairs of flags/options may be passed as an array with two elements.
	 *
	 * @param mixed $option
	 * @return boolean
	 */
	public function checkRequired() {
		if(!func_num_args()) {
			return true;
		}

		$optArr = func_get_args();
		foreach($optArr as $val) {
			$flag = $val;
			$altFlag = false;

			if(is_array($val)) {
				list($flag, $altFlag) = array_values($val);
			}

			if(!$this->isFlagSet($flag, $altFlag)) {
				return false;
			}
		}
	}

	/**
	 * Using the values passed in by addInput(), return a usage guide for a CLI script
	 *
	 * @return string
	 */
	public function getUsage() {
		// this function is not yet used.
	}

	/**
	 * Define an input flag or option.
	 *
	 * @param string long form such as --option
	 * @param string short form such as -o
	 * @param bool is required
	 * @param string description of option
	 * @param const determines whether a value should be expected: VALUE_OPTIONAL, VALUE_REQUIRED, or VALUE_NONE
	 */
	public function addInput($long, $short, $required, $desc, $valueSetting = self::VALUE_OPTIONAL) {
		// this function is not yet used.
	}

}
