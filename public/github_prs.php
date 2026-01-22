<?php

declare(strict_types=1);

// Proxy script for fetching Github pull request info

require_once __DIR__ . '/util.php';

use Github\Client;

handleRequest('prs_proxy', 'fetch_github_data');

function fetch_github_data(array $service)
{
    $repo = $service['github'] ?? null;
    error_log("Fetching Github data for $repo");
    if (!$repo) {
        return [];
    }
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
