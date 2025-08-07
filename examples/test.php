<?php
require __DIR__ . '/../vendor/autoload.php';

use Ryantxr\GooSheets\SheetClient;
use Ryantxr\GooSheets\TooManyRequestsException;

$spreadsheetId = getenv('SPREADSHEET_ID') ?: 'YOUR_SPREADSHEET_ID';
$keyFile = __DIR__ . '/../service-account.json';

$client = new SheetClient($spreadsheetId, $keyFile);

try {
    $title = $client->getSheetTitle();
    echo "First sheet: {$title}\n";

    $cell = $client->readCell($title, 'A1');
    var_dump($cell);

    $client->writeCell($title, 'A1', 'Hello World');

    $rows = $client->getPopulatedRange($title);
    print_r($rows);
} catch (TooManyRequestsException $e) {
    $retry = $e->getRetryAfter();
    error_log('Too many requests. Retry after ' . ($retry ?? 'unknown') . ' seconds');
}
