<?php

@ini_set('memory_limit', '16384M');

require('autoload.php');
require('../vendor/autoload.php');

require(realpath(__DIR__) . '/mtx2json.php');

use If65\Config;
use If65\Views\Articles;
use If65\Views\Promotions;
use If65\Views\Anagdafi;

use GetOpt\GetOpt;
use GetOpt\Option;

$config = Config::Init();
$anagdafi = new Anagdafi();

$tapMancanti = [];

$options = new GetOpt([
	Option::create('i', 'dataInizio', GetOpt::REQUIRED_ARGUMENT)
		->setDescription("Data Inizio Caricamento.")->setValidation(function ($value) {
			return (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $value)) ? $value : '';
		}),
	Option::create('f', 'dataFine', GetOpt::OPTIONAL_ARGUMENT)
		->setDescription("Data Fine Caricamento.")->setValidation(function ($value) {
			return (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $value)) ? $value : '';
		}),
	Option::create('s', 'sede', GetOpt::REQUIRED_ARGUMENT)
		->setDescription('Sede da Caricare.')->setValidation(function ($value) {
			return (preg_match('/^\d{4}$/', $value)) ? $value : '';
		})
]);

try {
	$options->process();
} catch (Missing $exception) {
	throw $exception;
}

$dataIniziale = $options->getOption('i');
$dataFinale = $options->getOption('f');
if ($dataFinale == null) {
	$dataFinale = $dataIniziale;
}
$store = $options->getOption('s');

$articles = new Articles();

$codiceCampagnaSconto = '10501';
$codicePromozioniSconto = '990011425';

$codiceCampagnaPunti = '10485';
$codicePromozionePunti = '990011267';

$startingDate = new DateTime($dataIniziale);
$endingDate = new DateTime($dataFinale);

$promotions = new Promotions($store, $startingDate->format("Y-m-d"), $endingDate->format("Y-m-d"));

