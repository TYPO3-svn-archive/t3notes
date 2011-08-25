<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Sonja Scholz <ss@cabag.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */

require_once(PATH_t3lib.'class.t3lib_svbase.php');


/**
 * Service "Lotus Notes authentification" for the "t3notes" extension.
 *
 * @author	Sonja Scholz <ss@cabag.ch>
 * @package	TYPO3
 * @subpackage	tx_t3notes
 */
class tx_t3notes_sv1 extends tx_sv_authbase {
	var $prefixId = 'tx_t3notes_sv1';		// Same as class name
	var $scriptRelPath = 'sv1/class.tx_t3notes_sv1.php';	// Path to this script relative to the extension dir.
	var $extKey = 't3notes';	// The extension key.
	
	/**
	 * Find a user (eg. look up the user record in database when a login is sent)
	 *
	 * @return	mixed		user array or false
	 */
	function getUser()	{
		$user = false;
		global $TYPO3_CONF_VARS;
		
		// Get extension configuration
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3notes']);
		
		// Instanciate a singleton tx_t3notes_auth object - singleton handled by TYPO3
		$tx_t3notes_auth = t3lib_div::makeInstance('tx_t3notes_auth');
		
		if ($this->writeDevLog) 	t3lib_div::devLog('getUser() - got login information from fe ', 'tx_t3notes_sv1', 0, $this->login);
		
		// NTLM login - receive all NTLM params
		$NTLMAuthparams = t3lib_div::GPvar('tx_t3notes_auth');
		
		// decrypt the username param
		$data = base64_decode($NTLMAuthparams["ntlmencrypt"]);
		$NTLMLoginVar = @mcrypt_decrypt(MCRYPT_3DES, substr($TYPO3_CONF_VARS['SYS']['encryptionKey'], 0, 21), $data, MCRYPT_MODE_ECB);

		// First, check the NTLM login
		$this->checkNTLMLogin($NTLMLoginVar);
		
		// check if the cookie to autologin is set
		$this->checkCookieLogin();
		
		if ($this->login['status']=='login' && $this->login['uident'])	{
			
			// authenticate user against notes server, forard cookie to user and save cookie in session
			if($tx_t3notes_auth->authenticate($this->login['uname'], $this->login['uident'], 1, 1, 1)) {
				$user = $this->fetchUserRecord(str_replace(' ', '', $this->login['uname']));
				
				// TODO: check if it should be saved
				if (t3lib_div::_GP('permalogin')) {
					$this->setCookieLogin($this->login['uname'], $this->login['uident']);
				}
				
				if(!is_array($user)) {
					if($response = $this->createFeUser()) {
						$user = $response;
						// login works - add option needed for authUser function
						$user['notesauthenticationok'] = 1;
						if ($this->writeDevLog) 	t3lib_div::devLog('getUser() - User found on Lotus Notes server and created new on T3', 'tx_t3notes_sv1', -1);
					} 
				} else {
					// login works - add option needed for authUser function
					$user['notesauthenticationok'] = 1;
					// User which was found is valid
					if ($this->writeDevLog) 	t3lib_div::devLog('getUser() - User found on Lotus Notes server and T3 user is valid', 'tx_t3notes_sv1', -1, $user);
				}
			} else {
					// Failed login attempt (no username found)
				$this->writelog(255,3,3,2,
					"Login-attempt from %s (%s), username '%s' not found on Lotus Notes server!!",
					Array($this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->login['uname']));	// Logout written to log
				t3lib_div::sysLog(
					sprintf( "Login-attempt from %s (%s), username '%s' not found on Lotus Notes server!", $this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->login['uname'] ),
					'Core',
					0
				);
				if ($this->writeDevLog) 	t3lib_div::devLog('getUser() - User/username not found on Lotus Notes server', 'tx_t3notes_sv1', 3, $this->login);
			}
		}
		
		return $user;
	}

