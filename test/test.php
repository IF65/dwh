<?php
/**
 * Procedura di test per verificare la corretta ripartizione degli sconti. Verrà anche utilizzata per ricostruire
 * gli errori commessi da Gamba Bruno quando l'anno scorso non ha scritto i netti sconti nei datacollect per
 * circa un mese. (+ 3 orzinuovi di gennaio 2021)
 */


@ini_set('memory_limit','16384M');

require('autoload.php');
require('../vendor/autoload.php');

require(realpath(__DIR__) . '/mtx2json.php');

use If65\Config;

$config = Config::Init();

$store = '0103';
$ddate = '2020-12-30';

$sediDaSistemare = [
	["sede" => "0146", "data" => "2020-12-29"],
	["sede" => "0146", "data" => "2020-12-30"],
	["sede" => "0146", "data" => "2020-12-31"],
	["sede" => "0147", "data" => "2020-12-29"],
	["sede" => "0147", "data" => "2020-12-30"],
	["sede" => "0147", "data" => "2020-12-31"],
	["sede" => "0148", "data" => "2020-12-29"],
	["sede" => "0148", "data" => "2020-12-30"],
	["sede" => "0148", "data" => "2020-12-31"],
	["sede" => "0149", "data" => "2020-12-29"],
	["sede" => "0153", "data" => "2020-12-29"],
	["sede" => "0153", "data" => "2020-12-30"],
	["sede" => "0155", "data" => "2020-12-23"],
	["sede" => "0155", "data" => "2020-12-24"],
	["sede" => "0155", "data" => "2020-12-27"],
	["sede" => "0155", "data" => "2020-12-28"],
	["sede" => "0155", "data" => "2020-12-29"],
	["sede" => "0155", "data" => "2020-12-30"],
	["sede" => "0156", "data" => "2020-12-29"],
	["sede" => "0156", "data" => "2020-12-30"],
	["sede" => "0170", "data" => "2020-12-29"],
	["sede" => "0170", "data" => "2020-12-30"],
	["sede" => "0170", "data" => "2020-12-31"],
	["sede" => "0171", "data" => "2020-12-27"],
	["sede" => "0171", "data" => "2020-12-28"],
	["sede" => "0171", "data" => "2020-12-29"],
	["sede" => "0171", "data" => "2020-12-30"],
	["sede" => "0171", "data" => "2020-12-31"],
	["sede" => "0172", "data" => "2020-12-30"],
	["sede" => "0172", "data" => "2020-12-31"],
	["sede" => "0173", "data" => "2020-12-30"],
	["sede" => "0173", "data" => "2020-12-31"],
	["sede" => "0176", "data" => "2020-12-30"],
	["sede" => "0176", "data" => "2020-12-31"],
	["sede" => "0178", "data" => "2020-12-30"],
	["sede" => "0178", "data" => "2020-12-31"],
	["sede" => "0181", "data" => "2020-12-02"],
	["sede" => "0181", "data" => "2020-12-03"],
	["sede" => "0181", "data" => "2020-12-04"],
	["sede" => "0181", "data" => "2020-12-05"],
	["sede" => "0181", "data" => "2020-12-06"],
	["sede" => "0181", "data" => "2020-12-07"],
	["sede" => "0181", "data" => "2020-12-08"],
	["sede" => "0181", "data" => "2020-12-09"],
	["sede" => "0188", "data" => "2020-12-21"],
	["sede" => "0188", "data" => "2020-12-23"],
	["sede" => "0188", "data" => "2020-12-24"],
	["sede" => "0188", "data" => "2020-12-27"],
	["sede" => "0188", "data" => "2020-12-28"],
	["sede" => "0188", "data" => "2020-12-29"],
	["sede" => "0188", "data" => "2020-12-30"],
	["sede" => "0188", "data" => "2020-12-31"],
	["sede" => "0202", "data" => "2020-12-01"],
	["sede" => "0202", "data" => "2020-12-02"],
	["sede" => "0202", "data" => "2020-12-03"],
	["sede" => "0202", "data" => "2020-12-04"],
	["sede" => "0202", "data" => "2020-12-05"],
	["sede" => "0202", "data" => "2020-12-06"],
	["sede" => "0202", "data" => "2020-12-07"],
	["sede" => "0202", "data" => "2020-12-08"],
	["sede" => "0202", "data" => "2020-12-09"],
	["sede" => "0202", "data" => "2020-12-10"],
	["sede" => "0202", "data" => "2020-12-12"],
	["sede" => "0202", "data" => "2020-12-13"],
	["sede" => "0202", "data" => "2020-12-15"],
	["sede" => "0202", "data" => "2020-12-18"],
	["sede" => "0202", "data" => "2020-12-19"],
	["sede" => "0202", "data" => "2020-12-20"],
	["sede" => "0202", "data" => "2020-12-21"],
	["sede" => "0202", "data" => "2020-12-23"],
	["sede" => "0202", "data" => "2020-12-24"],
	["sede" => "0202", "data" => "2020-12-27"],
	["sede" => "0202", "data" => "2020-12-28"],
	["sede" => "0202", "data" => "2020-12-29"],
	["sede" => "0202", "data" => "2020-12-30"],
	["sede" => "0202", "data" => "2020-12-31"],
	["sede" => "0464", "data" => "2020-12-01"],
	["sede" => "0464", "data" => "2020-12-02"],
	["sede" => "0464", "data" => "2020-12-03"],
	["sede" => "0464", "data" => "2020-12-04"],
	["sede" => "0464", "data" => "2020-12-05"],
	["sede" => "0464", "data" => "2020-12-06"],
	["sede" => "0464", "data" => "2020-12-07"],
	["sede" => "0464", "data" => "2020-12-08"],
	["sede" => "0464", "data" => "2020-12-09"],
	["sede" => "0467", "data" => "2020-12-23"],
	["sede" => "0467", "data" => "2020-12-24"],
	["sede" => "0467", "data" => "2020-12-27"],
	["sede" => "0467", "data" => "2020-12-30"],
	["sede" => "0501", "data" => "2020-12-28"],
	["sede" => "0501", "data" => "2020-12-29"],
	["sede" => "0501", "data" => "2020-12-30"],
	["sede" => "0501", "data" => "2020-12-31"],
	["sede" => "3151", "data" => "2020-12-03"],
	["sede" => "3151", "data" => "2020-12-04"],
	["sede" => "3151", "data" => "2020-12-05"],
	["sede" => "3151", "data" => "2020-12-06"],
	["sede" => "3151", "data" => "2020-12-07"],
	["sede" => "3151", "data" => "2020-12-08"],
	["sede" => "3151", "data" => "2020-12-09"],
	["sede" => "3151", "data" => "2020-12-10"],
	["sede" => "3151", "data" => "2020-12-11"],
	["sede" => "3151", "data" => "2020-12-12"],
	["sede" => "3151", "data" => "2020-12-13"],
	["sede" => "3151", "data" => "2020-12-14"],
	["sede" => "3151", "data" => "2020-12-15"],
	["sede" => "3151", "data" => "2020-12-16"],
	["sede" => "3151", "data" => "2020-12-17"],
	["sede" => "3151", "data" => "2020-12-18"],
	["sede" => "3151", "data" => "2020-12-19"],
	["sede" => "3151", "data" => "2020-12-20"],
	["sede" => "3151", "data" => "2020-12-21"],
	["sede" => "3151", "data" => "2020-12-22"],
	["sede" => "3151", "data" => "2020-12-23"],
	["sede" => "3151", "data" => "2020-12-24"],
	["sede" => "3151", "data" => "2020-12-27"],
	["sede" => "3151", "data" => "2020-12-28"],
	["sede" => "3151", "data" => "2020-12-29"],
	["sede" => "3151", "data" => "2020-12-30"],
	["sede" => "3151", "data" => "2020-12-31"],
	["sede" => "3152", "data" => "2020-12-14"],
	["sede" => "3152", "data" => "2020-12-18"],
	["sede" => "3152", "data" => "2020-12-19"],
	["sede" => "3152", "data" => "2020-12-20"],
	["sede" => "3152", "data" => "2020-12-21"],
	["sede" => "3152", "data" => "2020-12-22"],
	["sede" => "3152", "data" => "2020-12-23"],
	["sede" => "3152", "data" => "2020-12-24"],
	["sede" => "3152", "data" => "2020-12-27"],
	["sede" => "3152", "data" => "2020-12-29"],
	["sede" => "3152", "data" => "2020-12-30"],
	["sede" => "3152", "data" => "2020-12-31"],
	["sede" => "3659", "data" => "2020-12-28"],
	["sede" => "3687", "data" => "2020-12-24"]
];

