<?php

return [
    'required' => 'Ce champ est obligatoire.',
    'string' => 'Ce champ doit être un texte.',
    'email' => 'Saisissez une adresse e-mail valide.',
    'max' => ['string' => 'Ce champ ne doit pas dépasser :max caractères.'],
    'min' => ['string' => 'Ce champ doit contenir au moins :min caractères.'],
    'confirmed' => 'La confirmation ne correspond pas.',
    'different' => 'Le nouveau mot de passe doit être différent de l\'ancien.',
    'regex' => 'Le format est invalide.',
    'current_password' => 'Le mot de passe actuel est incorrect.',
    'unique' => 'Cette valeur est déjà utilisée.',
    'in' => 'La valeur choisie est invalide.',
    'password' => [
        'letters' => 'Le mot de passe doit contenir au moins une lettre.',
        'mixed' => 'Le mot de passe doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        'symbols' => 'Le mot de passe doit contenir au moins un symbole (par exemple ! ? # %).',
        'uncompromised' => 'Ce mot de passe a fuité publiquement. Choisissez-en un autre.',
    ],
    'attributes' => [
        'email' => 'e-mail',
        'password' => 'mot de passe',
        'name' => 'nom',
        'code' => 'code',
    ],
];
