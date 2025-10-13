Jesteś doświadczonym menedżerem produktu, którego zadaniem jest stworzenie kompleksowego dokumentu wymagań produktu (PRD) w oparciu o poniższe opisy:

<project_description>
• # Aplikacja - Multilotka Analytics (MVP)

  ## Główny problem

  Administratorzy Multi Multi nie mają jednego narzędzia, które bezpiecznie importuje pliki z losowaniami, weryfikuje ich poprawność i udostępnia podstawowe statystyki kombinacji oraz
  rekomendacje zakładów oparte na danych historycznych.

  ## Najmniejszy zestaw funkcjonalności

  - Strona główna z opisem wartości oraz CTA do rejestracji i logowania.
  - Rejestracja konta z potwierdzeniem e-mail, logowanie, reset hasła.
  - Dashboard administracyjny z informacją o ostatnim imporcie i stanie danych.
  - Wgranie pliku ml.txt (ręcznie lub z URL) z walidacją struktury i zapisem w storage.
  - Uruchomienie procesu ETL/ingest na wskazanym pliku z wyborem pełnego importu.
  - Podstawowy ranking najczęstszych kombinacji z filtrem dat i paginacją.
  - Generator dziesięciu rekomendacji (Tip na dziś) z confidencem i opisem czynników.
  - Eksport wygenerowanych kombinacji do pliku do dalszego użytku.

  ## Co NIE wchodzi w zakres MVP

  - Automatyczne pobieranie pliku o zadanej godzinie (cron).
  - Zaawansowane wizualizacje (np. wykresy miesięczne, zaległości cyfr).
  - Konfigurowalność algorytmu tipów i tryby probabilistyczne/deterministyczne.
  - Backtesting strategii na historycznych danych.
  - Wsparcie wielu ról użytkowników i rozbudowane uprawnienia.
  - Integracje z zewnętrznymi systemami lub płatnościami.

  ## Kryteria sukcesu

  - Co najmniej 80% wgranych plików przechodzi walidację i uruchamia pełny import bez błędu.
  - Minimum 10 unikalnych użytkowników rejestruje i aktywuje konto w pierwszym miesiącu po wdrożeniu MVP.
  - Każda sesja w dashboardzie kończy się wygenerowaniem co najmniej jednej rekomendacji w 70% przypadków.
  - Pierwszy raport miesięczny potwierdza, że wszystkie importy zostały zakończone w ciągu 24 godzin od publikacji pliku źródłowego.
</project_description>

<project_details>
 <conversation_summary>
  <decisions>

  1. Utrzymujemy tylko jedno konto administracyjne (administrator = użytkownik, brak dodatkowych ról).
  2. Do obsługi e-maili w środowisku testowym użyjemy kontenera Mailpit.
  3. Przechowujemy wyłącznie ostatni plik ml.txt; każdy nowy upload usuwa poprzedni.
  4. Proces ETL powstanie w Pythonie jako osobny kontener Docker Compose z wielowątkowym przetwarzaniem.
  5. Automatyczne pobieranie pliku przez CRON zostaje przesunięte poza MVP.
  6. Dane wejściowe obejmują około 16 000 losowań, import wykonywany będzie raz dziennie.
  7. Tipy generujemy na podstawie całej zawartości bazy; pełny import czyści poprzednie dane.
  8. Logi systemowe będziemy zapisywać w bazie MySQL.
  9. Nie wprowadzamy dodatkowych zabezpieczeń (2FA, whitelisty IP) ponad podstawowe logowanie.
  10. Alerty ETL ograniczamy do komunikatów banerowych w panelu.
  11. Nie planujemy stagingu ani backupu danych na tym etapie.
     </decisions>

  <matched_recommendations>

  1. Zaprojektować schema i klucz sortujący dedykowanej tabeli kombinacji w ClickHouse przed implementacją migracji.
  2. Spisać specyfikację API do komunikacji panel ↔ kontener ETL (trigger, status, formaty błędów) jeszcze przed kodowaniem.
  3. Wybrać i udokumentować proces migracji ClickHouse (format plików SQL, naming, procedura rollout/rollback).
  4. Zdefiniować strukturę tabeli logów w MySQL oraz politykę retencji, aby móc raportować metryki sukcesu.
  5. Zarejestrować metadane po imporcie (liczba losowań, kombinacji, czas trwania) i udostępniać je w dashboardzie, by ręcznie weryfikować kompletność.
     </matched_recommendations>

  <prd_planning_summary>
  Aplikacja Multilotka Analytics (MVP) rozwiązuje problem braku zintegrowanego narzędzia do bezpiecznego importu danych losowań, generowania kombinacji i udostępniania rekomendacji dla
  administratora Multi Multi. Kluczowe funkcjonalności obejmują: landing page z CTA, rejestrację z e-mailową aktywacją i resetem hasła, logowanie/wylogowanie, ręczne wgrywanie pliku
  ml.txt ze stałym formatem (poprzedni plik usuwany), pełny/przyrostowy import uruchamiany z panelu przy pomocy kontenera ETL w Pythonie, monitorowanie importu (status, procent, logi)
  w dashboardzie, ranking kombinacji, generator dziesięciu tipów oparty na całej bazie oraz eksport kombinacji. Logowanie zdarzeń trafi do bazy MySQL; komunikaty o błędach ETL będą
  prezentowane jako banery.

  Najważniejsza ścieżka użytkownika: administrator odwiedza stronę publiczną, rejestruje konto i potwierdza je e-mailem, loguje się, wgrywa najnowszy plik ml.txt (zastępując poprzedni),
  uruchamia import (otrzymuje postęp i ewentualne alerty), po zakończeniu generuje tipy oraz eksportuje kombinacje. Alternatywnie administrator może zrezygnować z importu i skupić się
  na analizie rankingów. Tymczasowo brak innych person czy ról.

  Kryteria sukcesu (zdefiniowane wcześniej) pozostają aktualne: 80% wgrywanych plików kończy się udanym importem, min. 10 aktywowanych kont w pierwszym miesiącu, 70% sesji kończy się
  wygenerowaniem tipów, a wszystkie importy w raporcie miesięcznym kończą się w ciągu 24h. Do pomiaru wskaźników wykorzystamy logi w MySQL (rejestrowanie importów, generacji tipów,
  eksportów) oraz metadane w dashboardzie.

  </prd_planning_summary>

  <unresolved_issues>

  - Brak decyzji co do sposobu tłumaczenia użytkownikowi metryk generatora (confidence, avg score); wymagana dalsza praca nad messagingiem/tooltipami.
  - Brak zdefiniowanego schematu tabeli ClickHouse, struktury migracji oraz kontraktu API między panelem a kontenerem ETL – konieczne doprecyzowanie przed implementacją.
  - Brak polityki monitoringu, testów wydajności oraz procedur odzyskiwania danych; temat backupu i środowisk testowych wymaga ponownego rozważenia przy dalszym planowaniu.
    </unresolved_issues>
    </conversation_summary>
