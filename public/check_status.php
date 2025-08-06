<?php
// Proxy script that checks HTTP status for allowed URLs

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$cache = new FilesystemAdapter(
    namespace: 'status_checker',
    defaultLifetime: 300,       // 5 minutes cache
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
$data = fetch_http_status($service);

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

/**
 * Fetch HTTP status for a given URL
 */
function fetch_http_status(array $service): array
{
    error_log("fetching status for service: " . $service['name'] . " at " . $service['url']);

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
        'cert' => $service['mtls_cert'] ?? null,
        'ssl_key' => $service['mtls_key'] ?? null,
        'verify' => $service['mtls_ca'] ?? true
    ]);

    try {
        $response = $client->get($service['url'], [
            'connect_timeout' => 2
        ]);

        $status = $response->getStatusCode();
        $finalUrl = $service['url'];

        // Get final URL after redirects
        if ($response->hasHeader('X-Guzzle-Redirect-History')) {
            $redirects = $response->getHeader('X-Guzzle-Redirect-History');
            $finalUrl = end($redirects) ?: $service['url'];
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
            'url' => $service['url'],
            'timestamp' => time()
        ];
    } catch (ConnectException $e) {
        return [
            'error' => 'host_not_found',
            'details' => $e->getMessage(),
            'url' => $service['url'],
            'timestamp' => time()
        ];
    }
}