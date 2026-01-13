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
        error_log("Fetching Github data for $owner $name");
        $latest = $client->api('repo')->releases()->latest($owner, $name);
        error_log("Latest release: " . json_encode($latest));
        return [
            'tag_name' => $latest['tag_name'],
            'published_at' => $latest['published_at'],
        ];
    } catch (Exception $e) {
        return ['error' => 'GitHub API error', 'detail' => $e->getMessage()];
    }
}
