<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentAlias;
use App\Models\ImportOrdonnance;
use App\Services\Journal;
use App\Support\Organisation\Import\Applicateur;
use App\Support\Organisation\Import\Classeur;
use App\Support\Organisation\Import\Comparateur;
use App\Support\Organisation\Import\Controle;
use App\Support\Organisation\Import\Noms;
use App\Support\Organisation\Import\Progression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/*
 | Chargement de l'ordonnance par fichier Excel : déposer le fichier, le contrôler, rapprocher les noms douteux, comparer, appliquer, annuler.
 | Un fichier déposé est un BROUILLON : rien n'est modifié dans l'organisation tant qu'il n'est pas appliqué. Il est conservé (et re-lu à chaque
 | correction des choix). Droit : ordonnances.importer (la Présidente ; l'administrateur a tous les droits).
 */
class ImportOrdonnanceController extends Controller
{
    private const DOSSIER = 'imports_ordonnance';

    public function index(): View
    {
        Gate::authorize('ordonnances.importer');

        return view('organisation.ordonnances.import.index', [
            'imports' => ImportOrdonnance::with('auteur:id,name')->orderByDesc('id')->limit(30)->get(),
            'modes' => ImportOrdonnance::MODES,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('ordonnances.importer');
        $donnees = $request->validate([
            'fichier' => ['required', 'file', 'max:10240', 'extensions:xlsx'],
            'mode' => ['required', Rule::in(array_keys(ImportOrdonnance::MODES))],
        ], ['fichier.required' => 'Choisissez le fichier Excel (.xlsx) à charger.', 'fichier.extensions' => 'Le fichier doit être un classeur Excel enregistré en .xlsx.', 'fichier.max' => 'Le fichier dépasse 10 Mo.']);

        $fichier = $request->file('fichier');
        $empreinte = sha1_file($fichier->getRealPath());
        if ($deja = ImportOrdonnance::where('empreinte', $empreinte)->where('statut', 'applique')->first()) {
            $message = 'Ce fichier a déjà été appliqué le '.$deja->applique_le?->format('d/m/Y à H:i').'. Corrigez-le et rechargez-le, ou annulez cet import.';

            return $request->expectsJson() ? response()->json(['ok' => false, 'message' => $message], 422) : back()->withInput()->with('error', $message);
        }

        $import = ImportOrdonnance::create([
            'user_id' => $request->user()?->id, 'nom_fichier' => mb_substr(preg_replace('/[^\p{L}\p{N}\-_. ()]+/u', '_', $fichier->getClientOriginalName()), 0, 200),
            'empreinte' => $empreinte, 'mode' => $donnees['mode'], 'statut' => 'brouillon',
        ]);
        Storage::disk('local')->putFileAs(self::DOSSIER, $fichier, $import->id.'.xlsx');
        Journal::audit('import_ordonnance_charge', $import, 'Fichier '.$import->nom_fichier, [], ['mode' => $import->mode]);

        // Écran : le fichier est déposé ; l'analyse se lance dans une seconde requête, dont l'avancement est suivi à l'écran.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $import->id, 'analyser' => route('organisation.ordonnances.import.analyser', $import->id), 'progression' => route('organisation.ordonnances.import.progression', $import->id), 'voir' => route('organisation.ordonnances.import.show', $import->id)]);
        }
        try {
            $this->analyser($import);
        } catch (RuntimeException $e) {
            $this->retirer($import);

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('organisation.ordonnances.import.show', $import->id);
    }

    /** Lance (ou relance) la lecture et le contrôle du fichier conservé ; l'avancement se lit par « progression ». */
    public function analyserFichier(int $id): JsonResponse
    {
        Gate::authorize('ordonnances.importer');
        $import = $this->brouillon($id);
        try {
            $this->analyser($import);
        } catch (RuntimeException $e) {
            Progression::fin($import->id, 'Échec');
            if (! ($import->controle ?? null)) {
                $this->retirer($import);   // un fichier jamais lu avec succès n'a rien à garder
            }

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        $k = $import->fresh()->controle ?? [];

        return response()->json([
            'ok' => true, 'voir' => route('organisation.ordonnances.import.show', $import->id),
            'erreurs' => count($k['erreurs'] ?? []), 'avertissements' => count($k['avertissements'] ?? []), 'noms' => count($k['noms'] ?? []), 'changements' => $k['nb_changements'] ?? null,
        ]);
    }

    /** Où en est la lecture du fichier (interrogé par l'écran). */
    public function progression(int $id): JsonResponse
    {
        Gate::authorize('ordonnances.importer');
        ImportOrdonnance::findOrFail($id);

        return response()->json(Progression::lire($id) ?? ['etape' => 'verification', 'pct' => 0, 'message' => 'En attente…', 'termine' => false, 'tick' => 0])->header('Cache-Control', 'no-store');
    }

    public function show(int $id): View
    {
        Gate::authorize('ordonnances.importer');
        $import = ImportOrdonnance::with('auteur:id,name')->findOrFail($id);
        if ($import->enCours() && $import->controle === null) {
            $this->analyser($import);   // arrivé ici sans passer par l'écran de chargement (lien direct, navigateur sans script)
            $import->refresh();
        }

        $agents = Agent::orderBy('nom')->orderBy('prenoms')->get(['id', 'nom', 'prenoms'])->map(fn ($a) => ['id' => $a->id, 'nom' => trim($a->nom.' '.$a->prenoms)])->all();

        return view('organisation.ordonnances.import.show', ['import' => $import, 'agents' => $agents, 'modes' => ImportOrdonnance::MODES]);
    }

    /** Enregistre les choix pour les noms douteux, puis recontrôle le fichier. */
    public function rapprocher(int $id, Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('ordonnances.importer');
        $import = $this->brouillon($id);
        $choix = (array) $request->input('choix', []);

        $rapp = $import->rapprochements ?? [];
        $textes = collect($import->controle['noms'] ?? [])->keyBy('cle');
        foreach ($choix as $cle => $valeur) {
            $cle = (string) $cle;
            if (! isset($textes[$cle]) && ! isset($rapp[$cle])) {
                continue;   // une clé que le contrôle n'a pas demandée : ignorée
            }
            $valeur = (string) $valeur;
            if ($valeur === '') {
                unset($rapp[$cle]);
            } elseif (preg_match('/^agent:(\d+)$/', $valeur, $m) && Agent::whereKey((int) $m[1])->exists()) {
                $rapp[$cle] = ['action' => 'agent', 'id' => (int) $m[1]];
                AgentAlias::updateOrCreate(['alias' => $cle], ['agent_id' => (int) $m[1], 'ecriture' => $textes[$cle]['texte'] ?? null, 'cree_par' => $request->user()?->id]);
            } elseif ($valeur === 'creer') {
                $rapp[$cle] = ['action' => 'creer'];
            } elseif ($valeur === 'ignorer') {
                $rapp[$cle] = ['action' => 'ignorer'];
            }
        }
        $import->update(['rapprochements' => $rapp]);

        return $this->aRecontroler($request, $import, 'Vos choix sont enregistrés et le fichier est recontrôlé.');
    }

    public function mode(int $id, Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('ordonnances.importer');
        $import = $this->brouillon($id);
        $donnees = $request->validate(['mode' => ['required', Rule::in(array_keys(ImportOrdonnance::MODES))]]);
        $import->update(['mode' => $donnees['mode']]);

        return $this->aRecontroler($request, $import, 'Le mode est changé et le fichier est recontrôlé.');
    }

    /** Relit le fichier conservé (après correction des choix) : le fichier lui-même ne change pas. */
    public function recontroler(int $id, Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('ordonnances.importer');

        return $this->aRecontroler($request, $this->brouillon($id), 'Le fichier est recontrôlé.');
    }

    /** À l'écran : on répond « allez lancer l'analyse » (dont on suit l'avancement) ; sans script : l'analyse se fait ici, puis on revient. */
    private function aRecontroler(Request $request, ImportOrdonnance $import, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message, 'analyser' => route('organisation.ordonnances.import.analyser', $import->id), 'progression' => route('organisation.ordonnances.import.progression', $import->id), 'voir' => route('organisation.ordonnances.import.show', $import->id)]);
        }
        $this->analyser($import);

        return redirect()->route('organisation.ordonnances.import.show', $import->id)->with('success', $message);
    }

