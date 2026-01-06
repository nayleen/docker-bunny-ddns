# Bunny DDNS Updater

## Usage

```shell
docker run -d \
  --name bunny-ddns-updater \
  -e API_KEY="00000000-0000-0000-0000-00000000000000000000-0000-0000-0000-000000000000" \
  -e ZONES="mydomain.com" \
  ghcr.io/nayleen/bunny-ddns:latest
```

## Deployment Templates

See example deployment templates for:

- [Docker Compose](./deploy/docker-compose.yaml)
- [Docker Swarm](./deploy/docker-swarm.yaml)

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
