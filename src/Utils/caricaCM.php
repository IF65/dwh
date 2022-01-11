<?php
@ini_set('memory_limit','16384M');

$pdo = null;
$user = 'root';
$password = 'mela';
$ip = '10.11.14.128';

$conStr = sprintf("mysql:host=%s", $ip);
$pdo = new PDO($conStr, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$stmt = "   insert ignore into cm.promozioni 
            	(id_esportazione,data_elaborazione,ora_elaborazione,codice_campagna,codice_promozione,descrizione,tipo,data_inizio,data_fine,calendario,ora_inizio,ora_fine,classe,tipo_attivita,codice_articolo,codice_ean,codice_reparto,slot_reparto,parametro_01,parametro_02,parametro_03,parametro_04,parametro_05,parametro_06,parametro_07,parametro_08,parametro_09,parametro_10,parametro_11,parametro_12) 
            values
                (:id_esportazione, :data_elaborazione, :ora_elaborazione, :codice_campagna, :codice_promozione, :descrizione, :tipo, :data_inizio, :data_fine, :calendario, :ora_inizio, :ora_fine, :classe, :tipo_attivita, :codice_articolo, :codice_ean, :codice_reparto, :slot_reparto, :parametro_01, :parametro_02, :parametro_03, :parametro_04, :parametro_05, :parametro_06, :parametro_07, :parametro_08, :parametro_09, :parametro_10, :parametro_11, :parametro_12 )";
$h_insert = $pdo->prepare($stmt);

$file = '/Users/if65/Desktop/promozioni.txt';
//$file = '/Users/stefano.orlandi/test/promozioni.txt';

$text = file_get_contents($file);
$rows = explode("\n", $text);

foreach ($rows as $row) {
	if(preg_match('/^\|P.{49}(\d{4}).{7}(\d{5}).{26}(\d{9}).{9}(.{50}).(..).{8}(\d\d)(\d\d)(\d{4}).{4}(\d\d)(\d\d)(\d{4}).{87}I.{13}(.{7}).{23}(.{14}).{60}(.{8})...(.{8})...(.{8})...(.{8})...(.{8})/', $row, $matches)) {
		$store = $matches[1];
		$campaignCode = $matches[2];
		$promotionCode = $matches[3];
		$promotionDescription = trim($matches[4]);
		$promotionType = $matches[5];
		$promotionStart = $matches[8] . '-' . $matches[7] . '-' . $matches[6];
		$promotionEnd = $matches[11] . '-' . $matches[10] . '-' . $matches[9];
		$articleCode =  trim($matches[12]);
		$departmentCode =  trim($matches[13]);
		$parameter_01 =  trim($matches[14]);
		$parameter_02 =  trim($matches[15]);
		$parameter_03 =  trim($matches[16]);
		$parameter_04 =  trim($matches[17]);
		$parameter_05 =  trim($matches[18]);

		$h_insert->execute([
			'id_esportazione' => 1000,
			'data_elaborazione' => '2021-09-16',
			'ora_elaborazione' => '16:48:21',
			'codice_campagna' => $campaignCode,
			'codice_promozione' => $promotionCode,
			'descrizione' => $promotionDescription,
			'tipo' => $promotionType,
			'data_inizio' => $promotionStart,
			'data_fine' => $promotionEnd,
			'calendario' => 'SSSSSSS',
			'ora_inizio' => '00:00:00',
			'ora_fine' => '23:59:00',
			'classe' => 0,
			'tipo_attivita' => 'I',
			'codice_articolo' => $articleCode,
			'codice_ean' => '',
			'codice_reparto' => $departmentCode,
			'slot_reparto' => 0,
			'parametro_01' => $parameter_01 *1,
			'parametro_02' => $parameter_02 *1,
			'parametro_03' => $parameter_03 *1,
			'parametro_04' => $parameter_04 *1,
			'parametro_05' => $parameter_05 *1,
			'parametro_06' => 0,
			'parametro_07' => 0,
			'parametro_08' => 0,
			'parametro_09' => 0,
			'parametro_10' => 0,
			'parametro_11' => 0,
			'parametro_12' => 0
		]);
	}

}