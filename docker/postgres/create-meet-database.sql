SELECT 'CREATE ROLE meet LOGIN PASSWORD ''secret''' WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'meet')\gexec
SELECT 'CREATE DATABASE meet OWNER meet' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'meet')\gexec
