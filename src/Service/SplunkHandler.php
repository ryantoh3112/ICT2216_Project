<?php

namespace App\Service;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Monolog\LogRecord;

class SplunkHandler extends AbstractProcessingHandler
{
    private HttpClientInterface $client;
    private string $url;
    private string $token;
    private string $index;

    public function __construct(
        HttpClientInterface $client,
        string $url,
        string $token,
        string $index,
        $level = Logger::INFO,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
        $this->client = $client;
        $this->url = $url;
        $this->token = $token;
        $this->index = $index;
    }
    /*
    protected function write(LogRecord $record): void
    {
        $data = $record->toArray();
           // ✅ Debug the payload sent to Splunk
        file_put_contents('/tmp/splunk_debug.log', json_encode($payload, JSON_PRETTY_PRINT));
        $response = $this->client->request('POST', $this->url, [
            'headers' => [
                'Authorization' => 'Splunk ' . $this->token,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'event' => 'Login monitoring',
                'fields' => [
                    'ip_address'         => $data['context']['ip_address'] ?? null,
                    'last_attempt_at'    => $data['context']['last_attempt_at'] ?? null,
                    'account_status'     => $data['context']['account_status'] ?? null,
                    'failed_login_count' => $data['context']['failed_login_count'] ?? null,
                ],
                'sourcetype' => '_json',
                'index'      => $this->index,
                'time'       => $data['datetime']->getTimestamp(),
            ],
        ]);


        if (200 !== $response->getStatusCode()) {
        // Optionally throw or log error - you can write to PHP error_log or another logger
            error_log('Splunk HEC request failed: ' . $response->getContent(false));
        }
    }
    */
    protected function write(LogRecord $record): void
    {
        $data = $record->toArray();

        $payload = [
            'event' => 'Login monitoring',
            'fields' => [
                'ip_address'         => $data['context']['ip_address'] ?? null,
                'last_attempt_at'    => $data['context']['last_attempt_at'] ?? null,
                'account_status'     => $data['context']['account_status'] ?? null,
                'failed_login_count' => $data['context']['failed_login_count'] ?? null,
            ],
            'sourcetype' => '_json',
            'index'      => $this->index,
            'time'       => $data['datetime']->getTimestamp(),
        ];

        // ✅ Debug the payload sent to Splunk
        file_put_contents('/tmp/splunk_debug.log', json_encode($payload, JSON_PRETTY_PRINT));

        // Proceed with sending to Splunk
        $response = $this->client->request('POST', $this->url, [
            'headers' => [
                'Authorization' => 'Splunk ' . $this->token,
                'Content-Type'  => 'application/json',
            ],
            'json' => $payload,
        ]);
        $content = $response->getContent(false); // Get response body even if not 200
        $status  = $response->getStatusCode();
        file_put_contents('/tmp/splunk_response.log', json_encode([
            'status' => $status,
            'body'   => $content,
        ], JSON_PRETTY_PRINT));

        if (200 !== $response->getStatusCode()) {
            error_log('Splunk HEC request failed: ' . $response->getContent(false));
        }
    }


}
?>
