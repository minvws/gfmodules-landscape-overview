<?php

declare(strict_types=1);

// Proxy script that fetches version.json from a given URL

require_once __DIR__ . '/util.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

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

    $isHapi = ($service['type'] ?? '') === 'HAPI';
    $url = $service['environments'][$env]['version_url']
        ?? $service['environments'][$env]['url'] . ($isHapi ? '/fhir/metadata' : '/version.json');

    try {
        $data = json_decode($client->get($url)->getBody()->getContents(), true);
    } catch (GuzzleException $e) {
        // Any transport failure (DNS, TLS, timeout, HTTP error) must still produce JSON.
        return ['error' => 'Fetch failed', 'details' => $e->getMessage()];
    }

    if ($isHapi) {
        $data = is_array($data) ? ($data['software'] ?? null) : null;
    }

    if (!is_array($data)) {
        return ['error' => 'Invalid response', 'details' => 'Response from ' . $url . ' is not version JSON'];
    }

    return $data;
}
