<?php

namespace MneSabre\CardDAV\Backend;

class PDO extends \Sabre\CardDAV\Backend\PDO {
	function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null) {
	
		// Current synctoken
		$stmt = $this->pdo->prepare('SELECT synctoken FROM ' . $this->addressBooksTableName . ' WHERE id = ?');
		$stmt->execute([ $addressBookId ]);
		$currentToken = $stmt->fetchColumn(0);
	
		if (is_null($currentToken)) return null;
	
		$result = [
				'syncToken' => $currentToken,
				'added'     => [],
				'modified'  => [],
				'deleted'   => [],
		];
	
		if ($syncToken) {
	
			$query = "SELECT uri, operation FROM " . $this->addressBookChangesTableName . " WHERE synctoken >= ? AND synctoken <= ? AND addressbookid = ? ORDER BY synctoken";
			if ($limit>0) $query.= " LIMIT " . (int)$limit;
	
			// Fetching all changes
			$stmt = $this->pdo->prepare($query);
			$stmt->execute([$syncToken, $currentToken, $addressBookId]);
	
			$changes = [];
	
			// This loop ensures that any duplicates are overwritten, only the
			// last change on a node is relevant.
			while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
	
				$changes[$row['uri']] = $row['operation'];
	
			}
	
			foreach($changes as $uri => $operation) {
	
				switch($operation) {
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
		} else {
			// No synctoken supplied, this is the initial sync.
			$query = "SELECT uri FROM " . $this->cardsTableName . " WHERE addressbookid = ?";
			$stmt = $this->pdo->prepare($query);
			$stmt->execute([$addressBookId]);
	
			$result['added'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
		}
		return $result;
	
	}
	
	function updateCard($addressBookId, $cardUri, $cardData) {
	
		$etag = md5($cardData);

		$stmt = $this->pdo->prepare('SELECT etag, carddata FROM ' . $this->cardsTableName . ' WHERE uri = ? AND addressbookid =?');
        $stmt->execute([ $cardUri, $addressBookId ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
		if ( $row['etag'] != $etag && md5($cardData) != $etag )
		{
			error_log(sprintf("Carddata are overwritten by old carddata %s:%s:%s", $row['etag'], $etag, md5($cardData)));
			error_log(sprintf("carddataold %s", $row['carddata']));
			error_log(sprintf("carddatanew %s", $cardData));
			$cardData = $row['carddata'];
		    $etag = md5($cardData);
		}
			
        $stmt = $this->pdo->prepare('UPDATE ' . $this->cardsTableName . ' SET carddata = ?, lastmodified = ?, size = ?, etag = ? WHERE uri = ? AND addressbookid =?');
	
		$stmt->execute([
				$cardData,
				time(),
				strlen($cardData),
				$etag,
				$cardUri,
				$addressBookId
		]);
	
		$this->addChange($addressBookId, $cardUri, 2);
	
		return '"' . $etag . '"';
	
	}
	
	function getMultipleCards($addressBookId, array $uris) {
	
	    $query = 'SELECT id, uri, lastmodified, etag, size FROM ' . $this->cardsTableName . ' WHERE addressbookid = ? AND uri IN (';
	    // Inserting a whole bunch of question marks
	    $query.=implode(',', array_fill(0, count($uris), '?'));
	    $query.=')';
	
	    $stmt = $this->pdo->prepare($query);
	    $stmt->execute(array_merge([$addressBookId], $uris));
	    $result = [];
	    while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
	        $row['etag'] = '"' . $row['etag'] . '"';
	        $result[] = $row;
	    }

        if (count($result) == 0) {
            $query = 'SELECT t0.id, t1.uri, t0.lastmodified, t0.etag, t0.size FROM ' . $this->cardsTableName . ' t0 INNER JOIN mne_sabredav.uri2personid t1 ON t0.id = t1.personid WHERE addressbookid = ? AND t1.uri IN (';
            // Inserting a whole bunch of question marks
            $query .= implode(',', array_fill(0, count($uris), '?'));
            $query .= ')';
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(array_merge([
                    $addressBookId
            ], $uris));
            $result = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $row['etag'] = '"' . $row['etag'] . '"';
                $result[] = $row;
            }
            if (count($result) == 0)
                throw new \Sabre\DAV\Exception\NotFound("No Cards");
        }
        
        return $result;
    }
	
}

