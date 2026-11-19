# Office Game Library

Suggest games for the office library and vote on the ones you want most.
Laravel 12 (PHP 8.4) + Inertia + React/TypeScript, backed by the Essense Code Challenge API.

## Run with Docker

**Prerequisites:** Docker with Compose (Docker Desktop includes both), and an Essense API key
from <https://codechallenge.essensedesigns.info/api-key>.

```bash
git clone https://github.com/pixelpollux/essense-assessment.git
cd essense-assessment
cp .env.example .env        # then set ESSENSE_API_KEY=your-key in .env
docker-compose up
```

Open <http://localhost:8000> and register an account.

The first run builds the image, which takes a few minutes. After that, `docker-compose up` starts in seconds.

### What happens on start

- The SQLite database is created on a Docker volume (`app-data`), and migrations run automatically.
- An app encryption key (`APP_KEY`) is generated on first start and kept on the same volume, so you stay logged in across restarts.
- The app runs in production mode with debug off, so errors show friendly pages rather than stack traces.

### Useful commands

| Task | Command |
|---|---|
| Run in the background | `docker-compose up -d` |
| Follow the app logs | `docker-compose logs -f` |
| Stop | `docker-compose down` |
| Stop and **delete all data** (database, key) | `docker-compose down -v` |
| Rebuild after pulling new code | `docker-compose up --build` |
| Use a different port | set `APP_PORT=8080` in `.env` |

### Troubleshooting

- **`required variable ESSENSE_API_KEY is missing a value`**: `.env` is missing or `ESSENSE_API_KEY` is empty. Copy `.env.example` to `.env` and set the key.
- **Port 8000 is already in use**: set `APP_PORT` in `.env` to a free port.