foreach ($sediDaSistemare as $sede) {
	$store = $sede['sede'];
	$ddate = $sede['data'];

	$jdc = getData($store, $ddate);
	$dc = json_decode($jdc, true);

//file_put_contents($config->getSetup($config::EXPORTFOLDER) . DIRECTORY_SEPARATOR . 'dc.json', $jdc);

	$salesList = [];
	foreach ($dc as $transaction) {
		foreach ($transaction['sales'] as $sale) {
			$calculatedTotalTaxableAmount = $sale['totalamount'];
			foreach ($sale['benefits'] as $benefit) {
				foreach ($benefit as $action) {
					$calculatedTotalTaxableAmount = round($calculatedTotalTaxableAmount + $action['amount'], 2);
				}
			}
			if ($sale['recordcode2'] == '5') {
				$calculatedTotalTaxableAmount = $calculatedTotalTaxableAmount * -1;
			}

			$taxRate = 0;
			if ($sale['taxcode'] == "1") {
				$taxRate = 4;
			} elseif ($sale['taxcode'] == "2") {
				$taxRate = 10;
			} elseif ($sale['taxcode'] == "3") {
				$taxRate = 22;
			} elseif ($sale['taxcode'] == "4") {
				$taxRate = 5;
			}
			$calculatedTotalTaxAmount = round($calculatedTotalTaxableAmount * $taxRate / 100, 2);

			$salesList[] = [
				'store' => $transaction['store'],
				'ddate' => $transaction['ddate'],
				'reg' => $transaction['reg'],
				'trans' => $transaction['trans'],
				'saleid' => $sale['saleid'],
				'barcode' => $sale['barcode'],
				'quantity' => $sale['quantity'],
				'taxcode' => $sale['taxcode'],
				'taxrate' => $taxRate,
				'totalamount' => $sale['totalamount'],
				'totaltaxableamount' => $sale['totaltaxableamount'],
				'taxamount' => $sale['taxamount'],
				'calculatedtotaltaxableamount' => $calculatedTotalTaxableAmount,
				'calculatedtaxamount' => $calculatedTotalTaxAmount,
				'transactionAmount' => $transaction['totalamount']
			];
		}
	}

	$jsonSalesList = json_encode($salesList, JSON_PRETTY_PRINT);
	file_put_contents($config->getSetup($config::EXPORTFOLDER) . DIRECTORY_SEPARATOR . 'sales.json', $jsonSalesList);

	$errors = [];
	foreach ($salesList as $sale) {
		if (abs($sale['totaltaxableamount'] - $sale['calculatedtotaltaxableamount']) > 0.02) {
			$errors[] = $sale;
		}
	}

	$jsonErrors = json_encode($errors, JSON_PRETTY_PRINT);
	file_put_contents($config->getSetup($config::EXPORTFOLDER) . DIRECTORY_SEPARATOR . 'errors.json', $jsonErrors);

	$scontrini = [];
	foreach ($salesList as $sale) {
		$id = $sale['store'] . str_replace('-', '', $sale['ddate']) . $sale['reg'] . str_pad($sale['trans'], 4, '0');
		if (!key_exists($id, $scontrini)) {
			$scontrini[$id] = ['id' => $id, 'totale' => $sale['transactionAmount'], 'calcolato' => 0, 'rilevato' => 0];
		}
		$importoCalcolato = $scontrini[$id]['calcolato'] + $sale['calculatedtotaltaxableamount'];
		$scontrini[$id]['calcolato'] = $importoCalcolato;

		$importoRilevato = $scontrini[$id]['rilevato'] + $sale['totaltaxableamount'];
		$scontrini[$id]['rilevato'] = $importoRilevato;
	}

	$scontriniAnomali = [];
	foreach ($scontrini as $scontrino) {
		if (round($scontrino['totale'] - $scontrino['calcolato'], 2) != 0 /*|| round($scontrino['totale'] - $scontrino['rilevato'], 2) != 0*/) {
			$scontriniAnomali[] = $scontrino;
		}
	}

	print_r($scontriniAnomali);
	$jsonAnomali = json_encode($scontriniAnomali, JSON_PRETTY_PRINT);
	file_put_contents($config->getSetup($config::EXPORTFOLDER) . DIRECTORY_SEPARATOR . 'anomalie.json', $jsonAnomali);


	try {
		$config = Config::Init();

		$user = $config->getSetup(Config::DB_QUADRATURE, 'user');
		$password = $config->getSetup(Config::DB_QUADRATURE, 'password');
		$host = $config->getSetup(Config::DB_QUADRATURE, 'host');

		$connectionString = sprintf("mysql:host=%s", $host);

		$pdo = new PDO($connectionString, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
		/** carico le transazioni valide */
		$stmt = "select count(*) from mtx.idc where store = :store and ddate = :ddate and reg = :reg and trans = :trans and saleid = :saleid and totaltaxableamount = 0";
		$h_count = $pdo->prepare($stmt);

		$stmt = "update mtx.idc set totaltaxableamount = :totaltaxableamount, taxamount = :taxamount where store = :store and ddate = :ddate and reg = :reg and trans = :trans and saleid = :saleid";
		$h_update = $pdo->prepare($stmt);


	} catch (PDOException $e) {
		echo "Errore: " . $e->getMessage();
		die();
	}

	foreach ($errors as $error) {
		$h_count->execute([
			':store' => $error['store'],
			':ddate' => $error['ddate'],
			':reg' => $error['reg'],
			':trans' => (int)$error['trans'],
			':saleid' => (int)$error['saleid']
		]);
		$count = (int)$h_count->fetchColumn(0);

		if ($count != 1) {
			print_r($error['reg'] . ' - ' . $error['trans'] . " - " . $error['saleid'] . " - count: $count\n");
		} else {
			$h_update->execute([
				':store' => $error['store'],
				':ddate' => $error['ddate'],
				':reg' => $error['reg'],
				':trans' => (int)$error['trans'],
				':saleid' => (int)$error['saleid'],
				':totaltaxableamount' => (float)$error['calculatedtotaltaxableamount'],
				':taxamount' => (float)$error['calculatedtaxamount'],
			]);
			print_r($error['store'] . ' - ' . $error['ddate'] . ' - ' . $error['reg'] . ' - ' . $error['trans'] . " - " . $error['saleid'] . " - " . $error['saleid'] . " - OK\n");
		}
	}
}
$pdo = null;