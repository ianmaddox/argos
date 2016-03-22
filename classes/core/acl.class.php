<?php
class core_acl {
	/** @var core_acl $instance This instance */
	private static $instance = false;

	/** @var core_db $db An instance of core_db */
	private $db;

	/** @var integer $userID The userID */
	private $userID;

	/** @var integer $context The context ID */
	private $contextID;

	/** @var array $groups The user's groups */
	private $groups = array();

	/** @var array $actions The user's actions */
	private $actions = array();

	/**
	 * Internal constructor
	 *
	 * @param integer $contextID The context ID
	 * @param integer $userID The user ID
	 */
	private function __construct($contextID, $userID) {
		$this->db = core_db::getDB();
		$this->contextID = $contextID;
		$this->userID = $userID;
	}

	/**
	 * Return an instance of ourselves
	 *
	 * @param string $contextName The context
	 * @param integer $userID The userID
	 * @return \core_acl
	 */
	public static function getInstance($contextName, $userID = 0) {
		$contextDB = db_acl_context::getInstanceByName($contextName);
		$contextID = $contextDB->id;

		if($contextID) {
			if(self::$instance === false) {
				// Instantiate a new ACL instance
				self::$instance = new core_acl($contextID, $userID);
			}

			// Refresh/Set our data
			self::$instance->setGroups();
			self::$instance->setGroupActions();

			return self::$instance;
		} else {
			// Log that we recieved an empty $context value
			trigger_error('Invalid ACL context specified: '.$contextName, E_USER_ERROR);

			return false;
		}
	}

	/**
	 * Check if a user is in a group
	 *
	 * @param string $groupName
	 * @return boolean
	 */
	public function inGroup($groupName) {
		return isset($this->groups[$groupName]);
	}

	/**
	 * Check if a user has access to an action
	 *
	 * @param string $actionName
	 * @return boolean
	 */
	public function hasAccess($actionName) {
		return isset($this->actions[$actionName]);
	}

	/**
	 * Get the groups for a user ID
	 *
	 * @return void
	 */
	private function setGroups() {
		$sql = "
			SELECT
				g.id,
				g.name
			FROM
				acl.userGroups as ug
			JOIN acl.groups as g ON ug.groupFK = g.id
			WHERE
				ug.userFK = " . (int) $this->userID . " AND
				g.contextFK = " . (int) $this->contextID;
		$results = $this->db->selectAll($sql);

		foreach($results as $row) {
			$this->groups[$row["name"]] = $row["id"];
		}
	}

	/**
	 * Get the available actions for a group
	 *
	 * @return void
	 */
	private function setGroupActions() {
		$sql = "
			SELECT
				a.id,
				a.name
			FROM
				acl.groupActions as ga
			JOIN acl.actions as a ON ga.actionFK = a.id
			WHERE
				ga.groupFK IN(".  implode(",", array_values($this->groups)).") AND
				a.contextFK = " . (int) $this->contextID;

		$results = $this->db->selectAll($sql);

		foreach($results as $row) {
			$this->actions[$row["name"]] = $row["id"];
		}
	}

	/**
	 * Insert a new user
	 *
	 * @param integer $contextID The context ID
	 * @param string $username The username e.g. blah@domain.com
	 * @param string $name The user's name
	 * @return integer
	 */
	public function insertUser($contextID, $username, $name) {
		if(!empty($contextID) && !empty($username) && !empty($name)) {
			$userDB = db_acl_users::getInstance();
			$userDB->contextFK = $contextID;
			$userDB->username = $username;
			$userDB->name = $name;
			$userDB->save();

			return $userDB->id;
		} else {
			// Log that we recieved an empty $contextID and $name value
			trigger_error('Can\'t insert a acl.users record with an empty $contextID and $name.', E_USER_ERROR);

			return false;
		}
	}

	/**
	 * Insert a new action
	 *
	 * @param integer $contextID The context ID
	 * @param string $actionName The action's name
	 * @return integer
	 */
	public function insertAction($contextID, $actionName) {
		if(!empty($contextID) && !empty($actionName)) {
			$actionDB = db_acl_actions::getInstance();
			$actionDB->contextFK = $contextID;
			$actionDB->name = $actionName;
			$actionDB->save();

			return $actionDB->id;
		} else {
			// Log that we recieved an empty $contextID and $actionName value
			trigger_error('Can\'t insert a acl.actions record with an empty $contextID and $actionName.', E_USER_ERROR);

			return false;
		}
	}

	/**
	 * Delete an action and all associations
	 *
	 * @param integer $contextID The context ID
	 * @param integer $actionID The action ID
	 * @return boolean
	 */
	public function deleteAction($contextID, $actionID) {
		if(!empty($contextID) && !empty($actionID)) {
			$sqlContextID = $this->db->escapeVal($contextID);
			$sqlActionID = $this->db->escapeVal($actionID);

			$sql = "
				DELETE
					a, ga
				FROM
					acl.actions as a
				JOIN acl.groupActions as ga on a.id = ga.groupFK
				WHERE
					g.id = {$sqlActionID} AND
					g.contextFK = {$sqlContextID}";

			$logInfoMessage = "Deleted acl.actions, acl.groupActions records for contextID: {$sqlContextID} AND actionID: {$sqlActionID}";
			util_log::write($this->context, $logInfoMessage);

			return $this->db->query($sql);
		} else {
			// Log that we recieved an empty $context and $actionID value
			trigger_error('Can\'t delete acl.actions, acl.groupActions records without a $contextID and $actionID.', E_USER_ERROR);

			return false;
		}
	}