	/**
	 * Authenticate a user (Check various conditions for the user that might invalidate its authentication, eg. password match, domain, IP, etc.)
	 *
	 * @param	array		Data of user.
	 * @return	boolean
	 */
	function authUser($user)	{
		global $TYPO3_CONF_VARS;
		
		$OK = 100;
		
		// Get extension configuration
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3notes']);
		
		if ($this->writeDevLog) 	t3lib_div::devLog('authUser() - user to authenticate ', 'tx_t3notes_sv1', 0, $user);
		
		// NTLM login - receive all NTLM params
		$NTLMAuthparams = t3lib_div::GPvar('tx_t3notes_auth');
		
		// decrypt the username param
		$data = base64_decode($NTLMAuthparams["ntlmencrypt"]);
		$NTLMLoginVar = @mcrypt_decrypt(MCRYPT_3DES, substr($TYPO3_CONF_VARS['SYS']['encryptionKey'], 0, 21), $data, MCRYPT_MODE_ECB);
		
		// First, check the NTLM login
		$this->checkNTLMLogin($NTLMLoginVar);
		
		// check if the cookie to autologin is set
		$this->checkCookieLogin();
		
		if ($this->login['uident'] && $this->login['uname'])	{
			// check if a user could be found in DB or Lotus Notes
			if(is_array($user)) {
				if($user['notesauthenticationok'] == 1) {
					
					if ($this->writeDevLog) 	t3lib_div::devLog('authUser() - user is a valid Lotus Notes and T3 user ', 'tx_t3notes_sv1', -1, $user);
					
					return 200;
				} else {
					if ($this->writeDevLog) 	t3lib_div::devLog('authUser() - user is in TYPO3 only - ask next service ', 'tx_t3notes_sv1', 2);
					
					return 100;
				}
			} else {
				if ($this->writeAttemptLog) {
					$this->writelog(255,3,3,1,
						"Lotus Notes Login-attempt from %s (%s), username '%s', password not accepted!",
						Array($this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->login['uname']));
					t3lib_div::sysLog(
						sprintf( "Lotus Notes Login-attempt from %s (%s), username '%s', password not accepted!", $this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->login['uname'] ),
						'Core',
						0
					);
				}
				if ($this->writeDevLog) 	t3lib_div::devLog('Password not accepted by Lotus Notes server: '.$this->login['uident'], 'tx_t3notes_sv1', 3);
			}
		}
		
		return $OK;
	}
	
	/**
	 * check if there is a valid NTLM login
	 * @return	array if there is a valid NTLM fe_user - false otherwise
	 */
	function checkNTLMLogin($NTLMLoginVar) {
		// First, check the NTLM login
		if($doNTLMAuth = t3lib_div::GPvar('tx_t3notes_auth')) {
			if($doNTLMAuth['NTLMAuth'] == 1 && !empty($NTLMLoginVar)) {
				if ($this->writeDevLog) 	t3lib_div::devLog('checkNTLMLogin() - NTLM Login activated for user: '.$NTLMLoginVar, 'tx_t3notes_sv1', 0);
				
				// NTLM Login is activated - search for a matching user
				$user = $this->fetchUserRecord(str_replace(' ', '', $NTLMLoginVar));
				if(!is_array($user)) {
					if ($this->writeDevLog) 	t3lib_div::devLog('checkNTLMLogin() - NTLM Login user NOT found as fe_user: '.$NTLMLoginVar, 'tx_t3notes_sv1', 1);
					return false;
				} else {
					if ($this->writeDevLog) 	t3lib_div::devLog('checkNTLMLogin() - NTLM Login user  found as fe_user: '.$NTLMLoginVar, 'tx_t3notes_sv1', -1, $user);
					$this->login['status'] ='login';
					$this->login['uident'] = $user['password'];
					$this->login['uname'] = $NTLMLoginVar;
					return $user;
				}
			}
		} 
		
		return false;
	}
	
	/**
	 * Checks for a stored encrypted username and resolves it.
	 *
	 * @return void
	 */
	protected function checkCookieLogin() {
		if (empty($this->extConf['enablePermanentCookie'])) {
			return;
		}
		
		$loginInfo = $_COOKIE['tx_t3notes_loginInfo'];
		
		if (!empty($loginInfo) && empty($this->login['status']) && empty($_COOKIE['tx_t3notes_loggedIn'])) {
			$loginInfo = base64_decode($loginInfo);
			$loginInfo = mcrypt_decrypt(MCRYPT_3DES, substr($TYPO3_CONF_VARS['SYS']['encryptionKey'], 0, 21), $loginInfo, MCRYPT_MODE_ECB);
			$loginInfo = json_decode(trim($loginInfo), true);
			
			if (is_array($loginInfo) && isset($loginInfo['u']) && isset($loginInfo['p'])) {
				if ($this->writeDevLog) t3lib_div::devLog('checkCookieLogin() - Cookie Login user found', 'tx_t3notes_sv1', -1, $user);
				$this->login['status'] = 'login';
				$this->login['uident'] = $loginInfo['p'];
				$this->login['uname'] = $loginInfo['u'];
				
				// set a cookie so we don't re auth all the time
				setcookie('tx_t3notes_loggedIn', 1, 0, '/', $GLOBALS['TSFE']->baseUrlWrap(''));
			}
		}
	}
	
