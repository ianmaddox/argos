<?php

class util_memoryUsage {
	private static $util = false;
	private $startMemory = 0;
	private $lastMemory = 0;
	private $storeMemory = 0;


	/**
	 * Constructor
	 */

	private function __construct() {
		$this->startMemory = memory_get_usage();
		$this->storeMemory = $this->startMemory;
		$this->lastMemory = $this->startMemory;
		$this->report('START');
	}

	/**
	 * Instantiate / retrieve memory usage reporter
	 * @return util_memoryUsage
	 */
	public static function getInstance() {
		if(!self::$util) {
			self::$util = new util_memoryUsage();
		}
		return self::$util;
	}

	/**
	 * Store the current memory in the store for tracking
	 */
	public function storeMemory() {
		$this->storeMemory = memory_get_usage();
	}

	/**
	 * Report the memory usage to the log and store the memory in lastMemory
	 * @param string $header Header to append to memory report
	 */
	public function report($header = '') {
		$data = $this->getStats();
		$this->lastMemory = $data['now'];
		if(!empty($header)) {
			$header .= ' ';
		}
		trigger_error("\n{$header}Memory: {$data['now']}\n  Start Delta: {$data['startDelta']}\n  Store Delta: {$data['storeDelta']}\n  Last Delta: {$data['lastDelta']}\n", E_USER_NOTICE);
	}

	/**
	 * Get current memory stats
	 * @return array Memory data in hash array
	 *					now -> curent memory use
	 *					start -> memory when initially created
	 *					store -> value stored in storeMemory
	 *					last -> value at last report
	 *					startDelta -> change since initialization
	 *					storeDelta -> change since store value
	 *					lastDelta -> change since last report
	 */
	public function getStats() {
		$nowMemory = memory_get_usage();
		return array(
			'now' => $nowMemory,
			'start' => $this->startMemory,
			'store' => $this->storeMemory,
			'last' => $this->lastMemory,
			'startDelta' => $nowMemory - $this->startMemory,
			'storeDelta' => $nowMemory - $this->storeMemory,
			'lastDelta' => $nowMemory - $this->lastMemory,
		);
	}
}