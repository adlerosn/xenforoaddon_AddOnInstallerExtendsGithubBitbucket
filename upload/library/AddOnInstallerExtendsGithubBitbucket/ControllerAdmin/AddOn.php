<?php

class AddOnInstallerExtendsGithubBitbucket_ControllerAdmin_AddOn extends XFCP_AddOnInstallerExtendsGithubBitbucket_ControllerAdmin_AddOn {
	/**
	 * @abstract
	 * 
	 * For the future: when called, this method in into an accont that
	 *   will be used to fetch some private repository.
	 * 
	 * @param Zend_Http_Client client
	 * @param string service
	 * @return boolean # Success on logging in.
	 */
	protected function _doTheGitLogin(Zend_Http_Client $client, $service){return false;}
	
	/**
	 * @abstract
	 * 
	 * For the future: when called, this method logs out from the
	 *   account used to fetch some private repository.
	 * 
	 * @param Zend_Http_Client client
	 * @param string service
	 * @return null
	 */
	protected function _doTheGitLogout(Zend_Http_Client $client, $service){return null;}
	
	/**
	 * Extend me with Chain-of-responsibility pattern.
	 * 
	 * @param string url
	 */
	protected function _getGitInfoFromUrl($url){
		$knonwRepositories = array(
			'github'    => array(
				'regex'=>'/https?:\/\/(github.com)\/([^\/]+)\/([^\/]+)(?:(?:(?:\/tree\/)|(?:\/commits\/))([^\/\n]+)\/?)?/',
				'userPos'=>2,
				'repoPos'=>3,
				'branchPos'=>4,
				'defaultBranch'=>'master',
				'canRetrieveNonDefault'=>true,
			),
			'bitbucket' => array(
				'regex'=>'/https?:\/\/(bitbucket.org)\/([^\/]+)\/([^\/]+)(?:\/(?:(?:branch\/)|(?:commits\/branch\/(?!all)))([^\/\n]+)\/?)?/',
				'userPos'=>2,
				'repoPos'=>3,
				'branchPos'=>4,
				'defaultBranch'=>'master',
				'canRetrieveNonDefault'=>false,
			),
		);
		$array = array('url'=>$url,'isGit'=>false);
		foreach($knonwRepositories as $service => $instructions){
			$matched = array();
			preg_match($instructions['regex'],$url,$matched);
			if(count($matched)>3){
				$array['isGit']=true;
				$array['service']=$service;
				$array['maintainer']=$matched[$instructions['userPos']];
				$array['project']=$matched[$instructions['repoPos']];
				$array['branch']=$instructions['defaultBranch'];
				if($instructions['branchPos']>0 && isset($matched[$instructions['branchPos']])){
					$branchCandidate = $matched[$instructions['branchPos']];
					if(strlen($branchCandidate)>0 && $instructions['canRetrieveNonDefault']){
						$array['branch']=$branchCandidate;
					}
				}
				return $array;
			}
		}
		return $array;
	}
	
	protected function _reassembleGitUrl($gitInfo){
		$instructions = array(
			'github'=>array(
				'link'=>'https://github.com/%%MAINTAINER%%/%%PROJECT%%/commits/%%BRANCH%%',
			),
			'bitbucket'=>array(
				'link'=>'https://bitbucket.org/%%MAINTAINER%%/%%PROJECT%%/commits/branch/%%BRANCH%%',
			),
		);
		$instruction = $instructions[$gitInfo['service']];
		$commitLookupUri = str_replace(
			array(       '%%MAINTAINER%%',      '%%PROJECT%%',      '%%BRANCH%%'),
			array($gitInfo['maintainer'],$gitInfo['project'],$gitInfo['branch']),
			$instruction['link']
		);
		return $commitLookupUri;
	}
	
