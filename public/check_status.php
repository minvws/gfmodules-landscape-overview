<?php
// Proxy script that checks HTTP status for allowed URLs

require_once __DIR__ . '/util.php';

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

handleRequest('status_checker', 'fetch_http_status');

/**
 * Fetch HTTP status for a given URL
 */
function fetch_http_status(array $service, string $env, array $mtls): array
{
    $client = new Client([
        'timeout' => 4,
        'allow_redirects' => [
            'max' => 5,
            'strict' => true,
            'referer' => true,
            'track_redirects' => true
        ],
        'headers' => [
            'User-Agent' => 'GFModules Status Checker/1.0'
        ],
        'http_errors' => false, // Don't throw exceptions for 4xx/5xx
        'cert' => $mtls['cert'] ?? null,
        'ssl_key' => $mtls['key'] ?? null,
        'verify' => $mtls['ca'] ?? true
    ]);

    try {
        $response = $client->get($service['environments'][$env]['url'], [
            'connect_timeout' => 2
        ]);

        $status = $response->getStatusCode();
        $finalUrl = $service['environments'][$env]['url'];

        // Get final URL after redirects
        if ($response->hasHeader('X-Guzzle-Redirect-History')) {
            $redirects = $response->getHeader('X-Guzzle-Redirect-History');
            $finalUrl = end($redirects) ?: $service['environments'][$env]['url'];
        }
        return [
            'http_status' => $status,
            'url' => $finalUrl,
            'timestamp' => time()
        ];

    } catch (RequestException $e) {
        return [
            'error' => 'connection_failed',
            'details' => $e->getMessage(),
            'url' => $service['environments'][$env]['url'],
            'timestamp' => time()
        ];
    } catch (ConnectException $e) {
        return [
            'error' => 'host_not_found',
            'details' => $e->getMessage(),
            'url' => $service['environments'][$env]['url'],
            'timestamp' => time()
        ];
    }
}