    /** Ce qui va changer dans l'organisation, élément par élément (rien n'est encore écrit). */
    public function comparer(int $id): View|RedirectResponse
    {
        Gate::authorize('ordonnances.importer');
        $import = $this->brouillon($id);
        if (! ($import->controle['peut_continuer'] ?? false)) {
            return redirect()->route('organisation.ordonnances.import.show', $import->id)->with('error', "Corrigez d'abord les erreurs et confirmez les noms : la comparaison n'est possible qu'ensuite.");
        }

        return view('organisation.ordonnances.import.comparer', [
            'import' => $import, 'changements' => Comparateur::calculer($import->donnees ?? [], $import->mode), 'blocs' => Comparateur::BLOCS,
        ]);
    }

    public function appliquer(int $id, Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('ordonnances.importer');
        $import = $this->brouillon($id);
        if (! ($import->controle['peut_continuer'] ?? false)) {
            return $this->retour($request, 'error', 'Le fichier a encore des erreurs ou des noms à confirmer.', route('organisation.ordonnances.import.show', $import->id));
        }
        $donnees = $request->validate(['sel' => ['required', 'array', 'min:1'], 'sel.*' => ['string', 'size:16']], ['sel.required' => 'Cochez au moins un changement à appliquer.', 'sel.min' => 'Cochez au moins un changement à appliquer.']);

        try {
            $res = Applicateur::appliquer($import, $donnees['sel'], $request->user());
        } catch (\Throwable $e) {
            report($e);

            return $this->retour($request, 'error', "L'application a échoué et rien n'a été modifié (opération annulée). Le détail est dans le journal du serveur.", route('organisation.ordonnances.import.comparer', $import->id));
        }
        $message = $res['appliques'].' changement'.($res['appliques'] > 1 ? 's' : '').' appliqué'.($res['appliques'] > 1 ? 's' : '').'.';

        return $this->retour($request, 'success', $message, route('organisation.ordonnances.import.show', $import->id), $res['ecartes'] ? implode(' ', array_slice($res['ecartes'], 0, 4)) : null);
    }

