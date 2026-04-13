<?php

$inputDir  = "C:\\Users\\dell\\Downloads\\tsvfiles";
$outputDir = "D:\\XML-files";

// make sure output folder exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$tsvFiles = glob($inputDir . "\\*.tsv");

foreach ($tsvFiles as $tsvFile) {

    $filenameOnly = pathinfo($tsvFile, PATHINFO_FILENAME);
    $outputFile = $outputDir . "\\" . $filenameOnly . ".xml";

    echo "Converting: $tsvFile\n";

    $lines = file($tsvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        echo "Skipping empty or unreadable file: $tsvFile\n";
        continue;
    }

    $headers = explode("\t", array_shift($lines));
    $xml = new SimpleXMLElement("<records></records>");

    foreach ($lines as $line) {
        $row = explode("\t", $line);
        $record = $xml->addChild("record");

        foreach ($headers as $i => $header) {
            $cleanHeader = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($header));
            $record->addChild($cleanHeader, htmlspecialchars($row[$i] ?? ""));
        }
    }

    $xml->asXML($outputFile);

    echo "Saved: $outputFile\n";
}

echo "Conversion completed.\n";
