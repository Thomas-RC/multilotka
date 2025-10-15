# Multilotka Analytics

## Table of Contents
- [1. Project Name](#1-project-name)
- [2. Project Description](#2-project-description)
- [3. Tech Stack](#3-tech-stack)
- [4. Getting Started Locally](#4-getting-started-locally)
- [5. Available Scripts](#5-available-scripts)
- [6. Project Scope](#6-project-scope)
- [7. Project Status](#7-project-status)
- [8. License](#8-license)

## 1. Project Name
Multilotka Analytics

## 2. Project Description
Multilotka Analytics is an administrative platform that prepares Multi Multi lottery data for deeper exploration. It streamlines secure account management, manual uploads of `ml.txt` draw files, and orchestrates end-to-end ETL jobs that cleanse and load both relational and analytical stores. The dashboard monitors import progress, shares audit metadata, and exposes recommendation tools that explain why suggested combinations matter.

## 3. Tech Stack
- PHP 8.2 MVC application with Composer-driven autoloading and Twig templates for presentation.
- Tailwind CSS and Alpine.js deliver responsive styling with lightweight interactivity in the admin UI.
- MariaDB persists transactional entities and operational logs; ClickHouse serves analytical workloads.
- Python-based ETL service ingests draw files and feeds the analytical warehouse via Docker Compose.
- Nginx fronts the PHP runtime, while Mailpit, cron jobs, and phpMyAdmin round out the supporting services.

## 4. Getting Started Locally
1. Install Docker and Docker Compose.
2. Copy environment defaults: `cp .env.example .env`.
3. Launch the stack: `docker compose -f docker/docker-compose.yml up -d`.
4. Install PHP dependencies inside the container: `docker exec mlt-php composer install`.
5. Apply database schema updates: `docker exec mlt-php ./vendor/bin/doctrine-migrations migrations:migrate --no-interaction`.
6. Run the web app via Nginx on http://localhost:8081 and load Mailpit at http://localhost:8025 when testing email flows.

### ClickHouse Access Control
- ClickHouse startuje z włączonym Access Control (`CLICKHOUSE_DEFAULT_ACCESS_MANAGEMENT=1`) i wykonuje plik `docker/dbh/init/00_access_setup.sql`, który tworzy użytkownika `multilotka` w katalogu Access Control.
- Hasło użytkownika i nazwa (domyślnie `multilotka`/`multilotka`) muszą odpowiadać wpisom w `.env`, z których korzysta aplikacja. Przy zmianie hasła zaktualizuj zarówno plik SQL, jak i zmienne środowiskowe.
- Skrypt `docker/dbh/init/01_create_schema.sql` nadaje role `etl_writer`, `analytics_reader` oraz polityki wierszy; dlatego zachowuj kolejność plików inicjalizacyjnych.

### Import plików ml.txt
- Formularz w panelu akceptuje wyłącznie pliki tekstowe w formacie:
  ```
  1. DD.MM.RRRR n1,n2,...,n20
  2. DD.MM.RRRR n1,n2,...,n20
  ...
  ```
  Liczby muszą być unikalne i mieścić się w zakresie 1–80.
- Po wgraniu plik trafia do `storage/uploads`, a poprzednia wersja jest automatycznie archiwizowana i usuwana z dysku.
- Jeżeli plik nie przejdzie walidacji (np. niewłaściwa liczba wartości, puste linie), panel pokaże komunikat błędu i nic nie zostanie zapisane.

## 5. Available Scripts
- `docker exec mlt-php composer test` — execute the full PHPUnit suite with human-readable output.
- `docker exec mlt-php ./vendor/bin/doctrine-migrations migrations:migrate` — synchronize database structures with the latest migrations.
- `docker exec mlt-php php scripts/analyse.php datasets/sample.csv` — trigger manual statistical analysis against a selected dataset.

## 6. Project Scope
- Secure administrator onboarding (registration, confirmation, authentication, password resets, and session lifecycle).
- Manual upload validation for daily draw files with storage in `storage/uploads` and session tracking.
- Orchestrated ETL execution with progress polling, error banners, and metadata logging for each import run.
- Tip generator producing ten five-number combinations with confidence scoring and factor breakdown.
- Export pipeline that saves the latest recommended combinations for download and logs every critical event.

## 7. Project Status
The MVP is in active development: containerized services are wired together, the admin workflow covers end-to-end data import, and ETL hooks are ready for iterative refinement. Upcoming iterations focus on broadening automated reporting and hardening security policies before wider rollout.

## 8. License
Released under the [MIT License](LICENSE).
