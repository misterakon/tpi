<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\AnneeJudiciaire;
use App\Models\Ordonnance;
use App\Services\Journal;
use App\Support\TexteRiche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Archive des ordonnances signées par la Présidente : dépôt du PDF, lecture dans l'application et téléchargement.
 * Le fichier n'est jamais modifiable (une erreur se corrige en supprimant puis en redéposant) ; il est servi uniquement
 * à travers l'application, après contrôle des droits. Droits : ordonnances.voir / creer / modifier / supprimer.
 */
class OrdonnanceController extends Controller
{
    private const TAILLE_MAX_KO = 30720;   // 30 Mo

    private const CHAMPS = ['annee_judiciaire_id', 'type', 'numero', 'objet', 'date_signature', 'date_effet', 'observations'];

    public function index(): View
    {
        Gate::authorize('ordonnances.voir');

        return view('organisation.ordonnances.index', [
            'lignes' => Ordonnance::with(['annee', 'deposePar'])->orderByDesc('date_signature')->orderByDesc('id')->get(),
            'annees' => AnneeJudiciaire::orderByDesc('debut')->get(),
        ]);
    }

    /** Page de lecture : le PDF s'affiche directement dans l'application. */
    public function consulter(int $id): View
    {
        Gate::authorize('ordonnances.voir');

        return view('organisation.ordonnances.lecture', ['o' => Ordonnance::with(['annee', 'deposePar'])->findOrFail($id)]);
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('ordonnances.voir');
        $o = Ordonnance::findOrFail($id);

        return response()->json([
            'id' => $o->id, 'annee_judiciaire_id' => $o->annee_judiciaire_id, 'type' => $o->type, 'numero' => $o->numero, 'objet' => $o->objet,
            'date_signature' => $o->date_signature?->format('Y-m-d'), 'date_effet' => $o->date_effet?->format('Y-m-d'), 'observations' => $o->observations,
            'nom_original' => $o->nom_original, 'taille' => $o->tailleLisible(),
        ]);
    }

