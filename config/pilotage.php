<?php

/*
 | Analyse et pilotage : l'analyse par l'intelligence artificielle est facultative. Elle est désactivée tant qu'on ne l'a pas activée dans
 | Réglages > Pilotage et IA, avec une clé d'API. Seuls des TOTAUX (nombres d'affaires, délais, retards par chambre) sont transmis :
 | jamais un nom de partie, un objet de dossier, un numéro d'affaire ni un document. Sans IA, l'analyse automatique (règles et modèles de phrases)
 | est toujours disponible et ne fait aucun appel externe.
 */
return [
    'ia_active' => env('PILOTAGE_IA_ACTIVE', 'non'),
    'ia_cle' => env('PILOTAGE_IA_CLE', env('ANTHROPIC_API_KEY')),   // l'une ou l'autre variable du .env
    'ia_modele' => env('PILOTAGE_IA_MODELE', 'claude-sonnet-5-5'),
    'ia_generations_par_jour' => (int) env('PILOTAGE_IA_PAR_JOUR', 10),
    'ia_delai_secondes' => 45,
];
