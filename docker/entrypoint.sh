#!/usr/bin/env sh
set -e
cd /app

echo "==> Preparing the WebBlocks CMS demo..."

# .env only needs to carry APP_KEY; the rest of the demo config comes from the
# container environment (see docker-compose.yml).
if [ ! -f .env ]; then
  cp .env.example .env
fi

# Rebuild the package manifest now that the full app and real env are present
# (the image was built with --no-scripts).
php artisan package:discover --ansi >/dev/null 2>&1 || true

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force --ansi
fi

# Persist runtime container environment into .env. `php artisan serve` handles
# each request in a subprocess that does not inherit arbitrary container env
# vars, so values only present in the environment (not the .env file) are
# invisible to web requests. Baking them into .env makes env() consistent for
# both console (migrate/seed) and web (URL generation, proxy trust) contexts.
for key in APP_ENV APP_DEBUG APP_URL ASSET_URL FORCE_HTTPS TRUSTED_PROXIES \
  DB_CONNECTION DB_DATABASE CACHE_STORE SESSION_DRIVER QUEUE_CONNECTION \
  MAIL_MAILER LOG_CHANNEL; do
  eval "val=\${$key:-}"
  if [ -n "$val" ]; then
    if grep -q "^${key}=" .env; then
      sed -i "s|^${key}=.*|${key}=${val}|" .env
    else
      printf '%s=%s\n' "$key" "$val" >> .env
    fi
  fi
done

# SQLite database file.
mkdir -p database
touch "${DB_DATABASE:-/app/database/database.sqlite}"

# Schema (idempotent — already-applied migrations are skipped on restart).
php artisan migrate --force

# Seed the core catalog, default site, and a demo admin only once. The package
# seeder persists the installed version (which marks the install complete) and
# refuses to run again on an already-installed site, so guard it with a marker.
if [ ! -f /app/storage/.demo-installed ]; then
  php artisan db:seed --force
  # Ship example pages, media, and navigation so the demo is not an empty install.
  php artisan db:seed --class='Database\Seeders\FullShowcaseSeeder' --force
  # Create the first active super admin (the runtime install guard requires one).
  php artisan tinker --execute="\App\Models\User::query()->updateOrCreate(['email' => getenv('DEMO_ADMIN_EMAIL') ?: 'admin@example.com'], ['name' => 'Demo Admin', 'password' => \Illuminate\Support\Facades\Hash::make(getenv('DEMO_ADMIN_PASSWORD') ?: 'password'), 'role' => 'super_admin', 'is_admin' => true, 'is_active' => true, 'email_verified_at' => now()]);"
  touch /app/storage/.demo-installed
fi

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan config:clear >/dev/null 2>&1 || true

echo ""
echo "======================================================================"
echo "  WebBlocks CMS demo is ready"
echo "  Public site:  http://localhost:8080/"
echo "  Admin:        http://localhost:8080/webadmin"
echo "  Login:        ${DEMO_ADMIN_EMAIL:-admin@example.com} / ${DEMO_ADMIN_PASSWORD:-password}"
echo "======================================================================"
echo ""

exec php artisan serve --host=0.0.0.0 --port=8080