    /** Dépôt (id_ vide : le PDF est obligatoire) ou modification des informations (id_ renseigné : le PDF reste inchangé). */
    public function store(Request $request): RedirectResponse
    {
        $o = $request->filled('id_') ? Ordonnance::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($o ? 'ordonnances.modifier' : 'ordonnances.creer');

        $request->merge(['observations' => TexteRiche::nettoyer($request->input('observations'))]);

        $donnees = $request->validate([
            'annee_judiciaire_id' => ['required', 'integer', 'exists:annees_judiciaires,id'],
            'type' => ['required', Rule::in(array_keys(Ordonnance::TYPES))],
            'numero' => ['required', 'string', 'max:60'],
            'objet' => ['nullable', 'string', 'max:255'],
            'date_signature' => ['required', 'date', 'before_or_equal:today'],
            'date_effet' => ['nullable', 'date'],
            'observations' => ['nullable', 'string', 'max:20000'],
            'fichier' => [$o ? 'prohibited' : 'required', 'file', 'mimes:pdf', 'max:'.self::TAILLE_MAX_KO],
        ], [
            'annee_judiciaire_id.required' => "Choisissez l'année judiciaire.", 'type.required' => "Choisissez le type d'ordonnance.", 'type.in' => "Type d'ordonnance invalide.",
            'numero.required' => "Le numéro de l'ordonnance est obligatoire.", 'date_signature.required' => 'La date de signature est obligatoire.',
            'date_signature.date' => 'La date de signature est invalide.', 'date_signature.before_or_equal' => 'La date de signature ne peut pas être dans le futur.',
            'date_effet.date' => "La date d'effet est invalide.", 'observations.max' => 'Les observations sont trop longues.',
            'fichier.required' => "Choisissez le fichier PDF de l'ordonnance signée.", 'fichier.mimes' => 'Le fichier doit être un PDF.',
            'fichier.uploaded' => 'Le téléversement a échoué : fichier trop volumineux (30 Mo au maximum).',
            'fichier.max' => 'Le fichier est trop volumineux (30 Mo au maximum).', 'fichier.prohibited' => 'Le PDF archivé ne peut pas être remplacé.',
        ]);

        $donnees['numero'] = trim($donnees['numero']);
        $donnees['objet'] = filled($donnees['objet'] ?? null) ? trim($donnees['objet']) : null;
        $donnees['observations'] = $donnees['observations'] ?? null;
        $donnees['date_effet'] = $donnees['date_effet'] ?? null;
        unset($donnees['fichier']);

        if ($o) {
            $avant = $this->valeurs($o);
            $o->forceFill($donnees)->save();
            Journal::audit('ordonnance_modifie', $o, $o->libelle(), $avant, $this->valeurs($o->refresh()));

            return redirect()->route('organisation.ordonnances')->with('success', "Informations de l'ordonnance modifiées.");
        }

        $fichier = $request->file('fichier');
        $debut = (string) @file_get_contents($fichier->getRealPath(), false, null, 0, 8);
        if (! str_starts_with($debut, '%PDF-')) {
            throw ValidationException::withMessages(['fichier' => "Ce fichier n'est pas un vrai PDF."]);
        }

        $empreinte = hash_file('sha256', $fichier->getRealPath());
        if ($doublon = Ordonnance::where('empreinte', $empreinte)->first()) {
            throw ValidationException::withMessages(['fichier' => 'Ce fichier est déjà archivé ('.$doublon->libelle().').']);
        }

        $chemin = Str::lower((string) Str::ulid()).'.pdf';
        Storage::disk('local')->putFileAs(Ordonnance::DOSSIER, $fichier, $chemin);

        $o = Ordonnance::create($donnees + [
            'chemin' => $chemin, 'nom_original' => Str::limit(preg_replace('/[\x00-\x1F\x7F]/u', '', $fichier->getClientOriginalName()), 190, ''),
            'taille' => $fichier->getSize(), 'empreinte' => $empreinte, 'depose_par' => $request->user()?->id,
        ]);

        Journal::audit('ordonnance_archivee', $o, $o->libelle(), [], $this->valeurs($o) + ['empreinte' => substr($empreinte, 0, 16).'…']);

        return redirect()->route('organisation.ordonnances')->with('success', 'Ordonnance archivée.');
    }

    /** Affichage dans le navigateur (cadre de la page de lecture). */
    public function lire(int $id): StreamedResponse
    {
        Gate::authorize('ordonnances.voir');

        return $this->servir(Ordonnance::findOrFail($id), 'inline');
    }

    public function telecharger(int $id): StreamedResponse
    {
        Gate::authorize('ordonnances.voir');
        $o = Ordonnance::findOrFail($id);
        Journal::audit('ordonnance_telechargee', $o, $o->libelle());

        return $this->servir($o, 'attachment');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('ordonnances.supprimer');
        $o = Ordonnance::findOrFail($id);

        Journal::audit('ordonnance_supprime', $o, $o->libelle(), $this->valeurs($o) + ['fichier' => $o->nom_original]);
        Storage::disk('local')->delete(Ordonnance::DOSSIER.'/'.$o->chemin);
        $o->delete();

        return response()->json(['status' => true, 'message' => "Ordonnance supprimée de l'archive."]);
    }

    private function servir(Ordonnance $o, string $disposition): StreamedResponse
    {
        $chemin = Ordonnance::DOSSIER.'/'.$o->chemin;
        abort_unless(Storage::disk('local')->exists($chemin), 404, 'Le fichier de cette ordonnance est introuvable.');

        $nom = 'Ordonnance-'.Str::slug($o->numero).'.pdf';

        return Storage::disk('local')->response($chemin, $nom, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }

    /** @return array<string, mixed> */
    private function valeurs(Ordonnance $o): array
    {
        $v = $o->only(self::CHAMPS);
        $v['date_signature'] = $o->date_signature?->format('Y-m-d');
        $v['date_effet'] = $o->date_effet?->format('Y-m-d');

        return $v;
    }
}
