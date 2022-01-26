<?php

$filePath = realpath(__DIR__) . "/dc/invio_004/";

$fileList = scandir($filePath);

foreach ($fileList as $file) {
	if (preg_match("/^DC.*\.DAT$/", $file)) {
		$transazioneAperta = false;
		$text = file_get_contents($filePath . $file);
		$rows = explode("\n", $text);
		if ($rows) {
			foreach ($rows as $i => $row) {

				/** inizio transazione */
				if (preg_match("/^.{21}004(\d{4})(\d{4})/", $row, $m)) {
					$transazione = $m[1];
					$cassa = $m[2];
					$totaleCalcolato = 0;
					$transazioneAperta = true;

					if ($transazione == "7667") {
						echo "\n";
					}
				}

				if ($transazioneAperta) {
					if (preg_match("/^.{21}1001.{18}(\d{7})(\d{2})/", $row, $m)) {
						$totaleCalcolato += $m[1] . '.' . $m[2];
					}
					if (preg_match("/^.{21}1013.{18}(\d{7})(\d{2})/", $row, $m)) {
						$totaleCalcolato -= $m[1] . '.' . $m[2];
					}
					if (preg_match("/^.{21}1088.{18}(\d{7})(\d{2})/", $row, $m)) {
						$totaleCalcolato -= $m[1] . '.' . $m[2];
					}
					if (preg_match("/^.{21}1091.{18}(\d{7})(\d{2})/", $row, $m)) {
						$totaleCalcolato -= $m[1] . '.' . $m[2];
					}
					if (preg_match("/^.{21}1085.{18}(\d{7})(\d{2})/", $row, $m)) {
						$totaleCalcolato -= $m[1] . '.' . $m[2];
					}
					if (preg_match("/^.{21}1086.{18}(\d{7})(\d{2})/", $row, $m)) {
						$totaleCalcolato -= $m[1] . '.' . $m[2];
					}
					if (preg_match("/^.{21}1087.{18}(\d{7})(\d{2})/", $row, $m)) {
						$totaleCalcolato -= $m[1] . '.' . $m[2];
					}
					if (preg_match("/^.{21}1094.{18}(\d{7})(\d{2})/", $row, $m)) {
						$totaleCalcolato -= $m[1] . '.' . $m[2];
					}
					if (preg_match("/^.{21}1055.{18}(\d{7})(\d{2})/", $row, $m)) {
						$totaleCalcolato -= $m[1] . '.' . $m[2];
					}
					if (preg_match("/^.{21}1050.{18}(\d{7})(\d{2})/", $row, $m)) {
						$totaleCalcolato -= $m[1] . '.' . $m[2];
					}
					if (preg_match("/^.{21}1062.{18}(\d{7})(\d{2})/", $row, $m)) {
						$totaleCalcolato -= $m[1] . '.' . $m[2];
					}

					if (preg_match("/^.{21}1000.{18}(\d{7})(\d{2})/", $row, $m)) {
						$totaleCalcolato -= $m[1] . '.' . $m[2];
					}
				}

				if (preg_match("/^.{21}1020.{18}(\d{7})(\d{2})/", $row, $m)) {
					$totale = ($m[1] . '.' . $m[2]) * 1;
					$transazioneAperta = false;

					if (round($totaleCalcolato - $totale,2)) {
						echo "$file, $transazione/$cassa : $totaleCalcolato <=> $totale\n";
					}
				}

			}
		}
	}
}

echo "\n";