<?php
/***********************************************
* File      :   backend/mnecombined/combined.php
* Project   :   MnePush
* Descr     :   Read Own Configuration for the Open Source ERP 
*               and use the Standard Combine Backend.
* 
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License, version 3,
* as published by the Free Software Foundation with the following additional
* term according to sec. 7:
*
* According to sec. 7 of the GNU Affero General Public License, version 3,
* the terms of the AGPL are supplemented with the following terms:
*
* ************************************************/

//include the CombinedBackend's own config file
require_once("backend/mnecombined/config.php");
require_once("backend/combined/combined.php");
require_once("backend/combined/importer.php");
require_once("backend/combined/exporter.php");

class BackendMneCombined extends BackendCombined  {

    /**
     * Constructor of the combined backend
     *
     * @access public
     */
    public function BackendMneCombined() {
        parent::Backend();
        $this->config = BackendMneCombinedConfig::GetBackendMneCombinedConfig();

        $backend_values = array_unique(array_values($this->config['folderbackend']));
        foreach ($backend_values as $i) {
            ZPush::IncludeBackend($this->config['backends'][$i]['name']);
            $this->backends[$i] = new $this->config['backends'][$i]['name']();
        }
        ZLog::Write(LOGLEVEL_DEBUG, sprintf("MneCombined %d backends loaded.", count($this->backends)));
    }
}
