<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2022 Eweol<eweol@outlook.com>
 *  (c) 2013 Uwe Steinmann <uwe@steinmann.cx>
 *  All rights reserved
 *
 *  This script is part of the SeedDMS project. The SeedDMS project is
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
 * OIDC extension
 *
 * @author  Eweol <eweol@outlook.com>
 * @package SeedDMS
 * @subpackage  OIDC
 */
class SeedDMS_OIDC extends SeedDMS_ExtBase
{

	/**
	 * Initialization
	 *
	 * Use this method to do some initialization like setting up the hooks
	 * You have access to the following global variables:
	 * $GLOBALS['settings'] : current global configuration
	 * $GLOBALS['settings']['_extensions']['example'] : configuration of this extension
	 * $GLOBALS['LANG'] : the language array with translations for all languages
	 * $GLOBALS['SEEDDMS_HOOKS'] : all hooks added so far
	 */
	function init()
	{
		$GLOBALS['SEEDDMS_HOOKS']['initDMS'][] = new SeedDMS_OIDC_initDMS;
		$GLOBALS['SEEDDMS_HOOKS']['controller']['logout'][] = new SeedDMS_OIDC_Logout;
	}

	function main()
	{
	}
}

/**
 * OIDC extension
 *
 * @author  Eweol <eweol@outlook.com>
 * @package SeedDMS
 * @subpackage  OIDC
 */
class SeedDMS_OIDC_Logout
{
	/**
	 * Hook after Logout from SeedDMS
	 */
	function postLogout($logout)
	{
		$extSettings =  $logout->getParam("settings")->_extensions;
		$oidcSettings = $extSettings['oidc'];

		if (!isset($oidcSettings['oidcEnable'])) {
			return;
		}
		if ($oidcSettings['oidcEnable'] !== "1") {
			return;
		}

		$oidcServer = new SeedDMS_OIDC_Server($oidcSettings);

		$oidcServer->RedirectToOidcLogout();
		exit;
	}
}

/**
 * OIDC extension
 *
 * @author  Eweol <eweol@outlook.com>
 * @package SeedDMS
 * @subpackage  OIDC
 */
class SeedDMS_OIDC_initDMS
{
	/**
	 * Hook after initializing DMS
	 */
	function postInitDMS($array)
	{

		$extSettings =  $array['settings']->_extensions;
		$settings = $array['settings'];
		$dms = $array['dms'];
		$oidcSettings = $extSettings['oidc'];

		if (!isset($oidcSettings['oidcEnable'])) {
			return;
		}
		if ($oidcSettings['oidcEnable'] !== "1") {
			return;
		}

		if ($this->sessionIsValid()) {
			return;
		}

		$oidcServer = new SeedDMS_OIDC_Server($oidcSettings);
		if (!str_contains($_SERVER["REQUEST_URI"], "/.well-known/callback")) {
			$oidcServer->RedirectToOidcLogin();
			return;
		}

		if (!isset($_REQUEST["code"]) || $_REQUEST["code"] === "") {
			$oidcServer->RedirectToOidcLogout();
			return false;
		}

		$oidcServer->GetToken($_REQUEST["code"]);
		$jwt = new SeedDMS_OIDC_JWT($oidcServer->token->id_token);

		if (count($jwt->ClaimsArray) < 1) {
			error_log('[Critical] JWT is not Valid');
			return;
		}

		$db = $dms->getDB();
		if (!class_exists('SeedDMS_Session')) {
			require_once("./inc/inc.ClassSession.php");
		}
		if (!class_exists('SeedDMS_Controller_Login')) {
			require_once("./inc/inc.ClassControllerCommon.php");
			require_once("./controllers/class.Login.php");
		}

		$login = new SeedDMS_Controller_Login($array);

		$username = $jwt->ClaimsArray[$oidcServer->UsernameClaim];
		$fullname = $jwt->ClaimsArray[$oidcServer->FullnameClaim];
		$email 	  = $jwt->ClaimsArray[$oidcServer->EmailClaim];
		$groups	  = $jwt->ClaimsArray[$oidcServer->GroupClaim];
		$userrole = in_array($oidcServer->AdminGroup, $groups) ? 1 : 0;

		$user = $dms->getUserByLogin($username);

		if (is_bool($user) && !$settings->_restricted) {
			$user = $dms->addUser($username, null, $fullname, $email, $settings->_language, $settings->_theme, "", $userrole);
		}

		if (is_bool($user)) {
			error_log('[Critical] User creation failed');
			return;
		}

		$userid = $user->getID();

		$user->clearLoginFailures();

		$lang = $user->getLanguage();
		if (strlen($lang) == 0) {
			$lang = $settings->_language;
			$user->setLanguage($lang);
		}

		$sesstheme = $user->getTheme();
		if (strlen($sesstheme) == 0) {
			$sesstheme = $settings->_theme;
			$user->setTheme($sesstheme);
		}

		$session = new SeedDMS_Session($db);

		// Delete all sessions that are more than 1 week or the configured
		// cookie lifetime old. Probably not the most
		// reliable place to put this check -- move to inc.Authentication.php?
		if ($settings->_cookieLifetime)
			$lifetime = intval($settings->_cookieLifetime);
		else
			$lifetime = 7 * 86400;
		$session->deleteByTime($lifetime);

		if (isset($_COOKIE["mydms_session"])) {
			/* This part will never be reached unless the session cookie is kept,
	         * but op.Logout.php deletes it. Keeping a session could be a good idea
	         * for retaining the clipboard data, but the user id in the session should
	         * be set to 0 which is not possible due to foreign key constraints.
	         * So for now op.Logout.php will delete the cookie as always
	         */
			/* Load session */
			$dms_session = $_COOKIE["mydms_session"];
			if (!$resArr = $session->load($dms_session)) {
				/* Turn off http only cookies if jumploader is enabled */
				setcookie("mydms_session", $dms_session, time() - 3600, $settings->_httpRoot, null, null, !$settings->_enableLargeFileUpload); //delete cookie
				header("Location: " . $settings->_httpRoot . "out/out.Login.php?referuri=" . $refer);
				exit;
			} else {
				$session->updateAccess($dms_session);
				$session->setUser($userid);
			}
		} else {
			// Create new session in database
			$id = $session->create(array('userid' => $userid, 'theme' => $sesstheme, 'lang' => $lang));

			// Set the session cookie.
			if ($settings->_cookieLifetime)
				$lifetime = time() + intval($settings->_cookieLifetime);
			else
				$lifetime = 0;
			setcookie("mydms_session", $id, $lifetime, $settings->_httpRoot, null, null, !$settings->_enableLargeFileUpload);
			$_COOKIE["mydms_session"] = $id;
		}

		$login->callHook('postLogin', $user);
	}

