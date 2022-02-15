<?php
@ini_set('memory_limit', '16384M');

use If65\Config;

function getRawData(string $store, string $ddate): array
{
	try {
		$config = Config::Init();

		$user = $config->quadrature['user'];
		$password = $config->quadrature['password'];
		$host = $config->quadrature['host'];

		$connectionString = sprintf("mysql:host=%s", $host);

		$pdo = new PDO($connectionString, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);


		/** carico le transazioni valide */
		$stmt = "	select 
	       			store, ddate, reg, trans, transstep, ttime, userno, hour, 
	       			recordtype, recordcode1, recordcode2, recordcode3, userno, misc, data,     
	       			saleid, taxcode, amount, totalamount, totaltaxableamount, taxamount, barcode, 
	       			quantita quantity, totalpoints, paymentform, actioncode  
				from mtx.idc 
				where store = :store and ddate = :ddate and recordcode1 = '1'  and binary recordtype not in ('f','b','u','X')
				      and ((binary recordtype = 'm' and misc like '00:%') or binary recordtype <> 'm') /*and reg = '001' and trans = 758*/
				order by store, ddate, reg, trans, transstep";
		$h_query = $pdo->prepare($stmt);
		$h_query->execute([':store' => $store, ':ddate' => $ddate]);
		$rows = $h_query->fetchAll(PDO::FETCH_ASSOC);

		$pdo = null;

		/** isolo le singole transazioni */
		$id_old = '';
		$transaction = [];
		$transactions = [];
		foreach ($rows as $row) {
			$id = $row['store'] . str_replace('-', '', $row['ddate']) . $row['reg'] . str_pad($row['trans'], 4, '0', STR_PAD_LEFT);
			if ($id != $id_old) {
				if (count($transaction)) {
					$transactions[$id_old] = $transaction;
				}
				$id_old = $id;
				$transaction = [];
			}
			$transaction[] = $row;
		}
		$transactions[$id_old] = $transaction;

		return $transactions;

	} catch (PDOException $e) {
		echo "Errore: " . $e->getMessage();
		die();
	}
}

