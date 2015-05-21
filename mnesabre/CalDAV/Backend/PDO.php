<?php
namespace MneSabre\CalDAV\Backend;

class PDO extends \Sabre\CalDAV\Backend\PDO {

    function __construct ($calendarTableName = 'calendars', $calendarObjectTableName = 'calendarobjects', $calendarChangesTableName = 'calendarchanges', $calendarSubscriptionsTableName = "calendarsubscriptions", 
            $schedulingObjectTableName = "schedulingobjects") {

        $this->calendarTableName = $calendarTableName;
        $this->calendarObjectTableName = $calendarObjectTableName;
        $this->calendarChangesTableName = $calendarChangesTableName;
        $this->schedulingObjectTableName = $schedulingObjectTableName;
        $this->calendarSubscriptionsTableName = $calendarSubscriptionsTableName;
    }

    function setPdo ($pdo) {

        $this->pdo = $pdo;
    }

    function getChangesForCalendar ($calendarId, $syncToken, $syncLevel, $limit = null) {
        
        // Current synctoken
        $stmt = $this->pdo->prepare('SELECT synctoken FROM ' . $this->calendarTableName . ' WHERE id = ?');
        $stmt->execute([
                $calendarId
        ]);
        $currentToken = $stmt->fetchColumn(0);
        
        if (is_null($currentToken))
            return null;
        
        $result = [
                'syncToken' => $currentToken,
                'added' => [],
                'modified' => [],
                'deleted' => []
        ];
        
        if ($syncToken) {
            
            $query = "SELECT uri, operation FROM " . $this->calendarChangesTableName . " WHERE synctoken >= ? AND synctoken <= ? AND calendarid = ? ORDER BY synctoken";
            if ($limit > 0)
                $query .= " LIMIT " . (int) $limit;
                
                // Fetching all changes
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([
                    $syncToken,
                    $currentToken,
                    $calendarId
            ]);
            
            $changes = [];
            
            // This loop ensures that any duplicates are overwritten, only the
            // last change on a node is relevant.
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $changes[$row['uri']] = $row['operation'];
            }
            
            foreach ($changes as $uri => $operation) {
                
                switch ($operation) {
                    case 1:
                        $result['added'][] = $uri;
                        break;
                    case 2:
                        $result['modified'][] = $uri;
                        break;
                    case 3:
                        $result['deleted'][] = $uri;
                        break;
                }
            }
        }
        else {
            // No synctoken supplied, this is the initial sync.
            $query = "SELECT uri FROM " . $this->calendarObjectTableName . " WHERE calendarid = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([
                    $calendarId
            ]);
            
            $result['added'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }
        return $result;
    }

    function getSubscriptionsForUser ($principalUri) {

        return [];
    }
}

