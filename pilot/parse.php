<?php

error_reporting(E_ALL);

require_once (dirname(__FILE__) . '/vendor/autoload.php');


use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;

$path = 'Species_Specimen_TransitionTable_67Species_071222.xlsx';
# open the file
$reader = ReaderEntityFactory::createXLSXReader();
$reader->open($path);

# read each cell of each row of each sheet
foreach ($reader->getSheetIterator() as $sheet) {

	echo "\n\nSheet\n\n";

    foreach ($sheet->getRowIterator() as $row) {
    
    	echo "\n\nRow\n\n";
    
        foreach ($row->getCells() as $cell) {
            var_dump($cell->getValue());
        }
    }
}
$reader->close();

?>

