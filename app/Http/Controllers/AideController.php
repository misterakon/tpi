<?php

namespace App\Http\Controllers;

use App\Support\Aide\ManuelAide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/*
 | Manuel d'utilisation intégré : sommaire, chapitres, recherche, manuel complet imprimable et panneau d'aide « ? » de chaque page.
 | Ouvert à tout compte connecté ; chacun ne voit que les chapitres dont il a le droit (voir ManuelAide).
 */
class AideController extends Controller
{
    public function index(Request $request): View
    {
        return view('aide.index', ['chapitres' => ManuelAide::visibles($request->user())]);
    }

    public function chapitre(Request $request, string $slug): View
    {
        $chapitre = ManuelAide::chapitre($slug, $request->user());
        abort_unless($chapitre, 404);
        $tous = array_values(ManuelAide::visibles($request->user()));
        $i = collect($tous)->search(fn ($c) => $c['slug'] === $slug);

        return view('aide.chapitre', [
            'chapitre' => $chapitre, 'chapitres' => $tous,
            'precedent' => $i > 0 ? $tous[$i - 1] : null, 'suivant' => $i !== false && isset($tous[$i + 1]) ? $tous[$i + 1] : null,
        ]);
    }

    /** Tous les chapitres lisibles sur une seule page, prête à imprimer ou à enregistrer en PDF depuis le navigateur. */
    public function manuel(Request $request): View
    {
        return view('aide.manuel', ['chapitres' => ManuelAide::visibles($request->user())]);
    }

    public function chercher(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');

        return response()->json(['resultats' => array_map(fn ($r) => $r + ['url' => route('aide.chapitre', $r['slug']).'#'.$r['ancre']], ManuelAide::chercher($q, $request->user()))]);
    }

    /** Le contenu du panneau « ? » : le chapitre qui explique la page où l'on se trouve (sinon le sommaire). */
    public function contexte(Request $request): View
    {
        $nom = preg_match('/^[a-z0-9_.-]{1,80}$/i', (string) $request->query('page')) ? (string) $request->query('page') : null;
        $chapitre = ManuelAide::pour($nom, $request->user());

        return view('aide._panneau', ['chapitre' => $chapitre, 'chapitres' => ManuelAide::visibles($request->user())]);
    }
}
