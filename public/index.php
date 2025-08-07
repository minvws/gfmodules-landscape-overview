<?php

require_once __DIR__ . '/../vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
$twig = new \Twig\Environment($loader);

$env_path = getenv('ENV_PATH') ?: __DIR__ . '/..';
$dotenv = Dotenv\Dotenv::createImmutable($env_path);
$dotenv->load();

$env = $_ENV['SERVICES_ENVIRONMENT']  ?: null;

$services = json_decode(file_get_contents(__DIR__ . '/../services.json'), true);

if($env){
    if(!array_key_exists($env, $services)){
        http_response_code(500);
        echo 'Environment not found';
        exit;
    }
    $services = [$env => $services[$env]];
}

echo $twig->render('index.twig', ['services' => $services]);