function getData(string $store, string $ddate): string
{
	$config = Config::Init();

	$transactions = getRawData($store, $ddate);

	foreach ($transactions as $id => $transaction) {
		/** DATI DI TESTATA/PIEDE SCONTRINO */
		$dc[$id]['fidelityCard'] = '';
		$dc[$id]['category'] = '';
		$dc[$id]['benefits'] = [];
		foreach ($transaction as $row) {
			if ($row['recordtype'] == 'H') {
				$dc[$id]['store'] = $row['store'];
				$dc[$id]['ddate'] = $row['ddate'];
				$dc[$id]['ttime'] = $row['ttime'];
				$dc[$id]['reg'] = $row['reg'];
				$dc[$id]['operator_code'] = $row['userno'];
				$dc[$id]['trans'] = str_pad($row['trans'], 4, '0', STR_PAD_LEFT);
			}
			if ($row['recordtype'] == 'F') {
				$dc[$id]['totalamount'] = $row['totalamount'] * 1;
			}

			if ($row['recordtype'] == 'k') {
				$dc[$id]['fidelityCard'] = $row['barcode'];
				$dc[$id]['category'] = $row['userno'] * 1;
				if ($dc[$id]['category'] == 4 || $dc[$id]['category'] == 5) {
					$dc[$id]['category'] = 1;
				}
			}
		}

		/** VENDITE */
		$sales = [];
		foreach ($transaction as $row) {
			if ($row['recordtype'] == 'S' /*and $row['totalamount'] != 0.00*/) {
				if (!preg_match('/^(998011|977011|998012)\d{3}(\d{2})\d{2}$/', $row['barcode'])) {
					$quantity = $row['quantity'];
					$soldByWeight = False;
					$weight = 0;
					if (preg_match('/^.{5}\./', $row['data'])) {
						$quantity = 1;
						$soldByWeight = True;
						$weight = $row['quantity'] * 1;
					}
					$sales[] = [
						'transstep' => $row['transstep'] * 1,
						'ttime' => $row['ttime'],
						'recordcode1' => $row['recordcode1'],
						'recordcode2' => $row['recordcode2'],
						'recordcode3' => $row['recordcode3'],
						'department' => str_pad($row['userno'], 4, '0', STR_PAD_LEFT),
						'saleid' => $row['saleid'],
						'taxcode' => $row['taxcode'],
						'amount' => $row['amount'] * 1,
						'totalamount' => $row['totalamount'] * 1,
						'totalnetamount' => $row['totalamount'] * 1, /* usato nel calcolo delle ripartizioni*/
						'totaltaxableamount' => $row['totaltaxableamount'] * 1,
						'taxamount' => $row['taxamount'] * 1,
						'barcode' => $row['barcode'],
						'quantity' => $quantity * 1,
						'soldByWeight' => $soldByWeight,
						'weight' => $weight,
						'benefits' => []
					];
				}
			}
		}

		/** 0055 - SCONTO SET A VALORE */
		if (true) {
			$discounts = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'D' && $transaction[$i + 1]['recordtype'] == 'm') {
					if (preg_match('/:0055\-(.*)\s+$/', $transaction[$i + 1]['misc'], $matches)) {
						$discount = [
							'type' => '0055',
							'benefitType' => 'discount',
							'amount' => $transaction[$i]['totalamount'] * 1,
							'promotionNumber' => $matches[1],
							'transstep' => $transaction[$i]['transstep'] * 1
						];

						$calculationBase = 0;
						$details = [];
						for ($j = $i - 1; $j >= 0; $j--) {
							if ($transaction[$j]['recordtype'] == 'd' &&
								preg_match('/:\s{0,}(\d+)\s{0,}$/', $transaction[$j]['misc'], $matches1) &&
								preg_match('/(^(?:\+|\-)\d{4}).*(\d{9})$/', $transaction[$j]['data'], $matches2)) {
								$details[] = [
									'id' => uniqid(),
									'barcode' => $matches1[1],
									'quantity' => $matches2[1] * 1,
									'amount' => $matches2[2] / 100
								];
								$calculationBase += $matches2[2] / 100;
							} else {
								break;
							}
						}

						/** stabilisco quale sia l'elemento con importo maggiore */
						$idMaxAmount = 0;
						for ($j = 0; $j < count($details); $j++) {
							if ($details[$j]['amount'] > $details[$idMaxAmount]['amount']) {
								$idMaxAmount = $j;
							}
						}

						/** calcolo la quota parte di sconto per ogni vendita */
						for ($j = 0; $j < count($details); $j++) {
							$details[$j]['share'] = round($details[$j]['amount'] / $calculationBase * $discount['amount'], 2);
						}

						/** cerco l'eventuale delta dovuto agli arrotondamenti */
						$delta = $discount['amount'];
						for ($j = 0; $j < count($details); $j++) {
							$delta = round($delta - $details[$j]['share'], 2);
						}
						$details[$idMaxAmount]['share'] = round($details[$idMaxAmount]['share'] + $delta, 2);

						$discount['details'] = $details;
						$discounts[] = $discount;
					}
				}
			}

			/* AGGANCIO GLI SCONTI ALLE VENDITE */
			foreach ($discounts as $id_discount => $discount) {
				$lastUsedTransstep = 10000;
				for ($k = 0; $k < count($discount['details']); $k++) {
					for ($i = count($sales) - 1; $i >= 0; $i--) {
						if ($sales[$i]['barcode'] == $discount['details'][$k]['barcode'] && $sales[$i]['transstep'] < $discount['transstep']) {
							if (!key_exists('0055', $sales[$i]['benefits'])) {
								$sales[$i]['benefits']['0055'] = [];
							}
							/*if ($sales[$i]['quantity'] == $discount['details'][$k]['quantity'] && ($sales[$i]['transstep'] < $lastUsedTransstep)) {
								$sales[$i]['benefits']['0055'][] = [
									'amount' => $discount['details'][$k]['share'],
									'promotionNumber' => $discount['promotionNumber']
								];
								$lastUsedTransstep = $sales[$i]['transstep'];
								break;
							}*/
							$usedQuantity = 0;
							foreach ($sales[$i]['benefits']['0055'] as $currentBenefit) {
								$usedQuantity += $currentBenefit['quantity'];
							}

							if (($sales[$i]['quantity'] - $usedQuantity) >= $discount['details'][$k]['quantity'] && ($sales[$i]['transstep'] <= $lastUsedTransstep)) {
								if (count($sales[$i]['benefits']['0055'])) {
									$tempAmount = $sales[$i]['benefits']['0055'][0]['amount'];
									$sales[$i]['benefits']['0055'][0]['amount'] = $tempAmount + $discount['details'][$k]['share'];
									$tempQuantity = $sales[$i]['benefits']['0055'][0]['quantity'];
									$sales[$i]['benefits']['0055'][0]['quantity'] = $tempQuantity + $discount['details'][$k]['quantity'];
								} else {
									$sales[$i]['benefits']['0055'][] = [
										'amount' => $discount['details'][$k]['share'],
										'promotionNumber' => $discount['promotionNumber'],
										'quantity' => $discount['details'][$k]['quantity']
									];
								}
								$lastUsedTransstep = $sales[$i]['transstep'];
								break;
							}
						}
					}
				}
			}

			/* CALCOLO IL VALORE NETTO DELLA VENDITA */
			for ($i = 0; $i < count($sales); $i++) {
				$sales[$i]['totalnetamount'] = $sales[$i]['totalamount'];
				if (count($sales[$i]['benefits'])) {
					foreach ($sales[$i]['benefits'] as $benefitType) {
						foreach ($benefitType as $benefit) {
							if (key_exists('amount', $benefit)) {
								$sales[$i]['totalnetamount'] += $benefit['amount'];
							}
						}
					}
				}
			}
		}

		/** 0054 - SCONTO SET IN PERCENTUALE */
		if (true) {
			$discounts = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'D' && $transaction[$i + 1]['recordtype'] == 'm') {
					if (preg_match('/:0054\-(.*)\s+$/', $transaction[$i + 1]['misc'], $matches)) {
						$discount = [
							'type' => '0054',
							'benefitType' => 'discount',
							'amount' => $transaction[$i]['totalamount'] * 1,
							'promotionNumber' => $matches[1],
							'transstep' => $transaction[$i]['transstep'] * 1
						];
						$calculationBase = 0;
						$details = [];
						for ($j = $i - 1; $j >= 0; $j--) {
							if ($transaction[$j]['recordtype'] == 'd' &&
								preg_match('/:\s{0,}(\d+)\s{0,}$/', $transaction[$j]['misc'], $matches1) &&
								preg_match('/(^(?:\+|\-)\d{4}).*(\d{9})$/', $transaction[$j]['data'], $matches2)) {
								$details[] = [
									'id' => uniqid(),
									'barcode' => $matches1[1],
									'quantity' => $matches2[1] * 1,
									'amount' => $matches2[2] / 100
								];
								$calculationBase += $matches2[2] / 100;
							} else {
								break;
							}
						}
						/** stabilisco quale sia l'elemento con importo maggiore */
						$idMaxAmount = 0;
						for ($j = 0; $j < count($details); $j++) {
							if ($details[$j] > $details[$idMaxAmount]) {
								$idMaxAmount = $j;
							}
						}

						/** calcolo la quota parte di sconto per ogni vendita */
						for ($j = 0; $j < count($details); $j++) {
							$details[$j]['share'] = round($details[$j]['amount'] / $calculationBase * $discount['amount'], 2);
						}
						$discount['details'] = $details;
						$discounts[] = $discount;
					}
				}
			}

			/* AGGANCIO GLI SCONTI ALLE VENDITE */
			foreach ($discounts as $id_discount => $discount) {
				$lastUsedTransstep = 10000;
				for ($k = 0; $k < count($discount['details']); $k++) {
					for ($i = count($sales) - 1; $i >= 0; $i--) {
						if ($sales[$i]['barcode'] == $discount['details'][$k]['barcode'] && $sales[$i]['transstep'] < $discount['transstep']) {
							if (!key_exists('0054', $sales[$i]['benefits'])) {
								$sales[$i]['benefits']['0054'] = [];
							}
							if ($sales[$i]['quantity'] == $discount['details'][$k]['quantity'] && ($sales[$i]['transstep'] < $lastUsedTransstep)) {
								$sales[$i]['benefits']['0054'][] = [
									'amount' => $discount['details'][$k]['share'],
									'promotionNumber' => $discount['promotionNumber']
								];
								$lastUsedTransstep = $sales[$i]['transstep'];
								break;
							}
						}
					}
				}
			}

			/* CALCOLO IL VALORE NETTO DELLA VENDITA */
			for ($i = 0; $i < count($sales); $i++) {
				$sales[$i]['totalnetamount'] = $sales[$i]['totalamount'];
				if (count($sales[$i]['benefits'])) {
					foreach ($sales[$i]['benefits'] as $benefitType) {
						foreach ($benefitType as $benefit) {
							if (key_exists('amount', $benefit)) {
								$sales[$i]['totalnetamount'] += $benefit['amount'];
							}
						}
					}
				}
			}
		}

		/** 0492 - ECONVENIENZA */
		if (true) {
			$discounts = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'C' && $transaction[$i + 1]['recordtype'] == 'm') {
					if (preg_match('/:0492\-(.*)\s+$/', $transaction[$i + 1]['misc'], $matches1)) {
						if (preg_match('/^(998011|977011|998012)\d{3}(\d{2})\d{2}$/', $transaction[$i - 1]['barcode'], $matches2)) {
							if ($matches2[1] == '998012') {
								$code = 1;
								$percentage = 30;
							} elseif ($matches2[1] == '977011') {
								$code = 2;
								$percentage = 30;
							} else {
								$code = 0;
								$percentage = $matches2[2] * 1;
							}

							$discount = [
								'type' => '0492',
								'amount' => $transaction[$i]['totalamount'] * 1,
								'barcode' => $transaction[$i]['barcode'],
								'promotionCode' => $code,
								'percentage' => $percentage,
								'promotionNumber' => $matches1[1],
								'transstep' => $transaction[$i]['transstep'] * 1
							];
							$discounts[] = $discount;
						}
					}
				}
			}

			/* AGGANCIO GLI SCONTI ALLE VENDITE */
			foreach ($discounts as $id_discount => $discount) {
				$lastUsedTransstep = 10000;
				for ($i = count($sales) - 1; $i >= 0; $i--) {
					if ($sales[$i]['barcode'] == $discount['barcode'] && $sales[$i]['transstep'] < $discount['transstep']) {
						if (!key_exists('0492', $sales[$i]['benefits']) && ($sales[$i]['transstep'] < $lastUsedTransstep)) {
							/** al massimo una per ogni vendita */
							$sales[$i]['benefits']['0492'][] = [
								'amount' => $discount['amount'],
								'promotionNumber' => $discount['promotionNumber'],
								'promotionCode' => $discount['promotionCode'],
								'percentage' => $discount['percentage'],
							];
							$lastUsedTransstep = $sales[$i]['transstep'];
							break;
						}
					}
				}
			}

			/* CALCOLO IL VALORE NETTO DELLA VENDITA */
			for ($i = 0; $i < count($sales); $i++) {
				$sales[$i]['totalnetamount'] = $sales[$i]['totalamount'];
				if (count($sales[$i]['benefits'])) {
					foreach ($sales[$i]['benefits'] as $benefitType) {
						foreach ($benefitType as $benefit) {
							if (key_exists('amount', $benefit)) {
								$sales[$i]['totalnetamount'] += $benefit['amount'];
							}
						}
					}
				}
			}
		}

		/** 0493 - SCONTO ARTICOLO SENZA RECORD M PER ERRORE DI SEQUENZA*/
		if (true) {
			$discounts = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'C' && $transaction[$i + 1]['recordtype'] == 'C' && $transaction[$i]['recordcode3'] == '3') {
					$discount = [
						'type' => '0493',
						'amount' => $transaction[$i]['totalamount'] * 1,
						'quantity' => $transaction[$i]['quantity'] * 1,
						'barcode' => $transaction[$i]['barcode'],
						'promotionNumber' => '',
						'transstep' => $transaction[$i]['transstep'] * 1
					];
					$discounts[] = $discount;
				}
			}

			foreach ($discounts as $id_discount => $discount) {
				$lastUsedTransstep = 10000;
				for ($i = count($sales) - 1; $i >= 0; $i--) {
					if ($sales[$i]['barcode'] == $discount['barcode'] && $sales[$i]['transstep'] < $discount['transstep']) {
						if (!key_exists('0493', $sales[$i]['benefits']) && ($sales[$i]['transstep'] < $lastUsedTransstep)) {
							if ($sales[$i]['quantity'] == $discount['quantity']) {
								$sales[$i]['benefits']['0493'] = [];
							}
							$sales[$i]['benefits']['0493'][] = [
								'amount' => $discount['amount'],
								'promotionNumber' => $discount['promotionNumber']
							];
							$lastUsedTransstep = $sales[$i]['transstep'];
							break;
						}
					}
				}
			}

			/* CALCOLO IL VALORE NETTO DELLA VENDITA */
			for ($i = 0; $i < count($sales); $i++) {
				$sales[$i]['totalnetamount'] = $sales[$i]['totalamount'];
				if (count($sales[$i]['benefits'])) {
					foreach ($sales[$i]['benefits'] as $benefitType) {
						foreach ($benefitType as $benefit) {
							if (key_exists('amount', $benefit)) {
								$sales[$i]['totalnetamount'] += $benefit['amount'];
							}
						}
					}
				}
			}
		}

		/** 0493 - SCONTO ARTICOLO */
		if (true) {
			$discounts = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'C' && $transaction[$i + 1]['recordtype'] == 'm') {
					if (preg_match('/:0493\-(.*)\s+$/', $transaction[$i + 1]['misc'], $matches)) {
						$discount = [
							'type' => '0493',
							'amount' => $transaction[$i]['totalamount'] * 1,
							'quantity' => $transaction[$i]['quantity'] * 1,
							'barcode' => $transaction[$i]['barcode'],
							'promotionNumber' => $matches[1],
							'transstep' => $transaction[$i]['transstep'] * 1
						];
						$discounts[] = $discount;
					}
				}
			}

			foreach ($discounts as $id_discount => $discount) {
				$lastUsedTransstep = 10000;
				for ($i = count($sales) - 1; $i >= 0; $i--) {
					if ($sales[$i]['barcode'] == $discount['barcode'] && $sales[$i]['transstep'] < $discount['transstep']) {
						if (!key_exists('0493', $sales[$i]['benefits']) && ($sales[$i]['transstep'] < $lastUsedTransstep)) {
							if ($sales[$i]['quantity'] == $discount['quantity']) {
								$sales[$i]['benefits']['0493'] = [];
							}
							$sales[$i]['benefits']['0493'][] = [
								'amount' => $discount['amount'],
								'promotionNumber' => $discount['promotionNumber']
							];
							$lastUsedTransstep = $sales[$i]['transstep'];
							break;
						}
					}
				}
			}

			/* CALCOLO IL VALORE NETTO DELLA VENDITA */
			for ($i = 0; $i < count($sales); $i++) {
				$sales[$i]['totalnetamount'] = $sales[$i]['totalamount'];
				if (count($sales[$i]['benefits'])) {
					foreach ($sales[$i]['benefits'] as $benefitType) {
						foreach ($benefitType as $benefit) {
							if (key_exists('amount', $benefit)) {
								$sales[$i]['totalnetamount'] += $benefit['amount'];
							}
						}
					}
				}
			}
		}

		/** SCONTO ARTICOLO SENZA RECORD M */
		if (true) {
			$discounts = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'C' && $transaction[$i]['recordcode3'] == '2' && $transaction[$i + 1]['recordtype'] != 'G' && ($transaction[$i + 2]['recordtype'] != 'm' ||
						($transaction[$i + 2]['recordtype'] == 'm' && !preg_match("/:0027/", $transaction[$i + 2]['misc'])))) {
					$discount = [
						'type' => '0493',
						'amount' => $transaction[$i]['totalamount'] * 1,
						'quantity' => $transaction[$i]['quantity'] * 1,
						'barcode' => $transaction[$i]['barcode'],
						'promotionNumber' => '',
						'transstep' => $transaction[$i]['transstep'] * 1
					];
					$discounts[] = $discount;
				}
			}

			foreach ($discounts as $id_discount => $discount) {
				$lastUsedTransstep = 10000;
				for ($i = count($sales) - 1; $i >= 0; $i--) {
					if ($sales[$i]['barcode'] == $discount['barcode'] && $sales[$i]['transstep'] < $discount['transstep']) {
						if (!key_exists('0493', $sales[$i]['benefits']) && ($sales[$i]['transstep'] < $lastUsedTransstep)) {
							if ($sales[$i]['quantity'] == $discount['quantity']) {
								$sales[$i]['benefits']['0493'] = [];
							}
							$sales[$i]['benefits']['0493'][] = [
								'amount' => $discount['amount'],
								'promotionNumber' => $discount['promotionNumber']
							];
							$lastUsedTransstep = $sales[$i]['transstep'];
							break;
						}
					}
				}
			}

			/* CALCOLO IL VALORE NETTO DELLA VENDITA */
			for ($i = 0; $i < count($sales); $i++) {
				$sales[$i]['totalnetamount'] = $sales[$i]['totalamount'];
				if (count($sales[$i]['benefits'])) {
					foreach ($sales[$i]['benefits'] as $benefitType) {
						foreach ($benefitType as $benefit) {
							if (key_exists('amount', $benefit)) {
								$sales[$i]['totalnetamount'] += $benefit['amount'];
							}
						}
					}
				}
			}
		}

		/** PAGO CON NIMIS PARTE 1 = SCONTO */
		if (true) {
			$discounts = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'C' && $transaction[$i]['recordcode3'] == '2' && $transaction[$i + 1]['recordtype'] == 'G') {
					if (preg_match('/:0027\-(.*)\s+$/', $transaction[$i + 2]['misc'], $matches)) {
						$discount = [
							'type' => '0027',
							'amount' => $transaction[$i]['totalamount'] * 1,
							'quantity' => $transaction[$i]['quantity'] * 1,
							'barcode' => $transaction[$i]['barcode'],
							'promotionNumber' => $matches[1],
							'transstep' => $transaction[$i]['transstep'] * 1
						];
						$discounts[] = $discount;
					}
				}
			}

			foreach ($discounts as $id_discount => $discount) {
				$lastUsedTransstep = 10000;
				for ($i = count($sales) - 1; $i >= 0; $i--) {
					if ($sales[$i]['barcode'] == $discount['barcode'] && $sales[$i]['transstep'] < $discount['transstep']) {
						if (!key_exists('0027', $sales[$i]['benefits']) && ($sales[$i]['transstep'] < $lastUsedTransstep)) {
							if ($sales[$i]['quantity'] == $discount['quantity']) {
								$sales[$i]['benefits']['0027'] = [];
							}
							$sales[$i]['benefits']['0027'][] = [
								'amount' => $discount['amount'],
								'promotionNumber' => $discount['promotionNumber']
							];
							$lastUsedTransstep = $sales[$i]['transstep'];
							break;
						}
					}
				}
			}

			/* CALCOLO IL VALORE NETTO DELLA VENDITA */
			for ($i = 0; $i < count($sales); $i++) {
				$sales[$i]['totalnetamount'] = $sales[$i]['totalamount'];
				if (count($sales[$i]['benefits'])) {
					foreach ($sales[$i]['benefits'] as $benefitType) {
						foreach ($benefitType as $benefit) {
							if (key_exists('amount', $benefit)) {
								$sales[$i]['totalnetamount'] += $benefit['amount'];
							}
						}
					}
				}
			}
		}

		/** 0481 - SCONTO A REPARTO */
		if (true) {
			$discounts = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'D' && $transaction[$i]['recordcode3'] == '6' && $transaction[$i + 1]['recordtype'] == 'w') {
					if (preg_match('/\s*(\d*)$/', $transaction[$i + 1]['misc'], $matches)) {
						$discount = [
							'type' => '0481',
							'amount' => $transaction[$i]['totalamount'] * 1,
							'barcode' => $matches[1],
							'transstep' => $transaction[$i]['transstep'] * 1
						];
						$calculationBase = 0;
						$details = [];
						for ($j = $i - 1; $j >= 0; $j--) {
							if ($transaction[$j]['recordtype'] == 'd' &&
								preg_match('/(?:^\s*(\d+)\s*$|\:\s*(\d+)\s*$|^()$)/', $transaction[$j]['misc'], $matches1) &&
								preg_match('/(^(?:\+|\-)\d{4}).*(\d{9})$/', $transaction[$j]['data'], $matches2)) {
								$details[] = [
									'id' => uniqid(),
									'barcode' => ($matches1[1] != '') ? $matches1[1] : (($matches1[2] != '') ? $matches1[2] : $matches1[3]),
									'quantity' => $matches2[1] * 1,
									'amount' => $matches2[2] / 100
								];
								$calculationBase += $matches2[2] / 100;
							} else {
								break;
							}
						}
						/** stabilisco quale sia l'elemento con importo maggiore */
						$idMaxAmount = 0;
						for ($j = 0; $j < count($details); $j++) {
							if ($details[$j]['amount'] > $details[$idMaxAmount]['amount']) {
								$idMaxAmount = $j;
							}
						}

						/** calcolo la quota parte di sconto per ogni vendita */
						for ($j = 0; $j < count($details); $j++) {
							$details[$j]['share'] = round($details[$j]['amount'] / $calculationBase * $discount['amount'], 2);
						}

						/** cerco l'eventuale delta dovuto agli arrotondamenti */
						$delta = $discount['amount'];
						for ($j = 0; $j < count($details); $j++) {
							$delta = round($delta - $details[$j]['share'], 2);
						}
						$details[$idMaxAmount]['share'] = round($details[$idMaxAmount]['share'] + $delta, 2);

						$discount['details'] = $details;
						$discounts[] = $discount;
					}
				}
			}

			foreach ($discounts as $id_discount => $discount) {
				$lastUsedTransstep = 10000;
				for ($k = 0; $k < count($discount['details']); $k++) {
					for ($i = count($sales) - 1; $i >= 0; $i--) {
						if ($sales[$i]['barcode'] == $discount['details'][$k]['barcode'] && $sales[$i]['transstep'] < $discount['transstep']) {
							if (!key_exists('0481', $sales[$i]['benefits'])) {
								$sales[$i]['benefits']['0481'] = [];
							}
							if ($sales[$i]['quantity'] == $discount['details'][$k]['quantity'] && ($sales[$i]['transstep'] < $lastUsedTransstep)) {
								$sales[$i]['benefits']['0481'][] = [
									'amount' => $discount['details'][$k]['share'],
									'barcode' => $discount['barcode']
								];
								$lastUsedTransstep = $sales[$i]['transstep'];
								break;
							}
						}
					}
				}
			}

			/* CALCOLO IL VALORE NETTO DELLA VENDITA */
			for ($i = 0; $i < count($sales); $i++) {
				$sales[$i]['totalnetamount'] = $sales[$i]['totalamount'];
				if (count($sales[$i]['benefits'])) {
					foreach ($sales[$i]['benefits'] as $benefitType) {
						foreach ($benefitType as $benefit) {
							if (key_exists('amount', $benefit)) {
								$sales[$i]['totalnetamount'] += $benefit['amount'];
							}
						}
					}
				}
			}
		}

		/** PAGO CON NIMIS PARTE 2 = PUNTI */
		if (true) {
			$points = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'G' && $transaction[$i]['recordcode2'] == '3' && $transaction[$i + 1]['recordtype'] == 'm') {
					if (preg_match('/:0027\-(.*)\s+$/', $transaction[$i + 1]['misc'], $matches)) {
						$point = [
							'type' => '0027',
							'amount' => $transaction[$i]['totalamount'] * 1,
							'points' => $transaction[$i]['totalpoints'] * 1,
							'quantity' => $transaction[$i]['quantity'] * 1,
							'barcode' => $transaction[$i]['barcode'],
							'promotionNumber' => $matches[1],
							'transstep' => $transaction[$i]['transstep'] * 1
						];
						$points[] = $point;
					}
				}
			}

			foreach ($points as $id_point => $point) {
				$lastUsedTransstep = 10000;
				for ($i = count($sales) - 1; $i >= 0; $i--) {
					if ($sales[$i]['barcode'] == $point['barcode'] && $sales[$i]['transstep'] < $point['transstep']) {
						if ($sales[$i]['transstep'] < $lastUsedTransstep) {
							/*if ($sales[$i]['quantity'] == $point['quantity']) {
								$sales[$i]['benefits']['0027'] = [];
							}*/
							$sales[$i]['benefits']['0027'][] = [
								'points' => $point['points'],
								'promotionNumber' => $point['promotionNumber']
							];
							$lastUsedTransstep = $sales[$i]['transstep'];
							break;
						}
					}
				}
			}
		}

		/** 0022 - ACCELERATORE PUNTI */
		if (true) {
			$points = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'G' && $transaction[$i]['recordcode2'] == '1' && $transaction[$i + 1]['recordtype'] == 'm') {
					if (preg_match('/:0022\-(.*)\s+$/', $transaction[$i + 1]['misc'], $matches)) {
						$point = [
							'type' => '0022',
							'amount' => $transaction[$i]['totalamount'] * 1,
							'points' => $transaction[$i]['totalpoints'] * 1,
							'quantity' => $transaction[$i]['quantity'] * 1,
							'barcode' => $transaction[$i]['barcode'],
							'promotionNumber' => $matches[1],
							'transstep' => $transaction[$i]['transstep'] * 1
						];
						$points[] = $point;
					}
				}
			}

			foreach ($points as $id_point => $point) {
				$lastUsedTransstep = 10000;
				for ($i = count($sales) - 1; $i >= 0; $i--) {
					if ($sales[$i]['barcode'] == $point['barcode'] && $sales[$i]['transstep'] < $point['transstep']) {
						if (!key_exists('0022', $sales[$i]['benefits']) && ($sales[$i]['transstep'] < $lastUsedTransstep)) {
							if ($sales[$i]['quantity'] == $point['quantity']) {
								$sales[$i]['benefits']['0022'] = [];
							}
							$sales[$i]['benefits']['0022'][] = [
								'points' => $point['points'],
								'promotionNumber' => $point['promotionNumber']
							];
							$lastUsedTransstep = $sales[$i]['transstep'];
							break;
						}
					}
				}
			}
		}

		/** 0023 - PREMI COLLECTION */
		if (true) {
			$points = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'G' && $transaction[$i]['recordcode2'] == '3' && $transaction[$i + 1]['recordtype'] == 'm') {
					if (preg_match('/:0023\-(.*)\s+$/', $transaction[$i + 1]['misc'], $matches)) {
						$point = [
							'type' => '0023',
							'amount' => 0,
							'points' => $transaction[$i]['totalpoints'] * 1,
							'quantity' => $transaction[$i]['quantity'] * 1,
							'barcode' => $transaction[$i]['barcode'],
							'promotionNumber' => $matches[1],
							'transstep' => $transaction[$i]['transstep'] * 1
						];
						$points[] = $point;
					}
				}
			}

			foreach ($points as $id_point => $point) {
				$lastUsedTransstep = 10000;
				for ($i = count($sales) - 1; $i >= 0; $i--) {
					if ($sales[$i]['barcode'] == $point['barcode'] && $sales[$i]['transstep'] < $point['transstep']) {
						if (!key_exists('0023', $sales[$i]['benefits']) && ($sales[$i]['transstep'] < $lastUsedTransstep)) {
							if ($sales[$i]['quantity'] == $point['quantity']) {
								$sales[$i]['benefits']['0023'] = [];
							}
							$sales[$i]['benefits']['0023'][] = [
								'points' => $point['points'],
								'promotionNumber' => $point['promotionNumber']
							];
							$lastUsedTransstep = $sales[$i]['transstep'];
							break;
						}
					}
				}
			}
		}

		/** 0503 - SCONTO TRANSAZIONALE */
		if (true) {
			$discounts = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'D' && $transaction[$i]['recordcode3'] == '7' && $transaction[$i + 1]['recordtype'] == 'w') {
					if (preg_match('/\s*(\d*)$/', $transaction[$i + 1]['misc'], $matches)) {
						$discount = [
							'type' => '0503',
							'amount' => $transaction[$i]['totalamount'] * 1,
							'barcode' => $matches[1]
						];
						$details = [];
						$calculationBase = 0;
						foreach ($sales as $sale) {
							$details[] = [
								'id' => uniqid(),
								'barcode' => $sale['barcode'],
								'quantity' => $sale['quantity'],
								'amount' => $sale['totalamount'],
								'totalnetamount' => $sale['totalnetamount'],
								'saleid' => $sale['saleid'],
							];
							$calculationBase += $sale['totalnetamount'];
						}

						/** stabilisco quale sia l'elemento con importo maggiore */
						$idMaxAmount = 0;
						for ($j = 0; $j < count($sales); $j++) {
							if ($sales[$j]['totalnetamount'] > $sales[$idMaxAmount]['totalnetamount']) {
								$idMaxAmount = $j;
							}
						}

						/** calcolo la quota parte di sconto per ogni vendita */
						for ($j = 0; $j < count($details); $j++) {
							$details[$j]['share'] = round($details[$j]['totalnetamount'] / $calculationBase * $discount['amount'], 2);
						}

						/** cerco l'eventuale delta dovuto agli arrotondamenti */
						$delta = $discount['amount'];
						for ($j = 0; $j < count($details); $j++) {
							$delta = round($delta - $details[$j]['share'], 2);
						}
						$details[$idMaxAmount]['share'] = round($details[$idMaxAmount]['share'] + $delta, 2);

						$discount['details'] = $details;
						$discounts[] = $discount;
					}
				}
			}


			foreach ($discounts as $id_discount => $discount) {
				$lastUsedTransstep = 10000;
				for ($k = 0; $k < count($discount['details']); $k++) {
					for ($i = count($sales) - 1; $i >= 0; $i--) {
						if ($sales[$i]['saleid'] == $discount['details'][$k]['saleid']) {
							if (!key_exists('0503', $sales[$i]['benefits'])) {
								$sales[$i]['benefits']['0503'] = [];
							}
							$sales[$i]['benefits']['0503'][] = [
								'amount' => $discount['details'][$k]['share'],
								'barcode' => $discount['barcode']
							];
						}
					}
				}
			}


			/* CALCOLO IL VALORE NETTO DELLA VENDITA */
			for ($i = 0; $i < count($sales); $i++) {
				$sales[$i]['totalnetamount'] = $sales[$i]['totalamount'];
				if (count($sales[$i]['benefits'])) {
					foreach ($sales[$i]['benefits'] as $benefitType) {
						foreach ($benefitType as $benefit) {
							if (key_exists('amount', $benefit)) {
								$sales[$i]['totalnetamount'] += $benefit['amount'];
							}
						}
					}
				}
			}
		}

		/** 0061 - SCONTO TRANSAZIONALE CON RECORD M */
		$discounts = [];
		for ($i = count($transaction) - 2; $i >= 0; $i--) {
			if ($transaction[$i]['recordtype'] == 'm' && $transaction[$i + 1]['recordtype'] == 'D') {
				if (preg_match('/:0061\-(.*)\s+$/', $transaction[$i]['misc'])) {
					if ($transaction[$i + 1]['recordcode2'] == '9' && $transaction[$i + 1]['recordcode3'] == '8') {
						$toSwap = $transaction[$i + 1];
						$transaction[$i + 1] = $transaction[$i];
						$transaction[$i] = $toSwap;
					}
				}
			}
		}
		for ($i = count($transaction) - 2; $i >= 0; $i--) {
			if ($transaction[$i]['recordtype'] == 'D' && $transaction[$i + 1]['recordtype'] == 'm') {
				if (preg_match('/:0061\-(.*)\s+$/', $transaction[$i + 1]['misc'], $matches)) {
					$discount = [
						'type' => '0061',
						'amount' => $transaction[$i]['totalamount'] * 1,
						'promotionNumber' => $matches[1]
					];
					$details = [];
					$calculationBase = 0;
					foreach ($sales as $sale) {
						$details[] = [
							'id' => uniqid(),
							'barcode' => $sale['barcode'],
							'quantity' => $sale['quantity'],
							'amount' => $sale['totalamount'],
							'totalnetamount' => $sale['totalnetamount'],
							'saleid' => $sale['saleid'],
						];
						$calculationBase += $sale['totalnetamount'];
					}

					/** stabilisco quale sia l'elemento con importo maggiore */
					$idMaxAmount = 0;
					for ($j = 0; $j < count($sales); $j++) {
						if ($sales[$j]['totalnetamount'] > $sales[$idMaxAmount]['totalnetamount']) {
							$idMaxAmount = $j;
						}
					}

					/** calcolo la quota parte di sconto per ogni vendita */
					for ($j = 0; $j < count($details); $j++) {
						$details[$j]['share'] = round($details[$j]['totalnetamount'] / $calculationBase * $discount['amount'], 2);
					}

					/** cerco l'eventuale delta dovuto agli arrotondamenti */
					$delta = $discount['amount'];
					for ($j = 0; $j < count($details); $j++) {
						$delta = round($delta - $details[$j]['share'], 2);
					}
					$details[$idMaxAmount]['share'] = round($details[$idMaxAmount]['share'] + $delta, 2);

					$discount['details'] = $details;
					$discounts[] = $discount;
				}
			}
		}

		foreach ($discounts as $id_discount => $discount) {
			for ($k = 0; $k < count($discount['details']); $k++) {
				for ($i = count($sales) - 1; $i >= 0; $i--) {
					if ($sales[$i]['saleid'] == $discount['details'][$k]['saleid']) {
						if (!key_exists('0061', $sales[$i]['benefits'])) {
							$sales[$i]['benefits']['0061'] = [];
						}
						$sales[$i]['benefits']['0061'][] = [
							'amount' => $discount['details'][$k]['share'],
							'promotionNumber' => $discount['promotionNumber']
						];
					}
				}
			}
		}

		/* CALCOLO IL VALORE NETTO DELLA VENDITA */
		for ($i = 0; $i < count($sales); $i++) {
			$sales[$i]['totalnetamount'] = $sales[$i]['totalamount'];
			if (count($sales[$i]['benefits'])) {
				foreach ($sales[$i]['benefits'] as $benefitType) {
					foreach ($benefitType as $benefit) {
						if (key_exists('amount', $benefit)) {
							$sales[$i]['totalnetamount'] += $benefit['amount'];
						}
					}
				}
			}
		}


		/** 0034 - PUNTI TRANSAZIONE */
		if (true) {
			$points = [];
			for ($i = count($transaction) - 2; $i >= 0; $i--) {
				if ($transaction[$i]['recordtype'] == 'G' && $transaction[$i + 1]['recordtype'] == 'm') {
					if (preg_match('/^.{3}((?:\+|\-)\d{5})/', $transaction[$i]['data'], $matches1)) {
						if (preg_match('/:0034\-(.*)\s+$/', $transaction[$i + 1]['misc'], $matches2)) {
							$point = [
								'type' => '0034',
								'totalPoints' => $matches1[1] * 1,
								'promotionNumber' => $matches2[1],
								'transstep' => $transaction[$i]['transstep'] * 1
							];
							$points[] = $point;
						}
					}
				}
			}
			if (count($points)) {
				$dc[$id]['benefits'][] = $points;
			}
		}

		$dc[$id]['sales'] = $sales;

		foreach ($dc[$id]['sales'] as $idSale => $sale) {
			if ($sale['totalamount'] < 0 && ($sale['recordcode2'] == '7' || $sale['recordcode2'] == '8')) {
				for ($i = 0; $i < $idSale; $i++) {
					if ($dc[$id]['sales'][$i]['saleid'] == $dc[$id]['sales'][$idSale]['saleid']) {
						$dc[$id]['sales'][$i]['status'] = 'deleted';
						$dc[$id]['sales'][$idSale]['status'] = 'deleted';
					}
				}
			}
		}

		for ($i = count($dc[$id]['sales']) - 1; $i >= 0; $i--) {
			if (key_exists('status', $dc[$id]['sales'][$i])) {
				if ($dc[$id]['sales'][$i]['status'] == 'deleted') {
					array_splice($dc[$id]['sales'], $i, 1);
				}
			}
		}

		$totalControlAmount = 0;
		foreach ($dc[$id]['sales'] as $idSale => $sale) {
			$totalControlAmount += $sale['totalnetamount'];
		}
		$wrongTransaction = false;
		if (round($dc[$id]['totalamount'] - $totalControlAmount, 2) != 0.00) {
			$wrongTransaction = true;
		}
		$dc[$id]['wrongTransaction'] = $wrongTransaction;

	}

	return json_encode($dc, JSON_PRETTY_PRINT);
}
