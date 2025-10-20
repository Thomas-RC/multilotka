# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Przegląd projektu

Multilotka Analytics to platforma analityczna Multi Multi Lotto zbudowana na PHP 8.2 MVC z dedykowanym serwisem ETL w Pythonie. System akceptuje manualne przesyłanie plików dziennych losowań (`ml.txt`), przetwarza je przez asynchroniczny pipeline ETL, przechowuje wyniki w MariaDB (transakcje) i ClickHouse (analityka), oraz udostępnia dashboard do monitorowania importów i generowania rekomendacji zakładów.

**MVP obejmuje**: bezpieczne logowanie administratora, upload plików z walidacją, import pełny/przyrostowy z monitoringiem postępu, generator dziesięciu rekomendacji (tipów) z czynnikami confidence oraz eksport kombinacji do pliku.

## Architektura

### Komponenty kluczowe

**Aplikacja PHP** (`app/src/`)
- **Core/Application.php**: Prosty router mapujący ścieżki na kontrolery; instancjonuje kontrolery z zależnościami Twig, Connection, SessionManager
- **Controller/**: Obsługuje żądania HTTP, renderuje szablony Twig lub zwraca RedirectResponse
- **Import/**: Logika domenowa dla walidacji uploadów, przechowywania plików, orkiestracji zadań importu
  - `UploadService`: Waliduje i przechowuje przesłane pliki w `storage/uploads/`, archiwizuje poprzednie wersje
  - `ImportCoordinator`: Tworzy zadania importu i początkowe zdarzenia w MariaDB, które worker ETL w Pythonie pobiera
  - `ImportJobRepository` i `ImportJobEventRepository`: Zarządzają stanem zadań i historią zdarzeń
  - `ImportFormatter`: Formatuje dane dla frontendowego dashboardu
- **Support/**: Autentykacja użytkowników, wysyłka e-maili przez Mailpit

**Serwis ETL Python** (`python/run_etl.py`)
- Long-running worker odpytujący tabelę `import_jobs` co 2 sekundy w poszukiwaniu wpisów ze `status='queued'`
- Parsuje pliki `ml.txt` (format: `1. DD.MM.RRRR n1,n2,...,n20`), waliduje 20 unikalnych liczb w zakresie 1-80
- Wstawia losowania do `analytics.draws` i generuje wszystkie kombinacje 5-elementowe do `analytics.draw_combinations`
- Obsługuje dwa tryby:
  - **full**: Reimportuje cały plik, po sukcesie oznacza wszystkie poprzednie `import_runs` jako `failed`
  - **incremental**: Pomija losowania z `draw_number <= max(istniejące losowania)`
- Aktualizuje postęp zadania i zdarzenia w MariaDB; loguje metadane w ClickHouse `analytics.import_runs`

**Architektura bazy danych**
- **MariaDB** (`mlt-db`): Konta użytkowników, metadane przesłanych plików, zadania importu, zdarzenia zadań
- **ClickHouse** (`mlt-dbh`): Tabele analityczne partycjonowane po `toYYYYMM(draw_date)`:
  - `analytics.draws`: Surowe dane losowań z tablicą liczb
  - `analytics.draw_combinations`: SummingMergeTree dla agregacji częstości kombinacji
  - `analytics.combo_aggregates`: AggregatingMergeTree z widokiem materializowanym do szybkich zapytań top-N
  - `analytics.import_runs`: Metadane ETL; tylko wpisy `status='succeeded'` są widoczne dla roli analytics_reader
  - Polityki wierszy zapewniają, że zapytania widzą dane tylko z udanych importów

### Przepływ danych

1. Użytkownik przesyła `ml.txt` przez `/dashboard/upload` (UploadController)
2. `UploadService` waliduje format, zapisuje plik, archiwizuje poprzedni upload
3. Użytkownik inicjuje import przez `/dashboard/import` (ImportController)
4. `ImportCoordinator` tworzy zadanie ze `status='queued'` i JSON `etl_request_payload`
5. Worker ETL pobiera zadanie, zmienia status na `running`, przetwarza plik w partiach
6. Worker wstawia losowania (rozmiar partii 64) i kombinacje (rozmiar partii 5000) do ClickHouse
7. Status zadania przechodzi do `succeeded` lub `failed`; zdarzenia są logowane dla pollingu frontendu
8. Dashboard odpytuje `/dashboard/import/status` w celu wyświetlenia procentu postępu i etapu

## Polecenia deweloperskie

Wszystkie polecenia muszą być uruchamiane wewnątrz kontenerów Docker:

```bash
# Instalacja/aktualizacja zależności PHP
docker exec mlt-php composer install

# Uruchomienie testów z czytelnym outputem
docker exec mlt-php composer test
# lub jawnie
docker exec mlt-php ./vendor/bin/phpunit --testdox

# Regeneracja autoloadera po dodaniu klas
docker exec mlt-php composer dump-autoload

# Uruchomienie migracji bazy danych
docker exec mlt-php ./vendor/bin/doctrine-migrations migrations:migrate --no-interaction

# Podgląd logów workera ETL
docker logs -f mlt-etl

# Restart ETL po zmianie python/requirements.txt
docker compose -f docker/docker-compose.yml restart mlt-etl
```

## Konfiguracja środowiska lokalnego

```bash
# Uruchomienie wszystkich serwisów
docker compose -f docker/docker-compose.yml up -d

# Serwisy i porty:
# - Aplikacja PHP (Nginx): http://localhost:8081
# - Mailpit web UI: http://localhost:8025
# - phpMyAdmin: http://localhost:8082
# - ClickHouse HTTP: http://localhost:8123
# - MariaDB: localhost:3306
```

## Wytyczne testowe

- Pliki testów odzwierciedlają strukturę `src/`: `tests/Import/UploadServiceTest.php` testuje `src/Import/UploadService.php`
- Stosuj opisowe nazwy metod: `testHandleStoresFileAndMetadata`, `testHandleArchivesPreviousFile`
- Cel: minimum 80% pokrycia; wyjaśnij pominięcia w pull requestach
- Dla algorytmów statystycznych wykorzystuj fixtures w `tests/fixtures/` i testuj przypadki brzegowe (niepełne losowania, wartości graniczne)
- Zawsze pisz testy natychmiast po implementacji nowej funkcjonalności

## Konwencje kodu

- **PSR-12**: wcięcia 4-spacjowe; przed PR uruchom `vendor/bin/phpcs --standard=PSR12 src tests`
- **Przestrzenie nazw**: Odzwierciedlają strukturę katalogów pod `Multilotka\*`; np. `Multilotka\Import\UploadService` znajduje się w `src/Import/UploadService.php`
- **Nazewnictwo**: PascalCase dla klas, camelCase dla metod/zmiennych, snake_case dla kluczy konfiguracji zgodnych z terminologią lotto (`draw_date`, `ball_frequencies`)
- Jeden cel na plik; preferuj obiekty wartości zamiast luźnych tablic
- **Conventional Commits**: Prefiksy `feat:`, `fix:`, `chore:`

## Format pliku importu

System oczekuje plików `.txt` o ścisłym formatowaniu:
```
1. DD.MM.RRRR n1,n2,...,n20
2. DD.MM.RRRR n1,n2,...,n20
```

Wymagania:
- Dokładnie 20 liczb całkowitych rozdzielonych przecinkami na losowanie
- Liczby muszą być unikalne i w zakresie 1-80
- Puste linie są pomijane
- Pliki są walidowane przez `UploadedFileValidator` przed zapisem

## Kontrola dostępu ClickHouse

- Access management włączony (`CLICKHOUSE_DEFAULT_ACCESS_MANAGEMENT=1`)
- Użytkownik `multilotka` tworzony w `docker/dbh/init/00_access_setup.sql` z `DEFAULT ROLE ALL`
- Role:
  - `etl_writer`: INSERT i SELECT na `draws`, `draw_combinations`, `import_runs`; ALTER UPDATE na `import_runs`
  - `analytics_reader`: SELECT na wszystkich tabelach w schemacie `analytics`
- Polityki wierszy filtrują zapytania tak, by zawierały tylko dane z `import_runs.status='succeeded'`
- Poświadczenia w `.env` muszą odpowiadać skryptom inicjalizacyjnym SQL
- **Ważne**: Użytkownik ETL wymaga `DEFAULT ROLE ALL` lub jawnego `SET ROLE` w połączeniu, by aktywować przyznane role

## Wymagania z PRD/MVP

**Kluczowe wymagania funkcjonalne (FR)**:
- FR-02: Rejestracja z walidacją hasła (≥8 znaków), wysyłka e-maila potwierdzającego
- FR-04: Logowanie weryfikuje dane, regeneruje sesję, zapisuje identyfikator użytkownika
- FR-08: Upload `ml.txt` z walidacją rozszerzenia, MIME i struktury linii; usuwa poprzedni plik
- FR-10: Uruchomienie ETL z wyborem trybu (pełny/przyrostowy); domyślnie ostatnio użyty tryb
- FR-12: Dashboard odświeża status importu (procent, etap, komunikaty) bez przeładowania strony
- FR-15: Każde kluczowe zdarzenie (rejestracja, logowanie, upload, import, generowanie tipów, eksport) logowane w MySQL
- FR-16: Generator tipów produkuje 10 unikalnych kombinacji 5-liczbowych z confidence ≥89, uwzględniając czynniki: due, frequency, gap
- FR-18: API zwraca 401 dla żądań administracyjnych bez ważnej sesji

**Kryteria sukcesu (MT)**:
- MT-01: ≥80% wgranych plików kończy się udanym importem
- MT-02: Min. 10 aktywowanych kont w pierwszym miesiącu
- MT-03: W 70% sesji generowany jest co najmniej jeden zestaw tipów lub eksport
- MT-04: Wszystkie importy kończą się w ciągu 24h od publikacji pliku źródłowego

## Decyzje projektowe

1. Tylko jedno konto administracyjne; brak dodatkowych ról
2. E-maile w środowisku testowym przez kontener Mailpit
3. Przechowywanie wyłącznie ostatniego pliku `ml.txt`; każdy nowy upload usuwa poprzedni
4. Proces ETL w Pythonie jako osobny kontener Docker Compose z wielowątkowym przetwarzaniem
5. Automatyczne pobieranie pliku przez CRON poza zakresem MVP
6. Dane wejściowe: ~16 000 losowań, import raz dziennie
7. Tipy generowane na podstawie całej bazy; pełny import czyści poprzednie dane
8. Logi systemowe w MySQL
9. Brak 2FA ani whitelisty IP poza podstawowym logowaniem
10. Alerty ETL jako banery w panelu
11. Brak stagingu ani backupu na etapie MVP

## Wspólne wzorce

**Dodawanie nowego kontrolera**:
1. Utwórz klasę w `app/src/Controller/` zgodnie z wzorcem podstawowym
2. Dodaj mapowanie trasy w tablicy `$routes` w `Application.php`
3. Konstruktor otrzymuje `Environment $twig`, `Connection $connection`, `SessionManager $session`
4. Metody zwracają `string` (renderowany HTML) lub `RedirectResponse`

**Dodawanie nowego etapu importu**:
1. Zaktualizuj enum `ImportJobEventType` o nowy typ zdarzenia
2. Emituj zdarzenie przez `ImportJobEventRepository::record()` w PHP lub `insert_event()` w Pythonie
3. Zaktualizuj logikę pollingu frontendu w szablonie dashboardu, by obsługiwał nowy etap

**Zmiany schematu bazy danych**:
- MariaDB: Wygeneruj migrację Doctrine, zastosuj przez `docker exec mlt-php ./vendor/bin/doctrine-migrations migrations:migrate`
- ClickHouse: Modyfikuj `docker/dbh/init/01_create_schema.sql`; wymaga odtworzenia kontenera, by uruchomić skrypty init

## Ważne uwagi

- **Język**: Wszystkie komunikaty commitów, opisy PR i komunikaty użytkownika w języku polskim
- **UI Framework**: Szablony Twig + Tailwind CSS + Alpine.js (brak standalone Blade/React/Vue)
- **Zarządzanie sesją**: Obsługiwane przez `SessionManager` w `Core/`; kontrolery otrzymują instancję sesji z Application
- **Archiwizacja plików**: Tylko jeden aktywny plik upload; poprzednia wersja oznaczana jako `archived` i usuwana z dysku
- **Brak anonimizacji danych**: Nie commituj danych osobowych graczy do `datasets/`; dokumentuj źródła danych w README przy dodawaniu nowych zestawów
- **Zmienne środowiskowe**: Nigdy nie commituj `.env`; dokumentuj wymagane zmienne w README.md
- **Preferencje językowe**: Komunikaty i informacje w języku polskim
