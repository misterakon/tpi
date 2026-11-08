<?php

namespace App\Support;

use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\ObjetLitige;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/*
 | Rapproche l'objet du litige, saisi librement par le greffier, de deux sources de mots-clés pour proposer la nature d'affaire, la matière
 | et la chambre :
 |  1. les objets connus (page « Objets du litige »), qui portent leur nature, leur matière et leur chambre ;
 |  2. les attributions de chaque chambre et de chacune de ses formations, telles que l'ordonnance les énumère (« Expropriation,
 |     copropriété, domaine foncier rural et urbain, servitudes… ») : chaque expression devient un mot-clé qui renvoie à sa chambre.
 | La recherche ignore les majuscules, les accents et les pluriels. La proposition la plus précise (celle dont tous les mots sont présents,
 | avec le plus de mots) vient en premier ; le greffier choisit ou corrige.
 */
class RapprochementObjet
{
    private const MOTS_VIDES = ['dans', 'pour', 'avec', 'sans', 'autre', 'autres', 'leur', 'leurs', 'cette', 'entre', 'dont', 'sont', 'ainsi', 'aux', 'des', 'les', 'une',
        'par', 'sur', 'que', 'qui', 'est', 'non', 'ont', 'car', 'son', 'ses', 'ces', 'mes', 'nos', 'vos', 'loi', 'fin', 'tel', 'dit', 'art', 'nul', 'ici', 'etc'];

    /** Mots trop courants dans les attributions pour reconnaître une chambre à eux seuls (matière, instance, procédure…). */
    private const MOTS_GENERIQUES = ['toute', 'tout', 'matiere', 'instance', 'procedure', 'tribunal', 'president', 'chambre', 'contentieux', 'infraction', 'competence', 'article', 'code', 'lorsque', 'celle', 'ceux', 'inferieur', 'superieur', 'egal', 'fcfa', 'interet', 'pecuniaire', 'excede', 'necessaire', 'juge', 'litige', 'differend', 'action', 'demande', 'conflit', 'affaire'];

    /** Familles de juridictions : le registre de saisie détermine les sections de chambres proposées. */
    private const SECTIONS = ['civil' => ['civile', 'urgence', 'sociale'], 'penal' => ['penale'], 'instruction' => ['instruction']];

