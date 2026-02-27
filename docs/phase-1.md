# Phase 1 — Simple Todo CRUD

> **Goal:** get familiar with Symfony by building a basic REST API from scratch.
> No complex architecture, no business logic — just learn how the framework works.

This was the starting point. The idea was simple: spin up Symfony, connect a database, expose a CRUD API for tasks, write some tests, and figure out the tooling.

---

## What was built

### Infrastructure
- [x] Docker setup (PHP 8.4-fpm, Nginx, PostgreSQL 17)
- [x] Symfony 8.0 skeleton
- [x] Doctrine ORM + Migrations

### Core
- [x] `Task` entity — `id`, `title`, `description`, `status`, `createdAt`, `updatedAt`
- [x] `TaskStatus` enum — `TODO`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`
- [x] `TaskController` — full CRUD at `/api/v1/tasks` (GET list, POST, GET one, PUT, DELETE)
- [x] DTO-based validation — `CreateTaskDTO`, `UpdateTaskDTO`
- [x] `TaskResource` — response transformer (no raw entity in API response)
- [x] `CreateTask` service

### Quality & Tooling
- [x] Integration tests — Zenstruck Foundry + DAMA DoctrineTestBundle
- [x] OpenAPI docs — NelmioApiDocBundle
- [x] PHP-CS-Fixer + PHPStan level 6
- [x] GitHub Actions CI (lint + tests with PostgreSQL service)
- [x] Dependabot weekly dependency updates
- [x] README with CI/PHP/Symfony/PHPStan badges

---

## Outcome

After completing this phase, the basics of Symfony were clear:
routing, controllers, Doctrine entities, service layer, DTOs, validation, testing infrastructure.

The CRUD worked, the tests passed, CI was green.

Then came the question: what's next? Just add more CRUD endpoints?

→ Decided to go deeper. See [roadmap.md](./roadmap.md).