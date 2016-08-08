<?php
class AddOnInstallerExtendsGithubBitbucket_DataWriter_AddOn extends XenForo_DataWriter {
	const TABLE_NM = 'kiror_addon_installer_git';
	const TABLE_PK = 'addonid';
	protected function _getFields() {
		return array(
			self::TABLE_NM => array(
				self::TABLE_PK => array(
					'type' => self::TYPE_STRING,
					'required' => true,
				),
				'isGit' => array(
					'type' => self::TYPE_BOOLEAN,
				),
				'service' => array(
					'type' => self::TYPE_STRING,
					'required' => true,
				),
				'maintainer' => array(
					'type' => self::TYPE_STRING,
					'required' => true,
				),
				'project' => array(
					'type' => self::TYPE_STRING,
					'required' => true,
				),
				'branch' => array(
					'type' => self::TYPE_STRING,
					'required' => true,
				),
				'installedCommitHash' => array(
					'type' => self::TYPE_STRING,
					'required' => true,
				),
				'installedCommitTime' => array(
					'type' => self::TYPE_UINT,
					'required' => false,
					'default' => XenForo_Application::$time
				),
				'latestCommitHash' => array(
					'type' => self::TYPE_STRING,
					'required' => true,
				),
				'latestCommitTime' => array(
					'type' => self::TYPE_UINT,
					'required' => false,
					'default' => XenForo_Application::$time
				),
			)
		);
	}
	protected function _getExistingData($data){
		if (!$id = $this->_getExistingPrimaryKey($data, self::TABLE_PK)){
			return false;
		}
		return array(self::TABLE_NM => $this->_getCorrectModel()->getById($id));
	}
	protected function _getUpdateCondition($tableName){
		return self::TABLE_PK . ' = ' . $this->_db->quote($this->getExisting(self::TABLE_PK));
	}
	public function _getCorrectModel(){
		return $this->getModelFromCache('AddOnInstallerExtendsGithubBitbucket_Model_AddOn');
	}
}
