<?php

namespace App\Service;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Monolog\LogRecord;
use Monolog\Level;

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
        $level = Level::Info,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
        $this->client = $client;
        $this->url = $url;
        $this->token = $token;
        $this->index = $index;
    }
    protected function write(LogRecord $record): void
    {
        $data = $record->toArray();

            // Actual event payload to be stringified
        $eventBody = [
            'ip_address'         => $data['context']['ip_address'] ?? null,
            'last_attempt_at'    => $data['context']['last_attempt_at'] ?? null,
            'account_status'     => $data['context']['account_status'] ?? null,
            'failed_login_count' => $data['context']['failed_login_count'] ?? null,
        ];
        $payload = [
            'event'      => json_encode($eventBody),  // 👈 Wrap as string
            'fields'     => [ 'event_type' => 'Login monitoring' ],
            'sourcetype' => '_json',
            'index'      => $this->index,
            'time'       => $data['datetime']->getTimestamp(),
        ];
        // Proceed with sending to Splunk
        $response = $this->client->request('POST', $this->url, [
            'headers' => [
                'Authorization' => 'Splunk ' . $this->token,
                'Content-Type'  => 'application/json',
            ],
            'json' => $payload,
        ]);
    
        if (200 !== $response->getStatusCode()) {
            error_log('Splunk HEC request failed: ' . $response->getContent(false));
        }
    }


}
?>
