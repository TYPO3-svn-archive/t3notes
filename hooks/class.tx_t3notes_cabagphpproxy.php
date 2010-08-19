<?php

class tx_t3notes_cabagphpproxy {
	function processCurlAdditionalPostParams($postParams) {
		return $postParams;
	}
	
	function processCurlResource($curlResource) {
		
		$tx_t3notes_auth = t3lib_div::makeInstance('tx_t3notes_auth');
		
		$this->cookie = $tx_t3notes_auth->getRequestCOOKIE();
		curl_setopt($curlResource, CURLOPT_COOKIE, $this->cookie);
		
		return $curlResource;
	}
	
	function processData($data,$dataAdditionalInfos) {
		
		return $data;
	}
}

?>
