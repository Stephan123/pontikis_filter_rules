<?php
/**
* Darstellen der Länder
*
* @package Controller
* @date 06.07.2015
* @author Stephan Krauß
*/

$laenderCode = array();

$laenderCode[0]['id'] = 'DE';
$laenderCode[0]['value'] = 'Germany';
$laenderCode[1]['id'] = 'EN';
$laenderCode[1]['value'] = 'Englisch';
$laenderCode[1]['id'] = 'ERZ';
$laenderCode[1]['value'] = 'Erzgebirge';

$response = json_encode($laenderCode);

echo $response;