	/**
	 * Store the notes username in encrypted form to a cookie with maximum lifetime.
	 *
	 * @param string $username The notes username.
	 * @return void
	 */
	protected function setCookieLogin($username, $password) {
		if (empty($this->extConf['enablePermanentCookie'])) {
			return;
		}
		
		$iv_size = mcrypt_get_iv_size(MCRYPT_3DES, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$loginInfo = array('u' => $username, 'p' => $password);
		$loginInfo = json_encode($loginInfo);
		$loginInfo = mcrypt_encrypt(MCRYPT_3DES, substr($TYPO3_CONF_VARS['SYS']['encryptionKey'], 0, 21), $loginInfo, MCRYPT_MODE_ECB, $iv);
		$loginInfo = base64_encode($loginInfo);
		setcookie('tx_t3notes_loginInfo', $loginInfo, time() + (86400 * 365 * 10), '/', $GLOBALS['TSFE']->baseUrlWrap(''));
	}
	
	/**
	 * creates a fe_user record if the user is authenticated by Lotus Notes server and not in DB
	 * @return bool	
	 */
	function createFeUser() {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*', 
				'fe_users', 
				'username=\''.str_replace(' ', '', $this->login['uname']).'\' 
					AND deleted=0');
		
		if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			// Get extension configuration
			$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3notes']);
			
			list($firstName, $lastName) = explode(' ', $this->login['uname']);
			
			// insertFields to write in the DB
			$insertFields = array(
				'pid' => $this->extConf['defaultUserPID'],
				'username' => str_replace(' ', '', $this->login['uname']),
				'password' => rand(1000000000,9999999999),
				'usergroup' => $this->extConf['defaultUserGroup'],
				'tstamp' => $GLOBALS['EXEC_TIME'],
				'first_name' => $firstName,
				'last_name' => $lastName
			);
			
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('fe_users', $insertFields);
			
			if($GLOBALS['TYPO3_DB']->sql_affected_rows() == 1) {
				$uidOfNewFeUser = $GLOBALS['TYPO3_DB']->sql_insert_id();
				$newFeUserRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'*', 
					'fe_users', 
					'uid='.$uidOfNewFeUser);
				
				$newFeUserRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($newFeUserRes);
				$GLOBALS['TYPO3_DB']->sql_free_result($newFeUserRes);
					
				return $newFeUserRow;
			} else {
				if ($this->writeAttemptLog) {
					$this->writelog(255,3,3,1,
						"Lotus Notes Login-attempt from %s (%s), username '%s', fe_user could not be created!",
						Array($this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->login['uname']));
					t3lib_div::sysLog(
						sprintf( "Lotus Notes Login-attempt from %s (%s), username '%s', fe_user could not be created!", $this->authInfo['REMOTE_ADDR'], $this->authInfo['REMOTE_HOST'], $this->login['uname'] ),
						'Core',
						0
					);
				}
				if ($this->writeDevLog) 	t3lib_div::devLog('createFeUser() - fe_user could not be created by Lotus Notes login service: '.$this->login['uident'], 'tx_t3notes_sv1', 2);
				return false;
			}
		} else {
			if ($this->writeDevLog) 	t3lib_div::devLog('createFeUser() - fe_user could not be created, because it already exists disabled or by time limitation ', 'tx_t3notes_sv1', 2);
			return false;
		}
	}
	
	function fetchUserRecord($username, $extraWhere='', $dbUserSetup='')	{
		$user = FALSE;
		
		// removed unwanted acsi signs from the end of the username if some exists
		$username = chop($username);
		
		$usernameClause = $username ? ('username ='.$GLOBALS['TYPO3_DB']->fullQuoteStr($username, $dbUser['table'])) : '';

		if ($username || $extraWhere)	{

				// Look up the user by the username and/or extraWhere:
			$dbres = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'*',
						'fe_users',
							$usernameClause.
							$extraWhere
					);

			if ($dbres)	{
				$user = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbres);
				$GLOBALS['TYPO3_DB']->sql_free_result($dbres);
			}
		}
		return $user;
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3notes/sv1/class.tx_t3notes_sv1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3notes/sv1/class.tx_t3notes_sv1.php']);
}

?>