	/**
	 * Insert a new group
	 *
	 * @param integer $contextID The context ID
	 * @param string $groupName The group's name
	 * @return integer
	 */
	public function insertGroup($contextID, $groupName) {
		if(!empty($contextID) && !empty($groupName)) {
			$groupDB = db_acl_groups::getInstance();
			$groupDB->contextFK = $contextID;
			$groupDB->name = $groupName;
			$groupDB->save();

			return $groupDB->id;
		} else {
			// Log that we recieved an empty $contextID and $groupName value
			trigger_error('Can\'t insert a acl.groups record with an empty $contextID and $groupName.', E_USER_ERROR);

			return false;
		}
	}

	/**
	 * Delete a group and all associations
	 *
	 * @param integer $contextID The context ID
	 * @param integer $groupID The group ID
	 * @return boolean
	 */
	public function deleteGroup($contextID, $groupID) {
		if(!empty($contextID) && !empty($groupID)) {
			$sqlContextID = $this->db->escapeVal($contextID);
			$sqlGroupID = $this->db->escapeVal($groupID);

			$sql = "
				DELETE
					g, ug, ga
				FROM
					acl.groups as g
				JOIN acl.userGroups as ug on g.id = ug.groupFK
				JOIN acl.groupActions as ga on g.id = ga.groupFK
				WHERE
					g.id = {$sqlGroupID} AND
					g.contextFK = {$sqlContextID}";

			$logInfoMessage = "Deleted acl.userGroups, acl.groupActions, acl.groups records for contextID: {$sqlContextID}AND groupID: {$sqlGroupID} ";
			util_log::write($this->context, $logInfoMessage);

			return $this->db->query($sql);
		} else {
			// Log that we recieved an empty $contextID and $groupID value
			trigger_error('Can\'t delete acl.userGroups, acl.groupActions, acl.groups records without a $contextID and $groupID.', E_USER_ERROR);

			return false;
		}
	}

	/**
	 * Insert a userGroup record for a specified userID
	 *
	 * @param integer $userID The user ID
	 * @param integer $groupID The group ID
	 * @return boolean
	 */
	public function insertUserGroup($userID, $groupID) {
		if(!empty($userID) && !empty($groupID)) {
			$userGroupDB = db_acl_userGroups::getInstance();
			$userGroupDB->userFK = $userID;
			$userGroupDB->groupFK = $groupID;
			$userGroupDB->save();

			return $userGroupDB->id;
		} else {
			// Log that we recieved an empty $userID and $groupID value
			trigger_error('Can\'t insert a userGroup record without a $userID and $groupID.', E_USER_ERROR);

			return false;
		}

		return true;
	}

	/**
	 * Delete a userGroup record for the specified userID
	 *
	 * @param integer $userID The user ID
	 * @param integer $groupID The group ID
	 * @return boolean
	 */
	public function deleteUserGroup($userID, $groupID) {
		if(!empty($userID) && !empty($groupID)) {
			$logInfoMessage = '';
			$sqlUserID = $this->db->escapeVal($userID);
			$sqlGroupID = $this->db->escapeVal($groupID);

			$sql = "
				DELETE FROM
					acl.userGroups as ug
				WHERE
					ug.userFK = {$sqlUserID} AND
					ug.groupFK = {$sqlGroupID}";

			$logInfoMessage = "Deleted acl.userGroups record for userID: {$sqlUserID} AND groupID: {$sqlGroupID}";
			util_log::write($this->context, $logInfoMessage);

			return $this->db->query($sql);
		} else {
			// Log that we recieved an empty $userID and $groupID value
			trigger_error('Can\'t delete a acl.userGroups record without a $userID and $groupID.', E_USER_ERROR);

			return false;
		}
	}

	/**
	 * Insert a groupAction record for a specified groupID and actionID
	 *
	 * @param integer $groupID The group ID
	 * @param integer $actionID The action ID
	 * @return boolean
	 */
	public function insertGroupAction($groupID, $actionID) {
		if(!empty($groupID) && !empty($actionID)) {
			$groupActionDB = db_acl_groupActions::getInstance();
			$groupActionDB->groupFK = $groupID;
			$groupActionDB->actionFK = $actionID;
			$groupActionDB->save();

			return $groupActionDB->id;
		} else {
			// Log that we recieved an empty $groupID and $actionID value
			trigger_error('Can\'t insert a acl.groupAction record without a $groupID and $actionID.', E_USER_ERROR);

			return false;
		}
	}

	/**
	 * Delete a groupActions record specified groupID
	 *
	 * @param integer $groupID The group ID
	 * @param integer $actionID The action ID
	 * @return boolean
	 */
	public function deleteGroupAction($groupID, $actionID) {
		if(!empty($groupID) && !empty($actionID)) {
			$logInfoMessage = '';
			$sqlGroupID = $this->db->escapeVal($groupID);
			$sqlActionID = $this->db->escapeVal($actionID);

			$sql = "
				DELETE FROM
					acl.groupActions as ga
				WHERE
					ga.actionFK = {$sqlActionID} AND
					ga.groupFK = {$sqlGroupID}";

			// Log the deletion
			$logInfoMessage = "Deleted acl.userGroups record for groupID: {$sqlGroupID} AND actionID: {$sqlActionID}";
			util_log::write($this->context, $logInfoMessage);

			return $this->db->query($sql);
		} else {
			// Log that we recieved an empty $groupID and $actionID value
			trigger_error('Can\'t delete a acl.userGroups record without a $groupID and $actionID.', E_USER_ERROR);

			return false;
		}
	}
}