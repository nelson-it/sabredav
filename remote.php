<?php

/*

CalendarServer example

This server features CalDAV support

*/

// settings
ini_set('log_errors', 1);
ini_set("error_log", "/var/log/mne/dav.log");
date_default_timezone_set('Europe/Berlin');

require_once 'config.php';

//Mapping PHP errors to exceptions
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("exception_error_handler");
// Files we need
require_once 'vendor/autoload.php';
// Backends
$carddavBackend   = new MneSabre\CardDAV\Backend\PDO( 'mne_sabredav.addressbooks', 'mne_sabredav.cards', 'mne_sabredav.addressbookchanges');
$calendarBackend = new MneSabre\CalDAV\Backend\PDO( 'mne_sabredav.calendarinstances', 'mne_sabredav.calendars', 'mne_sabredav.calendarobjects', 'mne_sabredav.calendarchanges');
$principalBackend = new MneSabre\DAVACL\PrincipalBackend\PDO( 'mne_sabredav.principals', 'mne_sabredav.groupmembers');
$authBackend = new MneSabre\DAV\Auth\Backend\MyAuth([$carddavBackend,$calendarBackend,$principalBackend], $database);

// Directory structure
$tree = [
    new Sabre\CalDAV\CalendarRoot($principalBackend, $calendarBackend),
	new \Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
    new Sabre\CalDAV\Principal\Collection($principalBackend),
];

$server = new MneSabre\DAV\Server($tree);

if (isset($baseUri))
    $server->setBaseUri($baseUri);

/* Server Plugins */

$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend,'SabreDAV'));
$server->addPlugin(new Sabre\DAVACL\Plugin());
$server->addPlugin(new Sabre\CalDAV\Plugin());
$server->addPlugin(new Sabre\CardDAV\Plugin());
$server->addPlugin( new Sabre\CalDAV\Subscriptions\Plugin());
$server->addPlugin( new Sabre\CalDAV\Schedule\Plugin());
$server->addPlugin(new Sabre\DAV\Sync\Plugin());

// Support for html frontend
$browser = new Sabre\DAV\Browser\Plugin();
$server->addPlugin($browser);

// And off we go!
$server->exec();
