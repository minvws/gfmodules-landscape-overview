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

// Get requested URL
if (!isset($_GET['url'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing URL parameter']);
    exit;
}

$url = $_GET['url'];

// Validate URL against allowed domains
$allowedUrls = get_allowed_urls(__DIR__ . '/../services.json');
$normalizedUrl = rtrim(parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH), '/');
$isAllowed = false;

foreach ($allowedUrls as $allowedUrl) {
    $normalizedAllowed = rtrim(parse_url($allowedUrl, PHP_URL_HOST) . parse_url($allowedUrl, PHP_URL_PATH), '/');
    if (str_starts_with($normalizedUrl, $normalizedAllowed)) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    echo json_encode(['error' => 'URL not allowed', 'url' => $url]);
    exit;
}

// Check cache
$cacheKey = sha1($url);
$cachedItem = $cache->getItem($cacheKey);

if ($cachedItem->isHit()) {
    header('Content-Type: application/json');
    echo json_encode($cachedItem->get());
    exit;
}

// Fetch fresh status
$data = fetch_http_status($url);

// Cache and return result
$cachedItem->set($data);
$cache->save($cachedItem);

header('Content-Type: application/json');
echo json_encode($data);
exit;

/**
 * Get allowed URLs from services.json
 */
function get_allowed_urls(string $settingsFile): array
{
    if (!file_exists($settingsFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Config missing']);
        exit;
    }

    $allowedUrls = [];
    $data = json_decode(file_get_contents($settingsFile), true);

    foreach ($data as $envServices) {
        foreach ($envServices as $svc) {
            if (!empty($svc['url'])) {
                $allowedUrls[] = rtrim($svc['url'], '/');
            }
        }
    }

    return array_unique($allowedUrls);
}

/**
 * Fetch HTTP status for a given URL
 */
function fetch_http_status(string $url): array
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
        'verify' => true,
        'http_errors' => false // Don't throw exceptions for 4xx/5xx
    ]);

    try {
        $response = $client->get($url, [
            'connect_timeout' => 2
        ]);

        $status = $response->getStatusCode();
        $finalUrl = $url;

        // Get final URL after redirects
        if ($response->hasHeader('X-Guzzle-Redirect-History')) {
            $redirects = $response->getHeader('X-Guzzle-Redirect-History');
            $finalUrl = end($redirects) ?: $url;
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
            'url' => $url,
            'timestamp' => time()
        ];
    } catch (ConnectException $e) {
        return [
            'error' => 'host_not_found',
            'details' => $e->getMessage(),
            'url' => $url,
            'timestamp' => time()
        ];
    }
}