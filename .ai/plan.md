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
  ml.txt ze stałym formatem `n. DD.MM.RRRR l1,l2,...,l20` (poprzedni plik usuwany), pełny/przyrostowy import uruchamiany z panelu przy pomocy kontenera ETL w Pythonie, monitorowanie importu (status, procent, logi)
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
