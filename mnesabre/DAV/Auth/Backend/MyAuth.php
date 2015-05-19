<?php

namespace MneSabre\DAV\Auth\Backend;

class MyAuth implements \Sabre\DAV\Auth\Backend\BackendInterface {
	
	protected $currentUser;

	function getCurrentUser() {
		return $this->currentUser;
	}
	
	function authenticate(\Sabre\DAV\Server $server, $realm) {

		$auth = new \Sabre\HTTP\Auth\Basic ( $realm, $server->httpRequest, $server->httpResponse );
		$userpass = $auth->getCredentials ( $server->httpRequest );
		if (! $userpass) {
			if (isset ( $_SERVER ["PHP_AUTH_USER"] ) && isset ( $_SERVER ["PHP_AUTH_PW"] ))
				$userpass = array (
						0 => $_SERVER ["PHP_AUTH_USER"],
						1 => $_SERVER ["PHP_AUTH_PW"] 
				);
		}
		
		if (! $userpass) {
			$auth->requireLogin ();
			throw new \Sabre\DAV\Exception\NotAuthenticated ( 'MneAuth: no password' );
		}
		
		$pdo = null;
		try {
			$pdo = new \PDO ( 'pgsql:dbname=erpdb', $userpass [0], $userpass [1] );
		} catch ( \PDOException $e ) {
			$auth->requireLogin ();
			throw new \Sabre\DAV\Exception\NotAuthenticated ( 'Username or password does not match' );
		}
		
		$sql = "SELECT * FROM mne_catalog.accessgroup WHERE member = '" . $userpass [0] . "' AND \"group\" = 'dav'";
		if ($pdo->query ( $sql ) === false)
			throw new \Exception ( $pdo->errorInfo ()[2] );
		
		foreach ( $pdo->query ( $sql ) as $row ) {
			$this->currentUser = $userpass [0];
			return true;
		}
		
		throw new \Exception ( 'no dav access' );
	}
}
