<?php
class AddOnInstallerExtendsGithubBitbucket_Install {
	public static function install($installedAddon){
		$version = is_array($installedAddon) ? $installedAddon['version_id'] : 0;
		$dbc=XenForo_Application::get('db');
		$dbc->query(self::$createDb);
	}
	public static function uninstall(){
		$dbc=XenForo_Application::get('db');
		$dbc->query(self::$dropDb);
	}
	public static $createDb = 'CREATE TABLE IF NOT EXISTS kiror_addon_installer_git (
		addonid VARCHAR(255) PRIMARY KEY,
		isGit BOOLEAN,
		service VARCHAR(255),
		maintainer VARCHAR(255),
		project VARCHAR(255),
		branch VARCHAR(255),
		installedCommitHash VARCHAR(255),
		installedCommitTime INT UNSIGNED,
		latestCommitHash VARCHAR(255),
		latestCommitTime INT UNSIGNED
		) CHARACTER SET utf8 COLLATE utf8_general_ci ;';
	public static $dropDb = 'DROP TABLE IF EXISTS kiror_addon_installer_git ;';
}
