# Panel Użytkownika

Interfejs webowy do zarządzania danymi.

## Widoki

- [Grid View](./grid-view.md) - Widok tabelaryczny (domyślny)
- [Kanban View](./kanban-view.md) - Widok tablicy Kanban

---

## Nawigacja

### Dashboard (`/dashboard`)

Strona główna po zalogowaniu. Wyświetla:
- Lista baz danych użytkownika
- Przycisk tworzenia nowej bazy
- Liczba tabel w każdej bazie

### Widok bazy (`/bases/{id}`)

Lista tabel w bazie z możliwością:
- Dodawania nowych tabel
- Otwierania tabeli w widoku Grid lub Kanban
- Zmiany nazwy/opisu bazy

### Widok tabeli

Dwa tryby wyświetlania:
- **Grid** (`/tables/{id}`) - Widok arkusza kalkulacyjnego
- **Kanban** (`/tables/{id}/kanban`) - Widok tablicy z kolumnami

---

## Funkcje wspólne

### Przełączanie widoków

W prawym górnym rogu każdego widoku tabeli znajduje się przełącznik Grid/Kanban.

### Breadcrumb

Nawigacja kontekstowa: `Dashboard / Nazwa bazy / Nazwa tabeli`

### Przełączanie tabel

Kliknięcie nazwy tabeli otwiera dropdown z listą wszystkich tabel w bazie.

---

## Skróty klawiszowe

| Skrót | Akcja |
|-------|-------|
| `↑` `↓` `←` `→` | Nawigacja między komórkami |
| `Enter` | Edycja zaznaczonej komórki |
| `Escape` | Anuluj edycję |
| `Tab` | Zapisz i przejdź do następnej komórki |
| `Delete` / `Backspace` | Wyczyść wartość komórki |

---

## Typy komórek

### Tekst
- Kliknięcie: zaznaczenie
- Podwójne kliknięcie: edycja inline
- Enter: zapis

### Liczba
- Input numeryczny
- Formatowanie z precyzją (domyślnie 2 miejsca)

### Data
- Date picker (datetime-local)
- Format wyświetlania: DD.MM.YYYY

### Checkbox
- Kliknięcie przełącza wartość
- Nie wymaga trybu edycji

### Select (pojedynczy wybór)
- Dropdown z opcjami
- Kolorowe etykiety

### Multi-select (wielokrotny wybór)
- Lista checkboxów
- Przycisk "Zapisz"
- Kolorowe tagi

### Załącznik
- Miniaturki obrazów
- Ikony dla innych plików
- Przycisk upload (+)
- Hover: przycisk usunięcia

### URL
- Tekst inline
- Kliknięcie otwiera link

### JSON
- Wyświetlanie jako string
- Edycja w textarea
