<?php

require_once __DIR__ . '/../vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
$twig = new \Twig\Environment($loader);

$env_path = getenv('ENV_PATH') ?: __DIR__ . '/..';
$dotenv = Dotenv\Dotenv::createImmutable($env_path);
$dotenv->load();

$services = json_decode(file_get_contents(__DIR__ . '/../services.json'), true);
$env = $_ENV['SERVICES_ENVIRONMENT']  ?: null;

echo $twig->render('index.twig', ['env' => $env, 'services' => $services]);
