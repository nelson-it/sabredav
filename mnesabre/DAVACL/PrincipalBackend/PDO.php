<?php
namespace MneSabre\DAVACL\PrincipalBackend;

class PDO extends \Sabre\DAVACL\PrincipalBackend\PDO {

    function __construct ($tableName = 'principals', $groupMembersTableName = 'groupmembers') {
        $this->tableName = $tableName;
        $this->groupMembersTableName = $groupMembersTableName;
    }

    function setPdo ($pdo) {
        $this->pdo = $pdo;
    }
}