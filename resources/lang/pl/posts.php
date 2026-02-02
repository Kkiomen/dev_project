<?php

return [
    // Status messages
    'cannot_mark_published' => 'Post nie moze byc oznaczony jako opublikowany w obecnym statusie. Tylko zatwierdzone lub zaplanowane posty moga byc oznaczone jako opublikowane.',
    'cannot_mark_failed' => 'Post nie moze byc oznaczony jako nieudany w obecnym statusie.',
    'post_published' => 'Post zostal opublikowany pomyslnie.',
    'post_failed' => 'Publikacja postu nie powiodla sie.',

    // Validation
    'invalid_platform' => 'Nieprawidlowa platforma.',
    'platform_not_found' => 'Platforma nie znaleziona dla tego postu.',
    'platform_not_enabled' => 'Platforma nie jest wlaczona dla tego postu.',

    // Actions
    'deleted' => 'Post zostal usuniety.',
    'approved' => 'Post zostal zatwierdzony.',
    'rejected' => 'Post zostal odrzucony.',
    'scheduled' => 'Post zostal zaplanowany do publikacji.',
    'duplicated' => 'Post zostal zduplikowany.',

    // API Documentation
    'api' => [
        'title' => 'API Postow Spolecznosciowych',
        'description' => 'API do zarzadzania postami w mediach spolecznosciowych z obsluga automatyzacji n8n.',
    ],
];
