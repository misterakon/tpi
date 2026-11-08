<?php

namespace App\Support\Redaction;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Gabarit de décision : un texte avec des variables que le système remplit à partir des données de l'affaire.
 *
 *   {{ variable }}                       valeur telle quelle
 *   {{ demandeur.nom }}                  une donnée d'un ensemble (ici le nom du demandeur)
 *   {{ variable|filtre }}                valeur transformée (voir FILTRES)
 *   {{ variable|filtre:argument }}       filtre avec argument, par exemple {{ demandeur.genre|accord:demandeur }}
 *   {{ variable|filtre1|filtre2 }}       plusieurs transformations à la suite
 *   {{> nom_du_bloc }}                   bloc réutilisable (visas, formule de dépens, clôture…), qui peut contenir des variables
 *
 * Une variable absente n'est jamais devinée : elle reste visible dans le texte et figure dans la liste des variables manquantes,
 * pour que le juge la complète. Seuls les filtres de la liste sont possibles : aucun code n'est jamais exécuté.
 *
 * Mode HTML : quand le modèle est un texte mis en forme (HTML), les valeurs insérées sont protégées (un « < » dans un nom
 * ne devient jamais une balise).
 */
class Gabarit
{
    public const FILTRES = [
        'date' => 'Date en lettres avec le jour (vendredi quatre juillet deux mil vingt-cinq)',
        'date_sans_jour' => 'Date en lettres sans le jour de la semaine',
        'date_courte' => 'Date écrite comme dans un jugement (16 janvier 2025 ; 1er mars 2025)',
        'date_chiffres' => 'Jour de la semaine puis date en chiffres (vendredi 04 juillet 2025)',
        'date_numerique' => 'Date en chiffres (04/07/2025)',
        'nombre' => 'Nombre en lettres',
        'nombre_chiffres' => 'Nombre en lettres puis en chiffres : trois (03) ; avec un nom : nombre_chiffres:enfant',
        'montant' => 'Montant en francs CFA, en lettres puis en chiffres',
        'heure' => 'Heure en lettres (neuf heures trente minutes), donnée comme 09:30',
        'accord' => 'Terme accordé selon le genre et le nombre des personnes : accord:demandeur',
        'civilite' => 'Monsieur, Madame, Messieurs, Mesdames',
        'ne' => 'né, née, nés, nées',
        'personne' => 'Civilité et nom d\'une personne : Madame KONE Awa ; personne:Maître pour une autre civilité',
        'personnes' => 'Plusieurs personnes, chacune avec sa civilité : Madame A et Madame B',
        'liste' => 'Énumération : A, B et C',
        'liste_numerotee' => 'Une ligne par personne, numérotée : 1- A, 2- B, 3- C',
        'adverbe' => 'Nature de la décision à l\'adverbe : contradictoire devient contradictoirement (par défaut reste par défaut)',
        'majuscule' => 'Tout en majuscules',
        'minuscule' => 'Tout en minuscules',
        'initiale' => 'Première lettre en majuscule',
    ];

    /** Éléments de mise en page fournis par le système (aucun texte à écrire) : {{> trait_pointille }}. html : dessin à l'aperçu ; texte : repli sans mise en forme. */
    public const ELEMENTS = [
        'trait_pointille' => ['libelle' => "Trait pointillé (sous la cour d'appel)", 'html' => '<span class="acte-trait acte-trait--pointille"></span>', 'texte' => '----------------'],
        'trait_plein' => ['libelle' => 'Trait plein (sous le numéro du RG)', 'html' => '<span class="acte-trait acte-trait--plein"></span>', 'texte' => '----------------'],
    ];

    private const PROFONDEUR_MAX = 5;

    /** Libellés des champs pour les pastilles des valeurs manquantes (null : on garde les accolades, pour les modèles). */
    private static ?array $libelles = null;

