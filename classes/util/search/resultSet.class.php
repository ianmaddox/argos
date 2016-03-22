<?php
/**
 * This class is used for returned result sets from search engines
 */
class util_search_resultSet implements Iterator, ArrayAccess, Countable {
	private $data = array();

	public function __construct() { }

	public function addResult(util_search_result $result) {
		$this->data[] = $result;
	}


	// Interface implementations
	public function count() {
		return count($this->data);
	}

	public function current() {
		return current($this->data);
	}

	public function key() {
		return key($this->data);
	}

	public function next() {
		return next($this->data);
	}

	public function offsetExists($offset) {
		return isset($this->data[$offset]);
	}

	public function offsetGet($offset) {
		return $this->data[$offset];

	}

	public function offsetSet($offset, $value) { }

	public function offsetUnset($offset) { }

	public function rewind() {
		reset($this->data);
	}

	public function valid() {
		return $this->offsetExists($this->key());
	}
}
