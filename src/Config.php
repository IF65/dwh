<?php
namespace If65;

class Config
{
	static $init = null;

	const DB_ARCHIVI = 'archivi';
	const DB_QUADRATURE = 'quadrature';
	const DB_CM = 'cm';
	const DB_ANAGDAFI = 'anagdafi';

	const EXPORTFOLDER = 'exportFolder';

	private const FILENAME = 'config';

	private $setup = [
		self::DB_ARCHIVI => [
			'host' => '',
			'user' => '',
			'password' => '',
		],
		self::DB_QUADRATURE => [
			'host' => '',
			'user' => '',
			'password' => ''
		],
		self::DB_CM => [
			'host' => '',
			'user' => '',
			'password' => ''
		],
		self::DB_ANAGDAFI => [
			'host' => '',
			'user' => '',
			'password' => ''
		],
		'exportFolder' => ''
	];

	/** Private to implement singleton pattern */
	private function __construct() {
		$this::LoadSetup();
	}

	/** @return Config */
	public static function Init()
	{
		return static::$init = (
		null === static::$init ? new self() : static::$init
		);
	}

	/**
	 * scrive la configurazione corrente su disco e se non esiste crea un file di bkp da utilizzare come modello
	 */
	private function writeSetup()
	{
		$fileName = __DIR__ . DIRECTORY_SEPARATOR . self::FILENAME;
		if (! file_exists( $fileName . '.cfg')) {
			file_put_contents($fileName . '.cfg', json_encode($this->setup, JSON_PRETTY_PRINT));
			file_put_contents($fileName . '.bkp', json_encode($this->setup, JSON_PRETTY_PRINT));
		}
	}

	/**
	 * carica la configurazione corrente
	 */
	private function loadSetup()
	{
		$fileName = __DIR__ . DIRECTORY_SEPARATOR . self::FILENAME;
		if ( file_exists( $fileName . '.cfg')) {
			$this->setup = json_decode(file_get_contents($fileName . '.cfg'), true);
		} else {
			/** nel caso non ci sia il file di configurazione ne scrive uno standard */
			$this->writeSetup();
		}
	}

	public function getSetup(string $type, string $selector = ''): string {
		if ($type == self::DB_ARCHIVI || $type == self::DB_CM || $type == self::DB_QUADRATURE || $type == self::DB_ANAGDAFI) {
			return $this->setup[$type][$selector];
		} if ($type == self::EXPORTFOLDER) {
			return $this->setup[$type];
		} else {
			return '';
		}
	}

}