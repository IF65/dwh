<?php

ini_set('memory_limit','8192M');

require __DIR__ . "/../vendor/autoload.php";

use GetOpt\GetOpt as Getopt;
use GetOpt\Option;

$options = new GetOpt([
    Option::create('h', 'help', GetOpt::NO_ARGUMENT)
        ->setDescription('Mostra questo help')->setDefaultValue(0),
    Option::create('d', 'data', GetOpt::REQUIRED_ARGUMENT )
        ->setDescription('Data da controllare'),
    Option::create('s', 'sede', GetOpt::REQUIRED_ARGUMENT )
        ->setDescription('Sede da controllare'),
]);

// constants
//$filePath = '/preparazione/inviati/';
$filePath = '/Users/stefano.orlandi/test/DC/';

// process arguments and catch user errors
try {
    try {
        $options->process();
    } catch (Missing $exception) {
        // catch missing exceptions if help is requested
        if (!$options->getOption('help')) {
            throw $exception;
        }
    }
} catch (ArgumentException $exception) {
    file_put_contents('php://stderr', $exception->getMessage() . PHP_EOL);
    echo PHP_EOL . $options->getHelpText();
    exit;
}

$help = $options->getOption('h');
if ($help) {
    echo PHP_EOL . $options->getHelpText();
    exit;
}

$data = $options->getOption('d');
if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $data)) {
    $data = '';
}

$sede = $options->getOption('s');
if (!preg_match('/^(00|01|02|04|05|31|36|60)\d{2}$/', $sede)) {
    $sede = '';
}

$headers = Array();
$sales = Array();
$sconti = Array();
$totals = Array();
$punti = Array();

$currTrans = 0;

if ($sede != '' and $data != '') {

    $date = new DateTime($data);
    $fileName = "DC" . $date->format('Ymd') . str_pad($sede, 5, '0', STR_PAD_LEFT) . "001.DAT";
    echo "Controllo file: " . $fileName . "\n";

    // apertura e lettura file
    $handle = fopen($filePath . $fileName, "r");
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            // process the line read.
            parseLine($line, $headers, $sales, $sconti, $totals, $punti, $currTrans);
        }
        fclose($handle);
    } else {
        // error opening the file.
        echo "Errore nella lettura del file \n";
    }

    // controlli
    checkTotals($headers, $sales, $sconti, $totals);
    checkSconti($sconti);

} else {
    echo PHP_EOL . $options->getHelpText();
}

function parseLine($line, &$headers, &$sales, &$sconti, &$totals, &$punti, &$currTrans) {
    $lineParts = Array();
    $lineParts[0] = substr($line, 0, 20);
    $lineParts[1] = substr($line, 21);
    $seq = substr($lineParts[0], 8, 8);
    $tipoRecord = $lineParts[1][0];

    if ($tipoRecord == 0) {
        // testata
        $header = Array();
        $currTrans = substr($lineParts[1], 3, 4);
        $header['tipoTrans'] = substr($lineParts[1], 1, 2);
        $header['numTrans'] = $currTrans;
        $header['cassa'] = substr($lineParts[1], 7, 4);
        $header['seq'] = $seq;
        $headers[$currTrans] = $header;
    } else if ($tipoRecord == 1) {
        // movimento
        $mov = Array();
        $mov['numTrans'] = $currTrans;
        $mov['tipoOperazione'] = substr($lineParts[1], 1, 1);
        $mov['tipoMovimento'] = substr($lineParts[1], 2, 2);
        $mov['ean'] = substr($lineParts[1], 4, 13);
        $mov['reparto'] = substr($lineParts[1], 18, 4);
        $mov['seq'] = $seq;
        $valore = substr($lineParts[1], 22, 9);
        switch ($mov['tipoMovimento']) {
                // vendite
            case '01' :
                $mov['importo'] = $valore;
                $sales[$currTrans][] = $mov;
                break;
                // sconto generico
            case '08' :
            case '09' :
            case '10' :
            case '11' :
            case '13' :
                // sconto
            case '91' :
            case '94' :
            case '62' :
            case '85' :
            case '86' :
                // buoni sconto
            case '70' :
            case '71' :
            case '75' :
            case '87' :
                // sconto set
            case '96' :
            case '97' :
                $mov['importo_sconto'] = $valore;
                $sconti[$currTrans][] = $mov;
                break;
                // totali
            case '20' :
                $mov['totale'] = $valore;
                $totals[$currTrans] = $mov;
                break;
                // punti
            case '77' :
                $mov['punti'] = $valore;
                $punti[$currTrans][] = $mov;
                break;
        }
    }
    //echo $line;
}

function checkTotals($headers, $sales, $sconti, $totals) {

    $i = 0;
    echo "Trovate " . count($totals) . " transazioni \n";
    foreach ($totals as $tot) {
        $currTrans = $tot['numTrans'];
        // vendite
        $salesTotal = 0;
        if (isset($sales[$currTrans])) {
            foreach ($sales[$currTrans] as $s) {
                $salesTotal += $s['importo'] * 1;
            }
        }
        // sconti
        $scontiTotal = 0;
        if (isset($sconti[$currTrans])) {
            foreach ($sconti[$currTrans] as $sc) {
                $scontiTotal += $sc['importo_sconto'] * 1;
            }
        }
        if ($tot['totale'] * 1 != ($salesTotal - $scontiTotal)) {
            $i++;
            echo $i . ") Totale errato su transazione (tipo " . $headers[$currTrans]['tipoTrans'] . ") numero " . $tot['numTrans'] . " (" . $tot['totale'] * 1 . " vs " . ($salesTotal - $scontiTotal) . ") \n";
        }
    }
}

function checkSconti($sconti) {
    // TODO
}
