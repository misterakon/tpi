<?php

namespace Tests\Feature;

use App\Models\Profil;
use App\Models\User;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Attaque : chaque route est appelée sans compte, puis avec un compte sans aucun droit, puis avec des entrées hostiles. */
class AttaqueSecuriteTest extends TestCase
{
    use RefreshDatabase;

    /** Pages que tout compte connecté a le droit d'ouvrir (libre-service). */
    private const LIBRES = ['accueil', 'accueil/donnees', 'mon-compte', 'verrouille', 'notifications/resume', 'dashboard', '/', 'up', 'logout', 'verrouiller', 'session/actif', 'notifications/tout-lire',
        'mon-compte/codes-secours', 'mon-compte/mot-de-passe', 'verrouille', 'alertes/resume', 'recherche', 'aide', 'aide/recherche', 'aide/contexte', 'aide/manuel', 'aide/{slug}'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
    }

    private function uri(\Illuminate\Routing\Route $r): string
    {
        return '/'.ltrim(preg_replace('/\{[^}?]+\??\}/', '1', $r->uri()), '/');
    }

    public function test_anonyme_toutes_les_routes_protegees_renvoient_a_la_connexion(): void
    {
        $fuites = [];
        foreach (Route::getRoutes() as $r) {
            $mw = implode(',', $r->gatherMiddleware());
            if (! str_contains($mw, 'auth') || str_contains($mw, 'guest')) {
                continue;
            }
            foreach (array_diff($r->methods(), ['HEAD', 'OPTIONS']) as $m) {
                $rep = $this->call($m, $this->uri($r));
                if (! in_array($rep->getStatusCode(), [302, 401, 403, 404, 419], true)) {   // 404 : paramètre de route d'un autre format, sans lien avec l'accès
                    $fuites[] = $m.' '.$r->uri().' => '.$rep->getStatusCode();
                }
            }
        }
        $this->assertSame([], $fuites, 'Routes accessibles sans connexion : '.implode(' | ', $fuites));
    }

    public function test_compte_sans_droit_ne_passe_aucune_route_protegee_et_aucune_erreur_500(): void
    {
        User::factory()->create();   // une autre cible, pour que les actions sur « 1 » ne visent pas le compte lui-même
        $u = User::factory()->create(['profil_id' => Profil::create(['nom' => 'Vide', 'droits' => []])->id]);
        $ouvertes = [];
        $erreurs = [];
        foreach (Route::getRoutes() as $r) {
            $mw = implode(',', $r->gatherMiddleware());
            if (! str_contains($mw, 'auth') || str_contains($mw, 'guest') || in_array(trim($r->uri(), '/'), self::LIBRES, true)) {
                continue;
            }
            foreach (array_diff($r->methods(), ['HEAD', 'OPTIONS']) as $m) {
                $rep = $this->actingAs($u)->withSession(['derniere_activite' => now()->timestamp])->call($m, $this->uri($r));
                $code = $rep->getStatusCode();
                if ($code >= 500) {
                    $erreurs[] = $m.' '.$r->uri().' => '.$code;
                } elseif ($code < 300 && ! str_contains($r->uri(), 'notifications') && $code !== 204) {
                    $ouvertes[] = $m.' '.$r->uri().' => '.$code;
                }
            }
        }
        $this->assertSame([], $erreurs, 'Erreurs 500 : '.implode(' | ', $erreurs));
        $this->assertSame([], $ouvertes, 'Routes ouvertes à un compte sans droit : '.implode(' | ', $ouvertes));
    }

    /** Du code hostile saisi partout où l'on peut écrire du texte ne doit jamais ressortir exécutable. */
    public function test_xss_stocke_neutralise_dans_les_pages(): void
    {
        $x = '"><script>alert(1)</script><img src=x onerror=alert(2)>';
        $admin = User::factory()->create();
        $chambre = \App\Models\Chambre::firstOrFail();
        $a = \App\Models\Affaire::create([
            'serie' => 'RP', 'annee' => 2026, 'numero' => 1, 'numero_complet' => 'RP-1/26', 'numero_saisi' => true, 'date_rg' => '2026-10-20', 'objet' => $x, 'articles' => $x, 'numero_parquet' => $x,
            'mode_saisine_id' => \App\Models\ModeSaisine::where('registre', 'penal')->value('id'), 'nature_affaire_id' => \App\Models\NatureAffaire::where('registre', 'RP')->value('id'),
            'matiere_id' => \App\Models\Matiere::value('id'), 'chambre_id' => $chambre->id, 'statut' => 'enrolee',
        ]);
        $p = \App\Models\Partie::create(['affaire_id' => $a->id, 'role' => 'prevenu', 'nature' => 'homme', 'nom' => $x, 'prenoms' => $x, 'cle' => 'x', 'mandat_depot_date' => '2026-10-21', 'lieu_detention' => $x, 'profession' => $x, 'domicile' => $x]);
        \App\Models\DecisionPenale::create(['affaire_id' => $a->id, 'partie_id' => $p->id, 'date_decision' => '2026-11-01', 'contradiction' => 'defaut', 'issue' => 'condamne', 'autres_peines' => $x, 'numero_decision' => $x, 'minute_transmise_a' => $x]);
        \App\Models\SeanceHorsAudience::create(['type' => 'tutelle', 'date_seance' => '2026-11-20', 'lieu' => $x, 'observations' => $x, 'statut' => 'prevue']);
        \App\Models\Infraction::create(['libelle' => $x, 'articles' => $x]);
        \App\Models\Scelle::create(['affaire_id' => $a->id, 'numero' => $x, 'designation' => $x, 'nature' => 'autre', 'etat' => 'conserve', 'lieu_conservation' => $x, 'observations' => $x]);

        $pages = [route('affaires.liste'), route('affaires.liste.fiche', $a->id), route('affaires.detenus'), route('decisions.repertoire_penal'), route('audiences.seances'), route('referentiels.infractions')];
        foreach ($pages as $url) {
            $html = $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('<script>alert(1)', $html, "XSS exécutable sur $url");
            $this->assertStringNotContainsString('<img src=x onerror', $html, "XSS (attribut) sur $url");
        }

        // Les listes paginées côté serveur renvoient des cellules HTML : le contenu hostile y est lui aussi neutralisé.
        foreach (['affaires.liste.donnees', 'affaires.detenus.donnees', 'decisions.repertoire_penal.donnees', 'affaires.dossiers.donnees', 'audiences.seances.donnees', 'referentiels.infractions.donnees'] as $nom) {
            $lignes = $this->lignesServeur($admin, $nom);
            $this->assertStringNotContainsString('<script>alert(1)', $lignes, "XSS exécutable dans $nom");
            $this->assertStringNotContainsString('<img src=x onerror', $lignes, "XSS (attribut) dans $nom");
        }
    }

    /** Les champs qui ne se saisissent pas (statut, auteur…) ne se forcent pas en ajoutant des paramètres. */
    public function test_assignation_de_masse_impossible(): void
    {
        $admin = User::factory()->create();
        $autre = User::factory()->create();
        $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->post(route('audiences.seances.store'), [
            'type' => 'tutelle', 'date_seance' => '2026-11-20', 'statut' => 'tenue', 'cree_par' => $autre->id, 'id' => 999,
        ])->assertSessionHasNoErrors();
        $s = \App\Models\SeanceHorsAudience::firstOrFail();
        $this->assertSame(['prevue', $admin->id], [$s->statut, $s->cree_par]);
        $this->assertNotSame(999, $s->id);
    }
}
