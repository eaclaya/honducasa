# Honducasa

Honducasa is a Laravel 13 and Inertia Vue 3 marketplace for renting, buying, and publishing properties in Honduras.

## Requirements

- PHP 8.4 with `bcmath`, `gd`, `intl`, `mbstring`, `pdo_pgsql`, and `redis`
- Composer 2
- Node.js 22 and npm
- PostgreSQL 16 with PostGIS
- Redis

## Local setup

1. Clone the repository and enter it:

   ```bash
   git clone git@github.com:eaclaya/honducasa.git
   cd honducasa
   ```

2. Create a PostgreSQL database and enable PostGIS:

   ```sql
   CREATE USER honducasa WITH PASSWORD 'honducasa';
   CREATE DATABASE honducasa OWNER honducasa;
   \c honducasa
   CREATE EXTENSION IF NOT EXISTS postgis;
   ```

3. Copy the environment file and adjust the PostgreSQL, Redis, mail, Google OAuth, OpenAI, and Mapbox values for your machine:

   ```bash
   cp .env.example .env
   ```

   The example configuration expects PostgreSQL on port `55432` and Redis on port `6381`. Change those ports if your services use their defaults.

4. Install dependencies, generate the application key, migrate the database, and build the frontend:

   ```bash
   composer install
   php artisan key:generate
   php artisan migrate
   php artisan storage:link
   npm ci
   npm run build
   ```

5. Start the application and frontend development server:

   ```bash
   composer dev
   ```

   When using Laravel Herd, point the site at this repository and open `https://honducasa.test`. Otherwise, run `php artisan serve` and `npm run dev` in separate terminals.

6. Run Horizon in a separate process when testing queued notifications or image enhancement:

   ```bash
   php artisan horizon
   ```

## Local verification

Run the blocking CI checks locally with:

```bash
npm run lint:check
npm run format:check
npm run types:check
npm run build
composer lint:check
php artisan test --compact
```

Tests use PostgreSQL/PostGIS and the connection defined in `phpunit.xml`. Run `composer types:check` separately to inspect the existing PHPStan backlog and reduce it incrementally.

## CI/CD

The [GitHub Actions workflow](.github/workflows/tests.yml) runs on every pull request, every push to `main`, and manual dispatches.

The CI job:

1. Starts PostgreSQL 16 with PostGIS.
2. Installs locked PHP and npm dependencies.
3. Builds the Vite frontend.
4. Runs ESLint, Prettier, Vue TypeScript checking, Laravel Pint, and Pest.

After CI succeeds on `main`, the production job builds a release and deploys it over SSH. Pull requests never receive production secrets and never deploy. The deployment creates an immutable release directory, links shared environment and storage data, runs Laravel optimization and migrations, atomically switches the `current` symlink, reloads long-running Laravel services, and retains the five newest releases.

### GitHub production environment

Create an environment named `production` under **Repository settings → Environments**. Restrict it to `main`; optionally require approval before deployment. Add these environment secrets:

| Secret | Description |
| --- | --- |
| `DEPLOY_HOST` | Production server hostname or IP address |
| `DEPLOY_USER` | Unprivileged SSH deployment user |
| `DEPLOY_PORT` | SSH port; leave empty to use `22` |
| `DEPLOY_PATH` | Absolute application root, for example `/var/www/honducasa` |
| `DEPLOY_SSH_KEY` | Private Ed25519 key authorized for the deployment user |
| `DEPLOY_KNOWN_HOSTS` | Verified server host-key line from `ssh-keyscan -H your-host` |
| `VITE_MAPBOX_ACCESS_TOKEN` | Public Mapbox browser token embedded during the Vite build |

Do not store the production `.env` file or application secrets in GitHub or the repository. It lives only at `${DEPLOY_PATH}/shared/.env` on the server.

Optionally set the production environment variable `VITE_MAPBOX_STYLE`; it defaults to `mapbox/streets-v12`.

## Production server setup

Provision an Ubuntu or equivalent server with Nginx, PHP 8.4 FPM and the extensions listed above, Composer 2, PostgreSQL/PostGIS, and Redis. Node.js is not required on the server because GitHub Actions builds the frontend assets.

Create the deployment layout as the deployment user:

```bash
sudo mkdir -p /var/www/honducasa/shared/storage
sudo chown -R deploy:www-data /var/www/honducasa
sudo chmod -R g+rwX /var/www/honducasa/shared
touch /var/www/honducasa/shared/.env
chmod 600 /var/www/honducasa/shared/.env
```

Populate `shared/.env` with production values. At minimum:

```dotenv
APP_NAME=Honducasa
APP_ENV=production
APP_KEY=base64:GENERATE_A_REAL_KEY
APP_DEBUG=false
APP_URL=https://example.com
APP_LOCALE=es

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=honducasa
DB_USERNAME=honducasa
DB_PASSWORD=use-a-secret-password
DB_SSLMODE=require

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

FILESYSTEM_DISK=public
MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=hola@example.com
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
VITE_MAPBOX_ACCESS_TOKEN=your-public-mapbox-token
```

Generate `APP_KEY` locally with `php artisan key:generate --show`; never regenerate it after production data exists. Add the remaining mail, Google OAuth, OpenAI, storage, exchange-rate, and Mapbox values from `.env.example` as required.

Create the database and enable PostGIS before the first deployment:

```sql
CREATE DATABASE honducasa OWNER honducasa;
\c honducasa
CREATE EXTENSION IF NOT EXISTS postgis;
```

Configure the web server document root as `/var/www/honducasa/current/public`. The `current` symlink is created by the first successful deployment. Ensure the PHP-FPM user can write to `/var/www/honducasa/shared/storage`.

### Scheduler and Horizon

Run Laravel's scheduler every minute:

```cron
* * * * * cd /var/www/honducasa/current && php artisan schedule:run >> /dev/null 2>&1
```

Run `php artisan horizon` under systemd, Supervisor, or another process monitor with automatic restart enabled. Deployments call `php artisan reload`, which gracefully terminates long-running Laravel services so the monitor starts them with the new release.

If the host needs an additional service reload, create an executable `${DEPLOY_PATH}/shared/restart.sh`. The deployment passes the activated release path as its first argument. Keep privileged commands narrowly scoped through `sudoers` rather than granting the deployment user unrestricted sudo.

### First deployment

1. Push the workflow changes to a branch and open a pull request.
2. Confirm the CI job passes.
3. Merge into `main`.
4. Approve the `production` environment job if approval protection is enabled.
5. Verify the site, Horizon, scheduled tasks, mail, uploads, and `/up` health endpoint.

### Rollback

Repoint `current` to a retained release and reload services:

```bash
cd /var/www/honducasa
ln -sfn /var/www/honducasa/releases/PREVIOUS_COMMIT_SHA current
cd current
php artisan reload
```

Code rolls back immediately. Database migrations are not automatically reversed; only deploy backward-compatible migrations or prepare an explicit database rollback plan.