</project_details>

Wykonaj następujące kroki, aby stworzyć kompleksowy i dobrze zorganizowany dokument:

1. Podziel PRD na następujące sekcje:
   a. Przegląd projektu
   b. Problem użytkownika
   c. Wymagania funkcjonalne
   d. Granice projektu
   e. Historie użytkownika
   f. Metryki sukcesu

2. W każdej sekcji należy podać szczegółowe i istotne informacje w oparciu o opis projektu i odpowiedzi na pytania wyjaśniające. Upewnij się, że:
   - Używasz jasnego i zwięzłego języka
   - W razie potrzeby podajesz konkretne szczegóły i dane
   - Zachowujesz spójność w całym dokumencie
   - Odnosisz się do wszystkich punktów wymienionych w każdej sekcji

3. Podczas tworzenia historyjek użytkownika i kryteriów akceptacji
   - Wymień WSZYSTKIE niezbędne historyjki użytkownika, w tym scenariusze podstawowe, alternatywne i skrajne.
   - Przypisz unikalny identyfikator wymagań (np. US-001) do każdej historyjki użytkownika w celu bezpośredniej identyfikowalności.
   - Uwzględnij co najmniej jedną historię użytkownika specjalnie dla bezpiecznego dostępu lub uwierzytelniania, jeśli aplikacja wymaga identyfikacji użytkownika lub ograniczeń dostępu.
   - Upewnij się, że żadna potencjalna interakcja użytkownika nie została pominięta.
   - Upewnij się, że każda historia użytkownika jest testowalna.

Użyj następującej struktury dla każdej historii użytkownika:
- ID
- Tytuł
- Opis
- Kryteria akceptacji

4. Po ukończeniu PRD przejrzyj go pod kątem tej listy kontrolnej:
   - Czy każdą historię użytkownika można przetestować?
   - Czy kryteria akceptacji są jasne i konkretne?
   - Czy mamy wystarczająco dużo historyjek użytkownika, aby zbudować w pełni funkcjonalną aplikację?
   - Czy uwzględniliśmy wymagania dotyczące uwierzytelniania i autoryzacji (jeśli dotyczy)?

5. Formatowanie PRD:
   - Zachowaj spójne formatowanie i numerację.
   - Nie używaj pogrubionego formatowania w markdown ( ** ).
   - Wymień WSZYSTKIE historyjki użytkownika.
   - Sformatuj PRD w poprawnym markdown.

Przygotuj PRD z następującą strukturą:

```markdown
# Dokument wymagań produktu (PRD) - {{app-name}}
## 1. Przegląd produktu
## 2. Problem użytkownika
## 3. Wymagania funkcjonalne
## 4. Granice produktu
## 5. Historyjki użytkowników
## 6. Metryki sukcesu
```

Pamiętaj, aby wypełnić każdą sekcję szczegółowymi, istotnymi informacjami w oparciu o opis projektu i nasze pytania wyjaśniające. Upewnij się, że PRD jest wyczerpujący, jasny i zawiera wszystkie istotne informacje potrzebne do dalszej pracy nad produktem.

Ostateczny wynik powinien składać się wyłącznie z PRD zgodnego ze wskazanym formatem w markdown, który zapiszesz w pliku .ai/prd.md