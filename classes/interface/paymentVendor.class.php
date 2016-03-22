<?php
/**
 *
 * @package framework
 * @subpackage interface
 */
interface interface_paymentVendor {
	public function __construct();

	/**
	 * Hold funds for capture at a later time.
	 * WARNING: Should only be used in conjunction with physical goods shipment.
	 * Otherwise, use sale()
	 *
	 * @param ecomm_purchInfo $purchInfo
	 * @return ecomm_result
	 */
	public function auth(ecomm_purchInfo $purchInfo);

	/**
	 * Capture funds held by a previous auth.
	 * WARNING: Should only be used in conjunction with physical goods shipment.
	 * Otherwise, use sale()
	 *
	 * @param ecomm_purchInfo $purchInfo
	 * @return ecomm_result
	 */
	public function capture(ecomm_purchInfo $purchInfo);

	/**
	 * Perform an immediate auth and capture the funds.  Should be used for every
	 * purchase where we are not delivering physical goods.
	 *
	 * @param ecomm_purchInfo $purchInfo
	 * @param array $orderItems Array of ecomm_orderItem
	 * @return ecomm_result
	 */
	public function sale(ecomm_purchInfo $purchInfo, $orderItems);

	/**
	 * Refund all or part of an order based on transactionID
	 *
	 * @param string $trxID
	 */
	public function refundOrder($trxID);

	/**
	 * Get an existing payment profile, or create one if it doesn't exist yet
	 *
	 * @param ecomm_purchInfo $profile Payment profile information
	 * @return int Payment profile ID
	 */
	public function getOrCreatePaymentProfile(ecomm_purchInfo $profile);

	/**
	 * Create a recurring billing schedule that is driven by the vendor.
	 *
	 * @param ecomm_purchInfo $purchInfo REQUIRED VALUES: name, address, cc, expiry, cvv, zip, email, ip
	 * @param array $orderItems Array of ecomm_orderItem
	 * @param type $unixStartDate The start date for the recurring payment
	 * @return ecomm_result
	 */
	public function createSchedule(ecomm_purchInfo $purchInfo, $orderItems, $unixStartDate = false);

	/**
	 * Update an existing recurring billing schedule.
	 *
	 * @param ecomm_purchInfo $purchInfo
	 * @param int $installments How many times to charge
	 * @param int $intervalNum
	 * @param const $intervalUnit PERIODIC_INTERVAL_*
	 * @param int $unixStartDate The start date for the recurring payment
	 * @return ecomm_result
	 */
	public function updateSchedule(ecomm_purchInfo $purchInfo, $installments, $intervalNum = false, $intervalUnit = false, $unixStartDate = false);

	/**
	 * Delete a recurring schedule
	 *
	 * @param int $orderID
	 * @param string $vendorOrderID
	 * @return ecomm_result
	 */
	public function deleteSchedule($orderID, $vendorOrderID);

	/**
	 * Tell whether this driver supports payment schedules
	 *
	 * @return bool
	 */
	public function supportsSchedules();

}
