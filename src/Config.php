<?php

namespace If65;

class Config
{
	static $init = null;

	public $oldDwhType = true;

	public $archivi = [
		"host" => "",
		"user" => "",
		"password" => ""
	];
	public $quadrature = [
		"host" => "",
		"user" => "",
		"password" => ""
	];
	public $cm = [
		"host" => "",
		"user" => "",
		"password" => ""
	];
	public $cm_old = [
		"host" => "",
		"user" => "",
		"password" => ""
	];
	public $anagdafi = [
		"host" => "",
		"user" => "",
		"password" => ""
	];
	public $exportFolder;
	public $debug;

	private const FILENAME = 'config.json';

	/** singleton */
	private function __construct()
	{
		$this->debug = true;
		$this->exportFolder = dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . "test" . DIRECTORY_SEPARATOR . "dc";

		$this::LoadSetup();
	}

	public static function Init()
	{
		return static::$init = (
		null === static::$init ? new self() : static::$init
		);
	}

	/**
	 * carica la configurazione corrente
	 */
	private function loadSetup()
	{
		$fileName = dirname(__DIR__, 1) . DIRECTORY_SEPARATOR . self::FILENAME;
		if (file_exists($fileName)) {
			$setup = json_decode(file_get_contents($fileName), true);

			if (key_exists('archivi', $setup)) {
				$this->archivi['host'] = $setup['archivi']['host'];
				$this->archivi['user'] = $setup['archivi']['user'];
				$this->archivi['password'] = $setup['archivi']['password'];
			}

			if (key_exists('cm', $setup)) {
				$this->cm['host'] = $setup['cm']['host'];
				$this->cm['user'] = $setup['cm']['user'];
				$this->cm['password'] = $setup['cm']['password'];
			}

			if (key_exists('cm_old', $setup)) {
				$this->cm_old['host'] = $setup['cm_old']['host'];
				$this->cm_old['user'] = $setup['cm_old']['user'];
				$this->cm_old['password'] = $setup['cm_old']['password'];
			}

			if (key_exists('quadrature', $setup)) {
				$this->quadrature['host'] = $setup['quadrature']['host'];
				$this->quadrature['user'] = $setup['quadrature']['user'];
				$this->quadrature['password'] = $setup['quadrature']['password'];
			}

			if (key_exists('anagdafi', $setup)) {
				$this->anagdafi['host'] = $setup['anagdafi']['host'];
				$this->anagdafi['user'] = $setup['anagdafi']['user'];
				$this->anagdafi['password'] = $setup['anagdafi']['password'];
			}

			if (key_exists('exportFolder', $setup)) {
				$this->exportFolder=$setup['exportFolder'];
			}

			if (key_exists('debug', $setup)) {
				$this->debug=$setup['debug'];
			}

			if (key_exists('oldDwhType', $setup)) {
				$this->oldDwhType=$setup['oldDwhType'];
			}
		}
	}
}