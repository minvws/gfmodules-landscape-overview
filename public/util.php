<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Handles a request by checking the cache for a service's data, executing an action if not cached,
 * and returning the data as JSON.
 *
 * @param string $cacheNamespace The namespace for the cache.
 * @param callable $action The action to execute if the data is not cached.
 */
function handleRequest(string $cacheNamespace, callable $action): void
{
    $cache = new FilesystemAdapter(
        namespace: $cacheNamespace,
        defaultLifetime: 300, // 5 minutes cache
        directory: __DIR__ . '/../.cache',
    );

    $env_path = getenv('ENV_PATH') ?: __DIR__ . '/..';
    $dotenv = Dotenv::createImmutable($env_path);
    $dotenv->load();

    $env = $_ENV['SERVICES_ENVIRONMENT'] ?: null;

    $service = getServiceFromRequestParams($env);

    if (isset($_GET['env'])) {
        $requestedEnv = $_GET['env'];

        if (!is_string($requestedEnv)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid environment parameter']);
            exit;
        }

        if (
            !isset($service['environments']) ||
            !is_array($service['environments']) ||
            !array_key_exists($requestedEnv, $service['environments'])
        ) {
            http_response_code(400);
            echo json_encode(['error' => 'Unknown environment']);
            exit;
        }

        $env = $requestedEnv;
    }

    $data = getFromCache($cache, sha1($service['name']));
    $mtls = getMtlsConfig();

    if (!$data) {
        $data = $action($service, $env, $mtls);
        saveToCache($cache, sha1($service['name']), $data);
    }

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function getServicesFilePath(): string
{
    $servicesFile = $_ENV['SERVICES_FILE'] ?? 'services.json';

    if (str_starts_with($servicesFile, DIRECTORY_SEPARATOR)) {
        return $servicesFile;
    }

    return __DIR__ . '/../' . $servicesFile;
}

/**
 * Fetches service from configuration and checks if it is allowed.
 *
 * @return array The service
 */
function getServiceFromRequestParams(?string $env): array
{
    // Get requested Service
    if (!isset($_GET['service'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing service parameter']);
        exit;
    }

    $settingsFile = getServicesFilePath();
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
}

/**
 * Returns the mTLS configuration from environment variables.
 *
 * @return array The mTLS configuration.
 */
function getMtlsConfig(): array
{
    $cert = $_ENV['MTLS_CERT'] ?? null;
    $key = $_ENV['MTLS_KEY'] ?? null;
    $ca = $_ENV['MTLS_CA'] ?? null;

    $verify = true;

    if ($ca !== null && $ca !== '') {
        $resolvedCa = realpath($ca);
        $baseDir = realpath(__DIR__ . '/../');

        if ($resolvedCa !== false && $baseDir !== false && str_starts_with($resolvedCa, $baseDir . DIRECTORY_SEPARATOR)) {
            $verify = $resolvedCa;
        } else {
            // Invalid CA path provided: log a warning and fall back to default verification
            error_log('Invalid MTLS_CA path provided; falling back to default CA verification.');
            $verify = true;
        }
    }

    return [
        'cert' => !empty($cert) ? $cert : null,
        'key' => !empty($key) ? $key : null,
        'ca' => $verify,
    ];
}

function slugifyEnvKey(string $value): string
{
    $slug = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '_', $value));
    $slug = trim($slug, '_');

    return $slug !== '' ? $slug : 'SERVICE';
}

function getBasicAuthEnvVarNames(array $service, string $env): array
{
    $envConfig = $service['environments'][$env] ?? [];
    $basicAuthConfig = $envConfig['basic_auth'] ?? [];

    if (!empty($basicAuthConfig['username_env']) && !empty($basicAuthConfig['password_env'])) {
        return [$basicAuthConfig['username_env'], $basicAuthConfig['password_env']];
    }

    $serviceKey = slugifyEnvKey($service['name'] ?? 'service');
    $envKey = slugifyEnvKey($env);

    return [
        "BASIC_AUTH_{$serviceKey}_{$envKey}_USERNAME",
        "BASIC_AUTH_{$serviceKey}_{$envKey}_PASSWORD",
    ];
}

function getBasicAuth(array $service, string $env): ?array
{
    $envConfig = $service['environments'][$env] ?? [];
    [$usernameEnv, $passwordEnv] = getBasicAuthEnvVarNames($service, $env);

    $username = getenv($usernameEnv);
    $password = getenv($passwordEnv);

    if ($username === false || $password === false || $username === '' || $password === '') {
        $basicAuthConfig = $envConfig['basic_auth'] ?? [];
        $username = $basicAuthConfig['username'] ?? null;
        $password = $basicAuthConfig['password'] ?? null;
    }

    if ($username === null || $password === null || $username === '' || $password === '') {
        return null;
    }

    return [
        'username' => $username,
        'password' => $password,
    ];
}

/**
 * Retrieves data from the cache using the provided cache key.
 *
 * @param FilesystemAdapter $cache The cache instance.
 * @param string $cacheKey The key to retrieve the cached item.
 *
 * @return string|null The cached data as JSON or null if not found.
 */
function getFromCache(FilesystemAdapter $cache, string $cacheKey): ?string
{
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
 * @param mixed $data The data to be cached.
 *
 * @return void
 * @param string $cacheKey The key to save the cached item.
 * @param mixed $data The data to be cached.
 */
function saveToCache(FilesystemAdapter $cache, string $cacheKey, mixed $data): void
{
    $cachedItem = $cache->getItem($cacheKey);
    $cachedItem->set($data);
    $cache->save($cachedItem);
}
