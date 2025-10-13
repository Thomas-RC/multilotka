# Repository Guidelines

## Struktura projektu i organizacja modułów
Kod produkcyjny PHP umieszczaj w `src/`, dzieląc przestrzenie nazw według domen, np. `Statistics`, `Draw`, `Support`. Skrypty CLI trafiają do `scripts/` (`scripts/analyse.php` jako główne wejście). Surowe losowania Lotto przechowuj w `datasets/`, a przetworzone zestawienia (CSV, JSON) w `storage/`. Struktura katalogów w `tests/` powinna odpowiadać katalogom w `src/`, aby `tests/Statistics/CombinationFrequencyTest.php` testował `src/Statistics/CombinationFrequency.php`. Gdy logika w katalogu wymaga kontekstu, dodaj krótkie `README` z opisem celu.

## Budowanie, testy i praca lokalna
Po zmianach zdalnych uruchom `composer install`, aby odświeżyć zależności i autoloader. Po dodaniu klas wykonaj `composer dump-autoload`, by zarejestrować przestrzenie nazw. Testy jednostkowe uruchamiaj przez `./vendor/bin/phpunit --testdox`; warto dodać alias `composer test`, który wywoła tę komendę. Analizę ręczną odpalaj komendą `php scripts/analyse.php datasets/latest.csv`, a wygenerowane wyniki zapisuj w `reports/`, by ułatwić porównania.

## Konwencje kodu i nazewnictwa
Stosuj PSR-12 oraz wcięcia o szerokości czterech spacji. Przestrzenie nazw muszą odzwierciedlać ścieżki katalogów w ramach `Multilotka\*`. Klasy, interfejsy i cechy nazywaj w PascalCase; metody i zmienne w camelCase; klucze konfiguracji trzymaj w snake_case zgodnym z terminologią Lotto (`draw_date`, `ball_frequencies`). Każdy plik powinien realizować jeden spójny cel; preferuj obiekty wartości zamiast luźnych tablic. Przed utworzeniem PR uruchom `vendor/bin/phpcs --standard=PSR12 src tests`.

## Wytyczne testowe
Korzystamy z PHPUnit; testy dziedziczą przestrzenie nazw modułów produkcyjnych. Metody testowe nazywaj `testOpisScenariusza`, zapewniając deterministyczne dane wejściowe (ustaw ziarna generatorów). Celem jest co najmniej 80% pokrycia, a pominięcia trzeba opisać w PR. Nowe algorytmy statystyczne testuj na fixturach w `tests/fixtures/`, obejmując zarówno typowe rozkłady, jak i przypadki brzegowe (np. niepełne losowania).

## Commity i pull requesty
Historia jest krótka, dlatego przyjmujemy Conventional Commits (`feat:`, `fix:`, `chore:`). Commity powinny być logicznie odseparowane i zawierać zwięzły opis zmiany. W PR dołącz link do powiązanego zgłoszenia, listę wykonanych weryfikacji (`composer test`, `php scripts/analyse.php datasets/sample.csv`) oraz, gdy zmienia się format danych, przykładowy fragment wygenerowanego raportu. Wspomnij o potencjalnych ryzykach, takich jak zmiany schematu danych czy nowe zależności środowiskowe.

## Dane i konfiguracja
Nie commituj danych osobowych graczy; przed dodaniem do `datasets/` anonimizuj źródła. Klucze API oraz hasła zapisuj w `.env`, a wymagane zmienne środowiskowe dokumentuj w `README.md`. Każdy zbiór danych opatruj informacją o pochodzeniu, aby możliwe było prześledzenie drogi danych do analiz.