    /** Défait un import appliqué (le dernier seulement) : ce qui a été créé est ôté, ce qui a été modifié ou retiré retrouve sa valeur d'avant. */
    public function annuler(int $id, Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('ordonnances.importer');
        $import = ImportOrdonnance::findOrFail($id);
        abort_unless(Applicateur::annulable($import), 403, 'Seul le dernier import appliqué peut être annulé.');
        $res = Applicateur::annuler($import, $request->user());

        return $this->retour($request, 'success', 'Import annulé : '.$res['defaits'].' changement'.($res['defaits'] > 1 ? 's' : '').' défait'.($res['defaits'] > 1 ? 's' : '').'.', route('organisation.ordonnances.import.show', $import->id), $res['echecs'] ? implode(' ', array_slice($res['echecs'], 0, 4)) : null);
    }

    public function abandonner(int $id, Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('ordonnances.importer');
        $import = $this->brouillon($id);
        $this->retirer($import, 'abandonne');
        Journal::audit('import_ordonnance_abandonne', $import, 'Fichier '.$import->nom_fichier);

        return $this->retour($request, 'success', "Chargement abandonné : rien n'a été modifié.", route('organisation.ordonnances.import'));
    }

    /** Réponse d'une action : en JSON pour l'écran (qui reste sur la page et affiche le message), en redirection sinon. */
    private function retour(Request $request, string $type, string $message, string $url, ?string $avertissement = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $type !== 'error', 'type' => $type, 'message' => $message, 'avertissement' => $avertissement, 'url' => $url], $type === 'error' ? 422 : 200);
        }
        $r = redirect($url)->with($type, $message);

        return $avertissement ? $r->with('error', $avertissement) : $r;
    }

    private function brouillon(int $id): ImportOrdonnance
    {
        $import = ImportOrdonnance::findOrFail($id);
        abort_unless($import->enCours(), 403, 'Ce chargement n\'est plus en préparation.');

        return $import;
    }

    /** Un fichier sans suite : on le range (statut) ou on l'efface s'il n'a jamais été lu. */
    private function retirer(ImportOrdonnance $import, ?string $statut = null): void
    {
        Storage::disk('local')->delete(self::DOSSIER.'/'.$import->id.'.xlsx');
        $statut ? $import->update(['statut' => $statut]) : $import->delete();
    }

    /** Lit le fichier conservé, le contrôle et compte ce qui changerait ; l'avancement est noté pour l'écran. @throws RuntimeException */
    private function analyser(ImportOrdonnance $import): void
    {
        $progres = Progression::rappel($import->id);
        Progression::debut($import->id);

        $chemin = Storage::disk('local')->path(self::DOSSIER.'/'.$import->id.'.xlsx');
        $classeur = Classeur::lire($chemin, $progres);
        $res = Controle::executer($classeur, $import->mode, $import->rapprochements ?? [], $progres);

        if ($res['controle']['peut_continuer']) {
            $progres('comparaison', 0, 1, "Comparaison avec l'organisation en cours…");
            $res['controle']['nb_changements'] = count(Comparateur::calculer($res['donnees'], $import->mode));
        } else {
            $res['controle']['nb_changements'] = null;
        }
        $import->update(['donnees' => $res['donnees'], 'controle' => $res['controle'], 'annee_judiciaire_id' => $res['donnees']['annee']['id'] ?? null]);
        Progression::fin($import->id, 'Contrôle terminé');
    }
}
