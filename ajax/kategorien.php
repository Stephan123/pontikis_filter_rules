<?php

$kategorien = array(
	'Kategorie A' => 1,
	'Kategorie B' => 2,
	'Kategorie C' => 3,
	'Kategorie D' => 4
);

$result = array();

foreach($kategorien as $key => $inhalt){
	
	$kategorie = array();
	$kategorie['lk_value'] = $key;
	$kategorie['lk_option'] = $inhalt;
	$result[] = $kategorie;
}	

echo json_encode($result);

?>