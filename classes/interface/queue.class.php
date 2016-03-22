<?php
/**
 * The global interface for a queue driver and the parent queue class.
 * Provides a simplified means of both sending and fetching data off of a given queue.
 *
 * @package framework
 * @subpackage interface
 */
interface interface_queue
{
	/**
	 * The construct method for util_queue
	 *
	 * @param string $queue the name of the queue to be instantiated
	 */
	public function __construct($queue);

	/**
	 * Send something to the queue
	 *
	 * @param mixed $data
	 * @param boolean $async
	 */
	public function send($data, $async = true);

	/**
	 * Grab something from the queue
	 */
	public function fetch();

	/**
	 * Return a count of the messages in the queue
	 *
	 * @return int
	 */
	public function count();

	/**
	 * Delete the queue
	 *
	 * @param bool $ifempty Only delete if the queue is empty
	 */
	public function delete($ifempty = true);

}
