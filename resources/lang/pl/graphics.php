<?php

return [
    'layer_types' => [
        'text' => 'Tekst',
        'textbox' => 'Pole tekstowe',
        'image' => 'Obraz',
        'rectangle' => 'Prostokąt',
        'ellipse' => 'Elipsa',
        'line' => 'Linia',
        'group' => 'Grupa',
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
        'newFromGroup' => 'Szablon z grupy',
    ],

    'layers' => [
        'title' => 'Warstwy',
        'add' => 'Dodaj warstwę',
        'delete' => 'Usuń warstwę',
        'visible' => 'Widoczny',
        'locked' => 'Zablokowany',
        'properties' => 'Właściwości',
        'notAGroup' => 'Wybrana warstwa nie jest grupą',
        'groupEmpty' => 'Grupa nie posiada warstw podrzędnych',
        'createTemplateFromGroup' => 'Utwórz szablon z grupy',
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
        'title' => 'Płótno',
        'settings' => 'Ustawienia płótna',
        'size' => 'Rozmiar',
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
        'text_direction' => 'Kierunek tekstu',
        'horizontal' => 'Poziomo',
        'vertical' => 'Pionowo',
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

    'psd' => [
        'errors' => [
            'uploadFailed' => 'Nie udało się zaimportować pliku PSD',
            'analyzeFailed' => 'Nie udało się przeanalizować pliku PSD',
            'parseFailed' => 'Nie udało się przetworzyć pliku PSD',
        ],
    ],

    'semantic_tags' => [
        'header' => 'Nagłówek',
        'subtitle' => 'Podtytuł',
        'paragraph' => 'Akapit',
        'url' => 'URL / Link',
        'social_handle' => 'Nazwa profilu (@)',
        'main_image' => 'Główny obraz',
        'logo' => 'Logo',
        'cta' => 'Wezwanie do działania',
        'primary_color' => 'Kolor główny',
        'secondary_color' => 'Kolor dodatkowy',
        'text_primary_color' => 'Tekst - kolor główny',
        'text_secondary_color' => 'Tekst - kolor dodatkowy',
        'content_tag' => 'Tag treści',
        'style_tag' => 'Tag stylu',
        'no_content_tag' => 'Brak tagu treści',
        'no_style_tag' => 'Brak tagu stylu',
    ],

    'template_preview' => [
        'no_tagged_templates' => 'Brak szablonów z tagami semantycznymi w bibliotece',
        'render_failed' => 'Nie udało się wygenerować podglądu',
    ],

    'library' => [
        'groupTemplateCreated' => 'Utworzono nowy szablon z grupy',
    ],

    'apiDocs' => [
        'title' => 'Dokumentacja API',
        'howItWorks' => 'Jak to działa',
        'howItWorksDesc' => 'Użyj API aby programowo generować obrazy z własną zawartością',
        'copy' => 'Kopiuj',
        'generateEndpoint' => 'Endpoint generowania',
        'modifiableLayers' => 'Edytowalne warstwy',
        'key' => 'Klucz',
        'noModifiableLayers' => 'Brak edytowalnych warstw w tym szablonie',
        'requestBody' => 'Ciało żądania',
        'optionalParams' => 'Opcjonalne parametry',
        'optionalParamsDesc' => 'format (png/jpeg/webp), quality (1-100), scale (1-4, domyślnie: 2 dla retina)',
        'response' => 'Odpowiedź',
        'curlCommand' => 'Polecenie cURL',
    ],
];
