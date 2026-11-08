<?php

namespace App\Support\Redaction;

use Illuminate\Support\Arr;

/**
 * Catalogue des variables que les modèles de décision peuvent utiliser : les données d'une affaire (numéros, dates, chambre),
 * la composition de la formation et les parties. Chaque modèle peut en ajouter d'autres qui lui sont propres (héritiers, défunt…).
 *
 * Une donnée composée s'écrit avec un point : {{ demandeur.nom }}, {{ demandeur.genre|ne }}.
 * Les exemples sont fictifs : ils servent à l'aperçu d'un modèle et ne viennent d'aucun dossier réel.
 * type : texte | date | nombre | genre (m ou f) | liste | personne (nom + genre) | personnes (liste de personnes)
 */
class VariablesDecision
{
    /** Types de variable et filtre conseillé à l'insertion. */
    public const TYPES = [
        'texte' => 'Texte', 'date' => 'Date', 'nombre' => 'Nombre', 'genre' => 'Genre (m ou f)', 'liste' => 'Liste',
        'personne' => 'Personne', 'personnes' => 'Plusieurs personnes',
    ];

    public const FILTRE_CONSEILLE = ['date' => 'date', 'nombre' => 'nombre_chiffres', 'liste' => 'liste', 'personne' => 'personne', 'personnes' => 'personnes', 'genre' => 'civilite'];

    /** @return array<string, list<array{code: string, libelle: string, type: string, exemple: mixed}>> groupe => variables */
    public static function standard(): array
    {
        return [
            "L'affaire et la décision" => [
                self::v('juridiction', 'Juridiction', 'texte', 'Tribunal de Première Instance de Bingerville'),
                self::v('cour_appel', "Cour d'appel", 'texte', "Cour d'appel d'Abidjan"),
                self::v('numero_rg', 'Numéro du RG (sans « RG »)', 'texte', '017/2026'),
                self::v('date_rg', 'Date du RG', 'date', '2026-02-02'),
                self::v('chambre', 'Chambre (écriture courte)', 'texte', '3ème chambre civile'),
                self::v('chambre_lettres', 'Chambre (en lettres)', 'texte', 'troisième chambre civile'),
                self::v('type_decision', 'Type de décision', 'texte', "Jugement d'hérédité"),
                self::v('numero_decision', 'Numéro de la décision', 'texte', '000'),
                self::v('date_decision', 'Date de la décision', 'date', '2026-11-06'),
                self::v('nature_decision', 'Nature de la décision (contradictoire, par défaut…)', 'texte', 'contradictoire'),
                self::v('matiere', 'Matière', 'texte', "civile d'état des personnes"),
                self::v('type_audience', "Type d'audience", 'texte', 'ordinaire'),
                self::v('date_audience', "Date de l'audience", 'date', '2026-11-06'),
            ],
            'Composition du tribunal' => [
                self::v('president', 'Président de la formation', 'personne', null),
                self::v('president.nom', 'Président : nom', 'texte', 'ADJOUA Prisca'),
                self::v('president.genre', 'Président : genre', 'genre', 'f'),
                self::v('president.fonction', 'Président : fonction', 'texte', 'Vice-Président du Tribunal'),
                self::v('assesseurs', 'Assesseurs', 'personnes', '[{"nom":"KOFFI Marie","genre":"f"},{"nom":"DOSSO Ibrahim","genre":"m"}]'),
                self::v('assesseurs.0.nom', 'Premier assesseur : nom', 'texte', 'KOFFI Marie'),
                self::v('assesseurs.0.genre', 'Premier assesseur : genre', 'genre', 'f'),
                self::v('assesseurs.1.nom', 'Second assesseur : nom', 'texte', 'DOSSO Ibrahim'),
                self::v('assesseurs.1.genre', 'Second assesseur : genre', 'genre', 'm'),
                self::v('greffier', 'Greffier', 'personne', null),
                self::v('greffier.nom', 'Greffier : nom', 'texte', 'YAO Aminata'),
                self::v('greffier.genre', 'Greffier : genre', 'genre', 'f'),
            ],
            'Le demandeur' => [
                self::v('demandeur', 'Demandeur', 'personne', null),
                self::v('demandeur.nom', 'Demandeur : nom et prénoms', 'texte', 'TRAORE Salif'),
                self::v('demandeur.genre', 'Demandeur : genre', 'genre', 'm'),
                self::v('demandeur.naissance', 'Demandeur : naissance', 'texte', 'le 01 mars 1970'),
                self::v('demandeur.lieu_naissance', 'Demandeur : lieu de naissance', 'texte', 'Bouaké'),
                self::v('demandeur.nationalite', 'Demandeur : nationalité', 'texte', 'ivoirienne'),
                self::v('demandeur.domicile', 'Demandeur : domicile', 'texte', 'Bingerville'),
                self::v('demandeur.comparution', 'Demandeur : mode de comparution', 'texte', 'en personne'),
            ],
        ];
    }

    /** Toutes les variables : celles du catalogue puis celles propres à un modèle. @param list<array{code: string, libelle: string, type: string, exemple: mixed}> $propres */
    public static function toutes(array $propres = []): array
    {
        $groupes = self::standard();
        if ($propres !== []) {
            $groupes['Propres à ce modèle'] = array_values($propres);
        }

        return $groupes;
    }

    /** Valeurs d'exemple (fictives) pour l'aperçu, sous forme d'ensembles : « demandeur.nom » devient demandeur[nom]. */
    public static function exemples(array $propres = []): array
    {
        $valeurs = [];
        foreach (self::toutes($propres) as $variables) {
            foreach ($variables as $v) {
                if ($v['exemple'] !== null && $v['exemple'] !== '') {
                    Arr::set($valeurs, $v['code'], self::decoder($v['type'], $v['exemple']));
                }
            }
        }

        return $valeurs;
    }

    /** Une variable propre à un modèle enregistre son exemple en texte : les listes et personnes sont écrites en JSON. */
    private static function decoder(string $type, mixed $exemple): mixed
    {
        if (! is_string($exemple) || ! in_array($type, ['liste', 'personne', 'personnes'], true)) {
            return $exemple;
        }
        $json = json_decode($exemple, true);

        return is_array($json) ? $json : array_values(array_filter(array_map('trim', preg_split('/\r?\n|;/', $exemple))));
    }

    /** @return array{code: string, libelle: string, type: string, exemple: mixed} */
    private static function v(string $code, string $libelle, string $type, mixed $exemple): array
    {
        return ['code' => $code, 'libelle' => $libelle, 'type' => $type, 'exemple' => $exemple];
    }
}
