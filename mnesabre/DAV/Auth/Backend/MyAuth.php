<?php
namespace MneSabre\DAV\Auth\Backend;

use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class MyAuth implements \Sabre\DAV\Auth\Backend\BackendInterface {

    protected $currentUser;
    protected $pdo;
    protected $pdoclients = array();
    protected $principalPrefix = 'principals/';

    function getCurrentUser () {

        return $this->currentUser;
    }

    function getPdo () {

        return $this->pdo;
    }

    function __construct ($pdoclients) {

        $this->pdoclients = $pdoclients;
    }

    function check(RequestInterface $request, ResponseInterface $response) {
        
        $auth = new \Sabre\HTTP\Auth\Basic('Mne Sabredav', $request, $response);
        $userpass = $auth->getCredentials();
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
            throw new \Sabre\DAV\Exception\NotAuthenticated('Username or password does not match ' . $e->getMessage());
        }
        
        $sql = "SELECT * FROM mne_catalog.accessgroup WHERE member = '" . $userpass[0] . "' AND \"group\" = 'erpdav'";
        if ($this->pdo->query($sql) === false)
            throw new \Exception($this->pdo->errorInfo()[2]);
        
        foreach ($this->pdoclients as $p)
           $p->setPDO($this->pdo);
        return [true, $this->principalPrefix . $userpass[0]];
        
        $auth->requireLogin();
        throw new \Exception('no dav access');
        
    }
    
    function challenge(RequestInterface $request, ResponseInterface $response) {
    
    }
}
