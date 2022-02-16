<?php

namespace If65\Views;

use If65\Config;
use PDO;

class Promotions
{
	private $pdo;
	private $user = '';
	private $password = '';
	private $host = '';
	private $storeType = '0'; // 0=asar, 1=cash, 2=tcpos

	private $config;

	private $barcodeMenu = [
		"9770110000016" => "9891094", //01-MENU 1 2021
		"9770110000023" => "9891101", //02-MENU 2 2021
		"9770110000030" => "9891003", //03-MENU KIDS 2021
		"9770110000047" => "9891012", //04-MENU HAMBURGER 2021
		"9770110000054" => "9891021", //05-MENU CLASSICO 2021
		"9770110000061" => "9891110", //06-MENU 6 2021
		"9770110000078" => "9891129", //07-MENU 7 2021
		"9770110000085" => "9891058", //08-MENU PRIMO 2021
		"9770110000092" => "9891138", //09-MENU 9 2021
		"9770110000108" => "9891147", //10-MENU 10 2021
		"9770110000115" => "9891085", //11-MENU KIDS 2021
		"9770110000122" => "9891030", //12-MENU SPECIALE 2021
		"9770110000139" => "9891049", //13-MENU GOURMET 2021
		"9770110000146" => "9891067", //14-MENU SECONDO DI CARNE 2021
		"9770110000153" => "9891076", //15-MENU SECONDO DI PESCE 2021
		"9770110000160" => "9891156", //16-MENU ARMONIA 2021
		"9770110000177" => "9891165", //17-MENU EQULIBRIO 2021
		"9770110000184" => "9891174", //18-MENU ARMONIA 2021
		"9770110000191" => "9891183", //19-MENU EQUILIBRIO 2021
		"9770110000207" => "9891192", //20-MENU 20 2021
	];

	private $promotionRows;
	private $promotions;

