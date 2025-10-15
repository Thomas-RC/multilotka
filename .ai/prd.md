# Dokument wymagań produktu (PRD) - Multilotka Analytics
## 1. Przegląd produktu
Multilotka Analytics to aplikacja webowa wspierająca pojedynczego administratora Multi Multi w przygotowaniu danych do analiz i generowaniu rekomendacji zakładów na podstawie historii losowań. Produkt składa się z warstwy HTTP (PHP), bazy ClickHouse przechowującej dane losowań i kombinacji oraz wielowątkowego procesu ETL w Pythonie uruchamianego jako osobny kontener Docker Compose. Wersja MVP działa w środowisku testowym z kontenerem Mailpit do obsługi e-maili transakcyjnych, zapisuje logi zdarzeń w MySQL i zakłada ręczne importowanie dziennych plików ml.txt. Panel administracyjny udostępnia pełny przepływ: od rejestracji i logowania, przez upload pliku, wyzwolenie importu, monitorowanie statusu, po generowanie tipów i eksport gotowych kombinacji.

## 2. Problem użytkownika
- Administrator nie dysponuje pojedynczym, bezpiecznym narzędziem do walidacji i ładowania plików z losowaniami Multi Multi.
- Aktualizacja danych wymaga manualnych, podatnych na błędy czynności oraz wielu rozproszonych narzędzi.
- Brakuje funkcjonalności do bieżącej oceny stanu importu i szybkiego generowania rekomendacji liczbowych z jasnym opisem czynników.
- Obecnie administrator nie ma prostego sposobu eksportu kombinacji w jednolitym formacie ani struktury do gromadzenia logów operacyjnych.

## 3. Wymagania funkcjonalne
- FR-01 Strona publiczna prezentuje podsumowanie wartości produktu, CTA do rejestracji i logowania oraz działa bez konieczności uwierzytelnienia.
- FR-02 Formularz rejestracji przyjmuje e-mail i hasło (≥8 znaków), tworzy konto oraz informuje o konieczności potwierdzenia adresu.
- FR-03 System obsługuje potwierdzenie konta za pomocą tokenu e-mailowego (wysyłanego przez Mailpit w środowisku testowym); błędny lub wygasły token zwraca komunikat o błędzie.
- FR-04 Logowanie weryfikuje dane, regeneruje sesję, zapisuje identyfikator użytkownika i przekierowuje na dashboard; błędne dane generują komunikat oraz zachowują wpisany e-mail.
- FR-05 Mechanizm resetu hasła umożliwia wysłanie linku z tokenem i ustawienie nowego hasła; token jest jednorazowy i kontroluje czas ważności.
- FR-06 Wylogowanie usuwa sesję, czyści tymczasowe dane (np. ścieżkę pliku) i przekierowuje na stronę logowania z informacją o sukcesie.
- FR-07 Dashboard po zalogowaniu pokazuje ostatni wgrany plik, datę ostatniego udanego importu, liczbę kombinacji oraz powiadamia, jeśli plik zawiera nowsze losowania niż baza.
- FR-08 Administrator może ręcznie wgrać plik ml.txt (stały format wiersza: `n. DD.MM.RRRR l1,l2,...,l20`, gdzie każda liczba mieści się w zakresie 1–80 i nie powtarza się); system weryfikuje rozszerzenie, MIME i strukturę każdej linii, usuwa poprzedni plik i zapisuje nowy w storage/uploads.
- FR-09 Panel przechowuje ścieżkę do ostatniego pliku w sesji i umożliwia podgląd lokalizacji oraz daty pliku.
- FR-10 Użytkownik może uruchomić proces ETL poprzez wskazanie trybu importu (pełny lub przyrostowy); domyślnie wybierany jest ostatni użyty tryb.
- FR-11 Aplikacja wywołuje endpoint kontenera ETL, przekazując ścieżkę pliku, tryb importu i oczekuje odpowiedzi JSON zawierającej potwierdzenie lub błąd; w razie braku pliku zwracany jest komunikat o błędzie.
- FR-12 Dashboard odświeża status importu, prezentując procent zaawansowania, aktualny etap i komunikaty zwracane przez ETL; postęp jest aktualizowany w interfejsie bez przeładowania strony.
- FR-13 Wszelkie błędy ETL (błędny format, przerwane połączenie) są prezentowane w postaci banera z treścią błędu; baner znika po kolejnej próbie lub ręcznym odrzuceniu.
- FR-14 Po zakończeniu importu system czyści poprzednie dane w trybie pełnym, rejestruje metadane importu (liczbę losowań, kombinacji, czas trwania) i udostępnia je na dashboardzie.
- FR-15 Każde kluczowe zdarzenie (rejestracja, logowanie, upload pliku, start importu, zakończenie importu, generowanie tipów, eksport) jest zapisywane w tabeli logów MySQL wraz z sygnaturą czasu i parametrami zdarzenia.
- FR-16 Moduł Tip na dziś generuje dziesięć unikalnych kombinacji z pięciu liczb na podstawie pełnego zbioru danych, zapewniając minimalny poziom confidence (≥89), średni wynik i listę czynników (due, frequency, gap).
- FR-17 Administrator może wygenerować plik eksportu z aktualnymi kombinacjami; plik zapisuje się w storage/uploads z datą generacji i jest dostępny do pobrania przez bezpieczny link.
- FR-18 API panelu zwraca kod 401 dla każdego żądania administracyjnego bez ważnej sesji i przekierowuje na logowanie przy próbie wejścia w interfejs.
- FR-19 Specyfikacja endpointów panel ↔ ETL (trigger, status) jest zdefiniowana i utrzymywana w repozytorium jako część dokumentacji, aby umożliwić niezależny rozwój komponentów.
- FR-20 Proces migracji schematu ClickHouse (w tym definicja dedykowanej tabeli kombinacji z kluczem sortującym) jest obsługiwany przez wersjonowane skrypty w repozytorium i uruchamiany z poziomu deploymentu.

