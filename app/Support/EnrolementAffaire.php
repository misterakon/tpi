<?php

namespace App\Support;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\NatureAffaire;
use App\Models\Partie;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/*
 | Règles communes à l'enrôlement et à la correction d'une affaire : contrôle du formulaire, nettoyage des parties, alerte de doublon,
 | contrôle de la date d'audience (date limite d'enrôlement, dérogation de la Présidente).
 */
class EnrolementAffaire
{
    public const SERIE = 'RG';

    public static function regles(bool $creation, bool $manuel, ?array $def = null): array
    {
        $def ??= RegistreAffaire::depuis('civil');
        $civil = $def['cle'] === 'civil';

        return [
            'mode_saisine_id' => ['required', 'integer', Rule::exists('modes_saisine', 'id')->where('registre', $def['modes'])],
            'objet' => ['required', 'string', 'max:1000'],
            'articles' => ['nullable', 'string', 'max:300'],
            'numero_parquet' => ['nullable', 'string', 'max:40'],
            'nature_affaire_id' => ['required', 'integer', Rule::exists('natures_affaire', 'id')->whereIn('registre', $def['natures'])],
            'matiere_id' => ['required', 'integer', 'exists:matieres,id'],
            'chambre_id' => $creation ? ['required', 'integer', 'exists:chambres,id'] : ['nullable'],
            // Mode manuel : le greffe gère l'enrôlement, on reporte sa date sans contrôle ; sinon la date ne peut pas être passée.
            'date_premiere_audience' => $manuel || ! $civil ? ['nullable', 'date'] : ['nullable', 'date', 'after_or_equal:today'],
            'heure_premiere_audience' => ['nullable', 'date_format:H:i'],
            'derogation_motif' => ['nullable', 'string', 'max:300'],
            'numero_rg' => $creation && $manuel ? ['required', 'regex:/^\d{1,6}\/\d{2}$/'] : ['nullable'],
            'date_rg' => $creation && $manuel ? ['nullable', 'date', 'before_or_equal:today'] : ['nullable'],
            'jonction' => ['nullable', 'boolean'],
            'parties' => ['required', 'array', 'min:1', 'max:30'],
            'parties.*.role' => ['required', Rule::in($def['roles'])],
            'parties.*.nature' => ['required', Rule::in($def['natures_partie'])],
            'parties.*.nom' => ['nullable', 'string', 'max:160'],
            'parties.*.prenoms' => ['nullable', 'string', 'max:200'],
            'parties.*.denomination' => ['nullable', 'string', 'max:250'],
            'parties.*.naissance_date' => ['nullable', 'date', 'before_or_equal:today'],
            'parties.*.naissance_annee' => ['nullable', 'integer', 'between:1800,'.date('Y')],
            'parties.*.lieu_naissance' => ['nullable', 'string', 'max:120'],
            'parties.*.nationalite' => ['nullable', 'string', 'max:60'],
            'parties.*.profession' => ['nullable', 'string', 'max:120'],
            'parties.*.domicile' => ['nullable', 'string', 'max:250'],
            'parties.*.mandat_depot_date' => ['nullable', 'date', 'before_or_equal:today'],
            'parties.*.lieu_detention' => ['nullable', 'string', 'max:160'],
            'parties.*.echeance_detention' => ['nullable', 'date', 'after_or_equal:parties.*.mandat_depot_date'],
            'parties.*.libere_le' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:parties.*.mandat_depot_date'],
            'parties.*.avocat' => ['nullable', 'string', 'max:200'],
            'parties.*.autres' => ['nullable', 'integer', 'between:0,999'],
        ];
    }

