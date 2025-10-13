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