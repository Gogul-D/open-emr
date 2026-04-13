<?php

$inputDir  = "C:\\Users\\dell\\Downloads\\tsvfiles";
$outputDir = "D:\\XML-files";

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$tsvFiles = glob($inputDir . "\\*.tsv");

foreach ($tsvFiles as $tsvFile) {

    $filenameOnly = pathinfo($tsvFile, PATHINFO_FILENAME);
    $outputFile = $outputDir . "\\" . $filenameOnly . ".xml";

    echo "Converting: $tsvFile\n";

    $handle = fopen($tsvFile, "r");
    if (!$handle) {
        echo "Cannot read file: $tsvFile\n";
        continue;
    }

    // Read header line only
    $headerLine = fgets($handle);
    if ($headerLine === false) {
        fclose($handle);
        echo "Skipping empty file: $tsvFile\n";
        continue;
    }

    $headers = explode("\t", trim($headerLine));

    // Open XML file for streaming output
    $xmlHandle = fopen($outputFile, "w");
    fwrite($xmlHandle, "<records>\n");

    // Process each line one by one
    while (($line = fgets($handle)) !== false) {
        $values = explode("\t", trim($line));

        fwrite($xmlHandle, "  <record>\n");

        foreach ($headers as $i => $header) {
            $cleanHeader = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($header));
            $value = htmlspecialchars($values[$i] ?? "");
            fwrite($xmlHandle, "    <$cleanHeader>$value</$cleanHeader>\n");
        }

        fwrite($xmlHandle, "  </record>\n");
    }

    fwrite($xmlHandle, "</records>");
    fclose($xmlHandle);
    fclose($handle);

    echo "Saved: $outputFile\n";
}

echo "Conversion completed.\n";