// procedura
// -----------------------------------------------------------
$currentDate = clone $startingDate;
while ($currentDate->format("Y-m-d") <= $endingDate->format("Y-m-d")) {
	$ddate = $currentDate->format("Y-m-d");

	$promotions->loadActivePromotions($ddate);

	$societa = '';
	$negozio = '';
	if (preg_match('/^(\d\d)(\d\d)$/', $store, $matches)) {
		$societa = $matches[1];
		$negozio = $matches[2];
	}

	$anno = '';
	$mese = '';
	$giorno = '';
	if (preg_match('/^\d{2}(\d{2})-(\d{2})-(\d{2})$/', $ddate, $matches)) {
		$anno = $matches[1];
		$mese = $matches[2];
		$giorno = $matches[3];
	}

	$jdc = getData($store, $ddate);
	$dc = json_decode($jdc, true);
	if ($dc != null) {
		$anagdafi->loadData($ddate, $store);

		if ($config->debug) {
			file_put_contents($config->exportFolder . DIRECTORY_SEPARATOR . 'dc.json', $jdc);
		}

		$wrongTransactionCount = 0;
		foreach ($dc as $id => $transaction) {
			if ($transaction['totalamount'] != 0.0) {
				if ($transaction['wrongTransaction']) {
					$wrongTransactionCount++;
				}
			}
		}

		if ($wrongTransactionCount <= 100) {
			$righe = [];
			$numRec = 0;
			ksort($dc, SORT_STRING);
			foreach ($dc as $id => $transaction) {
				if (!key_exists('totalamount', $transaction)) {
					print_r($transaction);
				}
				if ($transaction['totalamount'] != 0.0) {

					$ora = $transaction['ttime'];
					$cardNum = $transaction['fidelityCard'];
					$righe[] = sprintf('%08s%08d%-5s004%04d%04d%06d%08d%04s%13s%1s%45s',
						"20$anno$mese$giorno",
						++$numRec,
						$store,
						$transaction['trans'],
						$transaction['reg'],
						/*$transaction['operator_code']*/ 0,
						"20$anno$mese$giorno",
						substr($ora, 0, 4),
						$cardNum,
						0,
						''
					);

					if ($transaction['wrongTransaction']) {
						foreach ($transaction['sales'] as $article) {
							$articleCode = trim($articles->getArticleCode($article['barcode']));

							$righe[] = sprintf('%08s%08s%-5s1001%13s%1s%4s%09d%1d%09d%9s%9s%02d%-10s%13s%1d   ',
								"20$anno$mese$giorno",
								++$numRec,
								$store,
								($article['soldByWeight'] == True) ? substr($article['barcode'], 0, 7) : $article['barcode'],
								($articleCode != '') ? 'N' : 'Y',
								$articles->getArticleDepartmentByBarcode($article['barcode']),
								round($article['totaltaxableamount'] * 100, 0),
								($article['soldByWeight'] == True) ? 1 : 0,
								($article['soldByWeight'] == True) ? round($article['weight'] * 1000, 0) : round($article['quantity'] * 1000),
								'',
								'',
								0,
								$articles->getArticleCode($article['barcode']),
								'',
								0
							);
						}
					} else {
						foreach ($transaction['sales'] as $article) {
							$articleCode = trim($articles->getArticleCode($article['barcode']));
							$tapDiscount = 0;

							$tap = false;
							if (key_exists($articleCode, $anagdafi->articlesListedByCode)) {
								if ($anagdafi->articlesListedByCode[$articleCode]['type'] == 'TAP') {
									$tap = true;

									$salePrice = $anagdafi->articlesListedByCode[$articleCode]['salePrice'];
									$tapDiscount = round($salePrice * $article['quantity'] - $article['totalamount'], 2)/*$article['totaltaxableamount']*/
									;
									if ($article['soldByWeight']) {
										$salePrice = round($salePrice * $article['weight'], 2);
										$tapDiscount = round($salePrice - $article['totalamount'], 2);
									}

									if ($tapDiscount < 0) {
										$salePrice -= $tapDiscount;
										$tapDiscount = 0;
									}
									// cerco il codice e la campagna della promozione
									$details = $promotions->getPromotionCodes(['articleCode' => $articleCode] + ['type' => 'TAP']);
									if ($details['promotionCode'] != '990000000') {
										$codiceCampagnaSconto = $details['campaignCode'];
										$codicePromozioniSconto = $details['promotionCode'];
										$movementCode = $details['movementCode'];
									} else {
										$details = $promotions->getPromotionCodes(['type' => 'LX']);
										$codiceCampagnaSconto = $details['campaignCode'];
										$codicePromozioniSconto = $details['promotionCode'];
										$movementCode = $details['movementCode'];
									}
								}
							}

							if ($tap) {
								$righe[] = sprintf('%08s%08s%-5s1001%13s%1s%4s%09d%1d%09d%9s%9s%02d%-10s%13s%1d   ',
									"20$anno$mese$giorno",
									++$numRec,
									$store,
									($article['soldByWeight'] == True) ? substr($article['barcode'], 0, 7) : $article['barcode'],
									($articleCode != '') ? 'N' : 'Y',
									$articles->getArticleDepartmentByBarcode($article['barcode']),
									round($salePrice * 100 * $article['quantity'], 0),
									($article['soldByWeight'] == True) ? 1 : 0,
									($article['soldByWeight'] == True) ? round($article['weight'] * 1000, 0) : round($article['quantity'] * 1000),
									'',
									'',
									0,
									$articles->getArticleCode($article['barcode']),
									'',
									0
								);

								$righe[] = sprintf('%08s%08s%-5s10%02s%13s%1s%4s%09d%1d%09d%-9s%9s%02d%-10s%13s%1d   ',
									"20$anno$mese$giorno",
									++$numRec,
									$store,
									$movementCode,
									($article['soldByWeight'] == True) ? substr($article['barcode'], 0, 7) : $article['barcode'],
									($articleCode != '') ? 'N' : 'Y',
									$articles->getArticleDepartmentByBarcode($article['barcode']),
									round(abs($tapDiscount) * 100, 0),
									0,
									round(0, 0),
									$codiceCampagnaSconto,
									$codicePromozioniSconto,
									0,
									'',
									'',
									0
								);
							} else {
								$righe[] = sprintf('%08s%08s%-5s1001%13s%1s%4s%09d%1d%09d%9s%9s%02d%-10s%13s%1d   ',
									"20$anno$mese$giorno",
									++$numRec,
									$store,
									($article['soldByWeight'] == True) ? substr($article['barcode'], 0, 7) : $article['barcode'],
									($articleCode != '') ? 'N' : 'Y',
									$articles->getArticleDepartmentByBarcode($article['barcode']),
									round($article['totalamount'] * 100, 0),
									($article['soldByWeight'] == True) ? 1 : 0,
									($article['soldByWeight'] == True) ? round($article['weight'] * 1000, 0) : round($article['quantity'] * 1000),
									'',
									'',
									0,
									$articles->getArticleCode($article['barcode']),
									'',
									0
								);
							}

							foreach ($article['benefits'] as $type => $benefits) {
								foreach ($benefits as $benefit) {
									$sconto = (key_exists('amount', $benefit)) ? $benefit['amount'] : 0;
									$punti = (key_exists('points', $benefit)) ? $benefit['points'] : 0;
									$codiceCampagnaSconto = '10000';
									$codicePromozioniSconto = '990800000';
									$movementCode = '';
									$articleCode = $articles->getArticleCode($article['barcode']);
									$benefitBarcode = '';
									if (key_exists('barcode', $benefit)) {
										$benefitBarcode = $benefit['barcode'];
									}

									// cerco il codice e la campagna della promozione
									$details = $promotions->getPromotionCodes(['articleCode' => $articleCode, 'benefitBarcode' => $benefitBarcode, 'category' => $transaction['category']] + ['type' => $type] + $benefit);
									if (count($details)) {
										$codiceCampagnaSconto = $details['campaignCode'];
										$codicePromozioniSconto = $details['promotionCode'];
										$movementCode = $details['movementCode'];
									}

									if ($sconto != 0) {
										$righe[] = sprintf('%08s%08s%-5s10%02s%13s%1s%4s%09d%1d%09d%-9s%9s%02d%-10s%13s%1d   ',
											"20$anno$mese$giorno",
											++$numRec,
											$store,
											$movementCode,
											($article['soldByWeight'] == True) ? substr($article['barcode'], 0, 7) : $article['barcode'],
											'N',
											$articles->getArticleDepartmentByBarcode($article['barcode']),
											round(abs($sconto) * 100, 0),
											0,
											round(0, 0),
											$codiceCampagnaSconto,
											$codicePromozioniSconto,
											0,
											'',
											'',
											0
										);
									}
									if ($punti != 0) {
										$righe[] = sprintf('%08s%08s%-5s10%02s%13s%1s%4s%09d%1d%09d%-9s%9s%02d%-10s%13s%1d   ',
											"20$anno$mese$giorno",
											++$numRec,
											$store,
											$movementCode,
											($article['soldByWeight'] == True) ? substr($article['barcode'], 0, 7) : $article['barcode'],
											'N',
											$articles->getArticleDepartmentByBarcode($article['barcode']),
											round(abs($punti), 0),
											0,
											round(0, 0),
											$codiceCampagnaSconto,
											$codicePromozioniSconto,
											0,
											'',
											($movementCode == '90') ? $cardNum : '',
											0
										);
									}
								}
							}
						}
					}

					// punti transazione type = 0034, vengono scritti per ultimi
					foreach ($transaction['benefits'] as $benefits) {
						foreach ($benefits as $benefit) {
							if ($benefit['type'] == '0034') {
								$codiceCampagnaSconto = '10000';
								$codicePromozioniSconto = '990800000';
								$movementCode = '77';
								$details = $promotions->getPromotionCodes($benefit);
								if (count($details)) {
									$codiceCampagnaSconto = $details['campaignCode'];
									$codicePromozioniSconto = $details['promotionCode'];
									$movementCode = $details['movementCode'];
								}

								$righe[] = sprintf('%08s%08s%-5s1077%18s%09d%1d%09d%-9s%9s%02d%-10s%13s%1d   ',
									"20$anno$mese$giorno",
									++$numRec,
									$store,
									'',
									round($benefit['totalPoints'], 0),
									0,
									0,
									$codiceCampagnaSconto,
									$codicePromozioniSconto,
									0,
									'',
									'',
									0
								);
							}
						}
					}

					// chiusura transazione
					$righe[] = sprintf('%08s%08s%-5s1020%18s%09d%1d%09d%9s%9s%02d%-10s%13s%1d   ', "20$anno$mese$giorno",
						++$numRec,
						$store,
						'',
						round($transaction['totalamount'] * 100, 0),
						0,
						0,
						'',
						'',
						0,
						'',
						'',
						0
					);
				}
			}

// esportazione su file di testo
			if ($config->debug) {
				// accorpo gli sconti transazionali di tipo 0061 nel caso si usi il vecchio schema promozionale
				if ($config->oldDwhType) {
					$totalizzatore_0061 = 0;
					$rigaBase_0061 = '';
					$righeAccorpate = [];
					foreach ($righe as $riga) {
						if (preg_match('/^.{21}004/', $riga)) {
							$totalizzatore_0061 = 0;
							$rigaBase_0061 = '';
						}

						if (preg_match('/^.{23}77/', $riga) && $rigaBase_0061 != '') {
							if (preg_match('/^(.{23})51.{27}(.*)$/', $rigaBase_0061, $matches)) {
								$righeAccorpate[] = sprintf('%s%s', $matches[1], '010000000000000N00010000000000000000000                  000000000000             0   ');
								$righeAccorpate[] = sprintf('%s51%18s%09d%s', $matches[1], 'N0000', $totalizzatore_0061, $matches[2]);
							}
							$totalizzatore_0061 = 0;
							$rigaBase_0061 = '';
						}
						if (preg_match('/^.{23}20/', $riga) && $rigaBase_0061 != '') {
							if (preg_match('/^(.{23})51.{27}(.*)$/', $rigaBase_0061, $matches)) {
								$righeAccorpate[] = sprintf('%s%s', $matches[1], '010000000000000N00010000000000000000000                  000000000000             0   ');
								$righeAccorpate[] = sprintf('%s51%18s%09d%s', $matches[1], 'N0000', $totalizzatore_0061, $matches[2]);
							}
							$totalizzatore_0061 = 0;
							$rigaBase_0061 = '';
						}

						if (preg_match('/^.{23}51.{18}(\d{9}).*$/', $riga, $matches)) {
							$totalizzatore_0061 += (int)$matches[1];
							if ($rigaBase_0061 == '') {
								$rigaBase_0061 = $riga;
							}
						} else {
							$righeAccorpate[] = $riga;
						}
					}

					$righe = [];
					$contatore = 0;
					foreach ($righeAccorpate as $riga) {
						if (preg_match('/^(\d{8})\d{8}(.*)$/', $riga, $matches)) {
							$righe[] = sprintf('%s%08d%s', $matches[1], ++$contatore, $matches[2]);
						}
					}
				}

				// accorpo gli sconti transazionali di tipo 0503 nel caso si usi il vecchio schema promozionale
				/*if ($config->oldDwhType) {
					$totalizzatore_0503= 0;
					$rigaBase_0503 = '';
					$righeAccorpate = [];
					foreach ($righe as $riga) {
						if (preg_match('/^.{21}004/', $riga)) {
							$totalizzatore_0503 = 0;
							$rigaBase_0503 = '';
						}

						if (preg_match('/^.{23}77/', $riga) && $rigaBase_0503 != '') {
							if (preg_match('/^(.{23})86.{27}(.*)$/', $rigaBase_0503, $matches)) {
								$righeAccorpate[] = sprintf('%s%s', $matches[1], '010000000000000N00010000000000000000000                  000000000000             0   ');
								$righeAccorpate[] = sprintf('%s86%18s%09d%s', $matches[1], 'N0000', $totalizzatore_0503, $matches[2]);
							}
							$totalizzatore_0503 = 0;
							$rigaBase_0503 = '';
						}
						if (preg_match('/^.{23}20/', $riga) && $rigaBase_0503 != '') {
							if (preg_match('/^(.{23})86.{27}(.*)$/', $rigaBase_0503, $matches)) {
								$righeAccorpate[] = sprintf('%s%s', $matches[1], '010000000000000N00010000000000000000000                  000000000000             0   ');
								$righeAccorpate[] = sprintf('%s86%18s%09d%s', $matches[1], 'N0000', $totalizzatore_0503, $matches[2]);
							}
							$totalizzatore_0503 = 0;
							$rigaBase_0503 = '';
						}

						if (preg_match('/^.{23}86.{18}(\d{9}).*$/', $riga, $matches)) {
							$totalizzatore_0503 += (int)$matches[1];
							if ($rigaBase_0503 == '') {
								$rigaBase_0503 = $riga;
							}
						} else {
							$righeAccorpate[] = $riga;
						}
					}

					$righe = [];
					$contatore = 0;
					foreach ($righeAccorpate as $riga) {
						if (preg_match('/^(\d{8})\d{8}(.*)$/', $riga, $matches)) {
							$righe[] = sprintf('%s%08d%s', $matches[1], ++$contatore, $matches[2]);
						}
					}
				}*/

				file_put_contents($config->exportFolder . DIRECTORY_SEPARATOR . 'tap.txt', implode("\n", $tapMancanti));

				$fileName = "DC20$anno$mese$giorno" . '0' . $store . '001.DAT';
				file_put_contents($config->exportFolder . DIRECTORY_SEPARATOR . $fileName, implode("\r\n", $righe) . "\r\n");

				$fileName = "DC20$anno$mese$giorno" . '0' . $store . '001.CTL';
				file_put_contents($config->exportFolder . DIRECTORY_SEPARATOR . $fileName, '');
			}

		} else {
			echo "Transazioni Errate: $wrongTransactionCount\n";
		}
	}
	$currentDate->add(new DateInterval("P1D"));
}