    /** @return array{texte: string, manquantes: list<string>} */
    public static function rendre(string $modele, array $valeurs, array $blocs = [], bool $html = false, ?array $libelles = null): array
    {
        $manquantes = [];
        self::$libelles = $libelles;
        $texte = self::remplacer(self::normaliser($modele), $valeurs, $blocs, $manquantes, 0, $html);
        self::$libelles = null;

        return ['texte' => $texte, 'manquantes' => array_values(array_unique($manquantes))];
    }

    /** Variables utilisées par un modèle (blocs compris), pour savoir quoi demander avant de rédiger. @return list<string> */
    public static function variables(string $modele, array $blocs = []): array
    {
        $trouvees = [];
        self::parcourir(self::normaliser($modele), $blocs, $trouvees, 0);

        return array_values(array_unique($trouvees));
    }

    /** Transformations utilisées dans un texte qui n'existent pas (faute de frappe). @return list<string> */
    public static function filtresInconnus(string $modele): array
    {
        preg_match_all('/\{\{\s*>?\s*[a-zA-Z0-9_.]+\s*((?:\|\s*[a-z_]+\s*(?::\s*[^}|]*?)?\s*)*)\}\}/u', self::normaliser($modele), $m);
        $inconnus = [];
        foreach ($m[1] as $chaine) {
            foreach (array_filter(array_map('trim', explode('|', $chaine))) as $etape) {
                $filtre = trim(explode(':', $etape, 2)[0]);
                if (! array_key_exists($filtre, self::FILTRES)) {
                    $inconnus[] = $filtre;
                }
            }
        }

        return array_values(array_unique($inconnus));
    }

    /** Un éditeur de texte écrit « > » sous la forme « &gt; » : on rétablit l'appel de bloc. */
    private static function normaliser(string $modele): string
    {
        return (string) preg_replace('/\{\{\s*&gt;/u', '{{>', $modele);
    }

    private static function parcourir(string $modele, array $blocs, array &$trouvees, int $niveau): void
    {
        if ($niveau > self::PROFONDEUR_MAX) {
            return;
        }
        preg_match_all('/\{\{\s*(>?)\s*([a-zA-Z0-9_.]+)[^}]*\}\}/', $modele, $m, PREG_SET_ORDER);
        foreach ($m as [$tout, $bloc, $nom]) {
            if ($bloc === '>') {
                if (isset($blocs[$nom])) {
                    self::parcourir(self::normaliser($blocs[$nom]), $blocs, $trouvees, $niveau + 1);
                }
            } else {
                $trouvees[] = $nom;
            }
        }
    }

    private static function remplacer(string $modele, array $valeurs, array $blocs, array &$manquantes, int $niveau, bool $html): string
    {
        return (string) preg_replace_callback('/\{\{\s*(>?)\s*([a-zA-Z0-9_.]+)\s*((?:\|\s*[a-z_]+\s*(?::\s*[^}|]*?)?\s*)*)\}\}/u', function (array $m) use ($valeurs, $blocs, &$manquantes, $niveau, $html) {
            [$tout, $bloc, $nom] = $m;
            $chaine = trim($m[3] ?? '');

            if ($bloc === '>') {
                if (isset(self::ELEMENTS[$nom])) {
                    return self::ELEMENTS[$nom][$html ? 'html' : 'texte'];
                }
                if (! isset($blocs[$nom])) {
                    $manquantes[] = 'bloc '.$nom;

                    return $tout;
                }
                if ($niveau >= self::PROFONDEUR_MAX) {
                    return $tout;   // un bloc qui s'appelle lui-même : on s'arrête
                }

                return self::remplacer(self::normaliser($blocs[$nom]), $valeurs, $blocs, $manquantes, $niveau + 1, $html);
            }

            $valeur = Arr::get($valeurs, $nom);
            // Une personne dont on n'a que le genre n'est pas renseignée : c'est son nom qui manque.
            if (is_array($valeur) && ! array_is_list($valeur) && trim((string) ($valeur['nom'] ?? '')) === '') {
                $nom .= '.nom';
                $valeur = null;
            }
            if ($valeur === null || $valeur === '' || $valeur === []) {
                $manquantes[] = $nom;

                // Dans une décision en cours de rédaction, un champ vide s'affiche comme une pastille lisible, jamais avec des accolades.
                if ($html && self::$libelles !== null) {
                    return '<span class="acte-vide" data-champ="'.e($nom).'">[ '.e(self::$libelles[$nom] ?? $nom).' ]</span>';
                }

                return $tout;
            }

            $sortie = $valeur;
            try {
                if ($chaine === '') {
                    $sortie = is_array($valeur) ? Accords::enumerer($valeur) : (string) $valeur;
                }
                foreach ($chaine === '' ? [] : array_filter(array_map('trim', explode('|', $chaine))) as $etape) {
                    [$filtre, $argument] = array_pad(explode(':', $etape, 2), 2, '');
                    $filtre = trim($filtre);
                    if (! array_key_exists($filtre, self::FILTRES)) {
                        $manquantes[] = "filtre $filtre";

                        return $tout;
                    }
                    $sortie = self::filtrer($filtre, $sortie, trim($argument));
                }
            } catch (Throwable) {
                $manquantes[] = "$nom (valeur incorrecte pour « $chaine »)";

                return $tout;
            }

            $sortie = is_array($sortie) ? Accords::enumerer($sortie) : (string) $sortie;

            return $html ? nl2br(e($sortie), false) : $sortie;   // les retours à la ligne d'une liste deviennent des sauts de ligne
        }, $modele);
    }

