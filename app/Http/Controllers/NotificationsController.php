<?php

namespace App\Http\Controllers;

use App\Models\Alerte;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Notifications de l'utilisateur connecté (cloche de l'en-tête et page « Mes notifications »).
 | Chacun ne voit et ne modifie que les siennes. Une alerte déjà traitée (action faite) reste dans la liste, marquée « refermée ».
 */
class NotificationsController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->latest()->limit(300)->get();
        $alertes = Alerte::whereIn('id', $notifications->map(fn ($n) => $n->data['alerte_id'] ?? null)->filter()->all())->get()->keyBy('id');

        return view('alertes.notifications', [
            'notifications' => $notifications->map(fn ($n) => self::forme($n, $alertes->get($n->data['alerte_id'] ?? 0)))->values()->all(),
            'peutJustifier' => Gate::allows('alertes.justifier'),
        ]);
    }

    /** Ce qu'affiche la cloche : le nombre de non lues et les plus récentes. */
    public function resume(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['non_lues' => $user->unreadNotifications()->count(), 'recentes' => self::recentes($user)]);
    }

    /** Marque la notification comme lue puis ouvre l'écran où agir. */
    public function ouvrir(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $chemin = (string) ($notification->data['url'] ?? '');

        // Le chemin est relatif et vient du système ; on refuse tout ce qui ressemble à une adresse extérieure.
        return str_starts_with($chemin, '/') && ! str_starts_with($chemin, '//') ? redirect(url($chemin)) : redirect()->route('notifications.index');
    }

    public function lire(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['status' => true, 'non_lues' => $request->user()->unreadNotifications()->count()]);
    }

    public function toutLire(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['status' => true, 'non_lues' => 0]) : back();
    }

    /** Les notifications non lues les plus récentes, pour la cloche. @return list<array<string, mixed>> */
    public static function recentes(User $user, int $n = 6): array
    {
        return $user->unreadNotifications()->latest()->limit($n)->get()->map(fn ($x) => [
            'id' => $x->id, 'titre' => (string) ($x->data['titre'] ?? ''), 'niveau' => (string) ($x->data['niveau'] ?? 'attention'),
            'quand' => $x->created_at?->locale('fr')->diffForHumans(), 'ouvrir' => route('notifications.ouvrir', $x->id),
        ])->all();
    }

    /** Une notification mise en forme pour la page, avec l'état actuel de son alerte (refermée, justifiée, en cours, en retard de n jours). @return array<string, mixed> */
    public static function forme(DatabaseNotification $n, ?Alerte $alerte): array
    {
        $d = $n->data;
        $statut = ! $alerte ? 'ouverte' : (! $alerte->estOuverte() ? 'resolue' : ($alerte->estJustifiee() ? 'justifiee' : 'ouverte'));

        return [
            'id' => $n->id, 'lue' => $n->read_at !== null, 'titre' => (string) ($d['titre'] ?? ''), 'action' => (string) ($d['action'] ?? ''), 'niveau' => (string) ($d['niveau'] ?? 'attention'),
            'regle' => (string) ($d['regle'] ?? ''), 'regle_libelle' => (string) ($d['regle_libelle'] ?? ''), 'type' => (string) ($d['type'] ?? ''), 'type_libelle' => Alerte::TYPES[$d['type'] ?? ''] ?? '',
            'echeance' => isset($d['echeance']) ? \Illuminate\Support\Carbon::parse($d['echeance'])->format('d/m/Y') : '', 'retard' => $alerte && $alerte->estOuverte() ? $alerte->retard() : 0,
            'statut' => $statut, 'justifiee_jusqu' => $alerte?->justifiee_jusqu?->format('d/m/Y'), 'justification' => $alerte?->justification, 'alerte_id' => $alerte?->id,
            'sans_destinataire' => (bool) ($d['sans_destinataire'] ?? false), 'quand' => $n->created_at?->locale('fr')->diffForHumans(), 'date' => $n->created_at?->format('d/m/Y H:i'),
            'ouvrir' => route('notifications.ouvrir', $n->id),
        ];
    }
}
