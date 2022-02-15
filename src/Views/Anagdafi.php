<?php
namespace If65\Views;

use PDO;
use If65\Config;

class Anagdafi
{

	private $pdo;

	private $user = '';
	private $password = '';
	private $host = '';

	private $config;

	public $articlesListedByCode = [];

	public function __construct() {
		try {
			$this->config = Config::Init();

			if ($this->config->oldDwhType) {
				$this->user = $this->config->cm_old['user'];
				$this->password = $this->config->cm_old['password'];
				$this->host = $this->config->cm_old['host'];
			} else {
				$this->user = $this->config->cm['user'];
				$this->password = $this->config->cm['password'];
				$this->host = $this->config->cm['host'];
			}

			$connectionString = sprintf("mysql:host=%s", $this->host);

			$this->pdo = new PDO($connectionString, $this->user, $this->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

			$this->createTable();

		} catch (PDOException $e) {
			die($e->getMessage());
		}
	}

	private function createTable() {
		try {
			$sql = "CREATE TABLE IF NOT EXISTS dc.anagdafi (
                            `data` date NOT NULL,
                            `anno` smallint(5) unsigned NOT NULL DEFAULT '0',
                            `codice` varchar(7) NOT NULL DEFAULT '',
                            `negozio` varchar(4) NOT NULL DEFAULT '',
                            `bloccato` varchar(1) NOT NULL DEFAULT '',
                            `dataBlocco` date DEFAULT NULL,
                            `tipo` varchar(3) NOT NULL DEFAULT '',
                            `prezzoOfferta` decimal(9,2) NOT NULL DEFAULT '0.00',
                            `dataFineOfferta` date DEFAULT NULL,
                            `prezzoVendita` decimal(9,2) NOT NULL DEFAULT '0.00',
                            `prezzoVenditaLocale` decimal(9,2) NOT NULL DEFAULT '0.00',
                            `dataRiferimento` date NOT NULL,
                            PRIMARY KEY (`data`,`codice`,`negozio`),
                            KEY `codice` (`anno`,`codice`,`negozio`,`bloccato`,`dataBlocco`,`tipo`,`prezzoOfferta`,`dataFineOfferta`,`prezzoVendita`,`prezzoVenditaLocale`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
			$this->pdo->exec($sql);

			return true;
		} catch (PDOException $e) {
			die($e->getMessage());
		}
	}

	public function loadData(string $ddate, string $store) {
		try {
			$stmt = "select 
       					a.data ddate, a.anno year, a.codice code, a.negozio store, a.bloccato locked, a.dataBlocco lockingDate,
       					tipo type, prezzoOfferta offeringPrice, dataFineOfferta endOfferingDate, prezzoVendita salePrice,	
       					prezzoVenditaLocale localSalePrice, dataRiferimento referenceDate
       				from 
						(select codice, negozio store, max(data) dataMax from dc.anagdafi where data <= :ddate and negozio = :store group by 1 order by 1) as d join 
						dc.anagdafi as a on d.codice = a.codice and d.dataMax = a.data and d.store = a.negozio";

			$h_query = $this->pdo->prepare($stmt);
			$h_query->execute([':ddate' => $ddate, ':store' => $store]);
			$result = $h_query->fetchAll(PDO::FETCH_ASSOC);

			$this->articlesListedByCode = [];
			foreach ($result as $article) {
				$this->articlesListedByCode[$article['code']] = $article;
			}

			unset($result);

		} catch (PDOException $e) {
			die("Errore: " . $e->getMessage());
		}
	}

}