## 4. Granice produktu
- MVP obsługuje wyłącznie jeden profil administratora; brak panelu do zarządzania wieloma kontami i ról o różnych uprawnieniach.
- Automatyczne pobieranie pliku (cron), rozbudowane wizualizacje (wykresy miesięczne, zaległości cyfr), konfiguracja algorytmu tipów, backtesting oraz eksperymenty probabilistyczne nie wchodzą w zakres pierwszego wydania.
- System nie realizuje płatności, sprzedaży kuponów ani integracji z zewnętrznymi usługami poza kontenerem ETL i Mailpit.
- Brak dedykowanej warstwy bezpieczeństwa sieciowego (VPN, whitelisty IP) i mechanizmów 2FA; ochrona ogranicza się do standardowego logowania.
- MVP nie obejmuje środowiska staging ani strategii backupu; dane można odtworzyć ponownym importem pliku źródłowego.
- Import danych jest wyzwalany ręcznie przez administratora i zakłada stały format pliku ml.txt (`n. DD.MM.RRRR l1,l2,...,l20`; zmiany formatu wymagają osobnej iteracji).

## 5. Historyjki użytkowników
ID: US-001  
Tytuł: Zapoznanie z produktem  
Opis: Jako administrator chcę odwiedzić stronę publiczną, aby zrozumieć wartość narzędzia i przejść do rejestracji.  
Kryteria akceptacji:
- Strona prezentuje nazwę produktu, opis korzyści i przyciski do rejestracji/logowania.
- Strona nie wymaga logowania i ładuje się w czasie akceptowalnym dla łącza 3G (<2 s).
- Linki CTA prowadzą do odpowiednich formularzy w panelu.

ID: US-002  
Tytuł: Rejestracja konta  
Opis: Jako administrator chcę utworzyć konto, aby korzystać z panelu.  
Kryteria akceptacji:
- Formularz rejestracji wymaga poprawnego formatu e-maila i hasła ≥8 znaków.
- Po sukcesie wyświetlany jest komunikat o wysłaniu maila potwierdzającego.
- Rejestracja istniejącego e-maila zwraca komunikat o błędzie.

ID: US-003  
Tytuł: Potwierdzenie adresu e-mail  
Opis: Jako administrator chcę aktywować konto linkiem z wiadomości, aby móc się zalogować.  
Kryteria akceptacji:
- Kliknięcie ważnego tokena ustawia konto jako potwierdzone i przekierowuje na logowanie z komunikatem o sukcesie.
- Token jednorazowy, po użyciu nie jest akceptowany.
- Token przeterminowany generuje komunikat z instrukcją ponownego żądania.

ID: US-004  
Tytuł: Bezpieczne logowanie  
Opis: Jako administrator chcę się zalogować do panelu, aby korzystać z funkcji.  
Kryteria akceptacji:
- Poprawne dane logowania tworzą sesję, zapisują identyfikator użytkownika i przenoszą na dashboard.
- Błędne dane zwracają komunikat o błędzie i pozostawiają wpisany e-mail.
- Próba logowania nieaktywowanym kontem zwraca instrukcję potwierdzenia e-maila.

ID: US-005  
Tytuł: Reset hasła  
Opis: Jako administrator chcę odzyskać dostęp w przypadku zapomnienia hasła.  
Kryteria akceptacji:
- Formularz resetu wymaga poprawnego formatu e-maila.
- System wysyła (przez Mailpit) link z jednorazowym tokenem resetu.
- Formularz nowego hasła wymaga potwierdzenia i odrzuca niespójne wartości.

ID: US-006  
Tytuł: Wylogowanie  
Opis: Jako administrator chcę zakończyć sesję po pracy w panelu.  
Kryteria akceptacji:
- Akcja wylogowania usuwa dane sesji i przekierowuje na logowanie z komunikatem o sukcesie.
- Po wylogowaniu odświeżenie dashboardu przekierowuje na logowanie.
- Ścieżka ostatniego pliku jest czyszczona w sesji.

