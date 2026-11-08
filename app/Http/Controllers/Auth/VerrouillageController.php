<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 | Écran de verrouillage : après une période d'inactivité (ou sur demande), la session reste ouverte mais
 | bloquée. L'utilisateur ressaisit son mot de passe, sans nouveau code par e-mail, et retrouve sa page.
 |
 | Pourquoi c'est acceptable côté sécurité : la session est déjà authentifiée en deux étapes, sur ce poste ;
 | le verrouillage ne fait que protéger l'écran d'un regard ou d'un geste pendant une absence. Il reste donc
 | borné : durée maximale (config securite.ecran_verrouille_max_minutes), essais de mot de passe limités,
 | contrôle que le compte est toujours actif, tout est journalisé. Au-delà, connexion complète.
 */
class VerrouillageController extends Controller
{
    public const CLE = 'ecran_verrouille';

    /** Verrouille la session de la requête en cours (sans effet si elle l'est déjà). */
    public static function verrouiller(Request $request, string $motif, ?int $derniereActivite = null): void
    {
        $session = $request->session();
        if ($session->has(self::CLE)) {
            return;
        }

        $session->put(self::CLE, [
            'depuis' => now()->timestamp,
            'essais' => 0,
            'retour' => self::retourSur($request, $request->isMethod('GET') && ! $request->expectsJson() ? $request->fullUrl() : $request->input('retour')),
        ]);

        $user = $request->user();
        Journal::connexion(
            Journal::SESSION_VERROUILLEE, $user, true, $motif, $user->email,
            $derniereActivite ? ['derniere_activite' => date('Y-m-d H:i:s', $derniereActivite)] : [],
        );
    }

    public static function verrouillageTropLong(Request $request): bool
    {
        $depuis = (int) ($request->session()->get(self::CLE.'.depuis') ?? 0);

        return $depuis > 0 && now()->timestamp - $depuis > (int) config('securite.ecran_verrouille_max_minutes') * 60;
    }

    /** L'écran de verrouillage. */
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::CLE)) {
            return redirect()->route('dashboard');
        }
        if (self::verrouillageTropLong($request)) {
            return $this->fermer($request, 'verrouillage de plus de '.config('securite.ecran_verrouille_max_minutes').' minutes');
        }

        $user = $request->user();
        $essais = (int) $request->session()->get(self::CLE.'.essais', 0);

        return view('auth.locked', [
            'nom' => $user->name,
            'email' => $user->email,
            'minutesInactivite' => (int) config('securite.inactivite_minutes'),
            'minutesMax' => (int) config('securite.ecran_verrouille_max_minutes'),
            'essaisRestants' => max(0, (int) config('securite.deverrouillage_essais_max') - $essais),
        ]);
    }

    /** Verrouillage demandé : bouton « Verrouiller » du menu, ou minuteur d'inactivité du navigateur. */
    public function lock(Request $request): JsonResponse|RedirectResponse
    {
        self::verrouiller($request, $request->boolean('auto') ? 'inactivité (détectée par le navigateur)' : 'demandé par l\'utilisateur');

        return $request->expectsJson()
            ? response()->json(['redirect' => route('verrouillage.show')])
            : redirect()->route('verrouillage.show');
    }

    /** Déverrouillage par le mot de passe seul. */
    public function unlock(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->session()->has(self::CLE)) {
            return $this->vers($request, route('dashboard'));
        }

        $donnees = $request->validate(['password' => ['required', 'string', 'max:255']], [
            'password.required' => 'Saisissez votre mot de passe.',
        ]);

        $user = $request->user();

        if (self::verrouillageTropLong($request)) {
            return $this->fermer($request, 'verrouillage de plus de '.config('securite.ecran_verrouille_max_minutes').' minutes');
        }
        // Compte désactivé ou verrouillé pendant l'absence : plus d'accès, même avec le bon mot de passe.
        if (! $user->peutSeConnecter()) {
            return $this->fermer($request, 'compte indisponible');
        }

        if (! Hash::check($donnees['password'], $user->password)) {
            $essais = (int) $request->session()->get(self::CLE.'.essais', 0) + 1;
            $request->session()->put(self::CLE.'.essais', $essais);
            Journal::connexion(Journal::DEVERROUILLAGE_ECHEC, $user, false, 'mot de passe incorrect', $user->email, ['essai' => $essais]);

            $max = (int) config('securite.deverrouillage_essais_max');
            if ($essais >= $max) {
                return $this->fermer($request, 'trop d\'essais de déverrouillage', 'Trop d\'essais. Reconnectez-vous.');
            }

            throw ValidationException::withMessages(['password' => 'Mot de passe incorrect. '.($max - $essais).' essai(s) restant(s).']);
        }

        $retour = $request->session()->get(self::CLE.'.retour');

        // Nouvel identifiant de session (la session authentifiée est conservée), puis levée du verrou.
        $request->session()->regenerate();
        $request->session()->forget(self::CLE);
        $request->session()->put('derniere_activite', now()->timestamp);
        Journal::connexion(Journal::SESSION_DEVERROUILLEE, $user, true, null, $user->email);

        return $this->vers($request, $retour ?: route('dashboard'));
    }

    /** Battement de cœur : le navigateur signale que l'utilisateur est actif (le middleware rafraîchit l'horloge). */
    public function ping(): Response
    {
        return response()->noContent();
    }

    private function vers(Request $request, string $url): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect()->to($url);
    }

    /** Ferme la session (connexion complète exigée ensuite). */
    private function fermer(Request $request, string $motif, string $message = 'Votre session a expiré. Reconnectez-vous.'): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        Journal::connexion(Journal::DECONNEXION, $user, true, $motif, $user->email);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('status', $message);

        return $this->vers($request, route('login'));
    }

    /**
     * Adresse de retour : acceptée seulement si elle pointe vers ce même site (jamais vers l'extérieur)
     * et pas vers une page d'authentification.
     */
    private static function retourSur(Request $request, ?string $url): ?string
    {
        if (! is_string($url) || $url === '' || strlen($url) > 2000) {
            return null;
        }
        $parties = parse_url($url);
        if ($parties === false || ($parties['host'] ?? null) !== $request->getHost()) {
            return null;
        }
        $chemin = $parties['path'] ?? '/';
        if (preg_match('#/(verrouille|verrouiller|login|logout|two-factor|forgot-password|reset-password|session)(/|$)#', $chemin)) {
            return null;
        }

        return $url;
    }
}
