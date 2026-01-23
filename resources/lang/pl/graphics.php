<?php

return [
    'layer_types' => [
        'text' => 'Tekst',
        'image' => 'Obraz',
        'rectangle' => 'Prostokąt',
        'ellipse' => 'Elipsa',
    ],

    'templates' => [
        'title' => 'Szablony',
        'create' => 'Utwórz szablon',
        'edit' => 'Edytuj szablon',
        'duplicate' => 'Duplikuj szablon',
        'delete' => 'Usuń szablon',
        'delete_confirm' => 'Czy na pewno chcesz usunąć ten szablon?',
        'duplicated' => 'Szablon został zduplikowany.',
        'saved' => 'Szablon został zapisany.',
        'deleted' => 'Szablon został usunięty.',
    ],

    'layers' => [
        'title' => 'Warstwy',
        'add' => 'Dodaj warstwę',
        'delete' => 'Usuń warstwę',
        'visible' => 'Widoczny',
        'locked' => 'Zablokowany',
        'properties' => 'Właściwości',
    ],

    'editor' => [
        'title' => 'Edytor graficzny',
        'save' => 'Zapisz',
        'export' => 'Eksportuj',
        'download' => 'Pobierz',
        'upload' => 'Wyślij na serwer',
        'undo' => 'Cofnij',
        'redo' => 'Ponów',
        'zoom_in' => 'Powiększ',
        'zoom_out' => 'Pomniejsz',
        'fit_to_screen' => 'Dopasuj do ekranu',
    ],

    'canvas' => [
        'settings' => 'Ustawienia płótna',
        'width' => 'Szerokość',
        'height' => 'Wysokość',
        'background_color' => 'Kolor tła',
        'background_image' => 'Obraz tła',
    ],

    'properties' => [
        'position' => 'Pozycja',
        'size' => 'Rozmiar',
        'rotation' => 'Obrót',
        'opacity' => 'Przezroczystość',
        'fill' => 'Wypełnienie',
        'stroke' => 'Obrys',
        'stroke_width' => 'Grubość obrysu',
        'corner_radius' => 'Zaokrąglenie rogów',
        'font_family' => 'Czcionka',
        'font_size' => 'Rozmiar czcionki',
        'font_weight' => 'Grubość czcionki',
        'font_style' => 'Styl czcionki',
        'text_align' => 'Wyrównanie tekstu',
        'line_height' => 'Wysokość linii',
        'letter_spacing' => 'Odstępy między literami',
    ],

    'export' => [
        'title' => 'Eksportuj obraz',
        'format' => 'Format',
        'quality' => 'Jakość',
        'scale' => 'Skala',
        'filename' => 'Nazwa pliku',
    ],

    'fonts' => [
        'title' => 'Czcionki',
        'upload' => 'Prześlij czcionkę',
        'delete' => 'Usuń czcionkę',
        'supported_formats' => 'Obsługiwane formaty: TTF, OTF, WOFF, WOFF2',
    ],

    'aiChat' => [
        'title' => 'Asystent AI',
        'toggle' => 'Przełącz czat AI',
        'placeholder' => 'Poproś mnie o utworzenie lub modyfikację szablonu...',
        'send' => 'Wyślij',
        'thinking' => 'Myślę...',
        'error' => 'Coś poszło nie tak. Spróbuj ponownie.',
        'welcomeMessage' => 'Cześć! Mogę pomóc Ci tworzyć i modyfikować szablony. Spróbuj poprosić mnie o:',
        'enterHint' => 'Naciśnij Enter aby wysłać, Shift+Enter dla nowej linii',
        'clearHistory' => 'Wyczyść historię czatu',
        'suggestions' => [
            'createInstagram' => 'Utwórz szablon posta na Instagram',
            'changeText' => 'Zmień tekst na coś innego',
            'addShape' => 'Dodaj kształt w tle',
            'apiHelp' => 'Jak użyć API do generowania zdjęć?',
        ],
        'actions' => [
            'layerModified' => 'Warstwa ":name" zmodyfikowana',
            'layerAdded' => 'Nowa warstwa ":name" dodana',
            'layerDeleted' => 'Warstwa ":name" usunięta',
            'templateUpdated' => 'Szablon zaktualizowany',
        ],
    ],
];