ID: US-007  
Tytuł: Podgląd stanu danych  
Opis: Jako administrator chcę widzieć na dashboardzie, czy baza wymaga importu.  
Kryteria akceptacji:
- Dashboard prezentuje nazwę i datę ostatniego pliku, datę ostatniego importu oraz liczbę kombinacji w bazie.
- Jeśli plik zawiera nowsze losowania niż baza, pojawia się baner z liczbą dni wymagających importu.
- Próba wejścia na dashboard bez sesji przekierowuje na logowanie.

ID: US-008  
Tytuł: Ręczny upload pliku  
Opis: Jako administrator chcę wgrać aktualny plik ml.txt, aby przygotować dane do importu.  
Kryteria akceptacji:
- Formularz akceptuje wyłącznie pliki .txt ze stałą strukturą (20 liczb po dacie).
- Niepoprawne pliki są odrzucane, a komunikat informuje o przyczynie (np. niewłaściwy format linii).
- Po sukcesie system usuwa poprzedni plik, zapisuje ścieżkę nowego i pokazuje ją w dashboardzie.

ID: US-009  
Tytuł: Uruchomienie importu  
Opis: Jako administrator chcę wyzwolić proces ETL na podstawie wgranego pliku.  
Kryteria akceptacji:
- Użytkownik wybiera tryb importu (pełny lub przyrostowy); domyślnie ustawiany jest ostatni wybór.
- Wywołanie bez przygotowanego pliku zwraca komunikat o błędzie i nie uruchamia ETL.
- Po zaakceptowaniu formularza panel wyświetla komunikat o starcie i rozpoczyna monitorowanie statusu.

ID: US-010  
Tytuł: Monitorowanie importu  
Opis: Jako administrator chcę śledzić postęp ETL w czasie rzeczywistym.  
Kryteria akceptacji:
- Panel odpyta endpoint statusu i prezentuje procent wykonania, aktualny etap oraz logi.
- Błędy ETL pojawiają się w formie banera z treścią błędu.
- Po ukończeniu importu panel wyświetla metadane (liczba losowań, czas trwania).

ID: US-011  
Tytuł: Zapis logów zdarzeń  
Opis: Jako administrator chcę mieć dostęp do podstawowego dziennika zdarzeń, aby kontrolować operacje.  
Kryteria akceptacji:
- Każde kluczowe zdarzenie zapisuje wpis w MySQL z typem zdarzenia, parametrami i sygnaturą czasu.
- Panel umożliwia podgląd ostatnich zdarzeń (np. w sekcji dashboardu lub osobnej liście).
- Logi są dostępne tylko dla zalogowanego użytkownika; brak sesji zwraca 401.

ID: US-012  
Tytuł: Generowanie tipów  
Opis: Jako administrator chcę otrzymać listę rekomendowanych kombinacji po zakończonym imporcie.  
Kryteria akceptacji:
- Generator zwraca dziesięć unikalnych kombinacji po pięć liczb wraz z confidence ≥89 i opisem czynników.
- Brak danych w bazie lub importu skutkuje komunikatem informującym o konieczności aktualizacji danych.
- Wygenerowanie tipów zapisuje zdarzenie w logach MySQL.

ID: US-013  
Tytuł: Eksport kombinacji  
Opis: Jako administrator chcę pobrać plik z wygenerowanymi kombinacjami, aby wykorzystać je poza systemem.  
Kryteria akceptacji:
- Administrator wskazuje nazwę/zakres (domyślnie bieżące dane), a system generuje plik w uzgodnionym formacie (np. CSV) i zapisuje go w storage/uploads.
- Panel wyświetla link do pobrania wraz z datą, godziną i ilością kombinacji.
- Błąd podczas generowania powoduje komunikat 500 z treścią błędu i wpis w logach.

ID: US-014  
Tytuł: Obsługa nieautoryzowanych żądań  
Opis: Jako system chcę blokować dostęp do endpointów administracyjnych bez sesji.  
Kryteria akceptacji:
- Wszelkie żądania API bez sesji zwracają status 401.
- Próba wejścia w interfejs administracyjny bez logowania przekierowuje na formularz logowania.
- Sytuacje te są logowane w MySQL jako zdarzenia bezpieczeństwa.

## 6. Metryki sukcesu
- MT-01 Co najmniej 80% wgranych plików przechodzi walidację i kończy się udanym pełnym importem (mierzony liczbą zdarzeń „import_succeeded” / „import_started”).
- MT-02 Minimum 10 unikalnych kont zostaje zarejestrowanych i potwierdzonych w pierwszym miesiącu po wdrożeniu MVP (liczba zdarzeń rejestracji zakończonych potwierdzeniem).
- MT-03 W 70% sesji administracyjnych dochodzi do wygenerowania co najmniej jednego zestawu tipów lub eksportu (liczba sesji z eventem „tips_generated” lub „export_created” / łączna liczba sesji).
- MT-04 Pierwszy raport miesięczny potwierdza, że wszystkie importy zostały zakończone w ciągu 24 godzin od publikacji pliku źródłowego (różnica między eventami upload/import_succeeded ≤24h).
