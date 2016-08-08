<?php
class AddOnInstallerExtendsGithubBitbucket_Model_AddOn extends XenForo_Model {
	public function getById($pk){
		return $this->_getDb()->fetchRow('
			SELECT * FROM '.AddOnInstallerExtendsGithubBitbucket_DataWriter_AddOn::TABLE_NM.' '.
			'WHERE '.AddOnInstallerExtendsGithubBitbucket_DataWriter_AddOn::TABLE_PK.' = ?', $pk);
	}
	public function getAll() {
		return $this->fetchAllKeyed('SELECT * FROM '.AddOnInstallerExtendsGithubBitbucket_DataWriter_AddOn::TABLE_NM,
			AddOnInstallerExtendsGithubBitbucket_DataWriter_AddOn::TABLE_PK);
	}
	public function getAllKeys() {
		return $this->fetchAllKeyed('SELECT '.AddOnInstallerExtendsGithubBitbucket_DataWriter_AddOn::TABLE_PK.' FROM '.
			AddOnInstallerExtendsGithubBitbucket_DataWriter_AddOn::TABLE_NM,
			AddOnInstallerExtendsGithubBitbucket_DataWriter_AddOn::TABLE_PK);
	}
}
