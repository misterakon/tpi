<?php

namespace App\Http\Controllers\Affaires;

use App\Http\Controllers\Controller;
use App\Models\Affaire;
use App\Models\ConsultationPiece;
use App\Models\PieceDossier;
use App\Services\Journal;
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

/*
 | Dossier numérique d'une affaire : pièces PDF ou images, consultables directement dans l'application (visionneuse de la fiche).
 | Les fichiers vivent sur le disque privé, sous un nom généré, et ne sortent que par cette classe, après contrôle des droits et du
 | périmètre de chambre. Chaque ouverture ou téléchargement est journalisé ; une pièce se retire (motif), elle ne se supprime pas.
 | Droits : dossiers.voir / creer / modifier / supprimer, dossiers.telecharger.
 */
class DossierNumeriqueController extends Controller
{
    private const MIMES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Page « Dossier numérique » du menu : les dossiers des affaires, avec leur nombre de pièces et l'emplacement du dossier papier. */
    public function index(): View
    {
        Gate::authorize('dossiers.voir');

        return view('affaires.dossier.index', ['total' => Affaire::visiblesPour(auth()->user())->count(), 'pieces' => PieceDossier::whereIn('affaire_id', Affaire::visiblesPour(auth()->user())->select('id'))->count()]);
    }

    /** Les lignes de la page « Dossier numérique », une page à la fois (tri, recherche et filtre dans la base). */
    public function donnees(Request $request): JsonResponse
    {
        Gate::authorize('dossiers.voir');
        $base = Affaire::visiblesPour($request->user())->with(['parties', 'chambre'])->withCount('pieces')->withMax('pieces', 'created_at')->orderByDesc('annee')->orderByDesc('numero')->orderByDesc('id');
        $f = (string) $request->input('filtre.3', '');
        if ($f === 'avec') {
            $base->has('pieces');
        } elseif ($f === 'vide') {
            $base->doesntHave('pieces');
        }

        return \App\Support\TableauServeur::repondre(
            $request, $base,
            [0 => ['annee', 'numero'], 1 => ['objet'], 5 => ['localisation_dossier']],
            fn ($q, $mots) => \App\Support\RechercheTexte::appliquer($q, $mots, ['numero_complet', 'objet', 'localisation_dossier'], 'parties', ['nom', 'prenoms', 'denomination']),
            fn (Affaire $l) => view('affaires.dossier._ligne', ['l' => $l])->render(),
        );
    }

    /** Ajoute une ou plusieurs pièces. Tout est contrôlé avant d'enregistrer quoi que ce soit : un fichier refusé n'en laisse passer aucun. */
    public function ajouter(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('dossiers.creer');
        $affaire = Affaire::visiblesPour($request->user())->findOrFail($id);
        abort_if($affaire->estAnnulee(), 403, 'Le dossier d\'une affaire annulée ne reçoit plus de pièces.');

        $request->validate([
            'fichiers' => ['required', 'array', 'min:1', 'max:20'],
            'fichiers.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.PieceDossier::tailleMaxKo()],
            'meta' => ['nullable', 'array'],
            'meta.*.type' => ['nullable', Rule::in(array_keys(PieceDossier::TYPES))],
            'meta.*.titre' => ['nullable', 'string', 'max:200'],
            'meta.*.date' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'fichiers.required' => 'Choisissez au moins un fichier.', 'fichiers.max' => 'Vingt fichiers au plus à la fois.',
            'fichiers.*.mimes' => 'Seuls les PDF et les images (JPG, PNG, WebP) sont admis.', 'fichiers.*.max' => 'Un fichier dépasse la taille maximale ('.PieceDossier::tailleLisible(PieceDossier::tailleMaxKo() * 1024).').',
            'fichiers.*.uploaded' => 'Un fichier n\'a pas pu être envoyé (taille trop grande pour le serveur, peut-être).',
        ]);

        // Contrôle du contenu réel (pas seulement du nom) puis empreinte, avant tout enregistrement.
        $prets = [];
        $existantes = $affaire->pieces()->pluck('empreinte')->flip();
        $ignorees = 0;
        foreach ((array) $request->file('fichiers') as $i => $fichier) {
            $mime = $this->mimeReel($fichier->getRealPath());
            if ($mime === null) {
                throw ValidationException::withMessages(['fichiers' => '« '.Str::limit($fichier->getClientOriginalName(), 60).' » n\'est pas un vrai PDF ni une vraie image.']);
            }
            $empreinte = hash_file('sha256', $fichier->getRealPath());
            if ($existantes->has($empreinte)) {
                $ignorees++;

                continue;
            }
            $existantes->put($empreinte, true);
            $prets[] = compact('fichier', 'mime', 'empreinte', 'i');
        }

        $meta = (array) $request->input('meta', []);
        foreach ($prets as $p) {
            $m = $meta[$p['i']] ?? [];
            $nomOriginal = Str::limit((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $p['fichier']->getClientOriginalName()), 190, '');
            $titre = trim(strip_tags((string) ($m['titre'] ?? ''))) ?: pathinfo($nomOriginal, PATHINFO_FILENAME);
            $chemin = Str::lower((string) Str::ulid()).'.'.self::MIMES[$p['mime']];

            Storage::disk('local')->putFileAs(PieceDossier::DOSSIER.'/'.$affaire->id, $p['fichier'], $chemin);
            $piece = PieceDossier::create([
                'affaire_id' => $affaire->id, 'type' => $m['type'] ?? $this->typeProbable($titre), 'titre' => Str::limit($titre, 190, ''), 'date_piece' => $m['date'] ?? null,
                'chemin' => $chemin, 'nom_original' => $nomOriginal, 'mime' => $p['mime'], 'taille' => $p['fichier']->getSize(), 'empreinte' => $p['empreinte'], 'depose_par' => $request->user()?->id,
            ]);
            Journal::audit('piece_ajoutee', $piece, $affaire->numeroAffichage().' : '.$piece->titre, [], ['type' => $piece->typeLibelle(), 'taille' => PieceDossier::tailleLisible($piece->taille)]);
        }

        $n = count($prets);
        $message = $n === 0 ? 'Aucune pièce nouvelle.' : ($n === 1 ? 'Pièce ajoutée au dossier.' : $n.' pièces ajoutées au dossier.');
        if ($ignorees > 0) {
            $message .= ' '.$ignorees.' fichier'.($ignorees > 1 ? 's déjà présents ont été ignorés' : ' déjà présent a été ignoré').'.';
        }

        return redirect()->to(route('affaires.liste.fiche', $affaire->id).'#dossier')->with('success', $message);
    }

