<?php
/*
					COPYRIGHT

Copyright 2007 Sergio Vaccaro <sergio@inservibile.org>

This file is part of JSON-RPC PHP.

JSON-RPC PHP is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

JSON-RPC PHP is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with JSON-RPC PHP; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * This class build a json-RPC Server 1.0
 * http://json-rpc.org/wiki/specification
 *
 * @author sergio <jsonrpcphp@inservibile.org>
 */
 
 /**
  * Modified by James Nicolaysen
  * removed the necessity of a wrapper class.
  * assumes the method called has an included php file with the
  * necessary structure to run itself when called with $params
  */



require_once dirname(__FILE__) . '/../include/globals.php';

require_once dirname(__FILE__) . '/../application/Bootstrap.php';


class jsonRPCServer {
	
	 //----------------------------------
    //  Custom error codes 
    //----------------------------------
    
    const ERROR_UNKNOWN = -1;
    const ERROR_DIGEST_MISSING = -100;
    const ERROR_DIGEST_INVALID = -101;
    const ERROR_CREDENTIALS_INVALID = -102;
    
	 /**
     *  <p>The "realm" string used in authorization headers.</p>
     *  
     *  <p>This string is used to calculate hashes on both the client and server
     *  side. It is also displayed to the user in the password dialog that pops 
     *  up when visiting the page with a browser.</p>
     */
    const AUTH_REALM = 'LG Library API';
	
	//--------------------------------------------------------------------------
    //
    //  Variables
    //
    //--------------------------------------------------------------------------

    /**
     *  Error codes that the client should expect and be able to handle
     *  (except for <code>ERROR_UNKNOWN</code>). All error codes not in this 
     *  list will be reported to clients as <code>ERROR_UNKNOWN</code>,
     *  unless <code>isDebugging</code> is set to <code>true</code>.
     *  
     *  @var array
     */
    protected $_allowedErrorCodes = array(
        self::ERROR_DIGEST_MISSING,
        self::ERROR_DIGEST_INVALID,
        self::ERROR_CREDENTIALS_INVALID
    );
	
	/**
     *  The server instance used to handle the incoming request.
     *  
     *  @var Zend_Json_Server
     */
    protected $_server;
	
	public $authenticated = true;
	public $isDebugging = false;
	
	
	
	//the methods in this array do not require authentication to be accessed, primarily because they are used by the Web version
	public $exemptedMethods = array(
								'login'=>1,
								'getQuizXML'=>1,
								'getProductXML'=>1,
								'setQuizAnswerResult'=>1,
								'getProductExpandedWeb'=>1,
								'track_event'=>1,
								'setFeatureViewed'=>1,
								'updateUserID'=>1,
								'submitWebQuizAttempt'=>1,
								'submitWebTrackingEvents'=>1
							);
	
