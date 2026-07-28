# Bunny DDNS Updater

## Usage

```shell
docker run -d \
  --name bunny-ddns-updater \
  -e API_KEY="00000000-0000-0000-0000-00000000000000000000-0000-0000-0000-000000000000" \
  -e ZONES="mydomain.com" \
  ghcr.io/nayleen/bunny-ddns:1.1.0 # x-release-please-version
```

## Deployment Templates

See example deployment templates for:

- [Docker Compose](./deploy/docker-compose.yaml)
- [Docker Swarm](./deploy/docker-swarm.yaml)
- [Kubernetes](./deploy/kubernetes.yaml)

## Health Checks

The image includes a health check. Query it directly with:

```shell
docker exec bunny-ddns-updater php /app/src/app.php healthcheck
```

```json
{
    "status": "healthy",
    "ip": "203.0.113.1",
    "zones": ["mydomain.com"]
}
```

It exits `0` when healthy and `1` otherwise. The status stays `starting` until
the first successful update check, and becomes unhealthy after two missed update
intervals, with a minimum timeout of 60 seconds. Failure details are written to
the container logs when an update fails.

With `UPDATE_ON_START=false` the first check only runs after `UPDATE_INTERVAL`
seconds - increase `start_period` (or the Kubernetes startup probe budget)
accordingly for large intervals.

Docker Compose and Swarm inherit the image check. Kubernetes uses the startup
and liveness probes outlined in [`deploy/kubernetes.yaml`](./deploy/kubernetes.yaml).

## Configuration

The following environment variables can be set to configure updater:

| Required | Variable                    | Description                                   | Default Value |
|----------|-----------------------------|-----------------------------------------------|---------------|
| ☑️       | `API_KEY` \| `API_KEY_FILE` | Your Bunny.net API key                        | -             |
| ☑️       | `ZONES`                     | Comma-separated list of zones to update       | -             |
| ❎        | `AUTO_CREATE_ZONES`         | Whether to create zones that do not exist yet | `true`        |
| ❎        | `UPDATE_INTERVAL`           | Interval in seconds between IP update checks  | 30            |
| ❎        | `UPDATE_ON_START`           | Whether to run an update on container startup | `true`        |

## License
This project is licensed under the MIT License. See the [LICENSE](./LICENSE) file for details.
