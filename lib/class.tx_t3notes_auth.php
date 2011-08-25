<?php

/**
 * authentication class to authenticate every website user against Lotus Notes server
 * - if there is no fe_login - anonymous user is used and cookie will be saved in cache_hash
 * - if there is a fe_login request - username/password is used and cookie will be forwarded to user and saved in fe_user session
 * 
 * with singleton technology class object exists only once!
 */
class tx_t3notes_auth implements t3lib_Singleton {
	private $cookieName;
	private $cookieValue;
	private $cookieExpires = 0;
	private $cookiePath;
	private $cookieDomain;
	
	private $extConf;
	private $writeDevLog = false;
	
	/**
	 * get cookie from user session or order new cookie from server
	 */
	function __construct() {
		global $TYPO3_CONF_VARS;
		if($TYPO3_CONF_VARS['SYS']['enable_DLOG']) {
			$this->writeDevLog = true;
		}
		
		// Get extension configuration
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3notes']);
		
		// Get information from fe_user session
		$this->getFromUserSession();
	}
	
	/**
	 * authenticate user against lotus notes server
	 *
	 * @param	string	username
	 * @param	string	password
	 * @param	bool	should the notes cookie be forwarded to the browser/user?
	 * @param	bool	should the notes cookie be saved in the fe_user session?
	 * @param	bool	should the user login info be saved in the fe_user session?
	 */
	function authenticate($username, $password, $forwardCookieToUser, $saveCookieInSession, $saveLoginInfoInSession) {
		// params to send in the curl post request
		$postParams = array(
			'Password' => $password,
			'Username' => $username,
			);
		
		// create curl ressource
		$curlRessource = curl_init();
		
		// set url to go to
		curl_setopt($curlRessource, CURLOPT_URL, $this->extConf['authenticateURL']);
		// always do a POST request
		curl_setopt($curlRessource, CURLOPT_POST, true);
		// return the transfer on success, false on fail
		curl_setopt($curlRessource, CURLOPT_RETURNTRANSFER, true);
		// TRUE to include the header in the output. 
		curl_setopt($curlRessource, CURLOPT_HEADER, 1); 
		// add POST params
		curl_setopt($curlRessource, CURLOPT_POSTFIELDS, $postParams);
		
		// dont verfy ssl
		curl_setopt($curlRessource, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curlRessource, CURLOPT_SSL_VERIFYPEER, 0);
		
		// set max timeout for the curl request
		if(empty($this->extConf['curlTimeout'])) {
			$this->extConf['curlTimeout'] = 4;
		}
		curl_setopt ($curlRessource, CURLOPT_TIMEOUT, $this->extConf['curlTimeout']);
		
		// exec the request and get the result
		$curlResult = curl_exec($curlRessource);
		
		// check the response
		if($curlResult === false) {
			if ($this->writeDevLog) 	t3lib_div::devLog('authenticate() - received no/error curl result #'.curl_errno($curlRessource).' Message:'.curl_error($curlRessource), 'tx_t3notes_auth', 3, curl_getinfo($curlRessource));
			return false;
		} 
		
		// Parse the response message
		$curlResult = $this->httpParseHeaders($curlResult);
		
		if ($this->writeDevLog) 	t3lib_div::devLog('authenticate() - curl result', 'tx_t3notes_auth', 0, $curlResult);
		
		// Parse the cookie if there is any, otherwise login didn't success
		if(!$curlResult['Set-Cookie']) {
			return false;
		} 
		
		$cookieParams = $this->httpParseCookie($curlResult['Set-Cookie']);
		if ($this->writeDevLog) 	t3lib_div::devLog('authenticate() - cookie params from Lotus Notes server', 'tx_t3notes_auth', 0, $cookieParams);
		
		// save cookie informations in class vars
		$this->cookieName = $cookieParams['name'];
		$this->cookieValue = $cookieParams['value'];
		if(empty($this->extConf['cookieDomain'])) {
			$this->cookieDomain = $cookieParams['domain'];
		} else {
			$this->cookieDomain = $this->extConf['cookieDomain'];
		}
		$this->cookiePath = $cookieParams['path'];
		$this->cookieExpires = $cookieParams['expires'];
		
		// close cURL resource, and free up system resources
		curl_close($curlRessource);
		
		// only for individual logins needed
		if($forwardCookieToUser) {
			if(!$this->forwardCookieToUser()) {
				if ($this->writeDevLog) 	t3lib_div::devLog('authenticate() - cookie could not be forwarded to user', 'tx_t3notes_auth', 2, array($this->cookieName, $this->cookieValue, $this->cookieExpires, $this->cookiePath, $this->cookieDomain));
				return false;
			}
		}
		
		// only for individual logins needed
		if($saveCookieInSession) {
			if ($this->writeDevLog) 	t3lib_div::devLog('authenticate() - saved cookie in fe_user session', 'tx_t3notes_auth', -1, array($this->cookieName, $this->cookieValue, $this->cookieExpires, $this->cookiePath, $this->cookieDomain));
			$this->saveCookieInSession();
		}
		
		// if number of seconds are configured (not 0) save the username/password/tstamp in the fe_user session for the auto-login feature
		if($this->extConf['autoLoginWaitSeconds'] && $saveLoginInfoInSession) {
			$tstamp = time();
			if ($this->writeDevLog) 	t3lib_div::devLog('authenticate() - saved auto-login info in fe_user session', 'tx_t3notes_auth', -1, array($username, $password, $tstamp));
			$this->saveLoginInfoInSession($username, $password, $tstamp);
		}
		
		return true;
	}
	
	
	/**
	 * forwards cookie to user - calls setcookie
	 *
	 * @return bool	true on success, false on fail
	 */
	function forwardCookieToUser() {
		if ($this->writeDevLog) 	t3lib_div::devLog('forwardCookieToUser(), forward current cookie to user', 'tx_t3notes_auth', -1, array($this->cookieName, $this->cookieValue, $this->cookieExpires, $this->cookiePath, $this->cookieDomain));
		
		return setrawcookie($this->cookieName, $this->cookieValue, $this->cookieExpires, $this->cookiePath, $this->cookieDomain);
	}
	
