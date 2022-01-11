<?php
require(realpath(__DIR__ . '/..') . '/vendor/autoload.php');

function getCodiceLista(array $stores): string
{
	try {
		$xml = new SimpleXMLElement('<root></root>');

		$stores_list_hdr = $xml->addChild('stores_list_hdr');
		$stores_list_hdr->addChild('stores_list_name', 'Nuova Lista');

		$stores_list = $xml->addChild('stores_list');
		foreach ($stores as $store) {
				$store_line = $stores_list->addChild('store_line');
				$store_line->addChild('store_code', $store);
		}

		$soapclient = new SoapClient('http://10.11.14.207/CM_WEB_SVC.asmx?WSDL', array('trace' => 1));

		$parameters = [
			'Str_metod_IN' => 'cmweb.StoresLists_Test_Set',
			'Str_IN' => $xml->asXML()
		];

		$response = $soapclient->Funct_Invoke($parameters);
		if ((string)$response->Funct_InvokeResult->ret_status == '0') {
			if (preg_match('/\<store_list_code\>(.*)\<\/store_list_code\>/', (string)$response->Funct_InvokeResult->str_out, $matches)) {
				return $matches[1];
			}
		} else {
			return '';
		}
		unset($soapclient);

	} catch (PDOException $e) {
		echo "Errore: " . $e->getMessage();
		die();
	}

	return '';
}
