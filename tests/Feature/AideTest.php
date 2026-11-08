<?php

namespace Tests\Feature;

use App\Models\Profil;
use App\Models\User;
use App\Support\Aide\ManuelAide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Manuel d'utilisation intégré : chapitres selon les droits, recherche, aide de la page, manuel complet. */
class AideTest extends TestCase
{
    use RefreshDatabase;

    private function connecte(User $u): static
    {
        return $this->actingAs($u)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function sansDroit(): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Vide'.uniqid(), 'droits' => []])->id]);
    }

    private function avecDroits(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'P'.uniqid(), 'droits' => $droits])->id]);
    }

    public function test_les_chapitres_se_lisent_et_ont_des_sections(): void
    {
        ManuelAide::oublier();
        $tous = ManuelAide::tous();
        $this->assertGreaterThanOrEqual(9, count($tous));
        foreach (['demarrer', 'affaires', 'audiences', 'decisions', 'blocages'] as $slug) {
            $this->assertArrayHasKey($slug, $tous);
            $this->assertNotEmpty($tous[$slug]['sections']);
        }
        // Les ancres du parcours de la page d'accueil de l'aide existent bien.
        $ancres = fn ($slug) => array_column($tous[$slug]['sections'], 'ancre');
        $this->assertContains('enroler-une-affaire', $ancres('affaires'));
        $this->assertContains('preparer-les-roles', $ancres('audiences'));
        $this->assertContains('tenir-laudience', $ancres('audiences'));
        $this->assertContains('rediger-une-decision', $ancres('decisions'));
        $this->assertContains('signer-et-delivrer', $ancres('decisions'));
        $this->assertContains('recours-et-certificats', $ancres('decisions'));
    }

    public function test_il_faut_etre_connecte(): void
    {
        $this->get(route('aide.index'))->assertRedirect(route('login'));
        $this->getJson(route('aide.recherche', ['q' => 'audience']))->assertUnauthorized();
        $this->get(route('aide.contexte'))->assertRedirect(route('login'));
    }

    public function test_chacun_ne_voit_que_les_chapitres_de_ses_droits(): void
    {
        $page = $this->connecte($this->sansDroit())->get(route('aide.index'))->assertOk();
        $page->assertSee('Premiers pas')->assertSee('Je suis bloqué')->assertDontSee('href="'.route('aide.chapitre', 'affaires').'"', false);
        $this->connecte($this->sansDroit())->get(route('aide.chapitre', 'affaires'))->assertNotFound();
        $this->connecte($this->sansDroit())->get(route('aide.chapitre', 'demarrer'))->assertOk()->assertSee('Se connecter');

        $greffier = $this->avecDroits(['affaires.voir']);
        $this->connecte($greffier)->get(route('aide.chapitre', 'affaires'))->assertOk()->assertSee('Enrôler une affaire');
        $this->connecte($greffier)->get(route('aide.chapitre', 'decisions'))->assertNotFound();
        $this->connecte($greffier)->get(route('aide.chapitre', 'inexistant'))->assertNotFound();

        $admin = User::factory()->create();
        $this->connecte($admin)->get(route('aide.index'))->assertOk()->assertSee('Administration')->assertSee('Décisions');
    }

    public function test_la_recherche_suit_les_droits_et_met_en_evidence(): void
    {
        $admin = User::factory()->create();
        $r = $this->connecte($admin)->getJson(route('aide.recherche', ['q' => 'certificat recours']))->assertOk()->json('resultats');
        $this->assertNotEmpty($r);
        $this->assertStringContainsString('<mark>', implode(' ', array_column($r, 'extrait')));
        $this->assertStringContainsString('/aide/', $r[0]['url']);

        // Un compte sans droit ne trouve rien dans les chapitres qui lui sont fermés.
        $sans = $this->connecte($this->sansDroit())->getJson(route('aide.recherche', ['q' => 'jonction numéro enrôlement']))->assertOk()->json('resultats');
        foreach ($sans as $x) {
            $this->assertNotSame('affaires', $x['slug']);
        }
        // Mot trop court ou vide : aucun résultat, pas d'erreur. Saisie hostile : neutralisée.
        $this->assertSame([], $this->connecte($admin)->getJson(route('aide.recherche', ['q' => 'a']))->json('resultats'));
        $hostile = $this->connecte($admin)->getJson(route('aide.recherche', ['q' => '<script>alert(1)</script>']))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert', $hostile);
    }

    public function test_le_panneau_donne_l_aide_de_la_page_courante(): void
    {
        $admin = User::factory()->create();
        $this->connecte($admin)->get(route('aide.contexte', ['page' => 'affaires.liste']))->assertOk()->assertSee('data-titre="Affaires"', false)->assertSee('Enrôler une affaire');
        $this->connecte($admin)->get(route('aide.contexte', ['page' => 'decisions.redaction']))->assertOk()->assertSee('data-titre="Décisions"', false);
        $this->connecte($admin)->get(route('aide.contexte', ['page' => 'page.inconnue']))->assertOk()->assertSee('data-titre="Aide"', false);
        // Un paramètre qui n'est pas un nom de route est ignoré (jamais interprété).
        $this->connecte($admin)->get(route('aide.contexte', ['page' => '../../etc/passwd']))->assertOk()->assertSee('data-titre="Aide"', false);
        // Sans droit sur les affaires, l'aide de cette page est le sommaire.
        $this->connecte($this->sansDroit())->get(route('aide.contexte', ['page' => 'affaires.liste']))->assertOk()->assertSee('data-titre="Aide"', false);
    }

    public function test_le_manuel_complet_et_le_bouton_d_aide(): void
    {
        $admin = User::factory()->create();
        $this->connecte($admin)->get(route('aide.manuel'))->assertOk()->assertSee('Manuel complet')->assertSee('Recours et certificats')->assertSee('Je suis bloqué');
        $this->connecte($admin)->get(route('dashboard'))->assertOk()->assertSee('id="aide-ouvrir"', false)->assertSee('id="aide-tiroir"', false);
    }

    public function test_les_notes_et_les_etapes_sont_mises_en_forme(): void
    {
        ManuelAide::oublier();
        $html = implode(' ', array_column(ManuelAide::tous()['affaires']['sections'], 'html'));
        $this->assertStringContainsString('aide-note--attention', $html);
        $this->assertStringContainsString('aide-note--savoir', $html);
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<table>', implode(' ', array_column(ManuelAide::tous()['audiences']['sections'], 'html')));
    }

    public function test_les_captures_annotees_affichent_des_epingles_numerotees(): void
    {
        $m = new \ReflectionMethod(ManuelAide::class, 'html');
        $m->setAccessible(true);
        $html = $m->invoke(null, '![Un écran](aide/exemple.png "1@10,20=Premier | 2@50,60=Second")');
        $this->assertStringContainsString('class="aide-capture"', $html);
        $this->assertStringContainsString('class="aide-marque" data-n="1"', $html);
        $this->assertStringContainsString('<ol class="aide-legende">', $html);
        $this->assertStringContainsString('/assets/aide/exemple.png', $html);
        $this->assertStringNotContainsString('<p><figure', $html);

        // Toute capture citée par un chapitre doit exister (aucun lien cassé dans le manuel).
        ManuelAide::oublier();
        foreach (ManuelAide::tous() as $c) {
            foreach ($c['sections'] as $s) {
                preg_match_all('#/assets/aide/([A-Za-z0-9._-]+)#', $s['html'], $f);
                foreach ($f[1] as $fichier) {
                    $this->assertFileExists(public_path('assets/aide/'.$fichier), "Capture manquante : $fichier (chapitre {$c['slug']})");
                }
            }
        }
    }
}
