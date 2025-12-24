# Simple overview of the GFModule infrastructure landscape

This will be a simple overview of the GFModule infrastructure including versioning and open GitHub PR's.

Note that this is a work in progress and will be updated as we go along.

# .env
There is a simple `.env` that allows you to set some configuration options. You can copy the 
`.env.example` as a template.

Available variables:
- `APP_NAME`: optional, overrides the displayed app name.
- `SERVICES_ENVIRONMENT`: which environment from the services file to render.
- `SERVICES_FILE`: optional path/filename for the services catalog (defaults to `services.json`). Use this to point at `mgo-services.json` when running the MGO dashboard.
- `GITHUB_TOKEN`: optional token for GitHub API access.
- `MTLS_CERT` / `MTLS_KEY` / `MTLS_CA`: optional mTLS configuration passed through to downstream calls.

Basic auth credentials are injected via environment variables, never committed in the JSON. The default naming pattern is `BASIC_AUTH_<SERVICE>_<ENV>_USERNAME` and `BASIC_AUTH_<SERVICE>_<ENV>_PASSWORD`, where `<SERVICE>` and `<ENV>` are uppercased and non-alphanumeric characters are replaced by underscores. You can set custom variable names per service/environment using `basic_auth.username_env` and `basic_auth.password_env` in the JSON.

# Docker

You can build and run the Docker container:

```bash
docker build -t gfmodules-landscape-overview .
docker run -d --name gfmodules-landscape-overview -p 9999:80 gfmodules-landscape-overview
```

Now you can access the application at `localhost:9999`.

To stop the running container:

```bash
docker stop gfmodules-landscape-overview
```

To remove the container (after stopping):

```bash
docker rm gfmodules-landscape-overview
```

# Docker Compose

To run the application with Docker Compose:

```bash
cp .env.example .env
docker compose up --build
```

The app will be available at `http://localhost:9999`.

# Running locally
It's also possible to run this locally. For this, we need to have a running php-fpm and have caddy installed.
Then, you can run the following:

```bash
$ caddy run --config Caddyfile.local
```

Note that you might need to change the socket path in the Caddyfile.local to match your local setup.


# Caching
The application uses a caching mechanism to store the results of the Github API calls and versioning.
There is a default of 5 minute timeout on the cache.

# Settings.json

All service definitions live in a JSON file in the repository root. By default the app uses `services.json`; set `SERVICES_FILE` to use another file such as `mgo-services.json`.

```json
[
  {
    "name": "Name of the service",
    "environments": {
      "test": {
        "url": "https://service.test.example.com"
      },
      "acceptance": {
        "url": "https://service.acceptance.example.com"
      },
    },
    "github": "owner/repo",
    "type": "Python",
    "has_version": true,
    "description": "Short description of the service"
  }
]
```