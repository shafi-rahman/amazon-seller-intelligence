-- Extensions loaded once on first container start
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create test database
CREATE DATABASE asip_test;
GRANT ALL PRIVILEGES ON DATABASE asip_test TO asip;