	protected function _getGitExtraInfo(Zend_Http_Client $client, &$gitInfo){
		$instructions = array(
			'github'=>array(
				'link'=>'https://github.com/%%MAINTAINER%%/%%PROJECT%%/commits/%%BRANCH%%',
				//'line'=>'.commit',
				'commit'=>'.commit-links-group a.sha',
				'time'=>'.commit-author-section relative-time',
			),
			'bitbucket'=>array(
				'link'=>'https://bitbucket.org/%%MAINTAINER%%/%%PROJECT%%/commits/branch/%%BRANCH%%',
				//'line'=>'.commit-list .iterable-item',
				'commit'=>'a.hash.execute',
				'time'=>'.date time',
			),
		);
		$instruction = $instructions[$gitInfo['service']];
		$commitLookupUri = str_replace(
			array(       '%%MAINTAINER%%',      '%%PROJECT%%',      '%%BRANCH%%'),
			array($gitInfo['maintainer'],$gitInfo['project'],$gitInfo['branch']),
			$instruction['link']
		);
		$client->setUri($commitLookupUri);
		$dom = new Zend_Dom_Query($client->request('GET')->getBody());
		$latestCommit = array(
			'time'=>strtotime($dom->query($instruction['time'])->current()->getAttribute('datetime')),
			'hash'=>trim($dom->query($instruction['commit'])->current()->nodeValue),
		);
		$gitInfo['latestCommitTime'] = $latestCommit['time'];
		$gitInfo['latestCommitHash'] = $latestCommit['hash'];
		//die(print_r($latestCommit,true));
		return $latestCommit;
	}
	
	/**
	 * Extend me with Chain-of-responsibility pattern.
	 * 
	 * @param info array # output of self::_getGitInfoFromUrl/1
	 * @return array(0=>boolean, 1=>string)
	 */
	protected function _getGitZipUrl($info){
		if(!is_array($info) || !isset($info['url'])){
			return [false,''];
		}
		if(!isset($info['isGit']) || !$info['isGit'] || !isset($info['service']) ||
			!isset($info['maintainer']) || !isset($info['project']) || !isset($info['branch'])){
			return [false,$info['url']];
		}
		$toDownload = '';
		switch($info['service']){
			case 'github':
				$toDownload = 'https://github.com/'.$info['maintainer'].'/'.$info['project'].'/archive/'.$info['branch'].'.zip';
				break;
			case 'bitbucket':
				$toDownload = 'https://bitbucket.org/'.$info['maintainer'].'/'.$info['project'].'/get/HEAD.zip';
				break;
			default: throw new XenForo_Exception('Nobody handled the GIT service "'.$info['service'].'". Some other add-on that extended this one is broken.',true);
		}
		return [true,$toDownload];
	}
	
	/**
	 * Check if some add-on is installed and activated.
	 * 
	 * @param addOnId string
	 * @return boolean
	 */
	protected function _isAddOnIdInstalledAndActivated($addOnId){
		$addon = $this->_getAddOnModel()->getAddOnById($addOnId);
		return (is_array($addon) && isset($addon['active']) && $addon['active']);
	}
	
	protected $_gitUpdateDw = null;
	protected function _getGitUpdateDw(){
		if($this->_gitUpdateDw == null){
			$this->_gitUpdateDw = XenForo_DataWriter::create('AddOnInstallerExtendsGithubBitbucket_DataWriter_AddOn');
		}
		return $this->_gitUpdateDw;
	}
	
	/**
	 * 
	 */
	protected function _setAddOnToRepository($gitInfo,$zipUrl){
		$gitInfo['zipUrl']=$zipUrl;
		$dw = $this->_getGitUpdateDw();
		$fields = $dw->getFields();
		$fields = $fields[array_keys($fields)[0]];
		$gitInfo['installedCommitHash'] = $gitInfo['latestCommitHash'];
		$gitInfo['installedCommitTime'] = $gitInfo['latestCommitTime'];
		//Deciding it it'll be a update or not; ignore input
		$model = $dw->_getCorrectModel();
		$data = $model->getAllKeys();
		$isUpdate = array_key_exists($gitInfo['addonid'],$data);
		if($isUpdate){
			$dw->setExistingData($gitInfo['addonid']);
		}
		foreach($gitInfo as $column => $value){
			if(array_key_exists($column,$fields)){
				if(!($isUpdate && $column==AddOnInstallerExtendsGithubBitbucket_DataWriter_AddOn::TABLE_PK)){
					$dw->set($column,$value);
				}
			}
		}
		$dw->save();
	}
	
