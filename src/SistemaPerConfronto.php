<?php

$path = "/Users/if65/Desktop/test/";

$text = file_get_contents($path . 'NUOVO.DAT');
$datacollectNew = explode("\r\n", $text);
$articles = [];
foreach ($datacollectNew as $row) {
	if (preg_match('/^.{23}01.{13}N(\d{4}).{39}(\d{7})/', $row, $matches)) {
		$articles[$matches[2]] = $matches[1];
	}
}

$barcodes91 = [];
foreach ($datacollectNew as $row) {
	if (preg_match('/^.{23}91(.{13})N(\d{4})(.*)$/', $row, $matches)) {
		$barcodes91[$matches[1]] = $matches[2];
	}
}

$barcodes13 = [];
foreach ($datacollectNew as $row) {
	if (preg_match('/^.{23}13(.{13})N(\d{4})(.*)$/', $row, $matches)) {
		$barcodes13[$matches[1]] = $matches[2];
	}
}



/** sistemazione codice reparto nelle vendite */
$text = file_get_contents($path . 'ORIGINALE.DAT');
$datacollect = explode("\r\n", $text);
foreach ($datacollect as $i => $row) {
	if (preg_match('/^(.{23}01.{13}N)\d{4}(.{39})(\d{7})(.*)$/', $row, $matches)) {
		if (key_exists($matches[3], $articles)) {
			$old = $datacollect[$i];
			$new = $matches[1] . $articles[$matches[3]] . $matches[2] . $matches[3] . $matches[4];
			$datacollect[$i] = $matches[1] . $articles[$matches[3]] . $matches[2] . $matches[3] . $matches[4];
		}
	}
}

/** sistemazione codice reparto nelle promozioni di tipo 91 */
foreach ($datacollect as $i => $row) {
	if (preg_match('/^(.{23}91)(.{13})N\d{4}(.*)$/', $row, $matches)) {
		if (key_exists($matches[2], $barcodes91)) {
			$old = $datacollect[$i];
			$new = $matches[1] . $matches[2] . 'N' . $barcodes91[$matches[2]] . $matches[3];
			$datacollect[$i] = $matches[1] . $matches[2] . 'N' . $barcodes91[$matches[2]] . $matches[3];
		}
	}
}

/** sistemazione codice reparto nelle promozioni di tipo 13 */
foreach ($datacollect as $i => $row) {
	if (preg_match('/^(.{23}13)(.{13})N\d{4}(.*)$/', $row, $matches)) {
		if (key_exists($matches[2], $barcodes13)) {
			$old = $datacollect[$i];
			$new = $matches[1] . $matches[2] . 'N' . $barcodes13[$matches[2]] . $matches[3];
			$datacollect[$i] = $matches[1] . $matches[2] . 'N' . $barcodes13[$matches[2]] . $matches[3];
		}
	}
}


/** sistemazione articolo 17 */
foreach ($datacollect as $i => $row) {
	if (preg_match('/^(.{23}01)\s{11}17(.*)$/', $row, $matches)) {
		$datacollect[$i] = $matches[1] . '            1' . $matches[2];
	}
}

file_put_contents($path . 'VECCHIO.DAT', implode("\r\n", $datacollect));
