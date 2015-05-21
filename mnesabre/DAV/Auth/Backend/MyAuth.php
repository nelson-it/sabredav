<?php
namespace MneSabre\DAV\Auth\Backend;

class MyAuth implements \Sabre\DAV\Auth\Backend\BackendInterface {

    protected $currentUser;

    protected $pdo;

    protected $pdoclients = array();

    function getCurrentUser () {

        return $this->currentUser;
    }

    function getPdo () {

        return $this->pdo;
    }

    function __construct ($pdoclients) {

        $this->pdoclients = $pdoclients;
    }

    function authenticate (\Sabre\DAV\Server $server, $realm) {

        $auth = new \Sabre\HTTP\Auth\Basic($realm, $server->httpRequest, $server->httpResponse);
        $userpass = $auth->getCredentials($server->httpRequest);
        if (! $userpass) {
            if (isset($_SERVER["PHP_AUTH_USER"]) && isset($_SERVER["PHP_AUTH_PW"]))
                $userpass = array(
                        0 => $_SERVER["PHP_AUTH_USER"],
                        1 => $_SERVER["PHP_AUTH_PW"]
                );
        }
        
        if (! $userpass) {
            $auth->requireLogin();
            throw new \Sabre\DAV\Exception\NotAuthenticated('MneAuth: no password');
        }
        
        $this->pdo = null;
        try {
            $this->pdo = new \PDO('pgsql:dbname=erpdb', $userpass[0], $userpass[1]);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
        catch (\PDOException $e) {
            $auth->requireLogin();
            throw new \Sabre\DAV\Exception\NotAuthenticated('Username or password does not match');
        }
        
        $sql = "SELECT * FROM mne_catalog.accessgroup WHERE member = '" . $userpass[0] . "' AND \"group\" = 'dav'";
        if ($this->pdo->query($sql) === false)
            throw new \Exception($this->pdo->errorInfo()[2]);
        
        foreach ($this->pdo->query($sql) as $row) {
            $this->currentUser = $userpass[0];
            foreach ($this->pdoclients as $p)
                $p->setPDO($this->pdo);
            return true;
        }
        
        throw new \Exception('no dav access');
        
    }
}