    public static function messages(?array $def = null): array
    {
        $def ??= RegistreAffaire::depuis('civil');

        return [
            'mode_saisine_id.required' => 'Choisissez le mode de saisine.', 'objet.required' => ($def['objet'] === 'Objet du litige' ? "L'objet du litige" : 'La '.mb_strtolower($def['objet'])).' est obligatoire.',
            'nature_affaire_id.required' => "Choisissez la nature de l'affaire.", 'matiere_id.required' => 'Choisissez la matière.', 'chambre_id.required' => 'Choisissez la chambre.',
            'heure_premiere_audience.date_format' => "L'heure doit être au format HH:MM (ex. 09:00).", 'date_premiere_audience.after_or_equal' => 'La date de première audience ne peut pas être passée.', 'numero_rg.required' => 'Saisissez le numéro RG tel qu\'il figure au greffe (ex. 1391/25).', 'numero_rg.regex' => 'Le numéro RG s\'écrit « numéro/année sur deux chiffres », par exemple 1391/25.', 'date_rg.before_or_equal' => 'La date du RG ne peut pas être dans le futur.', 'parties.required' => 'Ajoutez au moins une partie.',
        ];
    }

    /** Parties nettoyées et contrôlées : une personne physique a un nom, les autres une dénomination ; au moins un demandeur. */
    public static function parties(array $saisies, ?array $def = null): array
    {
        $def ??= RegistreAffaire::depuis('civil');
        $nettoyer = fn ($v) => ($t = trim(strip_tags((string) $v))) === '' ? null : $t;
        $parties = [];
        $erreurs = [];

        foreach (array_values($saisies) as $i => $s) {
            $physique = in_array($s['nature'], ['homme', 'femme'], true);
            $nom = $nettoyer($s['nom'] ?? null);
            $denomination = $nettoyer($s['denomination'] ?? null);

            if ($physique && ! $nom) {
                $erreurs["parties.$i.nom"] = 'Le nom est obligatoire pour une personne physique.';
            }
            if ($s['nature'] === 'mp' && ! $denomination) {
                $denomination = 'MP';
            }
            if (! $physique && ! $denomination) {
                $erreurs["parties.$i.denomination"] = 'La dénomination est obligatoire.';
            }

            // La date exacte l'emporte ; sinon l'année seule (« né vers 1970 »).
            $mode = ! $physique ? null : (! empty($s['naissance_date']) ? 'date' : (! empty($s['naissance_annee']) ? 'annee' : null));
            $parties[] = [
                'role' => $s['role'], 'nature' => $s['nature'],
                'nom' => $physique ? $nom : null, 'prenoms' => $physique ? $nettoyer($s['prenoms'] ?? null) : null,
                'denomination' => $physique ? null : $denomination,
                'cle' => Partie::cle($physique ? $nom.' '.$nettoyer($s['prenoms'] ?? null) : (string) $denomination),
                'naissance_mode' => $mode,
                'naissance_date' => $mode === 'date' ? $s['naissance_date'] : null,
                'naissance_annee' => $mode === 'annee' ? $s['naissance_annee'] : null,
                'lieu_naissance' => $physique ? $nettoyer($s['lieu_naissance'] ?? null) : null,
                'nationalite' => $physique ? $nettoyer($s['nationalite'] ?? null) : null,
                'profession' => $physique ? $nettoyer($s['profession'] ?? null) : null,
                'domicile' => $nettoyer($s['domicile'] ?? null), 'avocat' => $nettoyer($s['avocat'] ?? null), 'autres' => (int) ($s['autres'] ?? 0),
                'mandat_depot_date' => $detenable = in_array($s['role'], ['prevenu', 'inculpe'], true) ? ($s['mandat_depot_date'] ?? null) : null,
                'lieu_detention' => $detenable ? $nettoyer($s['lieu_detention'] ?? null) : null,
                'echeance_detention' => $detenable ? ($s['echeance_detention'] ?? null) : null,
                'libere_le' => $detenable ? ($s['libere_le'] ?? null) : null,
            ];
        }

        if (! collect($parties)->contains('role', $def['principal'])) {
            $erreurs['parties'] = 'Ajoutez au moins un '.mb_strtolower(Partie::ROLES[$def['principal']]).'.';
        }
        if ($erreurs) {
            throw ValidationException::withMessages($erreurs);
        }

        return $parties;
    }

