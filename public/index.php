<?php

require_once __DIR__ . '/../vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
$twig = new \Twig\Environment($loader);

$services = json_decode(file_get_contents(__DIR__ . '/../services.json'), true);
echo $twig->render('index.twig', ['services' => $services]);
