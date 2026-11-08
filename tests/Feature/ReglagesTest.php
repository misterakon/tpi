<?php

namespace Tests\Feature;

use App\Mail\CodeConnexion;
use App\Models\Profil;
use App\Models\User;
use App\Services\Reglages;
use App\Support\Droits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReglagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@tribunal.test']);
    }

    private function avecDroits(array $droits): User
    {
        $profil = Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits]);

        return User::factory()->create(['profil_id' => $profil->id, 'email' => uniqid().'@tribunal.test']);
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    /** Valeurs actuelles d'une rubrique, sous la forme du formulaire, avec des modifications. */
    private function formulaire(string $rubrique, array $changements = []): array
    {
        $donnees = [];
        foreach (Reglages::catalogue()[$rubrique]['champs'] as $cle => $champ) {
            if (Reglages::estSecret($champ)) {
                continue;   // un mot de passe laissé vide est conservé
            }
            $donnees[Reglages::nomChamp($cle)] = config($cle);
        }

        return array_merge($donnees, $changements);
    }

    private function reglages(): Reglages
    {
        return app(Reglages::class);
    }

    /* ---------- Accès ---------- */

    public function test_l_admin_voit_la_page_reglages_avec_toutes_les_rubriques(): void
    {
        $this->connecte($this->admin())->get('/parametres/reglages')->assertOk()
            ->assertSee('Réglages')->assertSee('Organisation')->assertSee('Sécurité')->assertSee('Courriels')->assertSee('Interface')
            ->assertSee('Verrouillage après inactivité')->assertSee('Réglages protégés');
    }

    public function test_sans_droit_la_page_est_interdite(): void
    {
        $this->connecte($this->avecDroits([]))->get('/parametres/reglages')->assertForbidden();
        $this->post('/parametres/reglages/securite', $this->formulaire('securite'))->assertForbidden();
    }

    public function test_avec_le_droit_de_lecture_on_consulte_sans_pouvoir_modifier(): void
    {
        $lecteur = $this->avecDroits(['reglages.voir']);

        $html = $this->connecte($lecteur)->get('/parametres/reglages')->assertOk()
            ->assertSee('pas les modifier')->getContent();
        $this->assertStringNotContainsString('Valeurs par défaut</button>', $html);

        $this->post('/parametres/reglages/securite', $this->formulaire('securite', ['securite__inactivite_minutes' => 99]))->assertForbidden();
        $this->post('/parametres/reglages/securite/defaut')->assertForbidden();
        $this->assertDatabaseCount('reglages', 0);
    }

    public function test_les_onglets_n_utilisent_pas_jquery_au_chargement(): void
    {
        // Le script de la page est inclus avant jQuery : il ne doit utiliser que du JavaScript natif au chargement.
        $html = $this->connecte($this->admin())->get('/parametres/reglages')->assertOk()->getContent();

        $this->assertStringContainsString("onclick=\"afficher_rubrique('securite')\"", $html);
        $this->assertMatchesRegularExpression('/function afficher_rubrique\(cle\)\s*\{\s*document\.querySelectorAll/', $html);
        $this->assertStringNotContainsString("$('.el-rub').on(", $html);              // l'ancienne écriture, qui échouait ($ inconnu à ce moment)
        $this->assertStringNotContainsString("$('.el-reglage__ctrl [data-initial]').on(", $html);
    }

    public function test_les_encadres_d_information_contournent_l_icone(): void
    {
        $css = file_get_contents(public_path('assets/css/elouange-theme.css'));

        $this->assertStringContainsString('.el-note__icon, .el-banner__icon { float: left;', $css);
        $this->assertStringContainsString('.el-note, .el-banner { display: flow-root; }', $css);
    }

    public function test_le_menu_montre_reglages_selon_les_droits(): void
    {
        $this->connecte($this->avecDroits(['reglages.voir']))->get('/accueil')->assertSee('Réglages');
        $this->connecte($this->avecDroits(['comptes.voir']))->get('/accueil')->assertDontSee('/parametres/reglages', false);
    }

    /* ---------- Enregistrer ---------- */

    public function test_enregistrer_la_securite_modifie_la_configuration_et_la_base(): void
    {
        $this->connecte($this->admin())
            ->post('/parametres/reglages/securite', $this->formulaire('securite', ['securite__inactivite_minutes' => 30, 'securite__echecs_max' => 7]))
            ->assertRedirect(route('reglages.index', ['groupe' => 'securite']));

        $this->assertSame(30, config('securite.inactivite_minutes'));
        $this->assertSame(7, config('securite.echecs_max'));
        $this->assertDatabaseHas('reglages', ['cle' => 'securite.inactivite_minutes', 'valeur' => '30']);
        $this->assertDatabaseCount('reglages', 2);   // seuls les réglages changés sont enregistrés
    }

    public function test_un_reglage_modifie_s_applique_vraiment_au_comportement(): void
    {
        $user = $this->admin();
        $this->connecte($user)->post('/parametres/reglages/securite', $this->formulaire('securite', ['securite__inactivite_minutes' => 60]))->assertRedirect();

        $this->travel(45)->minutes();   // 45 min : verrouillé avec 20 min, mais pas avec 60 min
        $this->get('/accueil')->assertOk();
    }

    public function test_les_reglages_enregistres_survivent_a_un_redemarrage(): void
    {
        $this->connecte($this->admin())->post('/parametres/reglages/securite', $this->formulaire('securite', ['securite__echecs_max' => 8]))->assertRedirect();

        config(['securite.echecs_max' => 999]);   // simule un démarrage avec la valeur d'origine
        $neuf = new Reglages;
        $neuf->appliquer();

        $this->assertSame(8, config('securite.echecs_max'));
        $this->assertSame(999, $neuf->defaut('securite.echecs_max'));
    }

    public function test_une_valeur_remise_au_defaut_supprime_la_ligne(): void
    {
        $defaut = config('securite.echecs_max');
        $this->connecte($this->admin());

        $this->post('/parametres/reglages/securite', $this->formulaire('securite', ['securite__echecs_max' => 9]));
        $this->assertDatabaseHas('reglages', ['cle' => 'securite.echecs_max']);

        $this->post('/parametres/reglages/securite', $this->formulaire('securite', ['securite__echecs_max' => $defaut]));
        $this->assertDatabaseMissing('reglages', ['cle' => 'securite.echecs_max']);
    }

    public function test_aucune_modification_est_signalee_sans_ecrire_dans_le_journal(): void
    {
        $this->connecte($this->admin())->post('/parametres/reglages/securite', $this->formulaire('securite'))
            ->assertRedirect()->assertSessionHas('info');

        $this->assertDatabaseMissing('journal_audit', ['action' => 'reglages_modifies']);
    }

    public function test_chaque_modification_est_journalisee_avec_avant_et_apres(): void
    {
        $avant = config('securite.inactivite_minutes');
        $this->connecte($this->admin())->post('/parametres/reglages/securite', $this->formulaire('securite', ['securite__inactivite_minutes' => 45]));

        $ligne = DB::table('journal_audit')->where('action', 'reglages_modifies')->first();
        $this->assertNotNull($ligne);
        $this->assertSame('Sécurité', $ligne->libelle);
        $this->assertSame($avant, json_decode($ligne->avant, true)['securite.inactivite_minutes']);
        $this->assertSame(45, json_decode($ligne->apres, true)['securite.inactivite_minutes']);
    }

    /* ---------- Bornes ---------- */

    public function test_les_valeurs_hors_bornes_sont_refusees(): void
    {
        $this->connecte($this->admin());
        $refus = [
            ['securite__inactivite_minutes', 0], ['securite__inactivite_minutes', 241], ['securite__inactivite_minutes', 'abc'],
            ['securite__deverrouillage_essais_max', 2], ['securite__deverrouillage_essais_max', 11],
            ['securite__double_auth__validite_minutes', 1], ['securite__double_auth__renvoi_secondes', 5],
            ['securite__mot_de_passe_min', 4], ['securite__mot_de_passe_min', 7], ['securite__mot_de_passe_min', 65],
        ];

        foreach ($refus as [$champ, $valeur]) {
            $this->post('/parametres/reglages/securite', $this->formulaire('securite', [$champ => $valeur]))
                ->assertRedirect(route('reglages.index', ['groupe' => 'securite']))->assertSessionHasErrors($champ);
        }
        $this->assertDatabaseCount('reglages', 0);
    }

    public function test_la_longueur_minimale_du_mot_de_passe_ne_descend_jamais_sous_8(): void
    {
        $this->connecte($this->admin())->post('/parametres/reglages/securite', $this->formulaire('securite', ['securite__mot_de_passe_min' => 8]))->assertRedirect();
        $this->assertGreaterThanOrEqual(8, config('securite.mot_de_passe_min'));

        $this->post('/parametres/reglages/securite', $this->formulaire('securite', ['securite__mot_de_passe_min' => 3]))->assertSessionHasErrors('securite__mot_de_passe_min');
    }

    public function test_les_textes_sont_obligatoires_et_limites(): void
    {
        $this->connecte($this->admin());

        $this->post('/parametres/reglages/organisation', $this->formulaire('organisation', ['tpi__sigle' => '']))->assertSessionHasErrors('tpi__sigle');
        $this->post('/parametres/reglages/organisation', $this->formulaire('organisation', ['tpi__sigle' => str_repeat('A', 13)]))->assertSessionHasErrors('tpi__sigle');
        $this->post('/parametres/reglages/interface', $this->formulaire('interface', ['tpi__lignes_par_page' => 7]))->assertSessionHasErrors('tpi__lignes_par_page');
    }

    public function test_une_rubrique_inconnue_est_refusee(): void
    {
        $this->connecte($this->admin())->post('/parametres/reglages/inconnue', ['x' => 1])->assertNotFound();
        $this->post('/parametres/reglages/inconnue/defaut')->assertNotFound();
    }

    public function test_un_reglage_sensible_ne_peut_pas_etre_modifie_par_ce_formulaire(): void
    {
        $cleAvant = config('app.key');
        $this->connecte($this->admin())->post('/parametres/reglages/organisation', $this->formulaire('organisation') + [
            'app__key' => 'base64:pirate', 'database__connections__mysql__password' => 'pirate',
        ])->assertRedirect();

        $this->assertSame($cleAvant, config('app.key'));
        $this->assertDatabaseMissing('reglages', ['cle' => 'app.key']);
    }

    /* ---------- Valeurs par défaut ---------- */

    public function test_retablir_les_valeurs_par_defaut_d_une_rubrique(): void
    {
        $defaut = config('securite.inactivite_minutes');
        $this->connecte($this->admin());
        $this->post('/parametres/reglages/securite', $this->formulaire('securite', ['securite__inactivite_minutes' => 90, 'securite__echecs_max' => 9]));
        $this->assertDatabaseCount('reglages', 2);

        $this->post('/parametres/reglages/securite/defaut')->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseCount('reglages', 0);
        $this->assertSame($defaut, config('securite.inactivite_minutes'));
        $this->assertDatabaseHas('journal_audit', ['action' => 'reglages_retablis']);
    }

    public function test_retablir_une_rubrique_ne_touche_pas_aux_autres(): void
    {
        $this->connecte($this->admin());
        $this->post('/parametres/reglages/securite', $this->formulaire('securite', ['securite__echecs_max' => 9]));
        $this->post('/parametres/reglages/interface', $this->formulaire('interface', ['tpi__lignes_par_page' => 50]));

        $this->post('/parametres/reglages/securite/defaut');

        $this->assertDatabaseMissing('reglages', ['cle' => 'securite.echecs_max']);
        $this->assertDatabaseHas('reglages', ['cle' => 'tpi.lignes_par_page', 'valeur' => '50']);
    }

    /* ---------- Les réglages sont utilisés par l'application ---------- */

    public function test_la_marque_de_l_en_tete_vient_des_reglages(): void
    {
        $this->connecte($this->admin())->post('/parametres/reglages/organisation', $this->formulaire('organisation', ['tpi__sigle' => 'TGI', 'tpi__ville' => 'ABIDJAN']))->assertRedirect();

        $this->get('/accueil')->assertOk()->assertSee('TGI')->assertSee('ABIDJAN');
    }

    public function test_les_courriels_utilisent_les_reglages_d_organisation(): void
    {
        $this->connecte($this->admin())->post('/parametres/reglages/organisation', $this->formulaire('organisation', [
            'tpi__sigle' => 'TGI', 'tpi__ville' => 'ABIDJAN', 'tpi__juridiction' => 'Tribunal de Grande Instance d\'Abidjan',
        ]))->assertRedirect();

        $html = (new CodeConnexion('Awa', '123456', 10))->render();
        $this->assertStringContainsString('TGI', $html);
        $this->assertStringContainsString('ABIDJAN', $html);
        $this->assertStringContainsString('Tribunal de Grande Instance d&#039;Abidjan', $html);
    }

    public function test_le_nom_de_l_application_et_celui_de_l_expediteur_se_reglent(): void
    {
        $this->connecte($this->admin());
        $this->post('/parametres/reglages/organisation', $this->formulaire('organisation', ['app__name' => 'Greffe Numérique']))->assertRedirect();
        $this->post('/parametres/reglages/courriels', $this->smtp(['mail__default' => 'log', 'mail__from__name' => 'Greffe de Bingerville']))->assertRedirect();

        $this->assertSame('Greffe Numérique', config('app.name'));
        $this->assertSame('Greffe de Bingerville', config('mail.from.name'));
        $this->get('/accueil')->assertSee('Greffe Numérique');
    }

    public function test_le_nombre_de_lignes_par_page_est_transmis_aux_tableaux(): void
    {
        $this->connecte($this->admin())->post('/parametres/reglages/interface', $this->formulaire('interface', ['tpi__lignes_par_page' => 50]))->assertRedirect();

        $this->get('/accueil')->assertSee('window.TPI_LIGNES_PAR_PAGE = 50', false);
    }

    /* ---------- Intégrité du catalogue ---------- */

    public function test_le_catalogue_est_coherent_et_sans_reglage_sensible(): void
    {
        $this->assertNotEmpty(Reglages::champs());
        foreach (Reglages::champs() as $cle => $champ) {
            $this->assertNotEmpty($champ['libelle'].$champ['aide']);
            $this->assertStringNotContainsString('key', strtolower($cle));
            $this->assertStringNotContainsString('database', $cle);
            if (Reglages::estSecret($champ)) {
                $this->assertSame('mail.mailers.smtp.password', $cle);   // seul secret autorisé
                $this->assertSame('reglages.secrets', Reglages::droitRubrique('courriels'));

                continue;
            }
            if (! in_array($cle, ['mail.mailers.smtp.username'], true)) {
                $this->assertNotNull(config($cle), "$cle n'existe pas dans la configuration");
            }
            $this->assertStringNotContainsString('password', strtolower($cle));
            $this->assertStringNotContainsString('secret', strtolower($cle));

            if ($champ['type'] === 'entier') {
                $this->assertGreaterThan(0, $champ['min']);
                $this->assertLessThanOrEqual(100000, $champ['max']);   // jamais de borne démesurée
                $this->assertGreaterThanOrEqual($champ['min'], $this->reglages()->defaut($cle), "$cle : défaut hors bornes");
                $this->assertLessThanOrEqual($champ['max'], $this->reglages()->defaut($cle), "$cle : défaut hors bornes");
            }
        }
    }

    /* ---------- Serveur de courriel (SMTP) ---------- */

    private function smtp(array $changements = []): array
    {
        return $this->formulaire('courriels', array_merge([
            'mail__default' => 'smtp', 'mail__mailers__smtp__host' => 'smtp.exemple.ci', 'mail__mailers__smtp__port' => 587,
            'mail__mailers__smtp__scheme' => 'smtp', 'mail__mailers__smtp__username' => 'greffe@exemple.ci',
            'mail__from__address' => 'greffe@exemple.ci',
        ], $changements));
    }

    public function test_le_serveur_smtp_se_regle_et_le_mot_de_passe_est_chiffre(): void
    {
        $this->connecte($this->admin())->post('/parametres/reglages/courriels', $this->smtp(['mail__mailers__smtp__password' => 'MotDePasse-Secret-42']))->assertRedirect();

        $this->assertSame('smtp.exemple.ci', config('mail.mailers.smtp.host'));
        $this->assertSame('MotDePasse-Secret-42', config('mail.mailers.smtp.password'));
        $stocke = DB::table('reglages')->where('cle', 'mail.mailers.smtp.password')->value('valeur');
        $this->assertNotNull($stocke);
        $this->assertStringNotContainsString('MotDePasse-Secret-42', (string) $stocke);
        $this->assertStringNotContainsString('MotDePasse-Secret-42', json_encode(DB::table('journal_audit')->get()));
    }

    public function test_le_mot_de_passe_n_apparait_jamais_dans_la_page(): void
    {
        $this->connecte($this->admin())->post('/parametres/reglages/courriels', $this->smtp(['mail__mailers__smtp__password' => 'MotDePasse-Secret-42']));

        $this->get('/parametres/reglages?groupe=courriels')->assertOk()
            ->assertDontSee('MotDePasse-Secret-42')->assertSee('Un mot de passe est enregistré');
    }

    public function test_un_mot_de_passe_vide_conserve_l_ancien_et_effacer_le_supprime(): void
    {
        $this->connecte($this->admin())->post('/parametres/reglages/courriels', $this->smtp(['mail__mailers__smtp__password' => 'Ancien-Mdp-1']));
        $this->post('/parametres/reglages/courriels', $this->smtp(['mail__mailers__smtp__password' => '']))->assertRedirect();
        $this->assertSame('Ancien-Mdp-1', config('mail.mailers.smtp.password'));

        $this->post('/parametres/reglages/courriels', $this->smtp(['mail__mailers__smtp__password__effacer' => '1']))->assertRedirect();
        $this->assertEmpty(config('mail.mailers.smtp.password'));
    }

    public function test_sans_le_droit_secrets_la_rubrique_courriels_est_invisible_et_protegee(): void
    {
        $user = $this->avecDroits(['reglages.voir', 'reglages.modifier']);

        $this->connecte($user)->get('/parametres/reglages?groupe=courriels')->assertOk()->assertDontSee('Serveur SMTP')->assertDontSee('Tester l\'envoi');
        $this->post('/parametres/reglages/courriels', $this->smtp())->assertForbidden();
        $this->post('/parametres/reglages/courriels/test', ['destinataire' => 'a@b.ci'])->assertForbidden();
        $this->assertDatabaseMissing('reglages', ['cle' => 'mail.mailers.smtp.host']);
    }

    public function test_un_serveur_ou_un_expediteur_invalide_est_refuse(): void
    {
        $this->connecte($this->admin());
        $this->post('/parametres/reglages/courriels', $this->smtp(['mail__mailers__smtp__host' => 'serveur invalide; drop']))->assertSessionHasErrors('mail__mailers__smtp__host');
        $this->post('/parametres/reglages/courriels', $this->smtp(['mail__mailers__smtp__port' => 70000]))->assertSessionHasErrors('mail__mailers__smtp__port');
        $this->post('/parametres/reglages/courriels', $this->smtp(['mail__from__address' => 'pas-un-email']))->assertSessionHasErrors('mail__from__address');
        $this->assertDatabaseMissing('reglages', ['cle' => 'mail.mailers.smtp.host']);
    }

    public function test_le_courriel_de_test_est_envoye(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $this->connecte($this->admin())->postJson('/parametres/reglages/courriels/test', ['destinataire' => 'dest@exemple.ci'])
            ->assertOk()->assertJson(['status' => true]);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TestCourriel::class, fn ($m) => $m->hasTo('dest@exemple.ci'));
    }

    public function test_un_echec_d_envoi_est_signale_sans_reveler_le_mot_de_passe(): void
    {
        $this->connecte($this->admin())->post('/parametres/reglages/courriels', $this->smtp([
            'mail__mailers__smtp__host' => '127.0.0.1', 'mail__mailers__smtp__port' => 1, 'mail__mailers__smtp__password' => 'MotDePasse-Secret-42',
        ]));

        $reponse = $this->postJson('/parametres/reglages/courriels/test', ['destinataire' => 'dest@exemple.ci'])->assertOk()->assertJson(['status' => false]);
        $this->assertStringNotContainsString('MotDePasse-Secret-42', $reponse->getContent());
        $this->assertStringNotContainsString('MotDePasse-Secret-42', json_encode(DB::table('journal_audit')->get()));
    }

    public function test_le_test_exige_une_adresse_valide(): void
    {
        $this->connecte($this->admin())->postJson('/parametres/reglages/courriels/test', ['destinataire' => 'oups'])->assertStatus(422);
    }

    public function test_les_droits_reglages_sont_dans_le_catalogue_des_droits(): void
    {
        $this->assertContains('reglages.voir', Droits::tous());
        $this->assertContains('reglages.modifier', Droits::tous());
        $niveaux = Droits::versChoix(Droits::depuisChoix(['reglages' => 'lecture']));
        $this->assertSame('lecture', $niveaux['niveaux']['reglages']);
    }
}
