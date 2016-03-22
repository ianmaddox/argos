<?php

/**
 * Standard handler for date and time values
 *
 * @author ianmaddox
 *
 * @package framework
 * @subpackage core
 */
class core_dateTime {
	/**
	 * @var int $epochTime The timestamp
	 */
	private $epochTime;

	/**
	 * Return as string
	 *
	 * @return type
	 */
	public function  __toString() {
		return $this->getUnix();
	}

	/**
	 * Construct the class, optionally setting a date using a unix timestamp.  If not provided, it will
	 * default to today.
	 *
	 * @param int $unixDate
	 */
	public function __construct($unixDate = false) {
		if(!$unixDate) {
			$unixDate = time();
		}
		$this->epochTime = $unixDate;
	}

	/**
	 * Set the date and/or time using a pre-formatted input.
	 *
	 * @param string $date
	 */
	public function setString($date) {
		$this->epochTime = strtotime($date);
	}

	/**
	 * Fetch a custom formatted version of the date/time
	 *
	 * @param string $format
	 * @return string
	 */
	public function getFormatted($format) {
		return date($format, $this->epochTime);
	}

	/**
	 * Fetch a standard formatted date
	 *
	 * @return string
	 */
	public function getDate() {
		return date('Y-m-d', $this->epochTime);
	}

	/**
	 * Fetch a standard formatted date
	 *
	 * @return string
	 */
	public function getTime() {
		return date('g:i a', $this->epochTime);
	}

	/**
	 * Fetch the unix timestamp version of the date/time
	 *
	 * @return int
	 */
	public function getUnix() {
		return $this->epochTime;
	}

}
