<?php

namespace Tests\Feature;

use App\Http\Controllers\JournalController;
use App\Models\JournalAudit;
use App\Models\JournalConnexion;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournauxTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@tribunal.test']);
    }

    private function avecDroits(array $droits): User
    {
        $profil = Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits]);

        return User::factory()->create(['profil_id' => $profil->id]);
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function ligneConnexion(array $attributs = []): JournalConnexion
    {
        return JournalConnexion::create($attributs + ['evenement' => 'connexion_reussie', 'succes' => true, 'ip' => '10.0.0.1', 'navigateur' => 'Firefox']);
    }

    /* ---------- Accès ---------- */

    public function test_les_pages_exigent_le_droit_de_consultation(): void
    {
        $sansDroit = $this->avecDroits([]);
        $this->connecte($sansDroit)->get('/journaux/connexions')->assertForbidden();
        $this->get('/journaux/audit')->assertForbidden();
        $this->get('/journaux/connexions/export')->assertForbidden();
        $this->get('/journaux/audit/export')->assertForbidden();

        $this->connecte($this->avecDroits(['journaux.connexion']))->get('/journaux/connexions')->assertOk();
        $this->get('/journaux/audit')->assertForbidden();
        $this->connecte($this->avecDroits(['journaux.audit']))->get('/journaux/audit')->assertOk();
    }

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        $this->get('/journaux/connexions')->assertRedirect('/login');
    }

    public function test_l_export_exige_le_droit_exporter_et_le_bouton_est_masque(): void
    {
        $lecteur = $this->avecDroits(['journaux.connexion']);
        $this->connecte($lecteur)->get('/journaux/connexions')->assertOk()->assertDontSee('Exporter (CSV)');
        $this->get('/journaux/connexions/export')->assertForbidden();

        $this->connecte($this->avecDroits(['journaux.connexion', 'journaux.connexion.exporter']))->get('/journaux/connexions')->assertSee('Exporter (CSV)');
    }

    /* ---------- Journal de connexion ---------- */

    public function test_le_journal_de_connexion_affiche_les_lignes_avec_libelles(): void
    {
        $this->ligneConnexion(['evenement' => 'connexion_echec', 'succes' => false, 'email_saisi' => 'inconnu@exemple.ci', 'motif' => 'Mot de passe incorrect']);

        $this->connecte($this->admin())->get('/journaux/connexions')->assertOk()->assertSee('data-serveur', false);
        $l = $this->lignes('/journaux/connexions/donnees');
        foreach (['Échec de connexion', 'inconnu@exemple.ci', 'Mot de passe incorrect', '10.0.0.1'] as $texte) {
            $this->assertStringContainsString($texte, $l);
        }
    }

    public function test_les_filtres_du_journal_de_connexion(): void
    {
        $this->ligneConnexion(['email_saisi' => 'bon@exemple.ci']);
        $this->ligneConnexion(['evenement' => 'connexion_echec', 'succes' => false, 'email_saisi' => 'mauvais@exemple.ci', 'ip' => '10.9.9.9']);
        $this->connecte($this->admin());

        $this->assertSame([true, false], $this->voit('/journaux/connexions/donnees?resultat=echec', 'mauvais@exemple.ci', 'bon@exemple.ci'));
        $this->assertSame([true, false], $this->voit('/journaux/connexions/donnees?type=connexion_reussie', 'bon@exemple.ci', 'mauvais@exemple.ci'));
        $this->assertSame([true, false], $this->voit('/journaux/connexions/donnees?q=10.9.9', 'mauvais@exemple.ci', 'bon@exemple.ci'));
        $this->assertSame(0, $this->getJson('/journaux/connexions/donnees?du='.now()->addDay()->format('Y-m-d'))->assertOk()->json('recordsTotal'));
    }

    public function test_les_filtres_invalides_sont_refuses(): void
    {
        $this->connecte($this->admin());
        $this->get('/journaux/connexions?type=nimporte_quoi')->assertSessionHasErrors('type');
        $this->get('/journaux/connexions?du=pas-une-date')->assertSessionHasErrors('du');
        $this->get('/journaux/audit?q='.str_repeat('a', 101))->assertSessionHasErrors('q');
    }

    public function test_la_pagination_est_cote_serveur(): void
    {
        foreach (range(1, 60) as $i) {
            $this->ligneConnexion(['email_saisi' => "ligne{$i}@exemple.ci"]);
        }
        $this->connecte($this->admin());

        $p1 = $this->getJson('/journaux/connexions/donnees?start=0&length=20')->assertOk();
        $this->assertSame(60, $p1->json('recordsTotal'));
        $this->assertCount(20, $p1->json('data'));
        $this->assertStringContainsString('ligne60@', implode(' ', $p1->json('data.0')));
        $dernier = implode(' ', $this->getJson('/journaux/connexions/donnees?start=40&length=20')->json('data.19'));
        $this->assertStringContainsString('ligne1@exemple.ci', $dernier);
    }

    public function test_la_recherche_traite_les_jokers_comme_du_texte(): void
    {
        $this->ligneConnexion(['email_saisi' => 'abc@exemple.ci']);
        $this->connecte($this->admin());
        $this->assertSame(0, $this->getJson('/journaux/connexions/donnees?q=%25')->assertOk()->json('recordsTotal'));
    }

    /* ---------- Journal d'audit ---------- */

    public function test_le_journal_d_audit_montre_avant_et_apres(): void
    {
        JournalAudit::create(['action' => 'compte_modifie', 'libelle' => 'awa@exemple.ci', 'avant' => ['name' => 'Awa'], 'apres' => ['name' => 'Awa Koné'], 'ip' => '10.0.0.2']);

        $this->connecte($this->admin())->get('/journaux/audit')->assertOk();
        $l = $this->lignes('/journaux/audit/donnees');
        foreach (['Compte modifié', 'awa@exemple.ci', 'Awa Koné', '1 champ(s)'] as $texte) {
            $this->assertStringContainsString($texte, $l);
        }
        $this->assertCount(5, $this->getJson('/journaux/audit/donnees')->json('data.0'));   // le tableau avant/après reste dans sa cellule
    }

    public function test_le_filtre_par_action_et_la_recherche_d_audit(): void
    {
        JournalAudit::create(['action' => 'profil_cree', 'libelle' => 'Greffier']);
        JournalAudit::create(['action' => 'compte_supprime', 'libelle' => 'vieux@exemple.ci']);
        $this->connecte($this->admin());

        $this->assertSame([true, false], $this->voit('/journaux/audit/donnees?type=profil_cree', 'Greffier', 'vieux@exemple.ci'));
        $this->assertSame([true, false], $this->voit('/journaux/audit/donnees?q=vieux', 'vieux@exemple.ci', 'Greffier'));
    }

    /* ---------- Export ---------- */

    public function test_l_export_csv_respecte_les_filtres_et_est_journalise(): void
    {
        $this->ligneConnexion(['email_saisi' => 'bon@exemple.ci']);
        $this->ligneConnexion(['evenement' => 'connexion_echec', 'succes' => false, 'email_saisi' => 'mauvais@exemple.ci']);

        $reponse = $this->connecte($this->admin())->get('/journaux/connexions/export?resultat=echec');
        $reponse->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $reponse->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('mauvais@exemple.ci', $csv);
        $this->assertStringNotContainsString('bon@exemple.ci', $csv);
        $this->assertDatabaseHas('journal_audit', ['action' => 'journal_exporte', 'libelle' => 'Journal de connexion']);
    }

    public function test_l_export_neutralise_les_formules_excel(): void
    {
        foreach (['=SOMME(A1)', '+33', '-1', '@cmd', 'normal'] as $valeur) {
            $attendu = $valeur === 'normal' ? 'normal' : "'".$valeur;
            $this->assertSame($attendu, JournalController::neutraliser($valeur));
        }
        $this->assertSame('', JournalController::neutraliser(null));
    }

    /* ---------- Intégrité ---------- */

    public function test_une_ligne_de_journal_ne_peut_etre_ni_modifiee_ni_supprimee(): void
    {
        $ligne = $this->ligneConnexion();

        $this->expectException(\LogicException::class);
        $ligne->update(['ip' => '1.1.1.1']);
    }

    public function test_le_menu_affiche_les_journaux_selon_les_droits(): void
    {
        $this->connecte($this->avecDroits(['journaux.audit']))->get('/accueil')->assertOk()
            ->assertSee("Journal d'audit")->assertDontSee('Journal de connexion');
    }

    /** Le texte des cellules renvoyées par une liste paginée du journal. */
    private function lignes(string $url): string
    {
        return implode(' ', array_map(fn ($l) => implode(' ', $l), $this->getJson($url)->assertOk()->json('data')));
    }

    /** @return array{bool, bool} le premier texte est-il présent, le second aussi ? */
    private function voit(string $url, string $present, string $absent): array
    {
        $l = $this->lignes($url);

        return [str_contains($l, $present), str_contains($l, $absent)];
    }
}
