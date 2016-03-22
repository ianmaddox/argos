<?php
/**
 * This class implements an AWS RDS queue client and worker interface.
 *
 * @package Framework
 */
class util_queue_sqs implements interface_queue {
	private $sqs;
	private $queueName;
	private $url;
	private $connected;

	public function __construct($queue = false, $args = false)
	{

		if(empty($this->sqs)) {
			$config = cfg::get('aws-ec2');
			$sqsConfig = array('key' => $config['publicKey'], 'secret' => $config['privateKey']);
			$this->sqs = new AmazonSQS($sqsConfig);
			$this->sqs->set_region(AmazonSQS::REGION_US_W2);
		}
		$this->queueName = $queue;

		$this->connect();
	}

	private function connect() {
		$resp = $this->sqs->create_queue($this->queueName);
		if(!$resp->isOK()) {
			trigger_error("Could not connect to queue {$this->queueName}",E_USER_ERROR);
		}
		$this->url = (string)$resp->body->CreateQueueResult->QueueUrl;
		$this->connected = true;
	}

	public function send($data, $pri = false, $async = true)
	{
		$this->connected || $this->connect();

		$data = serialize($data);
		$resp = $this->sqs->send_message($this->url, $data);
		if(!$resp->isOK()) {
			trigger_error("Could not send message to queue {$this->queueName}",E_USER_ERROR);
		}
	}

	public function fetch()
	{
		$this->connected || $this->connect();

		$resp = $this->sqs->receive_message($this->url);
		/* @var CFResponse $resp */
		if(!$resp || !$resp->isOK() || empty($resp->body->ReceiveMessageResult)) {
			return false;
		}
		// Once we pull an element off the queue, we need to delete it so nobody else gets it.
		$delResp = $this->sqs->delete_message($this->url,$resp->body->ReceiveMessageResult->Message->ReceiptHandle);
		if(!$delResp->isOK()) {
			trigger_error("Could not delete message from SQS queue {$this->queueName}", E_USER_WARNING);
		}
		return unserialize((string)$resp->body->ReceiveMessageResult->Message->Body);
	}

	/**
	 * Return a count of the messages in the queue
	 *
	 * @return int
	 */
	public function count() {
		$this->connected || $this->connect();

		return $this->sqs->get_queue_size($this->url);
	}

	/**
	 * Delete the queue
	 *
	 * @param bool $ifempty Only delete if the queue is empty
	 */
	public function delete($ifempty = true) {
		$this->connected || $this->connect();

		// Be careful about this!  You must wait 60 seconds after deleting a queue to re-create it.
		if(!$ifempty || $this->count() == 0) {
			$this->sqs->delete_queue($this->url);
			$this->connected = false;
		}
	}
}
