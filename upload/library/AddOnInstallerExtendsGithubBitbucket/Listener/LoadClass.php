<?php

class AddOnInstallerExtendsGithubBitbucket_Listener_LoadClass {
	public static $extendedThisRun = false;
	public static function overrideThemeHouseController($class, array &$extend){
		if(!self::$extendedThisRun && in_array('ThemeHouse_InstallUpgrade_Extend_XenForo_ControllerAdmin_AddOn',$extend)){
			$extend[]='AddOnInstallerExtendsGithubBitbucket_ControllerAdmin_AddOn';
			self::$extendedThisRun = true;
		}
	}
	public static function overrideXenResourceController($class, array &$extend){
		if(!self::$extendedThisRun && in_array('AddOnInstaller_ControllerAdmin_AddOn',$extend)){
			$extend[]='AddOnInstallerExtendsGithubBitbucket_ControllerAdmin_AddOn';
			self::$extendedThisRun = true;
		}
	}
}