	/*******************************************
	 *
	 * Session related functions
	 *
	 *******************************************/
	
	/**
	 * get the cookie informations user session
	 * @return bool 
	 */
	function getUserCookieFromSession() {
		/* // check if user is realy logged in
		if(empty($GLOBALS['TSFE']->fe_user->user['uid'])) {
			return false;
		}
		
		// get perhaps existing cookie informations from user session
		$GLOBALS['TSFE']->fe_user->fetchSessionData();
		$this->userSession = $GLOBALS["TSFE"]->fe_user->getKey('ses', 'tx_t3notes_auth');
		
		if ($this->writeDevLog) 	t3lib_div::devLog('getUserCookieFromSession() - user session', 'tx_t3notes_auth', 0, $this->userSession);
 */
 		$this->getFromUserSession();
		
		if(is_array($this->userSession) && !empty($this->userSession)) {
			
			$this->cookieName = $this->userSession['name'];
			$this->cookieValue = $this->userSession['value'];
			if(empty($this->extConf['cookieDomain'])) {
				$this->cookieDomain = $this->userSession['domain'];
			} else {
				$this->cookieDomain = $this->extConf['cookieDomain'];
			}
			$this->cookiePath = $this->userSession['path'];
			$this->cookieExpires = $this->userSession['expires'];
			
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * request the informations from class vars to user session
	 */
	function getFromUserSession() {
		// check if user is realy logged in
		if(empty($GLOBALS['TSFE']->fe_user->user['uid'])) {
			return false;
		}
		
		// get perhaps existing cookie informations from user session
		$GLOBALS['TSFE']->fe_user->fetchSessionData();
		$this->userSession = $GLOBALS["TSFE"]->fe_user->getKey('ses', 'tx_t3notes_auth');
		
		if ($this->writeDevLog) 	t3lib_div::devLog('getFromUserSession() - user session', 'tx_t3notes_auth', 0, $this->userSession);
	}
	 
	/**
	 * save the cookie informations from class vars to user session
	 * @return always true
	 */
	function saveToUserSession() {
		if ($this->writeDevLog) 	t3lib_div::devLog('saveToUserSession() - user session', 'tx_t3notes_auth', 0, $this->userSession);
		
		$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_t3notes_auth', $this->userSession);
		$GLOBALS['TSFE']->fe_user->storeSessionData();
	}
	
	/**
	 * save the cookie informations in the fe_user session
	 *
	 */
	function saveCookieInSession() {
		$this->userSession['name'] = $this->cookieName;
		$this->userSession['value'] = $this->cookieValue;
		if(empty($this->extConf['cookieDomain'])) {
			$this->userSession['domain'] = $this->cookieDomain;
		} else {
			$this->userSession['domain'] = $this->extConf['cookieDomain'];
		}
		$this->userSession['path'] = $this->cookiePath;
		$this->userSession['expires'] = $this->cookieExpires;
		
		$this->saveToUserSession();
	}
	
	/**
	 * save the login informations in the fe_user session - needed for auto-login feature
	 *
	 * @param	string	username
	 * @param	string	password
	 * @param	int		tstamp of login
	 */
	function saveLoginInfoInSession($username, $password, $tstamp) {
		$this->userSession['username'] = $username;
		$this->userSession['password'] = $password;
		$this->userSession['tstamp'] = $tstamp;
		
		$this->saveToUserSession();
	}
	
	/*******************************************
	 *
	 * Parse helper functions
	 *
	 *******************************************/
	
	/**
	 * parse the response of the curl request into header and body
	 */
	function httpParseHeaders($headers=false){
		if($headers === false){
			return false;
			}
		$headers = str_replace("\r","",$headers);
		$headers = explode("\n",$headers);
		foreach($headers as $value){
			$header = explode(": ",$value);
			if($header[0] && !$header[1]){
				$headerdata['status'] = $header[0];
				}
			elseif($header[0] && $header[1]){
				$headerdata[$header[0]] = $header[1];
				}
			}
		return $headerdata;
    }
	
	/**
	 * parse the cookie information in the given header
	 */
	function httpParseCookie($setCookieValue) {
        $returnCookieParams = array();
		$cookieParams = explode('; ', $setCookieValue);
		
		foreach($cookieParams as $key=>$param) {
			if($key == 0) {
				$stringLength = strlen($param);
				$splitPosition = strpos($param, '=');
				$returnCookieParams['name'] = substr($param, 0, $splitPosition);
				$returnCookieParams['value'] = substr($param, $splitPosition+1, $stringLength);
			} else {
				$nameAndValue = explode('=',$param);
				$returnCookieParams[$nameAndValue[0]] = $nameAndValue[1];
			}
		}
		
		return $returnCookieParams;
	}
	
	/*******************************************
	 *
	 * Caching related
	 *
	 *******************************************/

	/**
	 * Stores the cookie information in the cache_hash table
	 * needed to save the anonymous cookie
	 * 
	 * @return	void
	 */
	public function saveCookieInCacheTable() {
		$expireTime = time()+$this->extConf['expireAfterSeconds'];
		
		// content to save
		$params = array();
		$params['name'] = $this->cookieName;
		$params['value'] = $this->cookieValue;
		
		if(empty($this->extConf['cookieDomain'])) {
			$params['domain'] = $this->cookieDomain;
		} else {
			$params['domain'] = $this->extConf['cookieDomain'];
		}
		
		$params['path'] = $this->cookiePath;
		$params['expires'] = $this->cookieExpires;
		$params['cacheValidUntil'] = $expireTime;
		
		$hash = md5('t3notes:'.implode(';',$params));
		
		// insertFields to write in the DB
		$insertFields = array(
			'hash' => $hash,
			'content' => serialize($params),
			'ident' => 'EXT:t3notes',
			'tstamp' => $GLOBALS['EXEC_TIME']
		);
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_hash', 'hash='.$GLOBALS['TYPO3_DB']->fullQuoteStr($hash, 'cache_hash'));
		$GLOBALS['TYPO3_DB']->exec_INSERTquery('cache_hash', $insertFields);
	}

	/**
	 * Returns string value stored for the hash string in the cache "cache_hash"
	 * Can be used to retrieved a cached value
	 *
	 * @return	bool	return true if a cookie is given and saved in class vars, fals otherwise
	 */
	public function getCookieFromCacheTable() {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'content, hash', 
				'cache_hash', 
				'ident=\'EXT:t3notes\'');
		
		if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			return false;
		} else {
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			$cookieContent = unserialize($row['content']);
			
			if ($this->writeDevLog) 	t3lib_div::devLog('getCookieFromCacheTable() - received cookie from cache_hash', 'tx_t3notes_auth', 0, $cookieContent);
			
			// check if the cache of the cookie in the DB is still valid
			if($cookieContent['cacheValidUntil'] > time()) {
				// is still valid
				if ($this->writeDevLog) 	t3lib_div::devLog('getCookieFromCacheTable() - valid cookie', 'tx_t3notes_auth', 0, $cookieContent);
				
				$this->cookieName = $cookieContent['name'];
				$this->cookieValue = $cookieContent['value'];
				if(empty($this->extConf['cookieDomain'])) {
					$this->cookieDomain = $cookieContent['domain'];
				} else {
					$this->cookieDomain = $this->extConf['cookieDomain'];
				}
				$this->cookiePath = $cookieContent['path'];
				$this->cookieExpires = $cookieContent['expires'];
				
				// Return true to avoid a new request to the Lotus Notes Server
				return true;
			} else {
				if ($this->writeDevLog) 	t3lib_div::devLog('getCookieFromCacheTable() - cookie expired - create new!', 'tx_t3notes_auth', 1);
				
				// Delete the old cache from DB
				$GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_hash', 'hash='.$GLOBALS['TYPO3_DB']->fullQuoteStr($row['hash'], 'cache_hash'));
				
				// Return fals to create a new request to the Lotus Notes server
				return false;
			}
		}
		
		return false;
	}
	
	
	/**
	 * get cookie for anonymous user and save it into cache_hash
	 */
	function getAnonymousCookieInformation() {
		// get anonymous cookie informations from cache_md5params table
		if($this->getCookieFromCacheTable()) {
			// received anonymous cookie from cache_hash and saved in class variables for future requests
		} else {
			// authenticate as anonymous user
			if(!$this->authenticate($this->extConf['anonymousUsername'],$this->extConf['anonymousPassword'],false,false,false)) {
				if ($this->writeDevLog) 	t3lib_div::devLog('getAnonymousCookieInformation() - Default Notes User is not valid', 'tx_t3notes_auth', 3);
				//throw new Exception('Default Notes User is not valid');
			}
			
			// save the given anonymous cookie in the DB
			$this->saveCookieInCacheTable();
		}
	}
	
