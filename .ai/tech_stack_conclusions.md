# Wnioski dotyczące stosu technologicznego

## 1. Szybkie dostarczenie MVP
- Dobór PHP 8.2 z Twig/Tailwind/Alpine ułatwia budowę panelu administracyjnego i wspiera wymagania przepływów opisanych w PRD (rejestracja, dashboard, import). Jednak równoległa integracja wielu usług (nginx, Docker, osobny ETL w Pythonie, MariaDB, ClickHouse) od początku MVP zwiększa czas konfiguracji i koordynacji pracy.

## 2. Skalowalność
- ClickHouse dobrze odpowiada na potrzebę analizy dużych zbiorów losowań, a Docker + GitHub Actions umożliwiają standaryzowane wdrożenia. Wielowątkowa usługa ETL w Pythonie uruchomiona w dedykowanym kontenerze (z healthcheckiem i zależnością od ClickHouse) jest potrzebna, by utrzymać przepustowość importu i raportowania — kluczowe, by dopracować kolejkę żądań oraz monitoring procesu.

## 3. Koszt utrzymania
- Dwie bazy (MariaDB dla kont i logów, ClickHouse dla ~250 mln rekordów losowań) są uzasadnione wymaganiami PRD, ale resztę ekosystemu warto odchudzić: Mailpit i phpMyAdmin tylko w dev, na produkcji wyłączenie zbędnych kontenerów i dopracowanie automatyzacji zmniejszy koszt obsługi przy jednym administratorze.

## 4. Konieczność złożoności
- Dwie bazy i dedykowany wielowątkowy ETL w Pythonie pozostają potrzebne (wysoki wolumen danych, zapytania analityczne). Warto natomiast ograniczyć liczbę usług towarzyszących w środowisku MVP (np. uprościć konfigurację nginx, utrzymać jedynie kontenery konieczne do importu i panelu), aby skrócić iteracje nad FR-08–FR-17.

## 5. Alternatywy
- Alternatywy dotyczą głównie uproszczenia orkiestracji otoczenia: zamiast rozbudowanego Docker Compose na produkcji można zostawić tylko kontenery krytyczne (PHP, ClickHouse, ETL) oraz zarządzać nimi np. przez menedżera procesów lub lekką orkiestrację. Kluczowe pozostaje utrzymanie wielowątkowego ETL w Pythonie oraz stabilnego połączenia z ClickHouse, ponieważ zamiana na procesy PHP obniżyłaby przepustowość.

## 6. Bezpieczeństwo
- Sam stack nie adresuje jeszcze wymogów bezpieczeństwa wymienionych w PRD (potwierdzanie kont, 401 przy braku sesji, logowanie zdarzeń bezpieczeństwa). Należy uzupełnić o politykę przechowywania sekretów, terminowanie TLS, hardening phpMyAdmin oraz kontrolę dostępu do usług kontenerowych, inaczej osiągnięcie poziomu bezpieczeństwa będzie problematyczne.
