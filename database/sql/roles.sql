-- Family Essentials — application database role & privileges.
-- Run once as a superuser (postgres) against the cluster:
--   psql -U postgres -h 127.0.0.1 -f database/sql/roles.sql
--
-- The runtime app connects as `family_app`, which is NOT the table owner and
-- has NOBYPASSRLS, so PostgreSQL Row-Level Security is actually enforced.
-- Migrations/seeders connect as `postgres` (the table owner) via the
-- `pgsql_admin` connection.

CREATE ROLE family_app
    LOGIN
    PASSWORD 'family_app'
    NOSUPERUSER
    NOCREATEDB
    NOCREATEROLE
    NOBYPASSRLS;

GRANT CONNECT ON DATABASE family_essentials TO family_app;
GRANT CONNECT ON DATABASE family_essentials_test TO family_app;

GRANT USAGE ON SCHEMA public TO family_app;

-- Existing objects (owner: postgres).
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO family_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO family_app;

-- Future objects created by postgres in the public schema.
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO family_app;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO family_app;
