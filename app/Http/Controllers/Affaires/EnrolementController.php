<?php

namespace App\Http\Controllers\Affaires;

use App\Http\Controllers\Controller;
use App\Models\Affaire;
use App\Models\Avocat;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Services\Journal;
use App\Services\NumerotationRegistre;
use App\Support\CalendrierEnrolement;
use App\Support\ParametresAffaires;
use App\Support\EnrolementAffaire;
use App\Support\RapprochementObjet;
use App\Support\RegistreAffaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/*
 | Enrôlement d'une affaire civile (série RG) : mode de saisine, objet du litige saisi librement (l'application propose nature, matière
 | et chambre par mots-clés), parties (demandeurs et défendeurs de natures différentes, avec leur avocat), date de première audience
 | contrôlée par la date limite d'enrôlement, puis attribution du numéro RG-0001/26. Droits : enrolement.voir / creer / deroger.
 */
class EnrolementController extends Controller
{
    /** Listes déroulantes du formulaire (partagées avec la correction d'une affaire). */
    public static function listes(): array
    {
        return [
            'modes' => ModeSaisine::where('actif', true)->where('registre', 'civil')->orderBy('libelle')->get(['id', 'libelle']),
            'natures' => NatureAffaire::where('actif', true)->where('registre', EnrolementAffaire::SERIE)->orderBy('libelle')->get(['id', 'libelle']),
            // Pénal et instruction : mêmes listes, avec leurs propres modes de saisine et natures.
            'modes_penal' => ModeSaisine::where('actif', true)->where('registre', 'penal')->orderBy('libelle')->get(['id', 'libelle']),
            'natures_penal' => NatureAffaire::where('actif', true)->where('registre', 'RP')->orderBy('libelle')->get(['id', 'libelle']),
            'modes_instruction' => ModeSaisine::where('actif', true)->where('registre', 'instruction')->orderBy('libelle')->get(['id', 'libelle']),
            'natures_instruction' => NatureAffaire::where('actif', true)->whereIn('registre', ['RI', 'RE'])->orderBy('libelle')->get(['id', 'libelle', 'registre']),
            'matieres' => Matiere::where('actif', true)->orderBy('libelle')->get(['id', 'libelle']),
            'chambres' => self::chambres(),
        ];
    }

    /** Propose nature, matière et chambre d'après l'objet saisi (recherche par mots-clés). */
    public function suggestions(Request $request): JsonResponse
    {
        abort_unless(Gate::allows('enrolement.creer') || Gate::allows('affaires.modifier'), 403);
        $v = $request->validate(['objet' => ['required', 'string', 'max:1000'], 'registre' => ['nullable', 'in:civil,penal,instruction']]);

        return response()->json(['status' => true, 'propositions' => RapprochementObjet::proposer($v['objet'], 5, RegistreAffaire::depuis($v['registre'] ?? 'civil')['natures'])]);
    }