	/**
	 * Downloads the ZIP from the GIT repo on the internet.
	 * 
	 */
	protected function _downloadGitRepository(Zend_Http_Client $client, &$gitInfo){
		//Define directories
		$downloadDirectory = 'install/git_addons/'.$gitInfo['service'].'/'.$gitInfo['maintainer'].'/'.$gitInfo['project'];
		$downloadFilename = $gitInfo['branch'].'.zip';
		$downloadPath = $downloadDirectory.'/'.$downloadFilename;
		if (!XenForo_Helper_File::createDirectory($downloadDirectory)){
			return $this->responseError('File System: could not create folder on "'.$downloadDirectory.'". Check permissions.');
		}
		//Download
		$zipUrl = $this->_getGitZipUrl($gitInfo);
		$client->setUri($zipUrl[1]);
		$fp = fopen($downloadPath,'w');
		$fileSize = fwrite($fp, $client->request('GET')->getRawBody());
		fclose($fp);
		return array('directory'=>$downloadDirectory,'filename'=>$downloadFilename,'path'=>$downloadPath,'size'=>$fileSize);
	}
	
	protected function _getAddOnIdFromXml($file){
		$addon_id = null;
		try{
			$xml = XenForo_Helper_DevelopmentXml::scanFile($file);
			if(isset($xml['addon_id'])){
				$addon_id = (string)$xml['addon_id'];
			}
		}
		catch(XenForo_Exception $e){;}
		catch(Exception $e){;}
		finally{;};
		return $addon_id;
	}
	
