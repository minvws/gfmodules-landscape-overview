<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/util.php';

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);

$env_path = getenv('ENV_PATH') ?: __DIR__ . '/..';
$dotenv = Dotenv::createImmutable($env_path);
$dotenv->load();

$services = getConfiguredServices();
$configuredEnvironment = getEnvironmentFromConfig();

$envs = [];
if ($configuredEnvironment !== null) {
    $envs = [$configuredEnvironment];
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

echo $twig->render('index.twig', ['envs' => $envs, 'services' => $services, 'app_name' => getAppName()]);
