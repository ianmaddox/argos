<?php

/**
 * @property-read int $id
 * @property-read string $date_created
 * @property-read string $date_modified
 * @property int $triggerID
 * @property string $templateName
 * @property string $subject
 * @property string $fromEmail
 * @property string $fromName
 * @property string $messageHtml
 */
class db_email_content extends core_row {
	const DB = 'email';
	const TABLE = 'content';
	const PK = 'id';

	/**
	 * Fetch an object instance based on an ID
	 *
	 * @param int $id
	 * @return db_mail_content
	 */
	public static function getInstance($id = NULL)
	{
		return new self($id);
	}

	/**
	 * Fetch an object instance based on a template name
	 *
	 * @param string $templateName The mail template name
	 * @return db_mail_content
	 */
	public static function getInstanceByTemplateName($templateName)
	{
		return self::getFromDB($templateName, 'templateName');
	}

	/**
	 * Fetch an object instance based on a column
	 *
	 * @param string $val The value
	 * @param string $column The column to look up the data
	 * @return db_mail_content
	 */
	private static function getFromDB($val, $col)
	{
		$obj = new self();
		$obj->load($val, $col);

		return $obj;
	}
}