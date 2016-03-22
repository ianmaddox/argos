<?php

/**
 * This class implements a rabbit queue client and worker interface.
 *
 * @package framework
 * @subpackage util_queue
 */
class util_queue_rabbit implements interface_queue {

	private $server;
	private $exchange;
	private $queue;
	private $queueName;
	private $exchangeName;
	private $routingKey;
	private $deleted;

	/**
	 * Constructor
	 *
	 * @param string $queue
	 * @throws Exception
	 */
	public function __construct($queue)
	{
		if(!isset($queue) || empty($queue))
		{
			trigger_error('$queue not specified or empty', E_USER_ERROR);
		}
		$args = array(
			'host' => cfg::get('rabbit_server'),
			'port' => cfg::get('rabbit_port'),
			'login' => cfg::get('rabbit_user'),
			'password' => cfg::get('rabbit_pass'),
			'vhost' => cfg::get('rabbit_vhost'));

		$this->queueName = $queue . "Queue";
		$this->exchangeName = $queue . "Exchange";
		$this->routingKey = 'routing.' . $queue;

		$this->connect();
	}

	private function connect() {
		try {
			// Create a connection
			if(empty($this->server))
			{
				$this->server = new AMQPConnection($args);
				$this->server->connect();
				// Create a new queue
				$this->queue = new AMQPQueue($this->server, $this->queueName);
				// Create a new exchange
				$this->exchange = new AMQPExchange($this->server, $this->exchangeName);

				if(!$this->exchange)
				{
					throw new Exception();
				}
			}
			if(!$this->exchange)
			{
				throw new Exception();
			}
			// Declare a new exchange
			$this->exchange->declare($this->exchangeName, AMQP_EX_TYPE_DIRECT, AMQP_DURABLE);
			// Declare a new queue
			$this->queue->declare($this->queueName, AMQP_DURABLE);

			// Bind the exchange to the queue and to the routing.key
			$this->exchange->bind($this->queueName, $this->routingKey);

			$this->deleted = false;
		} catch(Exception $e) {
			trigger_error("Could not connect to rabbit server", E_USER_WARNING);
			$this->deleted = true;
		}
	}

	/**
	 * Send an item to the queue
	 *
	 * @param mixed $data
	 * @param bool $async
	 * @return boolean true if successful, false otherwise
	 * @throws Exception
	 */
	public function send($data, $async = true)
	{
		if($data === false)
		{
			trigger_error("Cannot queue empty message", E_USER_WARNING);
			return false;
		}

		$data = serialize($data);
		$data = base64_encode($data);

		$this->deleted && $this->connect();

		try {
			if(!$this->exchange)
			{
				throw new Exception();
			}
			// Turns out async doesn't actually do anything right now.  Surprise!
			if($async == false)
			{
				$this->exchange->publish($data, $this->routingKey);
			}
			else
			{
				$this->exchange->publish($data, $this->routingKey);
			}
			return true;
		} catch(Exception $e) {
			trigger_error("Lost connection to rabbit server", E_USER_WARNING);
		}
		return false;
	}

	/**
	 * Grab an item from the queue
	 *
	 * @return mixed $content
	 */
	public function fetch()
	{
		try {
			$this->deleted && $this->connect();

			$message = $this->queue->get(FALSE);
			if(empty($message) || !isset($message['count']) || $message['count'] < 0)
			{
				return false;
			}
			$this->queue->ack($message['delivery_tag']);
			$content = base64_decode($message['msg']);
			$content = unserialize($content);

			return $content;
		} catch(Exception $e) {
			trigger_error("Lost connection to rabbit server", E_USER_WARNING);
		}

		return FALSE;
	}

	/**
	 * Return a count of the messages in the queue
	 *
	 * @return int
	 */
	public function count() {
		$this->deleted && $this->connect();

		// switch on passive
		$oldflags = $this->queue->getFlags();
		$this->queue->setFlags($oldflags | AMQP_PASSIVE);

		// redeclare
		$count = $this->queue->declare($this->queueName);

		// revert flags
		$this->queue->setFlags($oldflags);

		return $count;
	}

	/**
	 * Delete the queue
	 *
	 * @param bool $ifempty Only delete if the queue is empty
	 */
	public function delete($ifempty = true) {
		$this->deleted && $this->connect();

		if(!$ifempty || $this->count() == 0) {
			$this->queue->delete();
			$this->exchange->delete();
			$this->deleted = true;

			unset($this->queue, $this->exchange);
		}
	}

}

