<?php
namespace If65\Views;

use PDO;
use If65\Config;

class Articles
{

	private $pdo;

	private $user = '';
	private $password = '';
	private $host = '';

	private $articlesListedByCode;
	private $articlesListedByBarcode;

	public function __construct() {
		try {
			$config = Config::Init();

			$this->user = $config->getSetup(Config::DB_ARCHIVI, 'user');
			$this->password = $config->getSetup(Config::DB_ARCHIVI, 'password');
			$this->host = $config->getSetup(Config::DB_ARCHIVI, 'host');

			$connectionString = sprintf("mysql:host=%s", $this->host);

			$this->pdo = new PDO($connectionString, $this->user, $this->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

			$this->loadArticles();

		} catch (PDOException $e) {
			die($e->getMessage());
		}
	}

	private function loadArticles() {
		try {
			$stmt = "   select b.`BAR13-BAR2` barcode, b.`CODCIN-BAR2` code, d.`IDSOTTOREPARTO` department, a.`DES-ART2` description
                		from archivi.articox2 as a join archivi.barartx2 as b on a.`COD-ART2` = b.`CODCIN-BAR2` join dimensioni.articolo as d on b.`CODCIN-BAR2`=d.`CODICE_ARTICOLO`;";
			$h_query = $this->pdo->prepare($stmt);
			$h_query->execute();
			$result = $h_query->fetchAll(PDO::FETCH_ASSOC);

			$this->articlesListedByBarcode = [];
			foreach ($result as $article) {
				$this->articlesListedByBarcode[$article['barcode']] = [
					'code' => $article['code'],
					'department' => $article['department'],
					'description' => $article['description'],
					];
			}

			$this->articlesListedByCode = [];
			foreach ($result as $article) {
				if (! key_exists($article['code'], $this->articlesListedByCode)) {
					$this->articlesListedByCode[$article['code']] = [
						'department' => $article['department'],
						'description' => $article['description'],
						'barcodes' => [$article['barcode']]
					];
				} else {
					$this->articlesListedByCode[$article['code']]['barcodes'][] = $article['barcode'];
				}
			}

			unset($result);

		} catch (PDOException $e) {
			die("Errore: " . $e->getMessage());
		}
	}

	public function getArticlesListedByCode(): array {
		return $this->articlesListedByCode;
	}

	public function getArticlesListedByBarcode(): array {
		return $this->articlesListedByBarcode;
	}

	public function getArticleCode(string $barcode): string {
		if ($barcode == '1') {
			$barcode = '17';
		}
		if (key_exists($barcode, $this->articlesListedByBarcode)) {
			return $this->articlesListedByBarcode[$barcode]['code'];
		} else {
			if (key_exists(substr($barcode, 0, 7), $this->articlesListedByBarcode)) {
				return $this->articlesListedByBarcode[substr($barcode, 0, 7)]['code'];
			} else {
				return '';
			}
		}
	}

	public function getArticleBarcode(string $code): string {
		if (key_exists($code, $this->articlesListedByCode)) {
			$maxBarcode = 0;
			foreach ($this->articlesListedByCode[$code]['barcodes'] as $barcode) {
				if ($barcode > $maxBarcode) {
					$maxBarcode = $barcode;
				}
			}
			return (string)$maxBarcode;
		} else {
			return '';
		}
	}

	public function getArticleBarcodes(string $code): array {
		if (key_exists($code, $this->articlesListedByCode)) {
			return $this->articlesListedByCode[$code]['barcodes'];
		} else {
			return [];
		}
	}

	public function getArticleDepartmentByBarcode(string $barcode): string {
		if ($barcode == '1') {
			$barcode = '17';
		}
		if (key_exists($barcode, $this->articlesListedByBarcode)) {
			return $this->articlesListedByBarcode[$barcode]['department'];
		} else {
			if (key_exists(substr($barcode, 0, 7), $this->articlesListedByBarcode)) {
				return $this->articlesListedByBarcode[substr($barcode, 0, 7)]['department'];
			} else {
				return '0100';
			}
		}
	}

	public function getArticleDepartmentByCode(string $code): string {
		if (key_exists($code, $this->articlesListedByCode)) {
			return $this->articlesListedByCode[$code]['department'];
		} else {
			return '0100';
		}
	}
}