	/**
	 * getRequestCOOKIE()
	 * return the current valid cookie from fe_user session
	 * @return	string	cookie for request
	 */
	function getRequestCOOKIE() {
		if(empty($this->cookieValue)) {
			// get anonymous cookie if no individual cookie exists
			if(!$this->getUserCookieFromSession()) {
				if ($this->writeDevLog) 	t3lib_div::devLog('__construct() - received NO user cookie from session, auth as anonymous', 'tx_t3notes_auth', 0);
				$this->getAnonymousCookieInformation();
			} else {
				if ($this->writeDevLog) 	t3lib_div::devLog('__construct() - received user cookie from session', 'tx_t3notes_auth', 0, $this->userSession);
			}
		}
		
		if(empty($this->cookieName) || empty($this->cookieValue)) {
			// no cookie could be found - probably the lotus notes server request for the anonymous user failed
			if ($this->writeDevLog) 	t3lib_div::devLog('getRequestCOOKIE() - NO cookieString for request found - return false instead', 'tx_t3notes_auth', 3);
			return false;
		}
		
		//$cookieString = $this->cookieName.'='.'AAECAzRDMDc2NzY2NEMwNzZFNkVDTj1hbm9ueW0gVGVzdCBDYWIvT1U9S1NML089R1NETkVUwGg090A3vGJ0a17YbDq8c7txqMw=';
		
		$cookieString = $this->cookieName.'='.$this->cookieValue;
		
		if ($this->writeDevLog) 	t3lib_div::devLog('getRequestCOOKIE() - cookieString for request', 'tx_t3notes_auth', 1, array($cookieString));
		return $cookieString;
	}
	
