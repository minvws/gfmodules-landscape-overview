<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once __DIR__ . '/../vendor/autoload.php';

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);

$env_path = getenv('ENV_PATH') ?: __DIR__ . '/..';
$dotenv = Dotenv::createImmutable($env_path);
$dotenv->load();

$servicesFileEnv = $_ENV['SERVICES_FILE'] ?? '';
$servicesFile = $servicesFileEnv === '' ? 'services.json' : $servicesFileEnv;

if (!str_starts_with($servicesFile, DIRECTORY_SEPARATOR)) {
    $servicesFile = __DIR__ . '/../' . ltrim($servicesFile, '/');
}

if (!is_file($servicesFile)) {
    http_response_code(500);
    echo 'Services configuration file not found: ' . $servicesFile;
    exit;
}

$services = json_decode(file_get_contents($servicesFile), true);
$servicesEnvironment = $_ENV['SERVICES_ENVIRONMENT'] ?? '';
$env = $servicesEnvironment === '' ? null : $servicesEnvironment;
$appName = $_ENV['APP_NAME'] ?? 'GFModules Overview';

$envs = [];
if ($env !== null) {
    $envs = [$env];
} else {
    $envSet = [];
    foreach ($services as $service) {
        if (!isset($service['environments']) || !is_array($service['environments'])) {
            continue;
        }
        foreach (array_keys($service['environments']) as $envName) {
            $envSet[$envName] = true;
        }
    }
    $envs = array_keys($envSet);
    sort($envs);
}

echo $twig->render('index.twig', ['envs' => $envs, 'services' => $services, 'app_name' => $appName]);
