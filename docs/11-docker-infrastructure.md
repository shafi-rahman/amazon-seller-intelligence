# Docker & Infrastructure

---

## Overview

The entire stack runs in Docker Compose. One command starts everything:

```bash
bash start.sh
```

---

## Services

| Service | Image | Port | Purpose |
|---------|-------|------|---------|
| `nginx` | nginx:alpine | 80 | Reverse proxy → PHP-FPM |
| `app` | custom (PHP 8.4-FPM) | 9000 | Laravel application |
| `horizon` | same as `app` | — | Queue worker (Horizon) |
| `scheduler` | same as `app` | — | Laravel scheduler (`artisan schedule:work`) |
| `postgres` | pgvector/pgvector:pg16 | 5432 | PostgreSQL 16 + pgvector |
| `redis` | redis:7-alpine | 6379 | Queue, sessions, cache |
| `minio` | minio/minio:latest | 9000/9001 | S3-compatible file storage |
| `mailhog` | mailhog/mailhog | 1025/8025 | Dev email catcher |
| `node` | node:22-alpine | — | Frontend build (dev only) |

---

## docker-compose.yml

```yaml
version: '3.9'

networks:
  asip_net:
    driver: bridge

volumes:
  postgres_data:
  redis_data:
  minio_data:

services:

  nginx:
    image: nginx:alpine
    container_name: asip_nginx
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
    networks:
      - asip_net
    restart: unless-stopped

  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: asip_app
    volumes:
      - ./:/var/www/html
      - ./docker/php/php.ini:/usr/local/etc/php/php.ini
    environment:
      - PHP_IDE_CONFIG=serverName=asip
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_started
      minio:
        condition: service_started
    networks:
      - asip_net
    restart: unless-stopped

  horizon:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: asip_horizon
    command: php artisan horizon
    volumes:
      - ./:/var/www/html
    depends_on:
      - app
      - redis
      - postgres
    networks:
      - asip_net
    restart: unless-stopped

  scheduler:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: asip_scheduler
    command: php artisan schedule:work
    volumes:
      - ./:/var/www/html
    depends_on:
      - app
    networks:
      - asip_net
    restart: unless-stopped

  postgres:
    image: pgvector/pgvector:pg16
    container_name: asip_postgres
    environment:
      POSTGRES_DB: ${DB_DATABASE:-asip}
      POSTGRES_USER: ${DB_USERNAME:-asip}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql
    ports:
      - "5432:5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-asip} -d ${DB_DATABASE:-asip}"]
      interval: 5s
      timeout: 5s
      retries: 10
    networks:
      - asip_net
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    container_name: asip_redis
    command: redis-server --requirepass ${REDIS_PASSWORD:-secret}
    volumes:
      - redis_data:/data
    ports:
      - "6379:6379"
    networks:
      - asip_net
    restart: unless-stopped

  minio:
    image: minio/minio:latest
    container_name: asip_minio
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER: ${MINIO_ROOT_USER:-asip}
      MINIO_ROOT_PASSWORD: ${MINIO_ROOT_PASSWORD:-secret123}
    volumes:
      - minio_data:/data
    ports:
      - "9000:9000"
      - "9001:9001"
    networks:
      - asip_net
    restart: unless-stopped

  mailhog:
    image: mailhog/mailhog:latest
    container_name: asip_mailhog
    ports:
      - "1025:1025"
      - "8025:8025"
    networks:
      - asip_net
    restart: unless-stopped
```

---

## docker/php/Dockerfile

```dockerfile
FROM php:8.4-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    git curl zip unzip \
    postgresql-dev \
    linux-headers \
    $PHPIZE_DEPS

# PHP extensions
RUN docker-php-ext-install \
    pdo pdo_pgsql \
    pcntl \
    bcmath \
    opcache

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Non-root user for security
RUN addgroup -g 1000 -S www && adduser -u 1000 -S www -G www
USER www

EXPOSE 9000
CMD ["php-fpm"]
```

---

## docker/nginx/default.conf

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 55M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

---

## docker/postgres/init.sql

```sql
-- Runs once on first container start
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
```

---

## .env.example

```dotenv
APP_NAME="ASIP"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
APP_TIMEZONE=Asia/Kolkata

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=asip
DB_USERNAME=asip
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=secret
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis
HORIZON_PREFIX=asip_horizon

# File Storage (MinIO)
FILESYSTEM_DISK=minio
AWS_ACCESS_KEY_ID=asip
AWS_SECRET_ACCESS_KEY=secret123
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=asip-uploads
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

# Mail (Mailhog for dev)
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=noreply@asip.local
MAIL_FROM_NAME="${APP_NAME}"

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# AI Providers
ANTHROPIC_API_KEY=
OPENAI_API_KEY=
GEMINI_API_KEY=
GROQ_API_KEY=
OLLAMA_BASE_URL=http://host.docker.internal:11434

# MinIO Buckets (comma-separated, created on first run)
MINIO_BUCKETS=asip-uploads,asip-reports,asip-exports

# Minio root credentials
MINIO_ROOT_USER=asip
MINIO_ROOT_PASSWORD=secret123
```

---

## start.sh