	/**
	 * request()
	 * send a request to Lotus Notes server mit current auth settings
	 * 
	 * @param	string	url to request
	 * @param	array	POST parameter to sent in the request - only used in method POST
	 * @param	int		method for the request - GET=2, POST=1
	 */
	function request($url,$POSTparameter,$method=1) {
		
		// create curl ressource
		$curlResource = curl_init();
		
		// set url to go to
		curl_setopt($curlResource, CURLOPT_URL, $url);
		
		if($method === 1) {
			// do a POST request
			curl_setopt($curlResource, CURLOPT_POST, true);
			// add POST params
			curl_setopt($curlResource, CURLOPT_POSTFIELDS, $POSTparameter);
		} else {
			// do a GET request - CURLOPT_POSTFIELDS/params not possible here
			curl_setopt($curlResource, CURLOPT_HTTPGET, true);
		}
		
		// don't add the HTTP Header to the output
		curl_setopt($curlResource, CURLOPT_HEADER, 0);
		
		// preserve output header for debugging
		curl_setopt($curlResource, CURLINFO_HEADER_OUT, 1);
	
		// return the transfer on success, false on fail
		curl_setopt($curlResource, CURLOPT_RETURNTRANSFER, true);
		
		curl_setopt ($curlResource, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt ($curlResource, CURLOPT_SSL_VERIFYPEER, 0);
		
		// set max timeout for the curl request
		if(empty($this->extConf['curlTimeout'])) {
			$this->extConf['curlTimeout'] = 4;
		}
		curl_setopt ($curlResource, CURLOPT_TIMEOUT, $this->extConf['curlTimeout']);
		
		// get the COOKIE for the current user
		$cookieString = $this->getRequestCOOKIE();
		if($cookieString === false) {
			// no cookie could be found - probably the lotus notes server request for the anonymous user failed
			if ($this->writeDevLog) 	t3lib_div::devLog('request() - NO cookieString for request received - return false for the whole request', 'tx_t3notes_auth', 3, curl_getinfo($curlResource));
			return false;
		}
		
		// ADD the current valid COOKIE for the current user
		curl_setopt($curlResource, CURLOPT_COOKIE, $this->getRequestCOOKIE());
		
		// exec the request and get the result
		$curlResult = curl_exec($curlResource);
		
		// check the response
		if($curlResult === false) {
			if ($this->writeDevLog) 	t3lib_div::devLog('request() - no valid curlResult', 'tx_t3notes_auth', 1,curl_getinfo($curlResource));
			return false;
		} 
		
		//print_r(curl_getinfo($curlResource));
		
		if ($this->writeDevLog) 	t3lib_div::devLog('request() - valid result from curl request','tx_t3notes_auth', -1,$curlResult);
		
		return $curlResult;
	} 
	
	function checkT3NotesAutoLogin($content, $conf) {
		// is there a user logged in?
		$this->getFromUserSession();
		
		if(is_array($this->userSession)) {
			if ($this->writeDevLog) 	t3lib_div::devLog('checkT3NotesAutoLogin - got fe_user session', 'tx_t3notes_auth',0,$this->userSession);
		} else {
			if ($this->writeDevLog) 	t3lib_div::devLog('checkT3NotesAutoLogin - no fe_user session received', 'tx_t3notes_auth', 0,$this->userSession);
		}
		
		// is there a time in the session set?
		if(isset($this->userSession['tstamp']) && isset($this->userSession['username']) && isset($this->userSession['password'])) {
			if(($this->userSession['tstamp']+$this->extConf['autoLoginWaitSeconds']) < time()) {
				if ($this->writeDevLog) 	t3lib_div::devLog('checkT3NotesAutoLogin() - tstamp despired', 'tx_t3notes_auth', 0,$this->userSession);
				$this->authenticate($this->userSession['username'], $this->userSession['password'],1 , 1, 1);
			} else {
				if ($this->writeDevLog) 	t3lib_div::devLog('checkT3NotesAutoLogin() - tstamp is still valid', 'tx_t3notes_auth', -1,$this->userSession);
			}
		} else {
			if ($this->writeDevLog) 	t3lib_div::devLog('checkT3NotesAutoLogin - autologin infos are not set', 'tx_t3notes_auth', 0);
		}
	}
}

?>
