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

	private $barcodeMenu = [
		//'9770110000016' => '',
		//'9770110000023' => '',
		'9770110000030' => '9891003', //3-MENU KIDS 2020
		'9770110000047' => '9891012', //4-MENU HAMBURGER 2020
		'9770110000054' => '9891021', //5-MENU CLASSICO 2020
		//'9770110000061' => '',
		//'9770110000078' => '',
		'9770110000085' => '9891058', //8-MENU PRIMO 2020
		//'9770110000092' => '',
		//'9770110000108' => '',
		//'9770110000115' => '',
		'9770110000122' => '9891030', //12-MENU SPECIALE 2020
		'9770110000139' => '9891049', //13-MENU GOURMET 2020
		'9770110000146' => '9891067', //14-MENU SECONDO DI CARNE 2020
		'9770110000153' => '9891076' //15-MENU SECONDO DI PESCE 2020
		//'9770110000160' => '', //16-MENU ARMONIA
		//'9770110000177' => '', //17-MENU EQULIBRIO
		//'9770110000184' => '', //18-MENU ARMONIA - 3
		//'9770110000191' => '', //19-MENU EQUILIBRIO - 3
		//'9770110000207' => '',
	];


	private $promotions;

	public function __construct(string $store, string $ddate)
	{
		try {
			$config = Config::Init();

			$this->user = $config->cm['user'];
			$this->password = $config->cm['password'];
			$this->host = $config->cm['host'];

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

			$this->loadPromotions($store, $ddate);

		} catch (PDOException $e) {
			die($e->getMessage());
		}
	}

	private function loadPromotions(string $store, string $ddate)
	{
		try {
			/** carico la le promozioni valide */
			$stmt = "	select p.codice_campagna, p.codice_promozione, p.descrizione, p.tipo, p.codice_articolo, 
	       					p.codice_reparto, p.parametro_01, p.parametro_02, p.parametro_03 
						from cm.promozioni as p join cm.negozi_promozioni as n on p.codice_promozione = n.promozione_codice 
						where data_inizio <= :ddate and data_fine >= :ddate and n.`negozio_codice`= :store";
			$h_query = $this->pdo->prepare($stmt);
			$h_query->execute([':store' => $store, ':ddate' => $ddate]);
			$rows = $h_query->fetchAll(PDO::FETCH_ASSOC);

			/** indicizzo per tipo promozione */
			$this->promotions = [];
			foreach ($rows as $row) {
				if (!key_exists($row['tipo'], $this->promotions)) {
					$this->promotions[$row['tipo']] = [];
				}
				$this->promotions[$row['tipo']][] = $row;
			}

		} catch (PDOException $e) {
			echo "Errore: " . $e->getMessage();
			die();
		}
	}

	function getPromotionCodes(array $request): array
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
		if ($type == '0492') {
			foreach ($this->promotions['MP'] as $promotion) {
				if ($promotion['parametro_01'] == ($request['percentage'] * 100) && $promotion['parametro_02'] == $request['promotionCode']) {
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
				if ($this->barcodeMenu[$request['benefitBarcode']] == $promotion['codice_articolo']) {
					$codes = [
						'campaignCode' => $promotion['codice_campagna'],
						'promotionCode' => $promotion['codice_promozione'],
						'movementCode' => '13'];
					break;
				}
			}
		}
		return $codes;
	}
}