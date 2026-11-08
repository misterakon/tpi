<?php

namespace App\Support\Redaction;

use App\Models\BlocTexte;
use App\Models\ModeleDecision;
use App\Models\ModeleDecisionVersion;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Le formulaire de saisie d'une décision, déduit du modèle : une zone par variable que le texte du modèle utilise (catalogue
 * commun et variables propres au modèle). Chaque champ s'adapte au type de la variable : date, nombre, genre, liste, personne…
 * Les valeurs saisies sont conservées à plat (code => valeur) puis rangées en ensembles pour le gabarit (« demandeur.nom »).
 */
class ChampsDecision
{
    /** Types dont les données se saisissent d'un seul tenant (les parties d'un ensemble comme « demandeur » sont déjà des variables à part). */
    private const COMPOSES = ['personne', 'personnes'];

    /**
     * Les champs à remplir pour une version de modèle, dans l'ordre du catalogue.
     *
     * @return list<array{code: string, libelle: string, type: string, groupe: string, exemple: mixed}>
     */
    public static function pour(ModeleDecision $modele, ModeleDecisionVersion $version): array
    {
        $propres = $modele->variables->map(fn ($v) => ['code' => $v->code, 'libelle' => $v->libelle, 'type' => $v->type, 'exemple' => $v->exemple])->all();
        $blocs = BlocTexte::where('actif', true)->pluck('contenu', 'code')->all();

        $utilisees = [];
        foreach ([$version->entete, $version->qualite, $version->factum] as $texte) {
            $utilisees = array_merge($utilisees, Gabarit::variables((string) $texte, $blocs));
        }

        // Une personne écrite avec une civilité imposée (« greffier|personne:Maître ») n'a pas besoin de son genre dans ce modèle.
        $texte = collect([$version->entete, $version->qualite, $version->factum])->merge($blocs)->implode(' ');
        $sansGenre = [];
        if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*((?:\|[^}]*)?)\}\}/u', $texte, $m, PREG_SET_ORDER)) {
            foreach ($m as $t) {
                $imposee = (bool) preg_match('/^\|\s*personne\s*:\s*\S/u', $t[2]);
                $sansGenre[$t[1]] = ($sansGenre[$t[1]] ?? true) && $imposee;
            }
        }

        $champs = [];
        foreach (VariablesDecision::toutes($propres) as $groupe => $variables) {
            foreach ($variables as $v) {
                // Un ensemble déjà décrit par ses éléments (« demandeur » par « demandeur.nom », « demandeur.genre »…) n'a pas de champ à lui.
                if (in_array($v['type'], self::COMPOSES, true) && $v['exemple'] === null) {
                    continue;
                }
                if (str_ends_with($v['code'], '.genre') && ($sansGenre[substr($v['code'], 0, -6)] ?? false) && ! in_array($v['code'], $utilisees, true)) {
                    continue;   // le genre ne servirait à rien dans ce modèle
                }
                if (preg_match('/^assesseurs\.\d+\./', $v['code']) && in_array('assesseurs', $utilisees, true)) {
                    continue;   // les assesseurs se saisissent en liste (autant que nécessaire), pas un par un
                }
                if (in_array($v['code'], Preremplissage::AUTOMATIQUES, true)) {
                    continue;   // connu de l'application : jamais demandé
                }
                if (self::estUtilisee($v['code'], $utilisees)) {
                    $champs[] = ($v['code'] === 'demandeur.naissance' ? ['type' => 'naissance'] : []) + $v + ['groupe' => $groupe, 'options' => self::options($v['code'])];
                }
            }
        }

        return $champs;
    }

    /** Liste de choix d'un champ (fonctions et modes de comparution viennent de la base, le reste de la configuration), ou null. */
    private static function options(string $code): ?array
    {
        $liste = match ($code) {
            'president.fonction' => \App\Models\Agent::FONCTIONS,
            'demandeur.nationalite' => config('nationalites'),
            'nature_decision' => \App\Models\CaractereDecision::where('actif', true)->orderBy('ordre')->pluck('libelle')->map(fn ($l) => mb_strtolower($l))->all(),
            'demandeur.comparution' => \App\Models\ModeComparution::where('actif', true)->orderBy('ordre')->pluck('libelle')->map(fn ($l) => mb_strtolower($l))->all(),
            default => config('tpi.listes_decision.'.$code),
        };

        return $liste ?: null;
    }

    /** code => libellé, pour nommer les champs vides dans le texte (« demandeur » vient de « Demandeur : nom et prénoms »). */
    public static function libelles(array $champs): array
    {
        $libelles = [];
        foreach (VariablesDecision::standard() as $variables) {
            foreach ($variables as $v) {
                $libelles[$v['code']] = $v['libelle'];
            }
        }
        foreach ($champs as $c) {
            $libelles[$c['code']] = $c['libelle'];
            if (str_ends_with($c['code'], '.nom') && str_contains($c['libelle'], ' : ')) {
                $libelles[substr($c['code'], 0, -4)] = explode(' : ', $c['libelle'])[0];
            }
        }

        return $libelles;
    }

    /** Les champs vides, avec leur libellé, pour la liste « À compléter ». @return list<array{code: string, libelle: string}> */
    public static function aCompleter(array $champs, array $manquantes): array
    {
        $libelles = self::libelles($champs);

        return array_map(fn ($code) => ['code' => $code, 'libelle' => $libelles[$code] ?? $code], $manquantes);
    }

    /** Une variable est utilisée si le texte l'appelle, ou appelle l'ensemble dont elle fait partie (« demandeur|personne » utilise demandeur.nom). */
    private static function estUtilisee(string $code, array $utilisees): bool
    {
        foreach ($utilisees as $u) {
            if ($u === $code || str_starts_with($code, $u.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valeurs saisies, nettoyées et contrôlées champ par champ.
     *
     * @param  list<array{code: string, libelle: string, type: string}>  $champs
     * @return array<string, mixed> code => valeur (les champs laissés vides sont omis)
     *
     * @throws ValidationException
     */
    public static function nettoyer(array $champs, array $saisie): array
    {
        $valeurs = [];
        $erreurs = [];

        foreach ($champs as $champ) {
            $code = $champ['code'];
            $brut = $saisie[$code] ?? null;

            try {
                $valeur = self::convertir($champ['type'], $brut);
            } catch (\InvalidArgumentException $e) {
                $erreurs['valeurs.'.$code] = '« '.$champ['libelle'].' » : '.$e->getMessage();

                continue;
            }

            if ($valeur !== null && $valeur !== '' && $valeur !== []) {
                $valeurs[$code] = $valeur;
            }
        }

        if ($erreurs) {
            throw ValidationException::withMessages($erreurs);
        }

        return $valeurs;
    }

    /** @throws \InvalidArgumentException */
    private static function convertir(string $type, mixed $brut): mixed
    {
        switch ($type) {
            case 'date':
                $t = trim((string) $brut);
                if ($t === '') {
                    return null;
                }
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) || ! checkdate((int) substr($t, 5, 2), (int) substr($t, 8, 2), (int) substr($t, 0, 4))) {
                    throw new \InvalidArgumentException('date invalide.');
                }

                return $t;
            case 'nombre':
                $t = trim((string) $brut);
                if ($t === '') {
                    return null;
                }
                if (! preg_match('/^\d{1,9}$/', $t)) {
                    throw new \InvalidArgumentException('un nombre entier est attendu.');
                }

                return (int) $t;
            case 'genre':
                $t = trim((string) $brut);
                if ($t === '') {
                    return null;
                }
                if (! in_array($t, ['m', 'f'], true)) {
                    throw new \InvalidArgumentException('choisissez masculin ou féminin.');
                }

                return $t;
            case 'liste':
                $lignes = is_array($brut) ? $brut : preg_split('/\r?\n|;/', (string) $brut);

                return array_values(array_filter(array_map(fn ($l) => self::texte($l), $lignes), fn ($l) => $l !== ''));
            case 'naissance':
                // Date exacte (« le 01 mars 1970 ») ou année seulement (« vers 1970 ») quand le jour est inconnu.
                $brut = is_array($brut) ? $brut : [];
                if (($brut['mode'] ?? '') === 'annee') {
                    $annee = trim((string) ($brut['annee'] ?? ''));
                    if ($annee === '') {
                        return null;
                    }
                    if (! preg_match('/^\d{4}$/', $annee) || (int) $annee < 1800 || (int) $annee > (int) date('Y')) {
                        throw new \InvalidArgumentException("année invalide (quatre chiffres, par exemple 1970).");
                    }

                    return ['mode' => 'annee', 'annee' => $annee];
                }
                $date = trim((string) ($brut['date'] ?? ''));
                if ($date === '') {
                    return null;
                }
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                    throw new \InvalidArgumentException('date invalide.');
                }

                return ['mode' => 'date', 'date' => $date];
            case 'personne':
                return self::personne($brut);
            case 'personnes':
                $personnes = [];
                foreach (is_array($brut) ? $brut : [] as $p) {
                    if ($personne = self::personne($p)) {
                        $personnes[] = $personne;
                    }
                }

                return $personnes;
            default:
                $t = self::texte($brut);

                return $t === '' ? null : $t;
        }
    }

    private static function personne(mixed $brut): ?array
    {
        $brut = is_array($brut) ? $brut : [];
        $nom = self::texte($brut['nom'] ?? '');
        $genre = trim((string) ($brut['genre'] ?? ''));
        if ($nom === '') {
            return null;
        }
        if (! in_array($genre, ['m', 'f'], true)) {
            throw new \InvalidArgumentException('indiquez le genre de '.$nom.'.');
        }

        return ['nom' => $nom, 'genre' => $genre];
    }

    /** Texte d'une ligne : sans balise ni retour à la ligne, 500 caractères au plus. */
    private static function texte(mixed $valeur): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $valeur))), 0, 500);
    }

    /** Range les valeurs à plat en ensembles pour le gabarit : « demandeur.nom » devient demandeur[nom]. */
    public static function enEnsembles(array $valeurs): array
    {
        $ensemble = [];
        foreach ($valeurs as $code => $valeur) {
            Arr::set($ensemble, $code, $valeur);
        }

        return $ensemble;
    }

    /** Remplit un champ avec l'exemple fictif du catalogue (essais et formations). */
    public static function exemplesPour(array $champs): array
    {
        $exemples = [];
        foreach ($champs as $c) {
            if ($c['exemple'] === null || $c['exemple'] === '') {
                continue;
            }
            if ($c['type'] === 'naissance') {
                $exemples[$c['code']] = ['mode' => 'date', 'date' => '1970-03-01'];

                continue;
            }
            // Une liste à valeurs codées (fonctions) : l'exemple écrit en toutes lettres est retrouvé dans la liste.
            if (! empty($c['options']) && ! array_is_list($c['options']) && ! isset($c['options'][$c['exemple']])) {
                $cle = collect(array_keys($c['options']))->first(fn ($k) => Preremplissage::fonctionEnTexte($k, 'm') === $c['exemple']);
                if ($cle) {
                    $exemples[$c['code']] = $cle;
                }

                continue;
            }
            $exemples[$c['code']] = in_array($c['type'], ['liste', 'personne', 'personnes'], true) && is_string($c['exemple'])
                ? (json_decode($c['exemple'], true) ?? array_values(array_filter(array_map('trim', preg_split('/\r?\n|;/', $c['exemple'])))))
                : $c['exemple'];
        }

        return $exemples;
    }
}
