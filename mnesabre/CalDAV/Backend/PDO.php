<?php
namespace MneSabre\CalDAV\Backend;

use Sabre\CalDAV;
use Sabre\DAV;

class PDO extends \Sabre\CalDAV\Backend\PDO {

    function __construct ($calendarInstances = 'calendarInstances', $calendarTableName = 'calendars', $calendarObjectTableName = 'calendarobjects', $calendarChangesTableName = 'calendarchanges', $calendarSubscriptionsTableName = "calendarsubscriptions", 
            $schedulingObjectTableName = "schedulingobjects") {

        $this->calendarInstancesTableName = $calendarInstances;
        $this->calendarTableName = $calendarTableName;
        $this->calendarObjectTableName = $calendarObjectTableName;
        $this->calendarChangesTableName = $calendarChangesTableName;
        $this->schedulingObjectTableName = $schedulingObjectTableName;
        $this->calendarSubscriptionsTableName = $calendarSubscriptionsTableName;
    }

    function setPdo ($pdo) {

        $this->pdo = $pdo;
    }

    function getCalendarsForUser($principalUri) {
        
        $fields = array_values($this->propertyMap);
        $fields[] = 'calendarid';
        $fields[] = 'uri';
        $fields[] = 'synctoken';
        $fields[] = 'components';
        $fields[] = 'principaluri';
        $fields[] = 'transparent';
        $fields[] = 'access';
        
        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(<<<SQL
SELECT {$this->calendarInstancesTableName}.id as id, $fields FROM {$this->calendarInstancesTableName}
    LEFT JOIN {$this->calendarTableName} ON
        {$this->calendarInstancesTableName}.calendarid = {$this->calendarTableName}.id
WHERE principaluri = ? ORDER BY calendarorder ASC
SQL
        );
        $stmt->execute([$principalUri]);
        
        $calendars = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            
            $components = [];
            if ($row['components']) {
                $components = explode(',', $row['components']);
            }
            
            $calendar = [
                    'id'                                                                 => [$row['calendarid'], $row['id']],
                    'uri'                                                                => $row['uri'],
                    'principaluri'                                                       => $row['principaluri'],
                    '{' . CalDAV\Plugin::NS_CALENDARSERVER . '}getctag'                  => 'http://sabre.io/ns/sync/' . ($row['synctoken'] ? $row['synctoken'] : '0'),
                    '{http://sabredav.org/ns}sync-token'                                 => $row['synctoken'] ? $row['synctoken'] : '0',
                    '{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new CalDAV\Xml\Property\SupportedCalendarComponentSet($components),
                    '{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp'         => new CalDAV\Xml\Property\ScheduleCalendarTransp($row['transparent'] ? 'transparent' : 'opaque'),
                    'share-resource-uri'                                                 => '/ns/share/' . $row['calendarid'],
            ];
            
            $calendar['share-access'] = (int)$row['access'];
            // 1 = owner, 2 = readonly, 3 = readwrite
            if ($row['access'] > 1) {
                // We need to find more information about the original owner.
                //$stmt2 = $this->pdo->prepare('SELECT principaluri FROM ' . $this->calendarInstancesTableName . ' WHERE access = 1 AND id = ?');
                //$stmt2->execute([$row['id']]);
                
                // read-only is for backwards compatbility. Might go away in
                // the future.
                $calendar['read-only'] = (int)$row['access'] === \Sabre\DAV\Sharing\Plugin::ACCESS_READ;
            }
            
            foreach ($this->propertyMap as $xmlName => $dbName) {
                $calendar[$xmlName] = $row[$dbName];
            }
            
            $calendars[] = $calendar;
            
        }
        
        return $calendars;
        
    }
    
    function getChangesForCalendar ($calendarId, $syncToken, $syncLevel, $limit = null) {
        
        // Current synctoken
        list($calendarId, $instanceId) = $calendarId;

        $stmt = $this->pdo->prepare('SELECT synctoken FROM ' . $this->calendarTableName . ' WHERE id = ?');
        $stmt->execute([$calendarId]);
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
            
            $query = "SELECT uri, operation FROM " . $this->calendarChangesTableName . " WHERE synctoken >= ? AND calendarid = ? ORDER BY synctoken";
            if ($limit > 0)
                $query .= " LIMIT " . (int) $limit;
                
                // Fetching all changes
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([
                    $syncToken,
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

