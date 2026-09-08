# Check-In v2

**[Live demo ↗](https://ci.thatdevjp.com)**

An appointment scheduling and check-in platform for operations that run on arrivals -
drivers book a slot, arrive, and check in against it across sites. Built for produce
distribution, where a truck idling on a dock waiting for a free bay is spoiled inventory.

The first version shipped in 2018 and ran nine distribution centers across all four
continental U.S. time zones. Every slot, reminder, and daylight-saving shift resolves in
the site's local time rather than the server's. This is the rebuild.

### What it does

- **Appointments** - per-location weekly schedules with per-date overrides, resolved in the location's own timezone
- **Check-in** - drivers check in against a booked slot, distance-gated so it happens on site rather than from the highway
- **Notifications** - confirmations and reminders for guests and users, queued through Horizon and delivered over a custom notification channel with a swappable SMS sender
- **Access control** - per-location employee scoping via `spatie/laravel-permission` teams, so a site manager sees their site
- **Accounts** - passkeys (Fortify WebAuthn), email verification, password confirmation on sensitive actions, and per-user locale, theme, and default location
- **Documents** - PDF rendering, Code 39 barcodes generated as inline SVG with no barcode dependency, and short links for anything sent by SMS

> **Status:** actively developed, running on a staging environment. Local development is
> configured via Laravel Sail (Docker); see below.

## Stack

| Layer | Technology                                    |
|---|-----------------------------------------------|
| Backend | Laravel 13                                    |
| Language | PHP 8.5                                       |
| Testing | Pest (Wrapper for PHPUnit)                    |
| Database | PostgreSQL 18                                 |
| Frontend | React · TypeScript · Inertia.js · Tailwind CSS |
| Cache / Queue | Redis                                         |
| Queue Dashboard | Laravel Horizon                             |
| Mail | Mailpit (local) · Mailgun HTTP API (staging)   |

## Laravel Packages & Tooling

| Tool | Purpose                                                                                                              |
|---|----------------------------------------------------------------------------------------------------------------------|
| Sail | Docker-based local development environment                                                                           |
| Octane | High-performance application server (FrankenPHP)                                                                     |
| Pint | PHP code style fixer (Wrapper built ontop of PHP-CS-Fixer)                                                           |
| Larastan | Static analysis (PHPStan for Laravel)                                                                                |
| Rector | Automated code refactoring (Driftingly/Laravel Rector)                                                               |
| Horizon | Redis queue monitoring & worker management dashboard                                                                 |
| Fortify | Headless authentication backend - login, registration, email verification & password reset (custom Inertia/React UI) |
| Spatie Permission | Roles & permissions with team support - per-location employee scoping (spatie/laravel-permission)                    |
| Laravel Phone | Phone number validation (Propaganistas wrapper around Google's libphonenumber)                                       |

## Requirements

- [Composer](https://getcomposer.org/)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/)

```bash
# Install Composer (macOS)
brew install composer
```

## Installation

```bash
# 1. Clone the repository
git clone git@github.com:juanplazadev/check-in-v2.git && cd check-in-v2

# 2. Install PHP dependencies
composer install

# 3. Copy environment file
cp .env.example .env

# 4. Generate application key
./vendor/bin/sail artisan key:generate

# 5. Start Docker containers
./vendor/bin/sail up -d

# 6. Install and build frontend dependencies
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev   # or: npm run build

# 7. Run database migrations
./vendor/bin/sail artisan migrate

# 8. Run database seeders
./vendor/bin/sail artisan db:seed
```

## Accessing the Application

| Service | URL                               |
|---|-----------------------------------|
| Web application | https://martori.localhost         |
| Horizon dashboard | https://martori.localhost/horizon |
| Mailpit inbox | http://martori.localhost:8025     |

## Development

> 💡 Octane is configured to run in watch mode - backend changes are picked up automatically with no server restart needed.

### Frontend

```bash
./vendor/bin/sail npm run dev
```

### Queues (Horizon)

Background jobs are processed by [Laravel Horizon](https://laravel.com/docs/horizon), which manages the Redis queue workers and provides a monitoring dashboard at `/horizon`.

```bash
./vendor/bin/sail artisan horizon        # start the Horizon workers
./vendor/bin/sail artisan horizon:status # check the master supervisor status
```

Worker supervisors, queues, and balancing strategies are configured in `config/horizon.php`. Dashboard access is authorized via the `viewHorizon` gate.

#### Horizon on staging

Not reachable from the public internet: `HORIZON_DOMAIN` in the server-side `.env` scopes Horizon's routes to the tailnet hostname, so they are never registered on the public FQDN. The `handle /horizon*` block in `deploy/Caddyfile.staging` is optional defense in depth.

### Code Style (Pint)

Uses extensive custom rules defined in `pint.json` - including `declare_strict_types`, `final_class`, `strict_comparison`, `ordered_class_elements`, and more. See `pint.json` for the full ruleset.

```bash
./vendor/bin/pint                    # fix all files
./vendor/bin/pint --dirty            # fix only uncommitted files
./vendor/bin/pint --test             # dry-run (report without fixing)
```

### Static Analysis (Larastan)

Runs at **level 8** (out of 10). Configuration in `phpstan.neon`.

```bash
./vendor/bin/phpstan analyse --memory-limit=2G
```

### Automated Refactoring (Rector)

Uses [Driftingly/Laravel Rector](https://github.com/driftingly/rector-laravel) with Laravel-specific rule sets and prepared sets for dead code, code quality, type declarations, privatization, and early returns. Configuration in `rector.php`.

```bash
./vendor/bin/rector --dry-run          # preview changes without applying
./vendor/bin/rector                    # apply changes
```

### Tests

```bash
./vendor/bin/sail artisan test                                              # all tests
./vendor/bin/sail artisan test --compact                                    # compact output
./vendor/bin/sail artisan test --parallel                                   # run tests in parallel (faster)
./vendor/bin/sail artisan test --parallel --compact                         # parallel + compact output
./vendor/bin/sail pest --filter "switches locale when locale is available"  # specific test
./vendor/bin/sail pest tests/Unit/LocaleSwitchTest.php                      # specific file
```

#### Browser tests (Playwright)

Browser tests live in `tests/Browser` and drive a real Chromium instance via Playwright. The browser binaries must be installed inside the Sail container once (they live in the container filesystem, so reinstall after a container rebuild):

```bash
./vendor/bin/sail npx playwright install chromium               # download the Chromium binary
docker compose exec -u root checkin npx playwright install-deps chromium  # install system libraries (as root)
```

Then run the browser suite:

```bash
./vendor/bin/sail artisan test tests/Browser                    # all browser tests
./vendor/bin/sail artisan test --parallel tests/Browser         # browser tests in parallel
```