```bash
#!/usr/bin/env bash
# ============================================================
# ASIP — One-Command Startup Script
# Run: bash start.sh
# ============================================================

set -e

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info()    { echo -e "${BLUE}[ASIP]${NC} $1"; }
success() { echo -e "${GREEN}[ASIP]${NC} $1"; }
warn()    { echo -e "${YELLOW}[ASIP]${NC} $1"; }
error()   { echo -e "${RED}[ASIP]${NC} $1"; exit 1; }

# ─── Step 1: Prerequisites ───────────────────────────────────────────────────
info "Checking prerequisites..."
command -v docker >/dev/null 2>&1 || error "Docker is not installed."
command -v docker compose >/dev/null 2>&1 || error "Docker Compose is not installed."
success "Prerequisites OK"

# ─── Step 2: Environment ─────────────────────────────────────────────────────
if [ ! -f .env ]; then
    info "Creating .env from .env.example..."
    cp .env.example .env
    warn "Please add your API keys to .env before running AI features."
fi

# ─── Step 3: Build & Start Containers ────────────────────────────────────────
info "Building Docker images..."
docker compose build --no-cache

info "Starting containers..."
docker compose up -d

# ─── Step 4: Wait for PostgreSQL ─────────────────────────────────────────────
info "Waiting for PostgreSQL to be ready..."
RETRIES=30
until docker compose exec -T postgres pg_isready -U asip -d asip > /dev/null 2>&1; do
    RETRIES=$((RETRIES-1))
    if [ $RETRIES -le 0 ]; then
        error "PostgreSQL failed to start. Check: docker compose logs postgres"
    fi
    echo -n "."
    sleep 2
done
echo ""
success "PostgreSQL is ready"

# ─── Step 5: Install PHP Dependencies ────────────────────────────────────────
info "Installing Composer dependencies..."
docker compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader

# ─── Step 6: Laravel Setup ───────────────────────────────────────────────────
info "Generating app key..."
docker compose exec -T app php artisan key:generate --no-interaction

info "Running database migrations..."
docker compose exec -T app php artisan migrate --no-interaction --force

info "Seeding database..."
docker compose exec -T app php artisan db:seed --no-interaction --force

info "Creating storage symlink..."
docker compose exec -T app php artisan storage:link --no-interaction

# ─── Step 7: MinIO Buckets ───────────────────────────────────────────────────
info "Setting up MinIO buckets..."
docker compose exec -T app php artisan app:setup-minio

# ─── Step 8: Frontend ────────────────────────────────────────────────────────
info "Installing Node dependencies..."
docker compose run --rm node npm install

info "Building frontend assets..."
docker compose run --rm node npm run build

# ─── Step 9: Optimize ────────────────────────────────────────────────────────
info "Optimizing Laravel..."
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache

# ─── Done ────────────────────────────────────────────────────────────────────
success ""
success "========================================="
success " ASIP is running!"
success "========================================="
success " App:      http://localhost"
success " Horizon:  http://localhost/horizon"
success " MinIO:    http://localhost:9001"
success " MailHog:  http://localhost:8025"
success ""
success " Default credentials:"
success "   Admin:      admin@asip.local / password"
success "   Seller:     seller@asip.local / password"
success "========================================="
```

---

## MinIO Setup Command

```php
// app/Console/Commands/SetupMinioCommand.php
class SetupMinioCommand extends Command
{
    protected $signature   = 'app:setup-minio';
    protected $description = 'Create required MinIO buckets';

    public function handle(): void
    {
        $buckets = explode(',', env('MINIO_BUCKETS', 'asip-uploads,asip-reports,asip-exports'));
        $client  = Storage::disk('minio')->getClient();

        foreach ($buckets as $bucket) {
            $bucket = trim($bucket);
            if (!$client->doesBucketExist($bucket)) {
                $client->createBucket(['Bucket' => $bucket]);
                $this->info("Created bucket: {$bucket}");
            } else {
                $this->line("Bucket exists: {$bucket}");
            }
        }
    }
}
```

---

## Useful Commands

```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# View logs
docker compose logs -f app
docker compose logs -f horizon

# Run artisan commands
docker compose exec app php artisan {command}

# Run tests
docker compose exec app php artisan test

# Fresh database (drops all + re-migrates + seeds)
docker compose exec app php artisan migrate:fresh --seed

# Access PostgreSQL
docker compose exec postgres psql -U asip -d asip

# Access Redis CLI
docker compose exec redis redis-cli -a secret

# Restart Horizon
docker compose restart horizon

# Rebuild PHP image after Dockerfile changes
docker compose build app && docker compose up -d app
```

---

## Resource Requirements

| Component | Min RAM | Recommended |
|-----------|---------|-------------|
| postgres | 256 MB | 512 MB |
| redis | 64 MB | 128 MB |
| minio | 128 MB | 256 MB |
| app (PHP-FPM) | 256 MB | 512 MB |
| horizon | 256 MB | 512 MB |
| nginx | 32 MB | 64 MB |
| **Total** | **~1 GB** | **~2 GB** |

Minimum host machine: **4 GB RAM**, 4 CPU cores, 20 GB disk.
