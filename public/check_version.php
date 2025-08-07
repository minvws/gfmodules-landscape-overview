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

$env_path = getenv('ENV_PATH') ?: __DIR__ . '/..';
$dotenv = Dotenv\Dotenv::createImmutable($env_path);
$dotenv->load();

$mtls = [
    'cert' => $_ENV['MTLS_CERT'] ?: null,
    'key' => $_ENV['MTLS_KEY'] ?: null,
    'ca' => $_ENV['MTLS_CA'] ?: null
];
$env = $_ENV['SERVICES_ENVIRONMENT']  ?: null;

// Get requested Service
if (!isset($_GET['service'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing service parameter']);
    exit;
}

// Get requested Env
if (!isset($_GET['env'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing env parameter']);
    exit;
}

if (isset($env) && $env !== $_GET['env']) {
    http_response_code(400);
    echo json_encode(['error' => 'Environment not allowed']);
    exit;
}

$service = get_service(__DIR__ . '/../services.json', $_GET['service'], $_GET['env']);
if (!$service) {
    http_response_code(400);
    echo json_encode(['Service' => 'Requested service not found']);
    exit;
}

// Check cache
$versionUrl = $service['version_url'] ?? $service['url'];
$cacheKey = sha1($versionUrl);
$cachedItem = $cache->getItem($cacheKey);

if ($cachedItem->isHit()) {
    header('Content-Type: application/json');
    echo json_encode($cachedItem->get());
    exit;
}

// Fetch fresh status
$data = fetch_version_info($service, $mtls);

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
function get_service(string $settingsFile, string $serviceName, string $envName): ?array
{
    if (!file_exists($settingsFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Config missing']);
        exit;
    }
    $data = json_decode(file_get_contents($settingsFile), true);

    if (empty($data) || !is_array($data) || !isset($data[$envName]) || !is_array($data[$envName])) {
        return null;
    }

    // Search for the service by name
    foreach ($data[$envName] as $service) {
        if (isset($service['name']) && $service['name'] === $serviceName) {
            return $service;
        }
    }

    return null;
}

function fetch_version_info(array $service, array $mtls) {
    $client = new Client([
        'timeout' => 4,
        'headers' => [
        ],
        'cert' => $mtls['cert'] ?? null,
        'ssl_key' => $mtls['key'] ?? null,
        'verify' => $mtls['ca'] ?? true
    ]);
    try {
        if(strcmp($service['type'], "HAPI") == 0){
            $response = $client->get($service['version_url'] ?? $service['url'] . '/fhir/metadata');
            $json = $response->getBody()->getContents();
            return json_decode($json, true)['software'];
        } else {
            $response = $client->get($service['version_url'] ?? $service['url'] . '/version.json');
            $json = $response->getBody()->getContents();
            return json_decode($json, true);
        }
    } catch (RequestException $e) {
        return ['error' => 'Fetch failed', 'details' => $e->getMessage()];
    }
}