	public function __construct(string $store, string $startingDate, string $endingDate)
	{
		$this->config = Config::Init();

		try {
			$this->user = $this->config->cm['user'];
			$this->password = $this->config->cm['password'];
			$this->host = $this->config->cm['host'];

			$connectionString = sprintf("mysql:host=%s", $this->host);
			$this->pdo = new PDO($connectionString, $this->user, $this->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

			$stmt = "	select codice store, 0 storeType from archivi.negozi where societa in (02,05)
						union all
						select codiceTcPos store, 2 storeType from archivi.negozi where societa in (02,05) and codiceTcPos <> ''";

			$h_query = $this->pdo->prepare($stmt);
			$h_query->execute();
			$rows = $h_query->fetchAll(PDO::FETCH_ASSOC);
			$this->storeType = 0;
			foreach ($rows as $row) {
				if ($row['store'] == $store) {
					$this->storeType = $row['storeType'];
					break;
				}
			}

			$this->loadPromotions($store, $startingDate, $endingDate);

		} catch (PDOException $e) {
			die($e->getMessage());
		}
	}

	private function loadPromotions(string $store, string $startingDate, string $endingDate)
	{
		try {
			/** carico la le promozioni valide */
			$stmt = '';
			if ($this->config->oldDwhType) {
				$stmt = "	select p.codice_campagna, p.codice_promozione, p.descrizione, p.tipo, p.data_inizio, p.data_fine, p.codice_articolo, 
	       					p.codice_reparto, p.parametro_01, p.parametro_02, p.parametro_03 
						from cm.promozioni as p join cm.negozi_promozioni as n on p.codice_promozione = n.promozione_codice 
						where data_inizio <= :startingDate and data_fine >= :endingDate and n.`negozio_codice`= :store";
			} else {
				$stmt = "	select p.codice_campagna, p.codice_promozione, p.descrizione, p.tipo, p.data_inizio, p.data_fine, p.codice_articolo, 
	       					p.codice_reparto, p.parametro_01, p.parametro_02, p.parametro_03 
						from cm.promozioni_new as p join cm.negozi_promozioni_new as n on p.codice_promozione = n.promozione_codice 
						where data_inizio <= :startingDate and data_fine >= :endingDate and n.`negozio_codice`= :store";
			}
			$h_query = $this->pdo->prepare($stmt);
			$h_query->execute([':store' => $store, ':startingDate' => $startingDate, ':endingDate' => $endingDate]);
			$this->promotionRows = $h_query->fetchAll(PDO::FETCH_ASSOC);

			/** indicizzo per tipo promozione */
			/*$this->promotions = [];
			foreach ($rows as $row) {
				if (!key_exists($row['tipo'], $this->promotions)) {
					$this->promotions[$row['tipo']] = [];
				}
				$this->promotions[$row['tipo']][] = $row;
			}*/
		} catch (PDOException $e) {
			echo "Errore: " . $e->getMessage();
			die();
		}
	}

	public function loadActivePromotions(string $ddate) {
		$rows = [];
		foreach ($this->promotionRows as $row) {
			if ($row['data_inizio'] <= $ddate and $row['data_fine'] >= $ddate) {
				$rows[] = $row;
			}
		}

		$this->promotions = [];
		foreach ($rows as $row) {
			if (!key_exists($row['tipo'], $this->promotions)) {
				$this->promotions[$row['tipo']] = [];
			}
			$this->promotions[$row['tipo']][] = $row;
		}
	}

	public function getPromotionCodes(array $request): array
	{
		if ($this->config->oldDwhType) {
			return $this->getPromotionCodesOld($request);
		} else {
			return $this->getPromotionCodesNew($request);
		}
	}

	private function getPromotionCodesNew(array $request): array
	{
		$type = $request['type'];

		$codes = [
			'campaignCode' => '10000',
			'promotionCode' => '990000000',
			'movementCode' => '00'];

		if ($type == 'TAP') {
			foreach ($this->promotions['XP'] as $promotion) {
				if ($promotion['codice_articolo'] == $request['articleCode']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '50'];
					break;
				}
			}
			foreach ($this->promotions['MP'] as $promotion) {
				if ($promotion['codice_articolo'] == $request['articleCode']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '13'];
					break;
				}
			}
		}
		if ($type == 'LX') {
			foreach ($this->promotions['LX'] as $promotion) {
				$codes = [
					'campaignCode' => $promotion['codice_campagna'],
					'promotionCode' => $promotion['codice_promozione'],
					'movementCode' => '88'];
				break;
			}
		}
		if ($type == '0492') {
			foreach ($this->promotions['MP'] as $promotion) {
				if ($promotion['parametro_01'] == ($request['percentage'] * 100) && $promotion['parametro_02'] == $request['promotionCode']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '62'];
					break;
				}
			}
		}
		if ($type == '0493') {
			$found = false;
			if (key_exists('PF', $this->promotions)) {
				foreach ($this->promotions['PF'] as $promotion) {
					if ($promotion['codice_articolo'] == $request['articleCode']) {
						$codes = [
							'campaignCode' => $promotion['codice_campagna'],
							'promotionCode' => $promotion['codice_promozione'],
							'movementCode' => '91'];
						$found = true;
						break;
					}
				}
			}
			if (!$found and key_exists('AP', $this->promotions)) {
				foreach ($this->promotions['AP'] as $promotion) {
					if ($promotion['codice_articolo'] == $request['articleCode']) {
						$codes = [
							'campaignCode' => $promotion['codice_campagna'],
							'promotionCode' => $promotion['codice_promozione'],
							'movementCode' => '91'];
						break;
					}
				}
			}
		}
		if ($type == '0503') {
			foreach ($this->promotions['TV'] as $promotion) {
				$codes = [
					'campaignCode' => $promotion['codice_campagna'],
					'promotionCode' => $promotion['codice_promozione'],
					'movementCode' => '86'];
				break;
			}
		}
		if ($type == '0027') {
			if (!key_exists('points', $request)) {
				foreach ($this->promotions['BM'] as $promotion) {
					if ($promotion['codice_articolo'] == $request['articleCode']) {
						$codes = [
							'campaignCode' => $promotion['codice_campagna'],
							'promotionCode' => $promotion['codice_promozione'],
							'movementCode' => '94'];
						break;
					}
				}
			} else {
				foreach ($this->promotions['BM'] as $promotion) {
					if ($promotion['codice_articolo'] == $request['articleCode']) {
						$codes = [
							'campaignCode' => $promotion['codice_campagna'],
							'promotionCode' => $promotion['codice_promozione'],
							'movementCode' => '93'];
						break;
					}
				}
			}
		}
		if ($type == '0022') {
			foreach ($this->promotions['BJ'] as $promotion) {
				if ($promotion['codice_articolo'] == $request['articleCode']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '89'];
					break;
				}
			}
		}
		if ($type == '0023') {
			foreach ($this->promotions['BP'] as $promotion) {
				if ($promotion['codice_articolo'] == $request['articleCode']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '92'];
					break;
				}
			}
		}
		if ($type == '0055') {
			foreach ($this->promotions['SM'] as $promotion) {
				if ($promotion['codice_articolo'] == $request['articleCode']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '87'];
					break;
				}
			}
		}
		if ($type == '0034') {
			foreach ($this->promotions['BT'] as $promotion) {
				if ($promotion['parametro_03'] == '1') {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '77'];
					break;
				}
			}
		}
		if ($type == '0481') {
			foreach ($this->promotions['RV'] as $promotion) {
				$codes = [
					'campaignCode' => $promotion['codice_campagna'],
					'promotionCode' => $promotion['codice_promozione'],
					'movementCode' => '85'];
				break;

			}
		}
		/*if ($type == '0481' && $this->storeType == '0') {
			foreach ($this->promotions['BJ'] as $promotion) {
				if ($request['articleCode'] == $promotion['codice_articolo']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '89'];
					break;
				}
			}
		}
		if ($type == '0481' && $this->storeType == '2' ) {
			foreach ($this->promotions['MP'] as $promotion) {
				if ($this->barcodeMenu[$request['benefitBarcode']] == $promotion['codice_articolo']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '13'];
					break;
				}
			}
		}*/
		if ($type == '0061') {
			if ($request['category'] == 1) {
				foreach ($this->promotions['ED'] as $promotion) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '55'];
					break;
				}
			} else {
				foreach ($this->promotions['MT'] as $promotion) {
					if ($request['category'] == $promotion['parametro_02']) {
						$codes = [
							'campaignCode' => $promotion['codice_campagna'],
							'promotionCode' => $promotion['codice_promozione'],
							'movementCode' => '51'];
						break;
					}
				}
			}
		}
		return $codes;
	}

	private function getPromotionCodesOld(array $request): array
	{
		$type = $request['type'];

		$codes = [
			'campaignCode' => '10000',
			'promotionCode' => '990000000',
			'movementCode' => '00'
		];

		if ($type == '0492') {
			foreach ($this->promotions['MP'] as $promotion) {
				if ($promotion['parametro_01'] == ($request['percentage']) && $promotion['parametro_02'] == $request['promotionCode']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '13'];
					break;
				}
			}
		}
		if ($type == '0055') {
			foreach ($this->promotions['MP'] as $promotion) {
				if ($promotion['parametro_01'] == 30 && $promotion['parametro_02'] == 0) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '13'];
					break;
				}
			}
		}
		if ($type == '0493') {
			$found = false;
			if (key_exists('PF', $this->promotions)) {
				foreach ($this->promotions['PF'] as $promotion) {
					if ($promotion['codice_articolo'] == $request['articleCode']) {
						$codes = [
							'campaignCode' => $promotion['codice_campagna'],
							'promotionCode' => $promotion['codice_promozione'],
							'movementCode' => '91'];
						$found = true;
						break;
					}
				}
			}
			if (!$found and key_exists('AP', $this->promotions)) {
				foreach ($this->promotions['AP'] as $promotion) {
					if ($promotion['codice_articolo'] == $request['articleCode']) {
						$codes = [
							'campaignCode' => $promotion['codice_campagna'],
							'promotionCode' => $promotion['codice_promozione'],
							'movementCode' => '91'];
						break;
					}
				}
			}
		}
		if ($type == '0503') {
			foreach ($this->promotions['TV'] as $promotion) {
				$codes = [
					'campaignCode' => $promotion['codice_campagna'],
					'promotionCode' => $promotion['codice_promozione'],
					'movementCode' => '86'];
				break;
			}
		}
		if ($type == '0027') {
			if (!key_exists('points', $request)) {
				foreach ($this->promotions['BM'] as $promotion) {
					if ($promotion['codice_articolo'] == $request['articleCode']) {
						$codes = [
							'campaignCode' => $promotion['codice_campagna'],
							'promotionCode' => $promotion['codice_promozione'],
							'movementCode' => '94'];
						break;
					}
				}
			} else {
				foreach ($this->promotions['BM'] as $promotion) {
					if ($promotion['codice_articolo'] == $request['articleCode']) {
						$codes = [
							'campaignCode' => $promotion['codice_campagna'],
							'promotionCode' => $promotion['codice_promozione'],
							'movementCode' => '93'];
						break;
					}
				}
			}
		}
		if ($type == '0022') {
			foreach ($this->promotions['BJ'] as $promotion) {
				if ($promotion['codice_articolo'] == $request['articleCode']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '89'];
					break;
				}
			}
		}
		if ($type == '0023') {
			foreach ($this->promotions['BP'] as $promotion) {
				if ($promotion['codice_articolo'] == $request['articleCode']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '90'];
					break;
				}
			}
		}
		if ($type == '0034') {
			foreach ($this->promotions['BT'] as $promotion) {
				if ($promotion['parametro_03'] == '1') {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '77'];
					break;
				}
			}
		}
		if ($type == '0481' && $this->storeType == '0') {
			foreach ($this->promotions['BJ'] as $promotion) {
				if ($request['articleCode'] == $promotion['codice_articolo']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '89'];
					break;
				}
			}
		}
		if ($type == '0481' && $this->storeType == '2') {
			foreach ($this->promotions['MP'] as $promotion) {
				if ($request['benefitBarcode'] == "" || !key_exists($request['benefitBarcode'], $this->barcodeMenu)) {
					$request['benefitBarcode'] = "9770110000054";
				}

				if ($this->barcodeMenu[$request['benefitBarcode']] == $promotion['codice_articolo']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '13'];
					break;
				}
			}
		}
		if ($type == '0061') {
			if ($request['category'] == 1 || $this->storeType == 2) {
				foreach ($this->promotions['MT'] as $promotion) {
					if ($promotion['parametro_02'] == 1) {
						$codes = [
							'campaignCode' => $promotion['codice_campagna'],
							'promotionCode' => $promotion['codice_promozione'],
							'movementCode' => '51'];
						break;
					}
				}
			} else {
				foreach ($this->promotions['MT'] as $promotion) {
					if ($request['category'] == $promotion['parametro_02']) {
						$codes = [
							'campaignCode' => $promotion['codice_campagna'],
							'promotionCode' => $promotion['codice_promozione'],
							'movementCode' => '51'];
						break;
					}
				}
			}
		}

		return $codes;
	}
}