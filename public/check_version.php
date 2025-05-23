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

// Get requested URL
if (!isset($_GET['url'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing URL']);
    exit;
}

$url = $_GET['url'];

$allowedUrls = get_allowed_urls(__DIR__ . '/../services.json');
if (!in_array($url, $allowedUrls)) {
    http_response_code(403);
    echo json_encode(['error' => 'URL not allowed']);
    exit;
}


$cacheKey = sha1($url);
$cachedItem = $cache->getItem($cacheKey);
if ($cachedItem->isHit()) {
    header('Content-Type: application/json');
    echo json_encode($cachedItem->get());
    exit;
}

$data = fetch_version_info($url);
if (isset($data['error'])) {
    http_response_code(502);
    echo json_encode($data);
    exit;
}

$cachedItem->set($data);
$cache->save($cachedItem);
echo json_encode($data);
exit;


function get_allowed_urls(string $settingsFile)
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
            if (!empty($svc['has_version']) && !empty($svc['url'])) {
                $allowedUrls[] = rtrim($svc['url'], '/');
            }
        }
    }

    return $allowedUrls;
}

function fetch_version_info($url) {
    $client = new Client([
        'timeout' => 4,
        'headers' => [
        ],
        'verify' => true,
    ]);

    try {
        $response = $client->get($url . '/version.json');
        $json = $response->getBody()->getContents();
        return json_decode($json, true);
    } catch (RequestException $e) {
        return ['error' => 'Fetch failed', 'details' => $e->getMessage()];
    }
}
