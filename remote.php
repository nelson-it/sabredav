<?php

/*

CalendarServer example

This server features CalDAV support

*/

// settings
date_default_timezone_set('Europe/Berlin');

// If you want to run the SabreDAV server in a custom location (using mod_rewrite for instance)
// You can override the baseUri here.
$baseUri = '/dav';

/* Database */
$pdo = new PDO('pgsql:dbname=erpdb','mneerpdavdba');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

//Mapping PHP errors to exceptions
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("exception_error_handler");
// Files we need
require_once 'vendor/autoload.php';
// Backends
$authBackend = new MneSabre\DAV\Auth\Backend\MyAuth;
$carddavBackend   = new MneSabre\CardDAV\Backend\PDO($pdo, 'mne_sabredav.addressbooks', 'mne_sabredav.cards', 'mne_sabredav.addressbookchanges');
$calendarBackend = new MneSabre\CalDAV\Backend\PDO($pdo, 'mne_sabredav.calendars', 'mne_sabredav.calendarobjects', 'mne_sabredav.calendarchanges');
$principalBackend = new Sabre\DAVACL\PrincipalBackend\PDO($pdo, 'mne_sabredav.principals', 'mne_sabredav.groupmembers');

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