    /** Ouvre la pièce dans la visionneuse de la fiche (affichage intégré, rien n'est téléchargé). */
    public function fichier(int $id, Request $request): StreamedResponse
    {
        Gate::authorize('dossiers.voir');
        $piece = $this->piece($id, $request);
        $this->tracer($piece, 'vue', $request);

        return $this->servir($piece, 'inline');
    }

    public function telecharger(int $id, Request $request): StreamedResponse
    {
        Gate::authorize('dossiers.telecharger');
        $piece = $this->piece($id, $request);
        $this->tracer($piece, 'telechargee', $request);

        return $this->servir($piece, 'attachment');
    }

    public function modifier(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('dossiers.modifier');
        $piece = $this->piece($id, $request);

        $donnees = $request->validate([
            'type' => ['required', Rule::in(array_keys(PieceDossier::TYPES))],
            'titre' => ['required', 'string', 'max:200'],
            'date_piece' => ['nullable', 'date', 'before_or_equal:today'],
        ], ['titre.required' => 'Donnez un titre à la pièce.']);

        $avant = ['type' => $piece->typeLibelle(), 'titre' => $piece->titre, 'date' => $piece->date_piece?->format('d/m/Y')];
        $piece->forceFill(['type' => $donnees['type'], 'titre' => trim(strip_tags($donnees['titre'])), 'date_piece' => $donnees['date_piece'] ?? null])->save();
        Journal::audit('piece_modifiee', $piece, $piece->affaire->numeroAffichage().' : '.$piece->titre, $avant, ['type' => $piece->typeLibelle(), 'titre' => $piece->titre, 'date' => $piece->date_piece?->format('d/m/Y')]);

        return redirect()->to(route('affaires.liste.fiche', $piece->affaire_id).'#dossier-'.$piece->id)->with('success', 'Pièce modifiée.');
    }

    /** Retire la pièce du dossier : elle n'apparaît plus, le fichier et l'historique de consultation sont conservés. */
    public function retirer(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('dossiers.supprimer');
        $piece = $this->piece($id, $request);
        $motif = trim((string) $request->validate(['motif' => ['required', 'string', 'min:5', 'max:300']], ['motif.required' => 'Indiquez pourquoi la pièce est retirée.', 'motif.min' => 'Le motif est trop court.'])['motif']);

        $piece->forceFill(['retiree_le' => now(), 'retiree_par' => $request->user()?->id, 'motif_retrait' => $motif])->save();
        Journal::audit('piece_retiree', $piece, $piece->affaire->numeroAffichage().' : '.$piece->titre, [], ['motif' => $motif]);

        return redirect()->to(route('affaires.liste.fiche', $piece->affaire_id).'#dossier')->with('success', 'Pièce retirée du dossier.');
    }

