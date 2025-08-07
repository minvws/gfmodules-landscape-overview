<?php

// Proxy script for fetching Github pull request info

require_once __DIR__ . '/../vendor/autoload.php';

use Github\Client;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$env_path = getenv('ENV_PATH') ?? __DIR__ . '/..';
$dotenv = Dotenv\Dotenv::createImmutable($env_path);
$dotenv->load();

$cache = new FilesystemAdapter(
    namespace: 'prs_proxy',
    defaultLifetime: 300,       // 5 minutes
    directory: __DIR__ . '/../.cache'
);

header('Content-Type: application/json');

// Validate input
if (!isset($_GET['repo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing repo']);
    exit;
}

$repo = $_GET['repo'];

// Validate repo format
if (!preg_match('#^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$#', $repo)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid repo format']);
    exit;
}

$allowedRepos = get_allowed_repos(__DIR__ . '/../services.json');
if (!in_array($repo, $allowedRepos)) {
    http_response_code(403);
    echo json_encode(['error' => 'Repository not allowed']);
    exit;
}


// Check if item is cached, if so, return that
$cacheKey = sha1($repo);
$cachedItem = $cache->getItem($cacheKey);
if ($cachedItem->isHit()) {
    header('Content-Type: application/json');
    echo json_encode($cachedItem->get());
    exit;
}

$data = fetch_github_data($repo);
if (isset($data['error'])) {
    http_response_code(502);
    echo json_encode($data);
    exit;
}

$cachedItem->set($data);
$cache->save($cachedItem);
echo json_encode($data);
exit;


function fetch_github_data(string $repo) {
    $client = new Client();

    $token = $_ENV['GITHUB_TOKEN'] ?? null;
    if ($token) {
        $client->authenticate($token, null, Client::AUTH_ACCESS_TOKEN);
    }

    try {
        [$owner, $name] = explode('/', $repo, 2);
        $prs = $client->pullRequest()->all($owner, $name, ['state' => 'open']);

        return ['pull_requests' => count($prs)];
    } catch (Exception $e) {
        return ['error' => 'GitHub API error', 'detail' => $e->getMessage()];
    }

}


function get_allowed_repos(string $settingsFile)
{
    if (!file_exists($settingsFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Config missing']);
        exit;
    }

    $allowedRepos = [];
    $services = json_decode(file_get_contents($settingsFile), true);

    // Extract allowed GitHub repos (in org/repo format)
    foreach ($services as $env => $entries) {
        foreach ($entries as $svc) {
            if (isset($svc['github']) && preg_match('#([^/]+/[^/]+)#', $svc['github'], $m)) {
                $allowedRepos[] = $m[1]; // e.g., org/repo
            }
        }
    }

    return $allowedRepos;
}
