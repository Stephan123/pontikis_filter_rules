<?php
/**
* vorhandene Sprachen
*
* @package Controller
* @date 06.07.2015
* @author Stephan Krauß
*/


$sprachen = array();

$sprachen[0]['lk_option'] = 'deutsch';
$sprachen[0]['lk_value'] = 'de';
$sprachen[1]['lk_option'] = 'englisch';
$sprachen[1]['lk_value'] = 'en';
$sprachen[2]['lk_option'] = 'erzgebirgisch';
$sprachen[2]['lk_value'] = 'erz';

$response = json_encode($sprachen);

echo $response;