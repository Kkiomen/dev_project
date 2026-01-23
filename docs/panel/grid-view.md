# Grid View

Widok tabelaryczny - główny sposób pracy z danymi, podobny do arkusza kalkulacyjnego.

## Układ

```
┌─────────────────────────────────────────────────────────────┐
│ ← Dashboard / Baza / [Tabela ▼]              [Grid] [Kanban]│ <- Header
├─────────────────────────────────────────────────────────────┤
│ 150 rekordów  [🔍 Szukaj...]                    [+ Wiersz]  │ <- Toolbar
├─────────────────────────────────────────────────────────────┤
│ # │ Name ▼     │ Email        │ Status ▼      │ [+]        │ <- Column headers
├───┼────────────┼──────────────┼───────────────┼────────────┤
│ 1 │ Jan K.     │ jan@...      │ 🟢 Aktywny    │            │
│ 2 │ Anna N.    │ anna@...     │ 🟡 W trakcie  │            │
│ + │ Kliknij aby dodać...                                   │
└─────────────────────────────────────────────────────────────┘
```

---

## Funkcje

### Dodawanie wierszy

1. **Przycisk "Dodaj wiersz"** - w toolbarze
2. **Kliknięcie ostatniego wiersza** - placeholder "Kliknij aby dodać..."

Nowy wiersz pojawia się na końcu z kursorem w pierwszej komórce.

### Edycja komórek

1. **Kliknięcie** - zaznaczenie komórki (niebieska ramka)
2. **Podwójne kliknięcie** lub **Enter** - tryb edycji
3. **Enter** - zapis
4. **Escape** - anuluj
5. **Tab** - zapis i przejście do następnej komórki

### Operacje na wierszach

Najechanie na numer wiersza pokazuje przyciski:
- **Duplikuj** - kopiuje wiersz z wartościami (bez załączników)
- **Usuń** - kasuje wiersz po potwierdzeniu

### Wyszukiwanie

Pole "Szukaj..." filtruje wiersze w czasie rzeczywistym.
Przeszukuje wszystkie kolumny (tekst, liczby, nazwy opcji).

---

## Zarządzanie kolumnami

### Dodawanie pól

Kliknij `[+]` w ostatniej kolumnie nagłówka.

Modal pozwala:
1. Wpisać nazwę pola
2. Wybrać typ z wizualnej siatki
3. Dla select/multi_select: zdefiniować opcje z kolorami

### Menu pola

Kliknięcie strzałki `▼` przy nazwie pola otwiera menu:
- **Zmień nazwę** - edycja inline
- **Zmień typ** - otwiera modal edycji
- **Zarządzaj opcjami** - dla select/multi_select
- **Przesuń w lewo/prawo** - zmiana kolejności
- **Usuń pole** - kasuje kolumnę z danymi

### Zmiana szerokości kolumny

Przeciągnij krawędź nagłówka kolumny.
Szerokość zapisuje się automatycznie.

### Zmiana nazwy tabeli

Podwójne kliknięcie na nazwę tabeli w breadcrumb.

---

## Załączniki

### Upload

1. Kliknij `[+]` w komórce typu attachment
2. Wybierz plik(i) z dysku
3. Wskaźnik uploadu pojawia się w prawym dolnym rogu

### Podgląd

- Obrazy: miniaturki
- PDF/inne: ikona dokumentu
- Więcej niż 3: `+N` indicator

### Usuwanie

Najechanie na miniaturę pokazuje przycisk `X`.

---

## Pola select/multi_select

### Edycja opcji

1. Otwórz menu pola
2. Wybierz "Zarządzaj opcjami"
3. Możesz:
   - Zmienić nazwę opcji
   - Zmienić kolor (color picker)
   - Usunąć opcję
   - Dodać nową opcję

### Wybór wartości

**Select:**
- Dropdown z listą opcji
- Wybór zamyka dropdown

**Multi-select:**
- Lista checkboxów
- Przycisk "Zapisz" zatwierdza wybór

---

## Nawigacja klawiaturą

| Klawisz | Akcja |
|---------|-------|
| `↑` | Komórka wyżej |
| `↓` | Komórka niżej |
| `←` | Komórka w lewo |
| `→` | Komórka w prawo |
| `Enter` | Wejdź w tryb edycji |
| `Escape` | Wyjdź z edycji bez zapisu |
| `Tab` | Zapisz i następna komórka |
| `Delete` | Wyczyść wartość |

---

## Stany wizualne

### Komórka zaznaczona
Niebieska ramka (`ring-2 ring-blue-500`)

### Komórka w edycji
Niebieskie tło, widoczny input

### Wiersz zaznaczony
Lekko niebieskie tło

### Hover na wierszu
Bardzo lekkie niebieskie tło, widoczne przyciski akcji

---

## Optimistic Updates

Zmiany są natychmiast widoczne w UI przed zapisem na serwerze.
W przypadku błędu API, wyświetlany jest alert.
