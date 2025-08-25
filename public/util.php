<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Handles a request by checking the cache for a service's data, executing an action if not cached,
 * and returning the data as JSON.
 *
 * @param string $cacheNamespace The namespace for the cache.
 * @param callable $action The action to execute if the data is not cached.
 */
function handleRequest(string $cacheNamespace, callable $action): void {
    $cache = new FilesystemAdapter(
        namespace: $cacheNamespace,
        defaultLifetime: 300,       // 5 minutes cache
        directory: __DIR__ . '/../.cache'
    );

    $env_path = getenv('ENV_PATH') ?: __DIR__ . '/..';
    $dotenv = Dotenv\Dotenv::createImmutable($env_path);
    $dotenv->load();

    $env = $_ENV['SERVICES_ENVIRONMENT']  ?: null;

    $service = getServiceFromRequestParams($env);
    $data = getFromCache($cache, sha1($service['name']));
    $mtls = getMtlsConfig();

    if (!$data){
        $data = $action($service, $env, $mtls);
        saveToCache($cache, sha1($service['name']), $data);
    }

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Fetches service from configuration and checks if it is allowed.
 *
 * @return array The service
 */
function getServiceFromRequestParams(string $env): array{
    // Get requested Service
    if (!isset($_GET['service'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing service parameter']);
        exit;
    }

    $settingsFile = __DIR__ . '/../services.json';
    $serviceName = $_GET['service'];
    $envName = $_GET['env'];

    if (!file_exists($settingsFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Config missing']);
        exit;
    }
    $data = json_decode(file_get_contents($settingsFile), true);
    if (empty($data) || !is_array($data)) {
        http_response_code(400);
        echo json_encode(['Service' => 'Requested service not found']);
        exit;
    }
    // Search for the service by name
    foreach ($data as $service) {
        if (isset($service['name']) && $service['name'] === $serviceName && array_key_exists($envName, $service['environments'])) {
            return $service;
        }
    }

    http_response_code(400);
    echo json_encode(['Service' => 'Requested service not found']);
    exit;
};

/**
 * Returns the mTLS configuration from environment variables.
 *
 * @return array The mTLS configuration.
 */
function getMtlsConfig(): array{
    return [
        'cert' => $_ENV['MTLS_CERT'] ?: null,
        'key' => $_ENV['MTLS_KEY'] ?: null,
        'ca' => $_ENV['MTLS_CA'] ?: true
    ];
}

/**
 * Retrieves data from the cache using the provided cache key.
 *
 * @param FilesystemAdapter $cache The cache instance.
 * @param string $cacheKey The key to retrieve the cached item.
 * @return string|null The cached data as JSON or null if not found.
 */
function getFromCache(FilesystemAdapter $cache, string $cacheKey): ?string {
    $cachedItem = $cache->getItem($cacheKey);
    if ($cachedItem->isHit()) {
        header('Content-Type: application/json');
        return json_encode($cachedItem->get());
    }
    return null;
}

/**
 * Saves data to the cache with the provided cache key.
 *
 * @param FilesystemAdapter $cache The cache instance.
 * @param string $cacheKey The key to save the cached item.
 * @param mixed $data The data to be cached.
 * @return void
 */
function saveToCache($cache, $cacheKey, $data): void {
    $cachedItem = $cache->getItem($cacheKey);
    $cachedItem->set($data);
    $cache->save($cachedItem);
}