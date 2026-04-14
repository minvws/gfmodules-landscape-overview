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
 *
 * @throws \Psr\Cache\InvalidArgumentException
 */
function handleRequest(string $cacheNamespace, callable $action): never
{
    $cache = new FilesystemAdapter(
        namespace: $cacheNamespace,
        defaultLifetime: 300, // 5 minutes cache
        directory: __DIR__ . '/../.cache',
    );

    $env_path = getenv('ENV_PATH') ?: __DIR__ . '/..';
    $dotenv = Dotenv::createImmutable($env_path);
    $dotenv->load();

    [$environment, $service] = getEnvironmentAndServiceFromRequest();

    $data = getFromCache($cache, sha1($service['name']));

    if (!$data) {
        $data = $action($service, $environment, getMtlsConfig());
        saveToCache($cache, sha1($service['name']), $data);
    }

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Returns the services from the configuration file.
 *
 * @return array The services array
 */
function getConfiguredServices(): array
{
    $filename = $_ENV['SERVICES_FILE'] ?? 'services.json';

    if (!str_starts_with($filename, DIRECTORY_SEPARATOR)) {
        $filename = __DIR__ . '/../' . ltrim($filename, '/');
    }

    if (!is_file($filename)) {
        http_response_code(500);
        exit('Failed to load services configuration');
    }

    try {
        return json_decode(file_get_contents($filename), true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log(sprintf("%s\n%s", $e, $e->getTraceAsString()));
        http_response_code(500);
        exit('Failed to load services configuration');
    }
}

/**
 * Returns preconfigured service matching query filters if any.
 * Otherwise, returns an error.
 *
 * @return array{0: string, 1: array<string, mixed>} A list containing the requested environment and service
 */
function getEnvironmentAndServiceFromRequest(): array
{
    $requestedEnvironment = $_GET['env'] ?? null;

    if (empty($requestedEnvironment)) {
        http_response_code(400);
        exit('Missing query parameter: \'env\'');
    }

    $configuredEnvironment = getEnvironmentFromConfig();

    if ($configuredEnvironment !== null && $configuredEnvironment !== $requestedEnvironment) {
        http_response_code(400);
        exit('Query parameter \'env\' does not match configured environment');
    }

    $requestedService = $_GET['service'] ?? null;

    if (empty($requestedService)) {
        http_response_code(400);
        exit('Missing query parameter: \'service\'');
    }

    $services = getConfiguredServices();
    $matchedService = array_find(
        $services,
        static function (array $service) use ($requestedService, $requestedEnvironment): bool {
            $name = $service['name'] ?? null;
            $environments = array_keys($service['environments'] ?? []);

            return $name === $requestedService && in_array($requestedEnvironment, $environments);
        },
    );

    if ($matchedService === null) {
        http_response_code(400);
        exit('No service found matching the given name and environment');
    }

    return [$requestedEnvironment, $matchedService];
}

function getEnvironmentFromConfig(): ?string
{
    $environmentFromConfig = $_ENV['SERVICES_ENVIRONMENT'] ?? null;

    return is_string($environmentFromConfig) && $environmentFromConfig !== '' ? $environmentFromConfig : null;
}

/**
 * Returns the mTLS configuration from environment variables.
 *
 * @return array The mTLS configuration.
 */
function getMtlsConfig(): array
{
    return [
        'cert' => $_ENV['MTLS_CERT'] ?: null,
        'key' => $_ENV['MTLS_KEY'] ?: null,
        'ca' => $_ENV['MTLS_CA'] ?: true
    ];
}

function slugify(string $value, string $default = ''): string
{
    $slug = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '_', $value));
    $slug = trim($slug, '_');

    return $slug !== '' ? $slug : $default;
}

function getBasicAuthEnvVarNames(array $service, string $env): array
{
    $envConfig = $service['environments'][$env] ?? [];
    $basicAuthConfig = $envConfig['basic_auth'] ?? [];
    $basicAuthUserNameEnvVar = $basicAuthConfig['username_env'] ?? null;
    $basicAuthPasswordEnvVar = $basicAuthConfig['password_env'] ?? null;

    return !empty($basicAuthUserNameEnvVar) && !empty($basicAuthPasswordEnvVar)
        ? [$basicAuthUserNameEnvVar, $basicAuthPasswordEnvVar]
        : createBasicAuthEnvVarNames($service, $env);
}

/**
 * Create Basic Auth environment variable names based on service name and environment.
 *
 * @param array $service A single service configuration
 * @param string $env The environment name
 *
 * @return array{0: string, 1: string} An array containing the username and password environment variable names
 */
function createBasicAuthEnvVarNames(array $service, string $env): array
{
    $serviceAsSlug = slugify($service['name'] ?? '', 'SERVICE');
    $envAsSlug = slugify($env, 'ENV');

    return [
        sprintf('BASIC_AUTH_%s_%s_USERNAME', $serviceAsSlug, $envAsSlug),
        sprintf('BASIC_AUTH_%s_%s_PASSWORD', $serviceAsSlug, $envAsSlug),
    ];
}

function getBasicAuth(array $service, string $env): ?array
{
    $envConfig = $service['environments'][$env] ?? [];
    [$usernameEnv, $passwordEnv] = getBasicAuthEnvVarNames($service, $env);

    $username = getCredential($envConfig, $usernameEnv, 'username');
    $password = getCredential($envConfig, $passwordEnv, 'password');

    return $username === null && $password === null ? null : compact('username', 'password');
}

function getCredential(array $envConfig, string $basicAuthEnvVar, string $basicAuthKey)
{
    $credential = $_ENV[$basicAuthEnvVar];
    if (is_string($credential) && $credential !== '') {
        return $credential;
    }

    $basicAuthConfig = $envConfig['basic_auth'] ?? [];

    return $basicAuthConfig[$basicAuthKey] ?? null;
}

/**
 * Retrieves data from the cache using the provided cache key.
 *
 * @param FilesystemAdapter $cache The cache instance.
 * @param string $cacheKey The key to retrieve the cached item.
 *
 * @return string|null The cached data as JSON or null if not found.
 *
 * @throws \Psr\Cache\InvalidArgumentException
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
 * @param FilesystemAdapter $cache The cache instance.
 * @param string $cacheKey The key to save the cached item.
 * @param mixed $data The data to be cached.
 *
 * @throws \Psr\Cache\InvalidArgumentException
 */
function saveToCache(FilesystemAdapter $cache, string $cacheKey, mixed $data): void
{
    $cachedItem = $cache->getItem($cacheKey);
    $cachedItem->set($data);
    $cache->save($cachedItem);
}

/**
 * Returns the application name as per configuration or default.
 *
 * @return string
 */
function getAppName(): string
{
    return $_ENV['APP_NAME'] ?? 'GFModules Overview';
}
