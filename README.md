# Simple overview of the GFModule infrastructure landscape

This will be a simple overview of the GFModule infrastructure including versioning and open GitJub PR's.

Note that this is a work in progress and will be updated as we go along.

# .env
There is a simple `.env` that allows you to set some configuration options. You can copy the 
`.env.example` as a template.

# Docker

You can run the docker container:

```bash
docker build -t landscape .
docker run -d -p 9999:80 --env-file .env landscape
```

Now you can access the application at `localhost:9999`.


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

All information is stored in the services.json file in the root of the repository.

```json

{
  "testing": [    // Environment name
    {
      "name": "Name of the service",
      "url": "URL where this service is located (public facing)",
      "github": "Name of the github repository (owner/repo format)",
      "type": "Type of the application",
      "has_version": false  // Or true when this service have a /version.json
      "description": "Description of the service",
    },
    ...
}
```