    private static function filtrer(string $filtre, mixed $valeur, string $argument): string
    {
        return match ($filtre) {
            'date' => DateEnLettres::complete($valeur),
            'date_sans_jour' => DateEnLettres::sansJour($valeur),
            'date_courte' => DateEnLettres::courte($valeur),
            'date_chiffres' => DateEnLettres::jourEtChiffres($valeur),
            'date_numerique' => DateEnLettres::numerique($valeur),
            'nombre' => NombreEnLettres::convertir((int) $valeur),
            'nombre_chiffres' => NombreEnLettres::chiffresEtLettres((int) $valeur, $argument !== '' ? $argument : null),
            'montant' => NombreEnLettres::montant((int) preg_replace('/\D/', '', (string) $valeur)),
            'heure' => self::heure((string) $valeur),
            'accord' => Accords::accorder($argument, $valeur),
            'civilite' => Accords::civilite($valeur),
            'ne' => Accords::ne($valeur),
            'personne' => self::personne((array) $valeur, $argument),
            'personnes' => Accords::enumerer(array_map(fn ($p) => self::personne((array) $p, $argument), (array) $valeur)),
            'liste' => Accords::enumerer((array) $valeur),
            'adverbe' => (string) preg_replace('/^contradictoire$/iu', 'contradictoirement', trim((string) $valeur)),
            'liste_numerotee' => implode("\n", array_map(fn ($n, $i) => ($i + 1).'- '.$n, array_values((array) $valeur), array_keys(array_values((array) $valeur)))),
            'majuscule' => mb_strtoupper((string) $valeur),
            'minuscule' => mb_strtolower((string) $valeur),
            'initiale' => mb_strtoupper(mb_substr((string) $valeur, 0, 1)).mb_substr((string) $valeur, 1),
        };
    }

    /** « Madame KONE Awa » à partir de ['nom' => …, 'genre' => 'f'] ; une civilité imposée (« Maître ») remplace Monsieur ou Madame. */
    private static function personne(array $personne, string $civilite): string
    {
        $nom = trim((string) ($personne['nom'] ?? ''));
        if ($nom === '') {
            throw new \InvalidArgumentException('Nom manquant.');
        }
        $titre = $civilite !== '' ? $civilite : Accords::civilite((string) ($personne['genre'] ?? ''));

        return $titre.' '.$nom;
    }

    private static function heure(string $valeur): string
    {
        $t = Carbon::createFromFormat('H:i', trim($valeur));

        return NombreEnLettres::heure($t->hour, $t->minute);
    }
}
