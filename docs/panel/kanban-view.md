# Kanban View

Widok tablicy Kanban - organizacja rekordów w kolumnach według statusu.

## Układ

```
┌─────────────────────────────────────────────────────────────┐
│ ← Dashboard / Baza / Tabela    Grupuj wg: [Status ▼] [Grid]│
├─────────────────────────────────────────────────────────────┤
│  Bez statusu    │  🔵 Nowy      │  🟡 W trakcie │  🟢 Done  │
│  (3)            │  (5)          │  (8)          │  (2)      │
├─────────────────┼───────────────┼───────────────┼───────────┤
│ ┌─────────────┐ │ ┌───────────┐ │ ┌───────────┐ │           │
│ │ Rekord 1    │ │ │ Rekord 4  │ │ │ Rekord 7  │ │           │
│ │ Opis...     │ │ │ Opis...   │ │ │ Opis...   │ │           │
│ └─────────────┘ │ └───────────┘ │ └───────────┘ │           │
│                 │               │               │           │
│ [+ Dodaj kartę] │ [+ Dodaj]     │ [+ Dodaj]     │ [+ Dodaj] │
└─────────────────┴───────────────┴───────────────┴───────────┘
```

---

## Wymagania

Widok Kanban wymaga pola typu `select` lub `multi_select` do grupowania.

Jeśli tabela nie ma takiego pola, wyświetlany jest komunikat z linkiem do Grid View.

---

## Funkcje

### Wybór pola grupującego

Dropdown "Grupuj wg:" pozwala wybrać pole select/multi_select.
Zmiana pola przeładowuje widok.

### Kolumny

Każda opcja wyboru (choice) tworzy osobną kolumnę.
Dodatkowa kolumna "Bez statusu" dla rekordów bez przypisanej wartości.

Nagłówek kolumny zawiera:
- Kolor opcji (kropka)
- Nazwa opcji
- Liczba kart w kolumnie

### Karty (rekordy)

Każda karta wyświetla:
- **Tytuł** - wartość pola głównego (primary)
- **Opis** - wartość pierwszego pola tekstowego/numerycznego

---

## Drag & Drop

### Przenoszenie kart

1. Chwyć kartę (dowolne miejsce)
2. Przeciągnij do innej kolumny
3. Upuść - karta zmienia status

Kolumna docelowa podświetla się na niebiesko podczas przeciągania.

### Ograniczenia

- Karty można przenosić tylko między kolumnami (zmiana statusu)
- Kolejność kart w kolumnie nie jest zachowywana (alfabetycznie/chronologicznie)

---

## Modal szczegółów karty

Kliknięcie karty otwiera modal z edycją wszystkich pól.

### Obsługiwane typy pól

| Typ | Kontrolka |
|-----|-----------|
| Text | Input tekstowy |
| Number | Input numeryczny |
| Date | Datetime picker |
| Checkbox | Checkbox |
| Select | Dropdown |
| URL | Input tekstowy |

### Akcje

- **Edycja pól** - zmiany zapisują się automatycznie po blur
- **Usuń rekord** - przycisk w stopce modalu

---

## Dodawanie kart

Przycisk "+ Dodaj kartę" na dole każdej kolumny:
1. Tworzy nowy rekord z przypisanym statusem
2. Automatycznie otwiera modal szczegółów

---

## Stany wizualne

### Karta
- Białe tło, zaokrąglone rogi
- Cień przy hover
- Kursor pointer

### Kolumna podczas drop
- Niebieskie tło (`bg-blue-100`)

### Kolumna "Bez statusu"
- Szare tło (odróżnienie od statusów)

---

## Różnice względem Grid View

| Funkcja | Grid | Kanban |
|---------|------|--------|
| Edycja inline | Tak | Nie (modal) |
| Wszystkie pola widoczne | Tak | Nie (tytuł + opis) |
| Drag & drop wierszy | Nie | Tak |
| Zarządzanie polami | Tak | Nie |
| Wyszukiwanie | Tak | Nie |
| Bulk operations | Tak | Nie |

---

## URL Parameters

- `group_by={fieldId}` - ID pola do grupowania

Przykład: `/tables/01HQ.../kanban?group_by=01HQ...`
