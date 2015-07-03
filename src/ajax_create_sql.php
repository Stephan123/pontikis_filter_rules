<?php
/**
 * Creating sql template script. Angepasst Stephan Krauß
 *
 * @version 1.0.7 (08 Apr 2015)
 * @author Christos Pontikis http://pontikis.net
 * @license  http://opensource.org/licenses/MIT MIT license
 **/

include_once('juiFilterRules.php');
include_once('sparrow.php');

// Get params
$a_rules = $_POST['a_rules'];

// Abbruch wenn keine Rollen
if(count($a_rules) == 0) {
	exit();
}

$pstPlaceholder = 'question_mark';
$usePst = false;

$datenbankZugang = array(
    'type' => 'mysqli',
    'hostname' => 'localhost',
    'database' => 'statistik',
    'username' => 'statistik',
    'password' => 'statistik'
);

$allowedFunctions = array(
    'date_encode',
    'bla',
    'blub'
);

$sparrow = new Sparrow();
$sparrow->setDb($datenbankZugang);

// Ausgabe der SQL
$filterRules = new juiFilterRules($sparrow, $allowedFunctions, $usePst, false, $pstPlaceholder);

// Übergabe der Liste der erlaubten zusätzlichen Funktionen durch den Server
$filterRules->setAllowedFunctions($allowedFunctions);

// erstellen der 'where' Klausel !!!
$result = $filterRules->parseRules($a_rules);

// abfragen Fehler während der Erstellungder 'where' Klausel
$lastError = $filterRules->getLastError();


if(!is_null($lastError['error_message'])) {
	$result['error'] = $lastError;
}
echo json_encode($result);