    /** Affaires non annulées qui ont exactement les mêmes demandeurs et défendeurs. @return list<array{id: int, numero: string, objet: string}> */
    public static function doublons(array $parties, ?int $sauf = null, string $serie = self::SERIE): array
    {
        // Les personnes de chaque rôle, triées : deux affaires qui ont exactement les mêmes sont probablement la même.
        $empreinte = fn (array $liste) => collect($liste)->groupBy('role')->map(fn ($g) => $g->pluck('cle')->unique()->sort()->values()->all())->sortKeys()->all();
        $nouvelle = $empreinte($parties);

        $candidates = Partie::whereIn('cle', collect($parties)->pluck('cle')->all())->pluck('affaire_id')->unique();
        $trouvees = [];
        foreach (Affaire::with('parties')->whereIn('id', $candidates)->where('serie', $serie)->where('statut', '!=', 'annulee')->when($sauf, fn ($q) => $q->where('id', '!=', $sauf))->get() as $a) {
            $existantes = $a->parties->map(fn (Partie $p) => ['role' => $p->role, 'cle' => $p->cle])->all();
            if ($empreinte($existantes) === $nouvelle) {
                $trouvees[] = ['id' => $a->id, 'numero' => $a->numeroAffichage(), 'objet' => $a->objet];
            }
        }

        return $trouvees;
    }

    /**
     * Date de première audience : contrôlée (fermeture, jour d'audience de la chambre, date limite d'enrôlement) puis, si elle est vide,
     * la plus proche audience encore ouverte est proposée. Les référés et voies d'exécution ne sont pas soumis au contrôle.
     * Une date limite dépassée demande la dérogation de la Présidente (droit « enrolement.deroger ») avec un motif.
     *
     * @return array{0: ?string, 1: ?string} [date, motif de dérogation]
     */
    public static function dateAudience(array $donnees, Request $request, Chambre $chambre, ?array $def = null): array
    {
        $date = $donnees['date_premiere_audience'] ?? null;
        // La date limite d'enrôlement et les audiences proposées ne concernent que le civil.
        if (($def['cle'] ?? 'civil') !== 'civil') {
            return [$date, null];
        }
        // Mode manuel : l'enrôlement et sa date limite relèvent du greffe, on ne contrôle rien.
        if (ParametresAffaires::numeroManuel('RG')) {
            return [$date, null];
        }
        $nature = NatureAffaire::find($donnees['nature_affaire_id']);
        if ($nature?->exclue_enrolement) {
            return [$date, null];
        }
        if (! $date) {
            return [CalendrierEnrolement::proposer($chambre), null];
        }

        $c = CalendrierEnrolement::controler($chambre, Carbon::parse($date));
        if ($c['ferme']) {
            throw ValidationException::withMessages(['date_premiere_audience' => 'Le tribunal est fermé ce jour-là ('.$c['ferme'].').']);
        }
        if ($c['jour_siege'] === false) {
            throw ValidationException::withMessages(['date_premiere_audience' => "Cette chambre ne tient pas d'audience ce jour-là (jours d'audience : ".$c['jours'].').']);
        }
        if ($c['depassee']) {
            $motif = trim((string) $request->input('derogation_motif'));
            $peutDeroger = Gate::allows('enrolement.deroger');
            if ($peutDeroger && mb_strlen($motif) >= 5) {
                return [$date, $motif];
            }
            $suite = CalendrierEnrolement::proposer($chambre);
            session()->flash('derogation', $peutDeroger);
            throw ValidationException::withMessages(['date_premiere_audience' => "La date limite d'enrôlement pour cette audience est dépassée ("
                .CalendrierEnrolement::libelleLimite($c['limite']).').'.($suite ? ' Première audience possible : '.CalendrierEnrolement::libelleDate(Carbon::parse($suite)).'.' : '')
                .($peutDeroger ? " Indiquez le motif de la dérogation pour l'enrôler quand même." : " Seule la Présidente peut accorder une dérogation.")]);
        }

        return [$date, null];
    }
}