    /** Où se trouve le dossier papier (armoire, rayon, carton…). */
    public function localisation(int $id, Request $request): RedirectResponse
    {
        Gate::authorize('dossiers.modifier');
        $affaire = Affaire::visiblesPour($request->user())->findOrFail($id);
        $lieu = trim(strip_tags((string) $request->validate(['localisation' => ['nullable', 'string', 'max:200']])['localisation']));

        $avant = ['localisation' => $affaire->localisation_dossier];
        $affaire->forceFill(['localisation_dossier' => $lieu === '' ? null : $lieu])->save();
        Journal::audit('dossier_localise', $affaire, $affaire->numeroAffichage(), $avant, ['localisation' => $affaire->localisation_dossier]);

        return redirect()->to(route('affaires.liste.fiche', $affaire->id).'#dossier')->with('success', 'Emplacement du dossier papier enregistré.');
    }

    /** Qui a ouvert ou téléchargé la pièce (pour ceux qui gèrent le dossier). */
    public function consultations(int $id, Request $request): JsonResponse
    {
        Gate::authorize('dossiers.modifier');
        $piece = $this->piece($id, $request);

        return response()->json([
            'status' => true,
            'consultations' => $piece->consultations()->with('utilisateur')->limit(15)->get()->map(fn (ConsultationPiece $c) => [
                'qui' => $c->utilisateur?->name ?? '—', 'action' => $c->action === 'telechargee' ? 'a téléchargé' : 'a ouvert', 'quand' => $c->created_at->format('d/m/Y à H:i'),
            ])->values(),
        ]);
    }

    /** Une pièce non retirée, d'une affaire que l'utilisateur a le droit de voir. */
    private function piece(int $id, Request $request): PieceDossier
    {
        $piece = PieceDossier::with('affaire')->whereNull('retiree_le')->findOrFail($id);
        abort_unless(Affaire::visiblesPour($request->user())->whereKey($piece->affaire_id)->exists(), 404);

        return $piece;
    }

    private function servir(PieceDossier $piece, string $disposition): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($piece->chemin()), 404, 'Le fichier de cette pièce est introuvable.');
        $nom = Str::slug($piece->titre) ?: 'piece';

        return Storage::disk('local')->response($piece->chemin(), $nom.'.'.self::MIMES[$piece->mime], [
            'Content-Type' => $piece->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $disposition);
    }

    /** Une ouverture est journalisée une fois par minute et par personne (le navigateur peut recharger la pièce plusieurs fois). */
    private function tracer(PieceDossier $piece, string $action, Request $request): void
    {
        $recente = ConsultationPiece::where('piece_id', $piece->id)->where('user_id', $request->user()?->id)->where('action', $action)->where('created_at', '>=', now()->subMinute())->exists();
        if (! $recente) {
            ConsultationPiece::create(['piece_id' => $piece->id, 'user_id' => $request->user()?->id, 'action' => $action]);
        }
    }

    /** Le type MIME d'après le contenu du fichier, ou null si ce n'est ni un PDF ni une image admise. */
    private function mimeReel(string $chemin): ?string
    {
        $debut = (string) @file_get_contents($chemin, false, null, 0, 8);
        if (str_starts_with($debut, '%PDF-')) {
            return 'application/pdf';
        }
        $info = @getimagesize($chemin);

        return $info !== false && isset(self::MIMES[$info['mime']]) ? $info['mime'] : null;
    }

    /** Le type de pièce le plus probable d'après son titre (modifiable ensuite). */
    private function typeProbable(string $titre): string
    {
        $t = Str::lower(Str::ascii($titre));
        foreach (['assignation' => 'assignation', 'requete' => 'assignation', 'signification' => 'signification', 'procuration' => 'procuration', 'mandat' => 'mandat', 'conclusion' => 'conclusions', 'requisitoire' => 'requisitoire',
            'expertise' => 'expertise', 'jugement' => 'jugement', 'decision' => 'jugement', 'ordonnance' => 'ordonnance', 'audience' => 'pv_audience', 'procès' => 'pv_audience', 'pv' => 'pv_audience', 'piece' => 'pieces_versees', 'bordereau' => 'pieces_versees',
            'lettre' => 'correspondance', 'courrier' => 'correspondance'] as $mot => $type) {
            if (str_contains($t, $mot)) {
                return $type;
            }
        }

        return 'autre';
    }
}