    /** @return list<string> mots significatifs, au singulier */
    public static function mots(string $texte): array
    {
        $normal = trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii($texte))));
        $mots = [];
        foreach (explode(' ', $normal) as $mot) {
            $mot = self::radical($mot);
            // Trois lettres suffisent (« vol ») ; les petits mots de liaison sont écartés.
            if (strlen($mot) >= 3 && ! in_array($mot, self::MOTS_VIDES, true)) {
                $mots[] = $mot;
            }
        }

        return array_values(array_unique($mots));
    }

    /** Racine d'un mot : le pluriel et le féminin s'effacent (« foncière » et « foncier », « civiles » et « civil » se rejoignent). */
    private static function radical(string $mot): string
    {
        $mot = (string) preg_replace('/(s|x)$/', '', $mot);
        foreach (['iere' => 'ier', 'elle' => 'el', 'ive' => 'if', 'euse' => 'eur', 'trice' => 'teur', 'ale' => 'al'] as $fin => $masculin) {
            if (str_ends_with($mot, $fin) && strlen($mot) > strlen($fin) + 1) {
                return substr($mot, 0, -strlen($fin)).$masculin;
            }
        }

        return strlen($mot) > 5 && str_ends_with($mot, 'e') ? substr($mot, 0, -1) : $mot;
    }

    /** Les expressions d'une énumération d'attributions : « Tutelle, puissance paternelle ; émancipation… » devient trois mots-clés. @return list<string> */
    public static function expressions(string $attributions): array
    {
        $texte = str_replace(['(', ')', '…', '.', ':', "\n", "\r"], [',', ',', ',', ',', ',', ',', ' '], $attributions);
        $morceaux = preg_split('/[,;]+/u', $texte) ?: [];
        $expressions = [];
        foreach ($morceaux as $m) {
            $m = trim((string) preg_replace('/^(et|ou|ainsi que|toutes? (les )?(instances|procédures|actions)( en matière)?( de| d\')?|tous (les )?certificats relevant de)\s+/iu', '', trim($m)));
            if (mb_strlen($m) >= 4 && ! preg_match('/^[\d\s]+$/u', $m)) {
                $expressions[] = $m;
            }
        }

        return $expressions;
    }

    /**
     * @param  ?list<string>  $registres  séries de nature d'affaire admises (RG, RP, RI, RE) ; null : toutes
     * @return list<array{id: ?int, libelle: string, nature_id: ?int, nature: ?string, matiere_id: ?int, matiere: ?string, chambre_id: ?int, chambre: ?string, source: string}>
     */
    public static function proposer(string $objet, int $max = 5, ?array $registres = null): array
    {
        $texte = self::mots($objet);
        if ($texte === []) {
            return [];
        }

        $chambres = Chambre::with(['section', 'formations'])->whereIn('annee_judiciaire_id', AnneeJudiciaire::where('courante', true)->select('id'))->where('actif', true)->get();
        $parLibelle = $chambres->keyBy('libelle');

        // Seuls les objets de la bonne famille (civil, pénal, instruction) sont proposés, d'après le registre de leur nature d'affaire.
        $objets = ObjetLitige::with(['nature', 'matiere'])->where('actif', true)->get()
            ->filter(fn (ObjetLitige $o) => $registres === null || in_array($o->nature?->registre, $registres, true)
                // Un crime (tribunal criminel) n'a pas de nature d'affaire : on le propose au pénal d'après la section de sa chambre.
                || ($o->nature_affaire_id === null && self::familleDe($registres) === 'penal' && in_array($parLibelle->get($o->chambre_libelle)?->section?->code, self::SECTIONS['penal'], true)));

        $propositions = [];
        foreach ($objets as $o) {
            $part = self::correspondance(self::mots($o->libelle), $texte);
            if ($part === null) {
                continue;
            }
            $chambre = $parLibelle->get($o->chambre_libelle);
            $propositions[] = [
                'score' => [$part, count(self::mots($o->libelle)), 1],
                'id' => $o->id, 'libelle' => $o->libelle,
                'nature_id' => $o->nature_affaire_id, 'nature' => $o->nature?->libelle,
                'matiere_id' => $o->matiere_id, 'matiere' => $o->matiere?->libelle,
                'chambre_id' => $chambre?->id, 'chambre' => $chambre?->libelle ?? $o->chambre_libelle, 'source' => 'objet',
            ];
        }

        // Nature et matière habituelles d'une chambre : celles de ses objets connus (pour les mots-clés venant des attributions).
        $habituel = $objets->groupBy('chambre_libelle')->map(fn (Collection $g) => [
            'nature' => $g->groupBy('nature_affaire_id')->sortByDesc(fn ($x) => $x->count())->first()->first(),
            'matiere' => $g->groupBy('matiere_id')->sortByDesc(fn ($x) => $x->count())->first()->first(),
        ]);

        $sections = self::SECTIONS[$registres === null ? 'civil' : self::familleDe($registres)] ?? [];
        $dejaVu = collect($propositions)->map(fn ($p) => $p['chambre_id'].'|'.Str::lower($p['libelle']))->flip();
        $partiels = [];
        foreach (self::motsClesDesAttributions($chambres->filter(fn (Chambre $c) => $registres === null || in_array($c->section?->code, $sections, true))) as $mc) {
            // Chaque mot pèse d'autant plus qu'il est rare dans les attributions : « foncier » compte beaucoup, « ordonnance » peu.
            $poids = $mc['poids'];
            $part = array_sum(array_map(fn ($m) => in_array($m, $texte, true) ? $poids[$m] : 0, $mc['mots'])) / array_sum($poids);
            // Ou, à défaut, au moins deux mots sur trois de l'expression (peu importe leur rareté).
            $communsMots = count(array_intersect($mc['mots'], $texte));
            if ($communsMots >= 2 && $communsMots / count($mc['mots']) >= 0.6) {
                $part = max($part, $communsMots / count($mc['mots']));
            }
            if ($part < 0.6) {
                // Un seul mot distinctif de l'expression suffit à orienter vers la chambre (« foncier », « loyers », « blanchiment »…),
                // avec moins de poids qu'une expression complète : une seule proposition de ce type par chambre.
                $communs = array_intersect($mc['rares'], $texte);
                if ($communs !== []) {
                    $note = $part * 0.5;
                    $partiels[$mc['chambre']->id][] = ['part' => $note, 'mc' => $mc];
                }

                continue;
            }
            $propositions[] = self::depuisAttribution($mc, $part, $habituel, $dejaVu);
        }
        foreach ($partiels as $liste) {
            usort($liste, fn ($a, $b) => $b['part'] <=> $a['part']);
            foreach (array_slice($liste, 0, 2) as $x) {   // deux mots-clés au plus par chambre : le greffier voit lequel s'applique
                $propositions[] = self::depuisAttribution($x['mc'], $x['part'], $habituel, $dejaVu);
            }
        }
        $propositions = array_values(array_filter($propositions));


        usort($propositions, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Une même chambre n'est proposée qu'une fois avec le même libellé (« 1ere » et « 1ere chambre civile » sont la même) ; au plus $max propositions.
        $uniques = [];
        foreach ($propositions as $p) {
            $uniques[self::cleChambre($p['chambre']).'|'.Str::lower($p['libelle'])] ??= array_diff_key($p, ['score' => 1]);
        }
        $uniques = array_slice(array_values($uniques), 0, max(1, $max - 1));

        // Au civil, la chambre du Président peut connaître de « toutes les procédures qu'il juge nécessaire » (attributions de l'ordonnance) :
        // elle reste un choix possible à côté des chambres proposées.
        if ($uniques !== [] && $registres !== null && self::familleDe($registres) === 'civil') {
            foreach ($chambres as $c) {
                if (str_contains(Str::lower(Str::ascii((string) $c->attributions)), 'juge necessaire de connaitre')
                    && ! collect($uniques)->contains(fn ($p) => self::cleChambre($p['chambre']) === self::cleChambre($c->libelle))) {
                    $h = $habituel->get($c->libelle);
                    $uniques[] = [
                        'id' => null, 'libelle' => 'Chambre présidentielle : procédures que le Président juge nécessaire de connaître',
                        'nature_id' => $h['nature']->nature_affaire_id ?? null, 'nature' => $h['nature']->nature?->libelle ?? null,
                        'matiere_id' => $h['matiere']->matiere_id ?? null, 'matiere' => $h['matiere']->matiere?->libelle ?? null,
                        'chambre_id' => $c->id, 'chambre' => $c->libelle, 'source' => 'attributions',
                    ];
                    break;
                }
            }
        }

        return $uniques;
    }

    /** Une proposition issue des attributions d'une chambre (nature et matière : celles de ses objets connus). */
    private static function depuisAttribution(array $mc, float $part, Collection $habituel, Collection $dejaVu): ?array
    {
        $cle = $mc['chambre']->id.'|'.Str::lower($mc['expression']);
        if ($dejaVu->has($cle)) {
            return null;
        }
        $dejaVu->put($cle, true);
        $h = $habituel->get($mc['chambre']->libelle);

        return [
            'score' => [$part, count($mc['mots']), 0],
            'id' => null, 'libelle' => Str::ucfirst($mc['expression']),
            'nature_id' => $h['nature']->nature_affaire_id ?? null, 'nature' => $h['nature']->nature?->libelle ?? null,
            'matiere_id' => $h['matiere']->matiere_id ?? null, 'matiere' => $h['matiere']->matiere?->libelle ?? null,
            'chambre_id' => $mc['chambre']->id, 'chambre' => $mc['chambre']->libelle, 'source' => 'attributions',
        ];
    }

    /** Part des mots de l'expression retrouvés dans le texte, ou null si la correspondance est insuffisante. */
    private static function correspondance(array $mots, array $texte): ?float
    {
        if ($mots === []) {
            return null;
        }
        $part = count(array_intersect($mots, $texte)) / count($mots);

        // Tous les mots de l'expression, ou au moins les deux tiers quand elle en compte plusieurs (« détournement de deniers » pour « … deniers publics »).
        return $part >= 1 || (count($mots) >= 3 && $part >= 0.66) ? $part : null;
    }

    /**
     * Les mots-clés tirés des attributions des chambres et de leurs formations. Les mots qui reviennent dans de nombreuses
     * expressions (matière, instance, procédure…) ne servent pas à reconnaître une chambre.
     *
     * @return list<array{expression: string, mots: list<string>, poids: array<string, float>, rares: list<string>, chambre: Chambre}>
     */
    private static function motsClesDesAttributions(Collection $chambres): array
    {
        $brut = [];
        $frequence = [];
        foreach ($chambres as $chambre) {
            $textes = array_filter(array_merge([(string) $chambre->attributions], $chambre->formations->pluck('attributions')->map(fn ($t) => (string) $t)->all()));
            foreach ($textes as $t) {
                foreach (self::expressions($t) as $expression) {
                    $mots = self::mots($expression);
                    foreach (array_unique($mots) as $m) {
                        $frequence[$m] = ($frequence[$m] ?? 0) + 1;
                    }
                    $brut[] = ['expression' => $expression, 'mots' => $mots, 'chambre' => $chambre];
                }
            }
        }

        $generiques = array_map(fn ($m) => self::radical($m), self::MOTS_GENERIQUES);   // même racine que les mots du texte
        $resultat = [];
        foreach ($brut as $b) {
            $utiles = array_values(array_filter($b['mots'], fn ($m) => ! in_array($m, $generiques, true)));
            if ($utiles !== []) {
                $resultat[] = [
                    'expression' => $b['expression'], 'mots' => $utiles, 'chambre' => $b['chambre'],
                    'poids' => array_combine($utiles, array_map(fn ($m) => 1 / max(1, $frequence[$m] ?? 1), $utiles)),
                    // Mots distinctifs : rares dans l'ensemble des attributions.
                    'rares' => array_values(array_filter($utiles, fn ($m) => ($frequence[$m] ?? 0) <= 3 && strlen($m) >= 5)),
                ];
            }
        }

        return $resultat;
    }

    /** « 1ere chambre civile » et « 1ere chambre civile » désignent la même chambre. */
    private static function cleChambre(?string $libelle): string
    {
        return (string) preg_replace('/^(\d+)\s*(?:ere|re|er|ère|ème|eme|e)(?=\s)/iu', '$1', Str::lower(trim((string) $libelle)));
    }

    private static function familleDe(array $registres): string
    {
        return in_array('RP', $registres, true) ? 'penal' : (array_intersect(['RI', 'RE'], $registres) ? 'instruction' : 'civil');
    }
}
