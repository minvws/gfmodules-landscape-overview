<?php

// Proxy script that fetches version.json from a given URL

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
$cache = new FilesystemAdapter(
    namespace: 'version_proxy',
    defaultLifetime: 300,       // 5 minutes
    directory: __DIR__ . '/../.cache'
);

// Get requested Service
if (!isset($_GET['service'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing service parameter']);
    exit;
}
// Validate URL against allowed domains
$service = get_service(__DIR__ . '/../services.json', $_GET['service']);

// Check cache
$cacheKey = sha1($service['url']);
$cachedItem = $cache->getItem($cacheKey);

if ($cachedItem->isHit()) {
    header('Content-Type: application/json');
    echo json_encode($cachedItem->get());
    exit;
}

// Fetch fresh status
$data = fetch_version_info($service);

// Cache and return result
$cachedItem->set($data);
$cache->save($cachedItem);

header('Content-Type: application/json');
echo json_encode($data);
exit;

/**
 * Get the passed service for the given serviceName from services.json
 * @param array $services
 * @param string $serviceName
 * @return array|null
 */
function get_service(string $settingsFile, string $serviceName): ?array
{
    if (!file_exists($settingsFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Config missing']);
        exit;
    }
    $data = json_decode(file_get_contents($settingsFile), true);

    if (empty($data) || !is_array($data)) {
        return null;
    }

    // Search for the service by name
    foreach ($data as $envServices) {
        foreach ($envServices as $service) {
            if (isset($service['name']) && $service['name'] === $serviceName) {
                return $service;
            }
        }
    }

    return null;
}

function fetch_version_info($service) {
    $client = new Client([
        'timeout' => 4,
        'headers' => [
        ],
        'cert' => $service['mtls_cert'] ?? null,
        'ssl_key' => $service['mtls_key'] ?? null,
        'verify' => $service['mtls_ca'] ?? true
    ]);
    try {
        if(strcmp($service['type'], "HAPI") == 0){
            $response = $client->get($service['url'] . '/fhir/metadata');
            $json = $response->getBody()->getContents();
            return json_decode($json, true)['software'];
        } else {
            $response = $client->get($service['url'] . '/version.json');
            $json = $response->getBody()->getContents();
            return json_decode($json, true);
        }
    } catch (RequestException $e) {
        return ['error' => 'Fetch failed', 'details' => $e->getMessage()];
    }
}
