<?php

namespace App\Http\Controllers;

use App\Models\JournalAudit;
use App\Models\JournalConnexion;
use App\Services\Journal;
use App\Support\JournalLibelles;
use Illuminate\Database\Eloquent\Builder;
use App\Support\TableauServeur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 | Consultation des journaux (lecture seule) : filtres, recherche, pagination côté serveur, export CSV.
 | Droits : journaux.connexion / journaux.audit pour consulter ; journaux.connexion.exporter / journaux.audit.exporter pour exporter.
 | Chaque export est lui-même inscrit au journal d'audit.
 */
class JournalController extends Controller
{
    private const EXPORT_MAX = 10000;

    public function connexions(Request $request): View
    {
        Gate::authorize('journaux.connexion');
        $filtres = $this->filtres($request, array_keys(JournalLibelles::CONNEXION));

        return view('journal.connexions', [
            'total' => $this->requeteConnexions($filtres)->count(),
            'filtres' => $filtres,
            'evenements' => JournalLibelles::choix(JournalLibelles::CONNEXION),
            'echecs24h' => JournalConnexion::where('succes', false)->where('created_at', '>=', now()->subDay())->count(),
        ]);
    }

    public function audit(Request $request): View
    {
        Gate::authorize('journaux.audit');
        $filtres = $this->filtres($request, array_keys(JournalLibelles::AUDIT));

        return view('journal.audit', [
            'total' => $this->requeteAudit($filtres)->count(),
            'filtres' => $filtres,
            'actions' => JournalLibelles::choix(JournalLibelles::AUDIT),
        ]);
    }

    /** Les lignes du journal de connexion, une page à la fois (mêmes filtres que la page). */
    public function donneesConnexions(Request $request): JsonResponse
    {
        Gate::authorize('journaux.connexion');
        $filtres = $this->filtres($request, array_keys(JournalLibelles::CONNEXION));

        return TableauServeur::repondre($request, $this->requeteConnexions($filtres)->with('user:id,name,email')->latest('id'), [0 => ['id']], fn ($q, $mots) => null,
            fn (JournalConnexion $l) => view('journal._ligne_connexions', ['l' => $l])->render());
    }

    /** Les lignes du journal d'audit, une page à la fois (mêmes filtres que la page). */
    public function donneesAudit(Request $request): JsonResponse
    {
        Gate::authorize('journaux.audit');
        $filtres = $this->filtres($request, array_keys(JournalLibelles::AUDIT));

        return TableauServeur::repondre($request, $this->requeteAudit($filtres)->with('user:id,name,email')->latest('id'), [0 => ['id']], fn ($q, $mots) => null,
            fn (JournalAudit $l) => view('journal._ligne_audit', ['l' => $l])->render());
    }

    public function exporterConnexions(Request $request): StreamedResponse
    {
        Gate::authorize('journaux.connexion.exporter');
        $filtres = $this->filtres($request, array_keys(JournalLibelles::CONNEXION));
        $requete = $this->requeteConnexions($filtres)->with('user:id,name,email')->latest('id')->limit(self::EXPORT_MAX);

        return $this->csv('journal-connexion', 'Journal de connexion', $filtres,
            ['Date', 'Événement', 'Résultat', 'Utilisateur', 'E-mail saisi', 'Motif', 'Adresse IP', 'Navigateur'],
            $requete,
            fn (JournalConnexion $l) => [
                $l->created_at->format('d/m/Y H:i:s'), JournalLibelles::connexion($l->evenement)[0], $l->succes ? 'Réussi' : 'Échec',
                $l->user?->name, $l->email_saisi, $l->motif, $l->ip, $l->navigateur,
            ]);
    }

    public function exporterAudit(Request $request): StreamedResponse
    {
        Gate::authorize('journaux.audit.exporter');
        $filtres = $this->filtres($request, array_keys(JournalLibelles::AUDIT));
        $requete = $this->requeteAudit($filtres)->with('user:id,name,email')->latest('id')->limit(self::EXPORT_MAX);

        return $this->csv('journal-audit', "Journal d'audit", $filtres,
            ['Date', 'Action', 'Auteur', 'Élément', 'Avant', 'Après', 'Adresse IP'],
            $requete,
            fn (JournalAudit $l) => [
                $l->created_at->format('d/m/Y H:i:s'), JournalLibelles::audit($l->action)[0], $l->user?->name, $l->libelle,
                $l->avant ? json_encode($l->avant, JSON_UNESCAPED_UNICODE) : '', $l->apres ? json_encode($l->apres, JSON_UNESCAPED_UNICODE) : '', $l->ip,
            ]);
    }

    /* ---------- Filtres ---------- */

    /** Valide et nettoie les filtres de la barre de recherche. */
    private function filtres(Request $request, array $codes): array
    {
        $v = $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
            'type' => ['nullable', 'in:'.implode(',', $codes)],
            'resultat' => ['nullable', 'in:succes,echec'],
            'q' => ['nullable', 'string', 'max:100'],
        ], [
            'du.date' => 'Date de début invalide.', 'au.date' => 'Date de fin invalide.',
            'au.after_or_equal' => 'La date de fin doit être après la date de début.',
            'type.in' => 'Type invalide.', 'q.max' => 'Recherche trop longue (100 caractères au maximum).',
        ]);

        return array_filter($v, fn ($x) => $x !== null && $x !== '');
    }

    private function requeteConnexions(array $f): Builder
    {
        return JournalConnexion::query()
            ->when($f['du'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($f['au'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->when($f['type'] ?? null, fn (Builder $q, $t) => $q->where('evenement', $t))
            ->when($f['resultat'] ?? null, fn (Builder $q, $r) => $q->where('succes', $r === 'succes'))
            ->when($f['q'] ?? null, function (Builder $q, $mot) {
                $like = '%'.addcslashes($mot, '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('email_saisi', 'like', $like)->orWhere('ip', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            });
    }

    private function requeteAudit(array $f): Builder
    {
        return JournalAudit::query()
            ->when($f['du'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($f['au'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->when($f['type'] ?? null, fn (Builder $q, $t) => $q->where('action', $t))
            ->when($f['q'] ?? null, function (Builder $q, $mot) {
                $like = '%'.addcslashes($mot, '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('libelle', 'like', $like)->orWhere('ip', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            });
    }

    private function parPage(): int
    {
        return 30;
    }

    /* ---------- Export CSV ---------- */

    /** CSV lisible par Excel (UTF-8 avec BOM, séparateur « ; »), protégé contre l'injection de formules. */
    private function csv(string $fichier, string $titre, array $filtres, array $entetes, Builder $requete, callable $ligne): StreamedResponse
    {
        $lignes = $requete->get();
        Journal::audit('journal_exporte', null, $titre, [], ['filtres' => $filtres, 'lignes' => $lignes->count()]);

        $nom = $fichier.'-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($entetes, $lignes, $ligne) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, $entetes, ';');
            foreach ($lignes as $l) {
                fputcsv($sortie, array_map([self::class, 'neutraliser'], $ligne($l)), ';');
            }
            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /** Une cellule qui commence par = + - @ serait interprétée comme une formule par Excel. */
    public static function neutraliser(mixed $valeur): string
    {
        $texte = (string) $valeur;

        return $texte !== '' && str_contains("=+-@\t\r", $texte[0]) ? "'".$texte : $texte;
    }
}