	/**
	 * Usually GIT puts the content of the file inside a folder;
	 *   auto-intallers expect the XML on the root of the ZIP.
	 *   This method rezips a downloaded ZIP to match with the
	 *   expected directory structure inside the ZIP.
	 * 
	 * @param string zipFile
	 */
	protected function _rezipGitDownloaded($zipFile){
		$addonid = null;
		$pathArr = explode('/',$zipFile);
		$directory = $pathArr;
		$originalZipName = array_pop($directory);
		$zipOut = implode('/',$directory).'/rezipping';
		//Cleaning folder
		XenForo_Helper_File::createDirectory($zipOut);
		AddOnInstallerExtendsGithubBitbucket_Helper::deleteDir($zipOut);
		XenForo_Helper_File::createDirectory($zipOut);
		//Unzipping
		$unzip = new Zend_Filter_Decompress(array(
			'adapter' => 'Zip',
			'options' => array(
				'target' => $zipOut
			)
		));
		$unzip->filter($zipFile);
		//Listing File System nodes to add
		$folderToAdd = glob($zipOut.'/*')[0];
		//Starting rezipper
		$reZipName = str_replace('.','_',$originalZipName).'_rezipped.zip';
		$reZipped = implode('/',$directory).'/rezipped.zip';
		$reZipper = new ZipArchive();
		$reZipper->open($reZipped, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		//Rezipping
		/* Heavily inpired from http://stackoverflow.com/questions/4914750/how-to-zip-a-whole-folder-using-php */
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($folderToAdd),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($files as $name => $file){
			if (!$file->isDir()){
				$fullPath = $file->getRealPath();
				if(!$addonid){
					$addonid = $this->_getAddOnIdFromXml($file);
				}
				$relativePath = substr($fullPath, strpos($fullPath,$folderToAdd)+strlen($folderToAdd)+1);
				$reZipper->addFile($fullPath, $relativePath);
			}
		}
		/* End of "external insight" */
		//Closing rezipper
		$reZipper->close();
		//Deleting zipped folder
		AddOnInstallerExtendsGithubBitbucket_Helper::deleteDir($zipOut);
		//
		$newFileDefinitions = array('directory'=>implode('/',$directory),'filename'=>$reZipName,'path'=>$reZipped,'size'=>filesize($reZipped),'addonid'=>$addonid);
		return $newFileDefinitions;
	}
	
	/**
	 * Does everything when it's to install the add-on from a given URL
	 * 
	 * @return null
	 */
	protected function _getGitFromUrlAndThrowItAsUpload(&$superclassCalled, &$returnValue, $parentClassMethodName, $argsToParentCall, $inputFieldName, $fileFieldName){
		$addOnModel = $this->_getAddOnModel();
		$resourceUrl = $this->_input->filterSingle($inputFieldName, XenForo_Input::STRING);
		$gitInfo = $this->_getGitInfoFromUrl($resourceUrl);
		if($gitInfo['isGit']){
			$zipUrl = $this->_getGitZipUrl($gitInfo);
			if($zipUrl[0]){
				//Clearing request
				$this->_request->setParam($inputFieldName, '');
				//Create client
				$client = XenForo_Helper_Http::getClient($zipUrl[1]);
				//Log in
				$this->_doTheGitLogin($client, $gitInfo['service']);
				//Download
				$extra = $this->_getGitExtraInfo($client, $gitInfo);
				$download = $this->_downloadGitRepository($client, $gitInfo);
				$rezipped = $this->_rezipGitDownloaded($download['path']);
				//Something discovered that must be added
				$gitInfo['addonid'] = $rezipped['addonid'];
				//Log out
				$this->_doTheGitLogout($client, $gitInfo['service']);
				//Save this add-on to local repository state (will check updates later)
				$this->_setAddOnToRepository($gitInfo,$zipUrl[1]);
				//Inializing uploaded files array if unset
				if(is_null($_FILES) || !is_array($_FILES)){
					$GLOBALS['_FILES']=array();
				}
				//Simulate uploaded file
				$_FILES[$fileFieldName] = array(
					'name'=>$rezipped['filename'],
					'type'=>'application/octet-stream',
					'tmp_name'=>$rezipped['path'],
					'error'=>0,
					'size'=>$rezipped['size'],
				);
				//Call superclass while pretending that was a normal upload
				$returnValue = call_user_func_array(array('parent', $parentClassMethodName), $argsToParentCall);
				$superclassCalled = true;
			}
		}
	}
	
	/**
	 * @override AddOnInstaller_ControllerAdmin_AddOn::actionInstallUpgrade/0
	 */
	public function actionInstallUpgrade(){
		$superclassCalled = false;
		$returnValue = null;
		if($this->_isAddOnIdInstalledAndActivated('AddOnInstaller') && $this->isConfirmedPost()){
			$this->_getGitFromUrlAndThrowItAsUpload($superclassCalled,$returnValue,'actionInstallUpgrade',func_get_args(),'resource_url','upload_file');
		}
		if(!$superclassCalled){
			$returnValue = call_user_func_array(array('parent', 'actionInstallUpgrade'), func_get_args());
			$superclassCalled = true;
		}
		return $returnValue;
	}
	/**
	 * @override ThemeHouse_InstallUpgrade_Extend_XenForo_ControllerAdmin_AddOn::actionInstall/0
	 */
	public function actionInstall(){
		$superclassCalled = false;
		$returnValue = null;
		if($this->_isAddOnIdInstalledAndActivated('ThemeHouse_InstallUpgrade') && $this->isConfirmedPost()){
			$this->_getGitFromUrlAndThrowItAsUpload($superclassCalled,$returnValue,'actionInstall',func_get_args(),'server_file','upload_file');
		}
		if(!$superclassCalled){
			$returnValue = call_user_func_array(array('parent', 'actionInstall'), func_get_args());
			$superclassCalled = true;
		}
		return $returnValue;
	}
	/**
	 * @override ThemeHouse_InstallUpgrade_Extend_XenForo_ControllerAdmin_AddOn::actionOutdated/0
	 */
	public function actionOutdated(){
		$returnValue = call_user_func_array(array('parent', 'actionOutdated'), func_get_args());
		if($this->_isAddOnIdInstalledAndActivated('ThemeHouse_InstallUpgrade')){
			$gitAddOnModel = $this->_getGitUpdateDw()->_getCorrectModel();
			$addOnModel = $this->_getAddOnModel();
			$allInstalled = $addOnModel->getAllAddOns();
			$allGit = $gitAddOnModel->getAll();
			$installedFromGit = array_intersect_key($allGit,$allInstalled);
			foreach($installedFromGit as $k=>$v){
				if($v['installedCommitHash']==$v['latestCommitHash']){
					unset($installedFromGit[$k]);
				}
			}
			$toUpdate = array_intersect_key($allInstalled,$installedFromGit);
			foreach($toUpdate as $k=>$v){
				$toUpdate[$k]['canUpgrade']=true;
				$toUpdate[$k]['install_upgrade_filename']=$this->_reassembleGitUrl($installedFromGit[$k]);
			}
			$returnValue->params['addOns']=array_merge($returnValue->params['addOns'],$toUpdate);
		}
		//die(print_r($toUpdate,true));
		//die(print_r($returnValue,true));
		return $returnValue;
	}
	/**
	 * @override ThemeHouse_InstallUpgrade_Extend_XenForo_ControllerAdmin_AddOn::actionUpgrade/0
	 */
	public function actionUpgrade(){
		if(!$this->_isAddOnIdInstalledAndActivated('ThemeHouse_InstallUpgrade') || !$this->_request->isPost()){
			return call_user_func_array(array('parent', 'actionUpgrade'), func_get_args());
		}else{
			$gitAddOnModel = $this->_getGitUpdateDw()->_getCorrectModel();
			$allGit = $gitAddOnModel->getAll();
			$addOnIds = $this->_input->filterSingle('addon_ids', XenForo_Input::ARRAY_SIMPLE);
			foreach($addOnIds as $addOnId){
				if(!array_key_exists($addOnId,$addOnIds)){
					$addOnIds[$addOnId]=$addOnId;
				}
			}
			$toUpdate = array_intersect_key($allGit,$addOnIds);
			if(count($toUpdate)<=0){
				return call_user_func_array(array('parent', 'actionUpgrade'), func_get_args());
			}
			$installed = array();
			foreach($toUpdate as $addOnId){
				$this->_request->clearParams();
				$this->_request->setParam('addon_id', $addOnId['addonid']);
				$addOnId['url'] = $this->_reassembleGitUrl($addOnId);
				$zipUrl = $this->_getGitZipUrl($addOnId);
				//Create client
				$client = XenForo_Helper_Http::getClient($zipUrl[1]);
				//Log in
				$this->_doTheGitLogin($client, $addOnId['service']);
				//Download
				$extra = $this->_getGitExtraInfo($client, $addOnId);
				$download = $this->_downloadGitRepository($client, $addOnId);
				$rezipped = $this->_rezipGitDownloaded($download['path']);
				//Log out
				$this->_doTheGitLogout($client, $addOnId['service']);
				//Saving repo data
				$this->_setAddOnToRepository($addOnId,$zipUrl[1]);
				//Setting rezipped file to install
				$this->_request->setParam('server_file', realpath($rezipped['path']));
				$response = parent::actionInstall();
				if ($response instanceof XenForo_ControllerResponse_View) {
					return $response;
				}
				$installed[$addOnId['addonid']] = true;
			}
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				XenForo_Link::buildAdminLink('add-ons/outdated')
			);
		}
	}
	
	/**
	 * @override AddOnInstaller_ControllerAdmin_AddOn::actionUpdateCheck/0
	 */
	public function actionUpdateCheck(){
		if(!$this->_isAddOnIdInstalledAndActivated('AddOnInstaller')){
			return call_user_func_array(array('parent', 'actionUpdateCheck'), func_get_args());
		}
		return call_user_func_array(array('parent', 'actionUpdateCheck'), func_get_args()); //continue later
	}
	/**
	 * Done:
	 *   AddOnInstaller:
	 *     Install
	 *   ThemeHouse_InstallUpgrade:
	 *     Install
	 *     Updgrade
	 * Pending:
	 *   AddOnInstaller:
	 *     Updater
	 *     Uninstaller
	 *     Time-based update check
	 *     Manual update check
	 *     After-install repository set
	 *   ThemeHouse_InstallUpgrade:
	 *     Uninstaller
	 *     Time-based update check
	 *     After-install repository set
	 *   Own:
	 *     Repository manager
	 * Maybe later:
	 *   Own:
	 *     Log-in to fetch private git repo
	 * Maybe never:
	 *   Own:
	 *     Become a standalone add-on
	 * 
	 * Estimated time: 5 weekends of work (maybe I take some to rest).
	 */
}