    /** Prochaines audiences de la chambre avec leur date limite d'enrôlement. */
    public function dates(Request $request): JsonResponse
    {
        abort_unless(Gate::allows('enrolement.creer') || Gate::allows('affaires.modifier'), 403);
        $donnees = $request->validate(['chambre_id' => ['required', 'integer', 'exists:chambres,id'], 'nature_affaire_id' => ['nullable', 'integer'], 'registre' => ['nullable', 'in:civil,penal']]);
        $civil = ($donnees['registre'] ?? 'civil') === 'civil';

        $chambre = Chambre::findOrFail($donnees['chambre_id']);
        $nature = NatureAffaire::find($donnees['nature_affaire_id'] ?? null);

        return response()->json([
            'status' => true, 'exemptee' => ! $civil || (bool) $nature?->exclue_enrolement, 'sans_limite' => ! $civil,
            'jours' => implode(', ', array_map(fn ($j) => ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'][$j], CalendrierEnrolement::joursDAudience($chambre))),
            'dates' => CalendrierEnrolement::prochaines($chambre, 8, null, $civil),
        ]);
    }

    /** Le numéro saisi existe-t-il déjà ? Sert à demander « s'agit-il d'une jonction ? » dès la saisie. */
    public function numero(Request $request): JsonResponse
    {
        abort_unless(Gate::allows('enrolement.creer') || Gate::allows('affaires.modifier'), 403);
        $v = $request->validate(['numero_rg' => ['required', 'regex:/^\d{1,6}\/\d{2}$/'], 'serie' => ['nullable', 'in:RG,RP,RI,RE']]);
        [$n, $annee] = self::decouper($v['numero_rg']);

        $existantes = Affaire::with('parties', 'chambre')->where('serie', $v['serie'] ?? EnrolementAffaire::SERIE)->where('annee', $annee)->where('numero', $n)->get();

        return response()->json([
            'status' => true,
            'existe' => $existantes->isNotEmpty(),
            'affaires' => $existantes->map(fn (Affaire $a) => ['id' => $a->id, 'numero' => $a->numeroAffichage(), 'intitule' => $a->intitule(), 'chambre' => $a->chambre?->libelle, 'annulee' => $a->estAnnulee()])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('enrolement.creer');
        $def = RegistreAffaire::depuis($request->input('registre'));
        // La série d'abord (à l'instruction elle suit la nature), car chaque série a son mode : numéro saisi ou attribué.
        $serie = $def['serie'] ?? (NatureAffaire::whereIn('registre', $def['natures'])->find((int) $request->input('nature_affaire_id'))?->registre ?? 'RI');
        $manuel = ParametresAffaires::numeroManuel($serie);

        $donnees = $request->validate(EnrolementAffaire::regles(true, $manuel, $def), EnrolementAffaire::messages($def));
        $parties = EnrolementAffaire::parties($donnees['parties'], $def);
        $jonction = $manuel && $request->boolean('jonction');
        // Alerte de doublon probable : mêmes personnes qu'une affaire déjà saisie (sans objet pour une jonction).
        $doublons = $jonction ? [] : EnrolementAffaire::doublons($parties, null, $serie);
        if ($doublons !== [] && ! $request->boolean('confirmer_doublon')) {
            return back()->withInput()->with('doublons', $doublons);
        }

        if (! $jonction && filled($donnees['numero_parquet'] ?? null)) {
            $existante = Affaire::where('numero_parquet', trim($donnees['numero_parquet']))->where('statut', '!=', 'annulee')->first();
            if ($existante) {
                throw ValidationException::withMessages(['numero_parquet' => 'Ce numéro du parquet est déjà enrôlé sous '.$existante->numeroAffichage().'. Pour la même affaire, utilisez la jonction.']);
            }
        }

        [$dateAudience, $derogation] = EnrolementAffaire::dateAudience($donnees, $request, Chambre::findOrFail($donnees['chambre_id']), $def);

        $affaire = DB::transaction(function () use ($donnees, $parties, $request, $dateAudience, $derogation, $manuel, $jonction, $serie) {
            $principale = null;
            if ($manuel) {
                [$numero, $annee] = self::decouper($donnees['numero_rg']);
                // Le compteur de l'année sert de verrou : deux saisies simultanées du même numéro s'attendent.
                NumerotationRegistre::verrouiller($serie, $annee);
                $principale = self::controlerNumero($serie, $numero, $annee, $jonction, (int) $donnees['chambre_id']);
            } else {
                $attribue = NumerotationRegistre::attribuer($serie, (int) now()->year);
                [$numero, $annee] = [$attribue['numero'], $attribue['annee']];
            }

            $affaire = Affaire::create([
                'serie' => $serie, 'annee' => $annee, 'numero' => $numero, 'numero_complet' => NumerotationRegistre::formater($serie, $numero, $annee), 'articles' => $donnees['articles'] ?? null, 'numero_parquet' => filled($donnees['numero_parquet'] ?? null) ? trim($donnees['numero_parquet']) : null,
                'numero_saisi' => $manuel, 'jointe_a_id' => $principale?->id, 'meme_numero' => $principale !== null,
                'date_rg' => $manuel ? ($donnees['date_rg'] ?? now()->toDateString()) : now()->toDateString(),
                'mode_saisine_id' => $donnees['mode_saisine_id'], 'objet' => trim($donnees['objet']),
                'nature_affaire_id' => $donnees['nature_affaire_id'], 'matiere_id' => $donnees['matiere_id'], 'chambre_id' => $donnees['chambre_id'],
                'date_premiere_audience' => $dateAudience, 'heure_premiere_audience' => $dateAudience ? ($donnees['heure_premiere_audience'] ?? null) : null, 'derogation_motif' => $derogation, 'derogation_par' => $derogation ? $request->user()?->id : null,
                'statut' => 'enrolee', 'cree_par' => $request->user()?->id,
            ]);
            foreach ($parties as $i => $p) {
                [$p['avocat_id'], $p['avocat']] = Avocat::rattacher($p['avocat'] ?? null, Gate::allows('avocats.creer'));
                $affaire->parties()->create($p + ['ordre' => $i]);
            }

            return $affaire;
        });

        Journal::audit('affaire_enrolee', $affaire, $affaire->numeroAffichage(), [], [
            'numero' => $affaire->numeroAffichage(), 'objet' => $affaire->objet, 'parties' => count($parties), 'doublon_confirme' => $doublons !== [], 'derogation' => $derogation,
            'jointe_a' => $affaire->principale?->numeroAffichage(),
        ]);

        // L'enrôleur peut inscrire dans une chambre hors de son périmètre : il n'en ouvre alors pas la fiche.
        $destination = \App\Support\PerimetreChambres::voit($request->user(), (int) $affaire->chambre_id) ? route('affaires.liste.fiche', $affaire->id) : route('affaires.liste');

        return redirect($destination)->with('success', ($manuel ? 'Affaire enregistrée' : 'Affaire enrôlée').' sous le numéro '.$affaire->numeroAffichage().($jonction ? ' (jonction avec l\'affaire principale)' : '').'.');
    }

    /** « 1391/25 » devient [1391, 2025]. @return array{0: int, 1: int} */
    public static function decouper(string $numero): array
    {
        [$n, $yy] = explode('/', trim($numero));

        return [(int) $n, 2000 + (int) $yy];
    }

    /**
     * Contrôle d'un numéro saisi à la main (dans la transaction, compteur verrouillé) : libre, ou déjà pris.
     * Un numéro déjà pris n'est admis que pour une jonction (même chambre) ; sinon l'affaire est un doublon et la saisie est bloquée.
     * Retourne l'affaire principale en cas de jonction.
     */
    private static function controlerNumero(string $serie, int $numero, int $annee, bool $jonction, int $chambreId): ?Affaire
    {
        $existantes = Affaire::where('serie', $serie)->where('annee', $annee)->where('numero', $numero)->get();
        $libelle = $numero.'/'.sprintf('%02d', $annee % 100);
        $actives = $existantes->where('statut', '!=', 'annulee');

        if ($existantes->isEmpty()) {
            if ($jonction) {
                throw ValidationException::withMessages(['numero_rg' => "Aucune affaire n'a le numéro $libelle : ce n'est pas une jonction. Désactivez l'interrupteur « Jonction »."]);
            }

            return null;
        }
        if ($actives->isEmpty()) {
            throw ValidationException::withMessages(['numero_rg' => "Le numéro $libelle appartient à une affaire annulée : un numéro n'est jamais réutilisé."]);
        }
        if (! $jonction) {
            throw ValidationException::withMessages(['numero_rg' => "Doublon : le numéro $libelle est déjà enregistré (".$actives->first()->intitule().'). '
                ."S'il s'agit d'une jonction, activez l'interrupteur « Jonction » ; sinon cette affaire est un doublon et ne peut pas être enregistrée."]);
        }

        $principale = $actives->firstWhere('jointe_a_id', null) ?? Affaire::find($actives->first()->jointe_a_id);
        if ((int) $principale->chambre_id !== $chambreId) {
            throw ValidationException::withMessages(['chambre_id' => "Une jonction se fait dans la même chambre : l'affaire $libelle est dans une autre chambre."]);
        }

        return $principale;
    }

    /** @return \Illuminate\Support\Collection<int, Chambre> */
    public static function chambres()
    {
        return Chambre::whereIn('annee_judiciaire_id', AnneeJudiciaire::where('courante', true)->select('id'))->where('actif', true)->orderBy('ordre')->get(['id', 'libelle', 'intitule']);
    }
}
