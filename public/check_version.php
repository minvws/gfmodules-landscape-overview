<?php

declare(strict_types=1);

// Proxy script that fetches version.json from a given URL

require_once __DIR__ . '/util.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

handleRequest('version_proxy', 'fetch_version_info');

function fetch_version_info(array $service, ?string $env, array $mtls): array
{
    if ($env === null || !isset($service['environments'][$env]['url'])) {
        return ['error' => 'env_missing', 'details' => 'Environment not specified'];
    }
    $basicAuth = getBasicAuth($service, $env);

    $client = new Client([
        'timeout' => 4,
        'headers' => [],
        'cert' => $mtls['cert'],
        'ssl_key' => $mtls['key'],
        'verify' => $mtls['ca'],
        'auth' => $basicAuth ? [$basicAuth['username'], $basicAuth['password']] : null,
    ]);

    try {
        if (strcmp($service['type'], "HAPI") === 0) {
            $response = $client->get(
                $service['environments'][$env]['version_url'] ?? $service['environments'][$env]['url'] . '/fhir/metadata',
            );
            $json = $response->getBody()->getContents();
            return json_decode($json, true)['software'];
        }

        $response = $client->get($service['environments'][$env]['version_url'] ?? $service['environments'][$env]['url'] . '/version.json');
        $json = $response->getBody()->getContents();
        return json_decode($json, true);
    } catch (RequestException $e) {
        return ['error' => 'Fetch failed', 'details' => $e->getMessage()];
    }
}
