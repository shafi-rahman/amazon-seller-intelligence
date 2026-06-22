#!/usr/bin/env bash
# ============================================================
# ASIP — One-Command Startup
# Usage: bash start.sh
# ============================================================

set -e

BLUE='\033[0;34m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()    { echo -e "${BLUE}[ASIP]${NC} $1"; }
success() { echo -e "${GREEN}[ASIP]${NC} $1"; }
warn()    { echo -e "${YELLOW}[ASIP]${NC} $1"; }
error()   { echo -e "${RED}[ASIP]${NC} $1"; exit 1; }

# ── Prerequisites ─────────────────────────────────────────────
info "Checking prerequisites..."
command -v docker >/dev/null 2>&1          || error "Docker is not installed."
docker compose version >/dev/null 2>&1     || error "Docker Compose plugin is not installed."
success "Prerequisites OK"

# ── Environment ───────────────────────────────────────────────
# IMPORTANT: .env must be set before starting containers so Docker Compose
# picks up REDIS_PASSWORD, MINIO credentials, etc. from it.
if [ ! -f .env ]; then
    info "Creating .env from .env.example..."
    cp .env.example .env
    warn "API keys are empty — add ANTHROPIC_API_KEY and OPENAI_API_KEY to .env before using AI features."
fi

# ── Build & Start Containers ──────────────────────────────────
info "Building Docker images (this takes a few minutes on first run)..."
docker compose build

info "Starting containers..."
docker compose up -d

# ── Wait for PostgreSQL ───────────────────────────────────────
info "Waiting for PostgreSQL..."
RETRIES=40
until docker compose exec -T postgres pg_isready -U "${DB_USERNAME:-asip}" -d "${DB_DATABASE:-asip}" >/dev/null 2>&1; do
    RETRIES=$((RETRIES - 1))
    [ $RETRIES -le 0 ] && error "PostgreSQL failed to start. Run: docker compose logs postgres"
    printf "."
    sleep 2
done
echo ""
success "PostgreSQL ready"

# ── PHP Dependencies ──────────────────────────────────────────
# In production, never install dev tooling (Telescope/PHPUnit/Faker/Pint) into the
# running container. (For real prod deploys prefer the baked image — Dockerfile.prod.)
APP_ENV_VALUE=$(grep -E '^APP_ENV=' .env | cut -d= -f2 | tr -d '"' | tr -d ' ')
if [ "$APP_ENV_VALUE" = "production" ]; then
    info "Installing Composer dependencies (production: --no-dev)..."
    docker compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
else
    info "Installing Composer dependencies..."
    docker compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# ── Laravel Setup ─────────────────────────────────────────────
info "Generating application key..."
docker compose exec -T app php artisan key:generate --no-interaction --force

info "Publishing vendor config..."
# --skip-existing prevents re-publishing Sanctum/Spatie migrations when they already exist
docker compose exec -T app php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --no-interaction --tag="sanctum-config"
docker compose exec -T app php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider" --no-interaction --tag="horizon-assets" --tag="horizon-config"
docker compose exec -T app php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --no-interaction --tag="permission-config"

# Publish migrations only if they don't already exist
docker compose exec -T app php artisan vendor:publish \
    --provider="Laravel\Sanctum\SanctumServiceProvider" --tag="sanctum-migrations" \
    --no-interaction 2>/dev/null || true
docker compose exec -T app php artisan vendor:publish \
    --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-migrations" \
    --no-interaction 2>/dev/null || true

# Remove any duplicate Sanctum migration (vendor:publish creates a new one each run)
info "Removing duplicate Sanctum migration if present..."
SANCTUM_DUPS=$(ls database/migrations/*create_personal_access_tokens_table.php 2>/dev/null | sort | tail -n +2)
if [ -n "$SANCTUM_DUPS" ]; then
    echo "$SANCTUM_DUPS" | xargs rm -f
    warn "Removed duplicate Sanctum migration(s): $SANCTUM_DUPS"
fi

info "Running database migrations..."
docker compose exec -T app php artisan migrate --no-interaction --force

info "Seeding database..."
docker compose exec -T app php artisan db:seed --no-interaction --force

info "Setting up storage symlink..."
docker compose exec -T app php artisan storage:link --no-interaction --force

# ── MinIO Buckets ─────────────────────────────────────────────
info "Creating MinIO buckets..."
docker compose exec -T app php artisan app:setup-minio

# ── Frontend ──────────────────────────────────────────────────
info "Installing Node dependencies..."
docker run --rm \
    -v "$(pwd):/app" -w /app \
    node:22-alpine \
    npm install --legacy-peer-deps

info "Building frontend assets..."
docker run --rm \
    -v "$(pwd):/app" -w /app \
    node:22-alpine \
    npm run build

# ── Optimize ──────────────────────────────────────────────────
info "Caching config and routes..."
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache

# ── Done ──────────────────────────────────────────────────────
success ""
success "======================================================"
success " ASIP is running!"
success "======================================================"
APP_PORT="${APP_PORT:-7801}"
success " App:       http://localhost:${APP_PORT}"
success " Horizon:   http://localhost:${APP_PORT}/horizon"
success " MinIO UI:  http://localhost:9001  (asip / secret123)"
success " MailHog:   http://localhost:8025"
success ""
success " Default logins:"
success "   admin@asip.local     / password"
success "   seller@asip.local    / password"
success "   accountant@asip.local/ password"
success "======================================================"