	function __construct() {
		$this->_server = new Zend_Json_Server();
	}
	/**
	 * This function handle a request binding it to a given object
	 *
	 * @param object $object
	 * @return boolean
	 */
	public function handle() {
		
		
		
		header('ApiVersion: 1.0');
		if (!isset($_COOKIE['lg_app_guid'])) {
			//error_log("NO COOKIE");
			//setcookie("lg_app_guid", uniqid(rand(),true),time()+(10*365*24*60*60));
		} else {
			//error_log("cookie: ".$_COOKIE['lg_app_guid']);
			
		}
		// checks if a JSON-RCP request has been received
		
		if (($_SERVER['REQUEST_METHOD'] != 'POST' && (empty($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'],'application/json')===false)) && !isset($_GET['d'])) {
				echo "INVALID REQUEST";
			// This is not a JSON-RPC request
			return false;
		}
				
		// reads the input data
		if (isset($_GET['d'])) {
			define("WEB_REQUEST",true);
			$request=urldecode($_GET['d']);
			$request = stripslashes($request);
			$request = json_decode($request, true);
			
		} else {
			define("WEB_REQUEST",false);
			//error_log(file_get_contents('php://input'));
			$request = json_decode(file_get_contents('php://input'),true);
			//error_log(print_r(apache_request_headers(),true));
		}
		
		error_log("Method: ".$request['method']);
		if (!isset($this->exemptedMethods[$request['method']])) {
			try {
				$this->authenticate();
			} catch (Exception $e) {
				$this->authenticated = false;
				$this->handleError($e);
			}
		} else {
			//error_log('exempted');
		}
		track_call($request);
		//error_log("RPC Method Called: ".$request['method']);
		
		//include the document containing the function being called
		if (!function_exists($request['method'])) {
			$path_to_file = "./../include/methods/".$request['method'].".php";
			if (file_exists($path_to_file)) {
				include $path_to_file;
			} else {
				$e = new Exception('Unknown method. ('.$request['method'].')', 404, null);
            	$this->handleError($e);
				
			}
		}
		// executes the task on local object
		try {
			
			$result = @call_user_func($request['method'],$request['params']);
			
			if (!is_array($result) || !isset($result['result']) || $result['result']) {
				
				if (is_array($result) && isset($result['result'])) unset($result['result']);
				
				$response = array (
									'jsonrpc' => '2.0',
									'id' => $request['id'],
									'result' => $result,
									
								  );
			} else {
				unset($result['result']);
				
				$response = array (
									'jsonrpc' => '2.0',
									'id' => $request['id'],
									'error' => $result
								   );
			}
			
		} catch (Exception $e) {
			
			$response = array (
								'id' => $request['id'],
								'result' => NULL,
								'error' => $e->getMessage()
								);
			
		}
		// output the response
		if (!empty($request['id'])) { // notifications don't want response
			header('content-type: text/javascript');
			//error_log(@print_r($response));
			if (isset($_GET['d'])) $str_response = $_GET['jsoncallback']."(".str_replace('\/','/',json_encode($response)).")";
			else  $str_response = str_replace('\/','/',json_encode($response));
			
			if ($_SERVER['SERVER_ADDR']=='192.168.1.6') {
				//error_log($str_response);
			}
			echo $str_response;
		}
		
		
		// finish
		return true;
	}
	
	
	public function authenticate()
    {
		
        // Make sure the client passed an authorization header.
        if (!isset($_SERVER['PHP_AUTH_DIGEST']) || empty($_SERVER['PHP_AUTH_DIGEST'])) {
			//error_log("...".$_SERVER['PHP_AUTH_DIGEST']."...");
			error_log("Not Authorized");
			$e = new Exception('Authentication required.', self::ERROR_DIGEST_MISSING, null);
            $this->handleError($e);
        }
        
        $data = $this->_parseDigest();
		
		if (!$data) {
			$e = new Exception('Invalid authorization digest.', self::ERROR_DIGEST_INVALID, null);
            $this->handleError($e);  
        }
		if ($data['username']=="anonymous" || $data['username']=="") {
			Zend_Registry::set('userID', -1);
			return true;
		} else if (strpos($data['username'],"uuid_")===0) {
			Zend_Registry::set('userID', $data['username']);
			return true;
		}
        // Make sure the user exists.
       
       $knownUserData = mysql_query("SELECT * FROM `accounts` WHERE `username`='".mysql_real_escape_string($data['username'])."' OR `email`='".mysql_real_escape_string($data['username'])."'") or die(mysql_error());
        //if (!isset($this->_users[$data['username']])) {
        if (mysql_num_rows($knownUserData)!==1) { 
			$e = new Exception('Invalid credentials', self::ERROR_CREDENTIALS_INVALID, null);
            throw $e;
        }
		
		$knownUserData = mysql_fetch_assoc($knownUserData);
		// Save the username so we can use it for access control checks
        // in other scripts.
        Zend_Registry::set('username', $data['username']);
        
        // Generate the server response.
        $password = $knownUserData['lastKnownPassword']; //$this->_users[$data['username']];
		Zend_Registry::set('userID', $knownUserData['userID']);
		Zend_Registry::set('userIDEncrypted', $knownUserData['userIDEncrypted']);
		//check password & username against thinklg.com data
		
		
        $HA1 = md5($data['username'] . ':' . self::AUTH_REALM . ':' . $password);
		$HA2 = md5($_SERVER['REQUEST_METHOD'] . ':' . $data['uri']);
		$valid_response = md5(
            $HA1              // hash 1
            . ':'             // separator
            . $data['nonce']  // nonce
            . ':'             // separator
            . $data['nc']     // nonce count 
            . ':'             // separator
            . $data['cnonce'] // client nonce
            . ':'             // separator
            . $data['qop']    // quality of protection
            . ':'             // separator
            . $HA2            // hash 2
        );
        // Compare the server response to the client response.
        if ( $data['response'] != $valid_response ) {
            // The username is ok, but the password is wrong.
            $e = new Exception('Invalid credentials.', self::ERROR_CREDENTIALS_INVALID, null);
            throw $e;
        }
    }
	
	    /**
     *  <p>Parses an exception, and determines what error information 
     *  gets passed to the client in the JSON response.</p>
     *  
     *  <p><b>Note:</b> Calling this method will halt script execution.</p>
     *  
     *  @param string $message
     *  @param int $code
     *  @param mixed $data
     */
    public function handleError($e)
    {
        $code = (int) $e->getCode();
        $message = $e->getMessage();
        if ( !in_array($code, $this->_allowedErrorCodes) && $this->isDebugging ) {
            $code = self::ERROR_UNKNOWN;
        }
        
        $this->_server->fault($message, $code, null);
        
        /*
         *  Due to a bug in Zend_Json_Server_Error, we have to modify the error
         *  response to make sure the custom error code makes it to the client.
         */
        $badJson = $this->_server->getResponse()->toJson();
        
        $decoded = Zend_Json::decode($badJson);
        if ( isset($decoded['error']) 
                && isset($decoded['error']['code']) ) {
            $decoded['error']['code'] = $code;
        }
        
        $json = Zend_Json::encode($decoded);
        
        if ($code == self::ERROR_CREDENTIALS_INVALID 
                || $code == self::ERROR_DIGEST_INVALID 
                || $code == self::ERROR_DIGEST_MISSING) {
            $this->sendAuthChallengeHeader();
        }
        
        header('Content-Type: application/json');
        if (isset($_GET['d'])) die($_GET['jsoncallback']."(".$json.")");
        else die($json);
    }
	
	 
    /**
     *  Validates the authorization digest header to make sure it has all
     *  required parts, and then parses it.
     *  
     *  @return An associative array with all the parts, or false if not all parts
     *  were present.
     */
    protected function _parseDigest()
    {
		$digest = $_SERVER['PHP_AUTH_DIGEST'];
		
        // protect against missing data
        $needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
        $data = array();
        $keys = implode('|', array_keys($needed_parts));
    
        //preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $digest, $matches, PREG_SET_ORDER);
		//preg_match_all('@('.$keys.')=(?:(([\'"])(.+?)\3|([A-Za-z0-9/]+)))@', $digest, $matches, PREG_SET_ORDER);
		
		$matches = explode(",", $digest);
		//print_r($matches);
		foreach($matches as $v) {
			$t = explode("=",$v,2);
			$v = trim($t[1],"\" '");
			$k = trim($t[0]);
			$k = explode(" ",$k);
			$k = $k[count($k)-1];
			if (isset($needed_parts[$k])) {
				unset($needed_parts[$k]);
				$data[$k]=$v;
			}
		}
		
        /*
		foreach ($matches as $m) {
			//error_log($m[1].">".$m[3].">".$m[4]);
            $data[$m[1]] = $m[3] ? $m[3] : $m[4];
            unset($needed_parts[$m[1]]);
        }
		*/
		if (!isset($data['username']) || (isset($data['username']) && (trim($data['username'])=='' || $data['username']=='""' || $data['username']=='"' || strtolower($data['username'])=='anonymous'))) {
			
			$data['username']="";
			$needed_parts = false;
		}
		
        return $needed_parts ? false : $data;
    }
	/**
     *  Passes the authentication header to the client.
     */
    public function sendAuthChallengeHeader()
    {
		
		header('Authorization: Digest');
        header('WWW-Authenticate: Digest realm="' . self::AUTH_REALM 
               . '",qop="auth",nonce="' . uniqid() 
			   . '",algorithm="MD5"'
               . '",opaque="' . md5(self::AUTH_REALM) . '"');
		header('HTTP/1.1 200');
    }
}

?>