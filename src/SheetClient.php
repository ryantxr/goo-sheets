<?php

namespace Ryantxr\GooSheets;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class SheetClient
{
    private Client $httpClient;
    private ServiceAccountCredentials $credentials;
    private string $spreadsheetId;
    private int $maxRetries = 5;

    public function __construct(string $spreadsheetId, string $serviceAccountJsonPath, ?Client $httpClient = null)
    {
        $this->spreadsheetId = $spreadsheetId;
        $this->credentials = new ServiceAccountCredentials(
            ['https://www.googleapis.com/auth/spreadsheets'],
            $serviceAccountJsonPath
        );
        $this->httpClient = $httpClient ?? new Client();
    }

    public function read(string $range): array
    {
        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s',
            $this->spreadsheetId,
            rawurlencode($range)
        );
        $response = $this->sendRequest('GET', $url);
        $data = json_decode((string) $response->getBody(), true);
        return $data['values'] ?? [];
    }

    public function write(string $range, array $values): void
    {
        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?valueInputOption=USER_ENTERED',
            $this->spreadsheetId,
            rawurlencode($range)
        );
        $this->sendRequest('PUT', $url, ['json' => ['values' => $values]]);
    }

    public function writeCell(string $sheet, string $cellAddress, $value): void
    {
        $range = sprintf('%s!%s', $sheet, $cellAddress);
        $this->write($range, [[ $value ]]);
    }

    public function getPopulatedRange(string $sheet): array
    {
        $values = $this->read($sheet);
        return array_values(array_filter($values, function (array $row) {
            foreach ($row as $cell) {
                if ($cell !== null && $cell !== '') {
                    return true;
                }
            }
            return false;
        }));
    }

    public function readCell(string $sheet, string $cellAddress): ?string
    {
        $values = $this->read(sprintf('%s!%s', $sheet, $cellAddress));
        return $values[0][0] ?? null;
    }

    public function getSheetTitle(int $index = 0): string
    {
        $url = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s?fields=sheets(properties(title))',
            $this->spreadsheetId
        );
        $response = $this->sendRequest('GET', $url);
        $data = json_decode((string) $response->getBody(), true);
        if (!isset($data['sheets'][$index]['properties']['title'])) {
            throw new \RuntimeException('Sheet title not found');
        }
        return $data['sheets'][$index]['properties']['title'];
    }

    private function sendRequest(string $method, string $url, array $options = []): ResponseInterface
    {
        $attempt = 0;
        do {
            $options['headers']['Authorization'] = 'Bearer ' . $this->getAccessToken();
            try {
                return $this->httpClient->request($method, $url, $options);
            } catch (RequestException $e) {
                $response = $e->getResponse();
                if ($response && $response->getStatusCode() === 429) {
                    $retryAfter = $response->getHeaderLine('Retry-After');
                    $wait = $retryAfter !== '' ? (int) $retryAfter : $this->backoffDelay($attempt);
                    if ($retryAfter !== '') {
                        error_log("429 received. Retry after: {$retryAfter} seconds");
                    } else {
                        error_log("429 received. Backing off for {$wait} seconds");
                    }
                    if ($attempt++ < $this->maxRetries) {
                        usleep((int) ($wait * 1_000_000));
                        continue;
                    }
                    throw new TooManyRequestsException($retryAfter !== '' ? (int) $retryAfter : $wait, $e);
                }
                throw $e;
            }
        } while ($attempt < $this->maxRetries);
        throw new \RuntimeException('Request failed after retries');
    }

    private function getAccessToken(): string
    {
        $token = $this->credentials->fetchAuthToken($this->httpClient);
        if (!isset($token['access_token'])) {
            throw new \RuntimeException('Failed to retrieve access token');
        }
        return $token['access_token'];
    }

    private function backoffDelay(int $attempt): float
    {
        $base = 1 << $attempt; // exponential
        $jitter = random_int(0, 1000) / 1000;
        return min(64, $base) + $jitter;
    }
}