	private function sessionIsValid()
	{
		return isset($_COOKIE["mydms_session"]);
	}
}

/**
 * OIDC extension
 *
 * @author  Eweol <eweol@outlook.com>
 * @package SeedDMS
 * @subpackage  OIDC
 */
class SeedDMS_OIDC_Server
{
	public $Endpoint;
	public $RedirectUri;
	public $UsernameClaim;
	public $FullnameClaim;
	public $EmailClaim;
	public $GroupClaim;
	public $AdminGroup;
	public $token;

	private $clientId;
	private $clientSecret;
	private $configuration;

	function __construct($oidcSettings)
	{
		$this->Endpoint = $oidcSettings["oidcEndpoint"];
		$this->RedirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER["HTTP_HOST"] . "/.well-known/callback";
		$this->clientId = $oidcSettings["oidcClientId"];
		$this->clientSecret = $oidcSettings["oidcClientSecret"];
		$this->UsernameClaim = (!isset($oidcSettings["oidcUsername"])) ? "preferred_username" : $oidcSettings["oidcUsername"];
		$this->FullnameClaim = (!isset($oidcSettings["oidcFullName"])) ? "name" : $oidcSettings["oidcFullName"];
		$this->EmailClaim = (!isset($oidcSettings["oidcMail"])) ? "email" : $oidcSettings["oidcMail"];
		$this->GroupClaim = (!isset($oidcSettings["oidcGroup"])) ? "groups" : $oidcSettings["oidcGroup"];
		$this->AdminGroup = (!isset($oidcSettings["adminGroup"])) ? "admin" : $oidcSettings["adminGroup"];

		$this->configuration =  $this->CurlGetJson($this->Endpoint . ".well-known/openid-configuration");
	}

	public function GetToken($code)
	{
		$data = "grant_type=authorization_code&" .
			"client_id=" . $this->clientId . "&" .
			"client_secret=" . $this->clientSecret . "&" .
			"redirect_uri=" . $this->RedirectUri . "&" .
			"code=" . $code;
		$this->token = $this->CurlPostJson($this->configuration->token_endpoint, $data);
	}

	public function RedirectToOidcLogin()
	{
		header("Location: " . $this->configuration->authorization_endpoint . "?" .
			"client_id=" . $this->clientId . "&" .
			"redirect_uri=" . $this->RedirectUri . "&" .
			"scope=openid+profile+email&" .
			"response_type=code&state=init");
	}

	public function RedirectToOidcLogout()
	{
		header("Location: " . $this->configuration->end_session_endpoint);
	}

	private function CurlGetJson($endpoint)
	{
		$curl = curl_init();

		curl_setopt($curl, CURLOPT_HTTPGET, 1);
		curl_setopt($curl, CURLOPT_URL, $endpoint);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($curl);

		curl_close($curl);

		return json_decode($result);
	}

	private function CurlPostJson($endpoint, $data)
	{
		$curl = curl_init();

		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/x-www-form-urlencoded'
		));
		curl_setopt($curl, CURLOPT_URL, $endpoint);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($curl);

		curl_close($curl);

		return json_decode($result);
	}
}

/**
 * OIDC extension
 *
 * @author  Eweol <eweol@outlook.com>
 * @package SeedDMS
 * @subpackage  OIDC
 */
class SeedDMS_OIDC_JWT
{
	public $ClaimsArray;

	private $token;

	function __construct($jwt)
	{
		$this->token = $jwt;
		$this->getClaims();
	}

	private function getClaims()
	{
		$tokenParts = explode('.', $this->token);

		$payload = base64_decode(urldecode($tokenParts[1]));

		$this->ClaimsArray = json_decode($payload, true);
	}
}
