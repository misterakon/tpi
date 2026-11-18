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
        'paragraphes' => 'Texte long : un paragraphe par ligne saisie, une ligne vide entre eux (à écrire seul dans un paragraphe)',
        'lignes' => 'Une ligne par élément, sans ligne vide entre elles (à écrire seul dans un paragraphe)',
        'tirets' => 'Liste à tirets : -déclarer son action recevable ; (à écrire seul dans un paragraphe)',
        'sections' => 'Rubriques : chaque titre souligné puis son texte (à écrire seul dans un paragraphe)',
        'dispositifs' => 'Les lignes de dispositif des rubriques (à écrire seul dans un paragraphe)',
        'parties_qualites' => 'Qualités complètes des parties : nom, naissance, nationalité, profession, domicile (à écrire seul dans un paragraphe)',
        'parties_noms' => 'Civilité et nom des parties : Monsieur A et Madame B',
        'parties_timbre' => 'Noms des parties en majuscules, numérotés quand elles sont plusieurs (timbre)',
        'sg_pl' => 'Selon le nombre de personnes : sg_pl:a,ont (le premier mot pour une personne, le second pour plusieurs)',
        'm_f' => 'Selon le genre : m_f:le,la (le premier mot pour un homme, le second pour une femme)',
        'pronom' => 'il, elle, ils ou elles, selon les parties',
        'numero' => 'Le numéro seul : 1145/26 devient 1145',
        'annee' => "L'année d'une date",
    ];

    /** Mots des conditions : {{#si variable}}, {{#si variable = valeur}}, {{#si variable != valeur}}, {{#si variable > 1}}, {{sinon}}, {{/si}}, {{#exige variable}}. */
    public const CONDITIONS = [
        '{{#si variable}}…{{/si}}' => 'Le texte ne paraît que si la variable est renseignée (une valeur « non » compte pour vide)',
        '{{#si variable = valeur}}…{{sinon}}…{{/si}}' => 'Le texte dépend de la valeur choisie',
        '{{#si variable != valeur}}…{{/si}}' => 'Le texte paraît sauf pour cette valeur',
        '{{#si parties > 1}}…{{/si}}' => 'Le texte paraît quand il y a plusieurs parties',
        '{{#exige variable}}' => 'Signale la variable à compléter si elle est vide, sans rien écrire',
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

    /** Un éditeur de texte écrit « > » sous la forme « &gt; » : on rétablit l'appel de bloc et les signes des conditions. */
    private static function normaliser(string $modele): string
    {
        $modele = (string) preg_replace('/\{\{\s*&gt;/u', '{{>', $modele);

        return (string) preg_replace_callback('/\{\{\s*(?:#si|#exige|sinon|\/si)[^}]*\}\}/u', fn ($m) => str_replace(['&nbsp;', "\u{00A0}", '&gt;', '&lt;', '&amp;'], [' ', ' ', '>', '<', '&'], $m[0]), $modele);
    }

    /** Une valeur est vide quand elle est absente, une chaîne vide ou une liste vide. */
    private static function vide(mixed $valeur): bool
    {
        return $valeur === null || $valeur === '' || $valeur === [];
    }

    /** Un marqueur écrit seul dans un paragraphe ({{#si …}}, {{sinon}}, {{/si}}) ne laisse pas de paragraphe vide. */
    private static function marqueurs(string $texte): string
    {
        return (string) preg_replace('~<p(?:\s[^>]*)?>\s*(\{\{\s*(?:#si\b[^}]*|#exige\b[^}]*|sinon|/si)\s*\}\})\s*</p>~u', '$1', $texte);
    }

    /** Résout les conditions d'un texte : ne garde que les passages dont la condition est vraie. @param list<string> $manquantes */
    private static function conditions(string $texte, array $valeurs, array &$manquantes): string
    {
        if (! preg_match('~\{\{\s*(?:#|sinon|/si)~u', $texte)) {
            return $texte;
        }
        $texte = self::marqueurs($texte);
        $morceaux = preg_split('~(\{\{\s*(?:#si\s+[^}]*|sinon|/si)\s*\}\})~u', $texte, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$texte];

        $pile = [];
        $sortie = '';
        foreach ($morceaux as $i => $morceau) {
            if ($i % 2 === 0) {
                $sortie .= self::actif($pile) ? $morceau : '';

                continue;
            }
            if (preg_match('~^\{\{\s*#si\s+(.*?)\s*\}\}$~us', $morceau, $c)) {
                $parent = self::actif($pile);
                $pile[] = ['parent' => $parent, 'vrai' => $parent && self::condition($c[1], $valeurs), 'sinon' => false];
            } elseif (preg_match('~^\{\{\s*sinon\s*\}\}$~u', $morceau)) {
                if ($pile) {
                    $pile[array_key_last($pile)]['sinon'] = true;
                }
            } else {
                array_pop($pile);
            }
        }

        // Une variable exigée et vide est signalée « à compléter » sans rien écrire dans le texte.
        return (string) preg_replace_callback('~\{\{\s*#exige\s+([a-zA-Z0-9_.]+)\s*\}\}~u', function ($m) use ($valeurs, &$manquantes) {
            if (self::vide(Arr::get($valeurs, $m[1]))) {
                $manquantes[] = $m[1];
            }

            return '';
        }, $sortie);
    }

    private static function actif(array $pile): bool
    {
        if (! $pile) {
            return true;
        }
        $n = end($pile);

        return $n['parent'] && ($n['sinon'] ? ! $n['vrai'] : $n['vrai']);
    }

    /** « variable », « variable = valeur », « variable != valeur » ou « variable > nombre » (une liste compte ses éléments). */
    private static function condition(string $expression, array $valeurs): bool
    {
        if (! preg_match('~^([a-zA-Z0-9_.]+)\s*(?:(!=|=|>)\s*(.+))?$~us', trim($expression), $m)) {
            return false;
        }
        $valeur = Arr::get($valeurs, $m[1]);
        $operateur = $m[2] ?? '';
        if ($operateur === '') {
            return ! self::vide($valeur) && ! (is_string($valeur) && in_array(mb_strtolower(trim($valeur)), ['non', 'false', '0'], true));
        }
        $attendu = mb_strtolower(trim($m[3]));
        if ($operateur === '>') {
            return (is_array($valeur) ? count(Parties::lignes($valeur)) : (float) $valeur) > (float) $attendu;
        }
        $reel = is_array($valeur) ? '' : mb_strtolower(trim((string) $valeur));

        return $operateur === '=' ? $reel === $attendu : $reel !== $attendu;
    }

    /** Un bloc écrit seul dans un paragraphe qui commence par ses propres paragraphes n'a pas besoin du paragraphe qui l'entoure. */
    private static function deplier(string $texte, array $blocs): string
    {
        return (string) preg_replace_callback('~<p(?:\s[^>]*)?>\s*(\{\{\s*>\s*([a-zA-Z0-9_.]+)\s*\}\})\s*</p>~u', function ($m) use ($blocs) {
            $contenu = isset($blocs[$m[2]]) ? ltrim(self::normaliser($blocs[$m[2]])) : '';

            return $contenu !== '' && ($contenu[0] === '<' || str_starts_with($contenu, '{{#')) ? $m[1] : $m[0];
        }, $texte);
    }

    /** Les variables écrites seules dans un paragraphe avec un filtre « paragraphes » (texte long, liste, rubriques, qualités). */
    private static function paragraphesSeuls(string $texte, array $valeurs, array &$manquantes): string
    {
        $re = '~<p(?:\s[^>]*)?>\s*\{\{\s*([a-zA-Z0-9_.]+)\s*\|\s*('.implode('|', MiseEnPage::FILTRES).')\s*(?::\s*([^}|]*?))?\s*\}\}\s*</p>~u';

        return (string) preg_replace_callback($re, function ($m) use ($valeurs, &$manquantes) {
            [$tout, $nom, $filtre] = $m;
            $valeur = Arr::get($valeurs, $nom);
            if (self::vide($valeur) || ($filtre === 'sections' || $filtre === 'dispositifs') && ! is_array($valeur)) {
                if ($filtre === 'sections' || $filtre === 'dispositifs') {
                    return '';
                }
                $manquantes[] = $nom;

                return self::$libelles !== null ? '<p><span class="acte-vide" data-champ="'.e($nom).'">[ '.e(self::$libelles[$nom] ?? $nom).' ]</span></p>' : $tout;
            }
            try {
                return MiseEnPage::bloc($filtre, $valeur, trim($m[3] ?? ''), true);
            } catch (Throwable) {
                $manquantes[] = "$nom (valeur incorrecte pour « $filtre »)";

                return $tout;
            }
        }, $texte);
    }

    private static function parcourir(string $modele, array $blocs, array &$trouvees, int $niveau): void
    {
        if ($niveau > self::PROFONDEUR_MAX) {
            return;
        }
        // Les variables des conditions : le formulaire doit les demander même si une seule branche s'applique.
        preg_match_all('~\{\{\s*#(?:si|exige)\s+([a-zA-Z0-9_.]+)~u', $modele, $conditions);
        foreach ($conditions[1] as $nom) {
            $trouvees[] = $nom;
        }
        preg_match_all('/\{\{\s*(>?)\s*([a-zA-Z0-9_.]+)[^}]*\}\}/', $modele, $m, PREG_SET_ORDER);
        foreach ($m as [$tout, $bloc, $nom]) {
            if ($nom === 'sinon' && $bloc === '') {
                continue;
            }
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
        $modele = self::conditions($modele, $valeurs, $manquantes);
        if ($html) {
            $modele = self::paragraphesSeuls(self::deplier($modele, $blocs), $valeurs, $manquantes);
        }

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
            'accord' => Accords::accorder($argument, Parties::genres($valeur)),
            'civilite' => Accords::civilite(Parties::genres($valeur)),
            'ne' => Accords::ne(Parties::genres($valeur)),
            'paragraphes', 'lignes', 'tirets', 'sections', 'dispositifs', 'parties_qualites' => MiseEnPage::bloc($filtre, $valeur, $argument, false),
            'parties_noms' => Parties::noms(Parties::lignes($valeur)),
            'parties_timbre' => Parties::timbre(Parties::lignes($valeur)),
            'sg_pl' => self::selon($valeur, $argument, true),
            'm_f' => self::selon($valeur, $argument, false),
            'pronom' => Parties::pronom($valeur),
            'numero' => preg_match('/\d+/', (string) $valeur, $n) ? $n[0] : (string) $valeur,
            'annee' => (string) Carbon::parse((string) $valeur)->year,
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

    /** « a,ont » : le premier mot pour une personne (ou un homme), le second pour plusieurs (ou une femme). */
    private static function selon(mixed $valeur, string $argument, bool $nombre): string
    {
        [$un, $deux] = array_pad(array_map('trim', explode(',', $argument, 2)), 2, '');
        [$genre, $pluriel] = Parties::resoudre($valeur);

        return $nombre ? ($pluriel ? $deux : $un) : ($genre === 'm' ? $un : $deux);
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
