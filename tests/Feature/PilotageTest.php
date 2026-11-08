<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\RoleAudience;
use App\Models\SynthesePilotage;
use App\Models\User;
use App\Support\Pilotage\AnalyseAuto;
use App\Support\Pilotage\DonneesPilotage;
use App\Support\Pilotage\RedacteurIA;
use App\Support\Roles\GenerateurRole;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Analyse et pilotage : chiffres, analyse automatique, analyse par l'IA (encadrée), droits et périmètre. */
class PilotageTest extends TestCase
{
    use RefreshDatabase;

    private Chambre $chambre;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->chambre = Chambre::where('libelle', 'like', '%chambre civile%')->orderBy('id')->firstOrFail();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function avecDroits(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits])->id]);
    }

    private function presidente(array $en_plus = []): User
    {
        return $this->avecDroits(array_merge(['tableau.voir', 'affaires.toutes_chambres', 'affaires.famille.mineurs', 'affaires.famille.instruction'], $en_plus));
    }

    private function affaire(string $dateRg, string $audience): Affaire
    {
        static $n = 0;
        $n++;
        $affaire = Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 600 + $n, 'numero_complet' => 'RG-'.(600 + $n).'/26', 'numero_saisi' => true, 'date_rg' => $dateRg, 'objet' => 'Paiement de loyers '.$n,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $this->chambre->id, 'statut' => 'enrolee', 'date_premiere_audience' => $audience,
        ]);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'demandeur', 'nature' => 'homme', 'nom' => 'Kone', 'prenoms' => 'Ibrahima '.$n, 'cle' => 'k'.$n]);

        return $affaire;
    }

    private function rolePublie(string $date): RoleAudience
    {
        $role = RoleAudience::create(['chambre_id' => $this->chambre->id, 'date_audience' => $date, 'heure' => '09:00', 'fait_a' => 'Bingerville', 'date_signature' => $date]);
        GenerateurRole::completer($role);
        $role->forceFill(['statut' => 'publie'])->save();

        return $role->refresh();
    }

    /** Une affaire enrôlée le 14/10 et jugée le 28/10 (sortie, 14 jours), une autre enrôlée le 20/10 et renvoyée (en cours). */
    private function activite(): array
    {
        $jugee = $this->affaire('2026-10-14', '2026-10-28');
        $encours = $this->affaire('2026-10-20', '2026-10-28');
        $role = $this->rolePublie('2026-10-28');
        $role->lignes()->where('affaire_id', $jugee->id)->update(['issue' => 'juge']);
        $role->lignes()->where('affaire_id', $encours->id)->update(['issue' => 'renvoi', 'renvoi_au' => '2026-11-20']);

        return [$jugee, $encours];
    }

    // ---------------------------------------------------------------- chiffres

    public function test_les_chiffres_de_la_periode(): void
    {
        $this->activite();
        $u = $this->presidente();

        $d = DonneesPilotage::calculer($u, DonneesPilotage::periode('30j'));
        $k = $d['kpis'];

        $this->assertSame(2, $k['entrees']);
        $this->assertSame(1, $k['sorties']);
        $this->assertSame(50, $k['taux_sortie']);
        $this->assertSame(14, $k['delai_moyen'], 'Du 14/10 au 28/10 : quatorze jours.');
        $this->assertSame(1, $k['stock'], "Seule l'affaire renvoyée est en cours.");
        $this->assertSame(1, $k['a_rediger'], 'La jugée est sans décision validée.');
        $this->assertSame(100, $k['feuilles_saisies_pct']);
        $this->assertSame(50, $k['renvois_pct']);

        $ligne = collect($d['par_chambre'])->firstWhere('id', $this->chambre->id);
        $this->assertSame([1, 2, 1, 50, 14], [$ligne['stock'], $ligne['entrees'], $ligne['sorties'], $ligne['taux_sortie'], $ligne['delai_moyen']]);
        $this->assertCount(12, $d['mensuel']['mois']);
        $this->assertSame(2, array_sum($d['mensuel']['entrees']));
        $this->assertSame(1, array_sum($d['mensuel']['sorties']));
        $this->assertSame(6, collect($d['suites'])->count());

        // Hors période (le 14/10 est dans les 30 derniers jours, pas dans les sept derniers) : l'année judiciaire garde tout.
        $this->assertSame('annee', DonneesPilotage::periode('annee')['type']);
        $this->assertSame('30j', DonneesPilotage::periode('inconnu')['type'], 'Une période inconnue est ignorée.');
    }

    public function test_chacun_ne_voit_que_les_chiffres_de_ses_chambres(): void
    {
        $this->activite();
        $limite = $this->avecDroits(['tableau.voir']);   // aucune chambre affectée

        $d = DonneesPilotage::calculer($limite, DonneesPilotage::periode('30j'));

        $this->assertSame(0, $d['kpis']['stock']);
        $this->assertSame(0, $d['kpis']['entrees']);
        $this->assertSame([], $d['par_chambre']);
        $this->assertSame('Aucune chambre', $d['perimetre']);
    }

    // ---------------------------------------------------------------- analyse automatique

    public function test_l_analyse_automatique_est_deterministe_et_chiffree(): void
    {
        $this->activite();
        $d = DonneesPilotage::calculer($this->presidente(), DonneesPilotage::periode('30j'));

        $a = AnalyseAuto::generer($d);
        $this->assertSame($a, AnalyseAuto::generer($d), 'Les mêmes chiffres donnent la même analyse.');
        $this->assertGreaterThanOrEqual(0, $a['sante']['score']);
        $this->assertLessThanOrEqual(100, $a['sante']['score']);
        $this->assertSame(100 + array_sum(array_column($a['sante']['facteurs'], 'points')), $a['sante']['score'], "L'indice est 100 moins ce qui le fait baisser.");
        foreach (["## Situation d'ensemble", '## Points forts', "## Points d'attention", '## Recommandations'] as $titre) {
            $this->assertStringContainsString($titre, $a['texte']);
        }
        $this->assertStringContainsString('1 affaire jugée sans décision validée', $a['texte'], "L'accord du pluriel est respecté.");
        $this->assertNotEmpty($a['recommandations']);
    }

    public function test_l_analyse_signale_un_stock_qui_grossit_et_accorde_le_pluriel(): void
    {
        $d = $this->donneesVides();
        $d['kpis'] = array_merge($d['kpis'], ['stock' => 40, 'entrees' => 30, 'sorties' => 6, 'taux_sortie' => 20, 'a_rediger' => 3, 'plus_ancienne_decision' => 40, 'alertes_critiques' => 2, 'alertes_ouvertes' => 5, 'detenus_proches' => 2]);

        $a = AnalyseAuto::generer($d);

        $this->assertStringContainsString('30 affaires enrôlées pour 6 affaires terminées', $a['texte']);
        $this->assertStringContainsString('3 affaires jugées sans décision validée', $a['texte']);
        $this->assertStringContainsString('2 alertes critiques', $a['texte']);
        $this->assertStringContainsString('2 détenus', $a['texte']);
        $this->assertSame('critique', $a['points_attention'][0]['gravite'], 'Le plus grave passe en premier.');
        $this->assertSame('alerte', $a['sante']['niveau']);

        $sain = $this->donneesVides();
        $sain['kpis'] = array_merge($sain['kpis'], ['stock' => 10, 'entrees' => 5, 'sorties' => 8, 'taux_sortie' => 160, 'decisions_validees' => 4, 'feuilles_saisies_pct' => 100, 'audiences_passees' => 3, 'delai_moyen' => 60]);
        $b = AnalyseAuto::generer($sain);
        $this->assertSame(100, $b['sante']['score']);
        $this->assertSame('bon', $b['sante']['niveau']);
    }

    /** @return array<string, mixed> */
    private function donneesVides(): array
    {
        return [
            'periode' => ['type' => '30j', 'libelle' => '30 derniers jours', 'debut' => '2026-10-12', 'fin' => '2026-11-10', 'jours' => 30], 'perimetre' => 'Toutes les chambres',
            'kpis' => ['stock' => 0, 'entrees' => 0, 'sorties' => 0, 'taux_sortie' => null, 'delai_moyen' => null, 'a_rediger' => 0, 'plus_ancienne_decision' => 0, 'decisions_validees' => 0, 'retards' => 0, 'alertes_ouvertes' => 0,
                'alertes_critiques' => 0, 'alertes_en_retard' => 0, 'audiences_passees' => 0, 'feuilles_saisies_pct' => null, 'renvois_pct' => null, 'detenus_proches' => 0, 'affaires_renvoyees_3_fois' => 0],
            'par_chambre' => [], 'mensuel' => ['mois' => [], 'entrees' => [], 'sorties' => []], 'suites' => [], 'alertes_par_type' => [], 'alertes_recentes' => [], 'agents_sans_compte' => 0,
        ];
    }

    // ---------------------------------------------------------------- page

    public function test_la_page_et_ses_donnees_exigent_le_droit(): void
    {
        $this->activite();
        $sans = $this->avecDroits(['roles.voir']);
        $this->connecte($sans)->get(route('audiences.pilotage'))->assertForbidden();
        $this->connecte($sans)->getJson(route('audiences.pilotage.donnees'))->assertForbidden();

        $u = $this->presidente();
        $this->connecte($u)->get(route('audiences.pilotage'))->assertOk()->assertSee('Analyse et pilotage')->assertSee('Note PDF');
        $j = $this->connecte($u)->getJson(route('audiences.pilotage.donnees', ['periode' => '90j']))->assertOk();
        $j->assertJsonPath('donnees.periode.type', '90j')->assertJsonPath('donnees.kpis.entrees', 2)->assertJsonStructure(['donnees' => ['kpis', 'par_chambre', 'mensuel', 'suites'], 'analyse' => ['sante' => ['score', 'niveau', 'facteurs'], 'texte'], 'ia', 'genere_le']);
        $j->assertJsonCount(7, 'analyse.sante.regles');   // les règles de l'indice sont données à l'écran pour que chacun le comprenne
        $this->assertStringContainsString('Enrôlées', $this->connecte($u)->get(route('audiences.pilotage'))->getContent(), "Le vocabulaire est celui de l'application (enrôlées, terminées).");
        $this->connecte($u)->getJson(route('audiences.pilotage.donnees', ['periode' => "x'; drop table"]))->assertOk()->assertJsonPath('donnees.periode.type', '30j');
    }

    public function test_le_menu_mene_a_la_page_pilotage(): void
    {
        $page = $this->connecte($this->presidente())->get(route('audiences.pilotage'))->getContent();
        $this->assertStringContainsString('/audiences/pilotage', $page);
        $this->assertSame(1, preg_match_all('#<li><a class="active" href="[^"]*/audiences/pilotage">#', $page));
    }

    // ---------------------------------------------------------------- IA

    private function activerIA(): void
    {
        config(['pilotage.ia_active' => 'oui', 'pilotage.ia_cle' => 'cle-d-essai', 'pilotage.ia_modele' => 'claude-sonnet-5-5', 'pilotage.ia_generations_par_jour' => 2]);
    }

    private function reponseIA(string $texte): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);   // des essais successifs ne s'additionnent pas
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'thinking', 'thinking' => '', 'signature' => 'x'], ['type' => 'text', 'text' => $texte]]], 200)]);
    }

    private const NOTE = "## Situation d'ensemble\nLe tribunal reçoit plus d'affaires qu'il n'en juge.\n\n## Points forts\n- Les feuilles sont saisies.\n\n## Points d'attention\n- **Stock** en hausse.\n\n## Recommandations\n- Augmenter le rythme de jugement.";

    public function test_la_note_pdf_est_dessinee_par_le_serveur(): void
    {
        $this->activite();
        $u = $this->presidente();

        $r = $this->connecte($u)->get(route('audiences.pilotage.pdf', ['periode' => '30j']))->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->assertDatabaseHas('journal_audit', ['action' => 'pilotage_note_pdf']);

        // Sans le droit de voir la page : refusé.
        $this->connecte($this->avecDroits([]))->get(route('audiences.pilotage.pdf'))->assertForbidden();
    }

    public function test_l_ia_n_est_proposee_que_si_elle_est_activee_et_autorisee(): void
    {
        $this->activite();
        $u = $this->presidente(['tableau.ia']);

        // Désactivée : refus lisible, aucun appel externe.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake();
        $this->connecte($u)->postJson(route('audiences.pilotage.analyser'), ['periode' => '30j'])->assertStatus(422)->assertJsonPath('status', false);
        Http::assertNothingSent();

        $this->activerIA();
        $this->reponseIA(self::NOTE);
        // Sans le droit : interdit.
        $this->connecte($this->presidente())->postJson(route('audiences.pilotage.analyser'), ['periode' => '30j'])->assertForbidden();
        // Avec le droit : la note est rédigée, conservée et tracée.
        $r = $this->connecte($u)->postJson(route('audiences.pilotage.analyser'), ['periode' => '30j'])->assertOk();
        $r->assertJsonPath('status', true)->assertJsonPath('ia.source', 'ia')->assertJsonPath('quota', 1);
        $this->assertStringContainsString('## Recommandations', $r->json('ia.contenu'));
        $this->assertStringNotContainsString('**', $r->json('ia.contenu'), 'Le texte est nettoyé.');
        $this->assertDatabaseHas('syntheses_pilotage', ['source' => 'ia', 'user_id' => $u->id, 'modele_ia' => 'claude-sonnet-5-5']);
        $this->assertDatabaseHas('journal_audit', ['action' => 'pilotage_analyse_ia']);

        // La note ressort dans les données de la page, et se relit.
        $j = $this->connecte($u)->getJson(route('audiences.pilotage.donnees'))->assertOk();
        $this->assertSame('ia', $j->json('ia.source'));
        $this->connecte($u)->getJson(route('audiences.pilotage.synthese', SynthesePilotage::firstOrFail()->id))->assertOk()->assertJsonPath('ia.source', 'ia');
    }

    public function test_seuls_des_totaux_partent_vers_l_ia(): void
    {
        $this->activite();
        $this->activerIA();
        $this->reponseIA(self::NOTE);
        $u = $this->presidente(['tableau.ia']);

        $this->connecte($u)->postJson(route('audiences.pilotage.analyser'), ['periode' => '30j'])->assertOk();

        Http::assertSent(function (ClientRequest $requete) {
            $corps = json_encode($requete->data(), JSON_UNESCAPED_UNICODE);

            return $requete->url() === 'https://api.anthropic.com/v1/messages'
                && $requete->hasHeader('x-api-key', 'cle-d-essai')
                && ! str_contains($corps, 'Kone') && ! str_contains($corps, 'Ibrahima') && ! str_contains($corps, 'RG-6') && ! str_contains($corps, 'loyers')   // ni partie, ni numéro, ni objet
                && str_contains($corps, 'par_chambre') && str_contains($corps, 'taux_de_traitement_pct');
        });
    }

    public function test_le_plafond_quotidien_et_les_pannes_retombent_sur_l_analyse_automatique(): void
    {
        $this->activite();
        $this->activerIA();
        $u = $this->presidente(['tableau.ia']);

        // Réponse incomplète : refusée, rien n'est conservé.
        $this->reponseIA('Voici un texte sans la structure demandée.');
        $this->connecte($u)->postJson(route('audiences.pilotage.analyser'), ['periode' => '30j'])->assertStatus(422);
        $this->assertSame(0, SynthesePilotage::count());

        // Service refusé (clé invalide) : message clair.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'invalid x-api-key'], 401)]);
        $r = $this->connecte($u)->postJson(route('audiences.pilotage.analyser'), ['periode' => '30j'])->assertStatus(422);
        $this->assertStringContainsString('401', $r->json('message'));
        $this->assertStringNotContainsString('cle-d-essai', $r->getContent(), 'La clé ne sort jamais.');

        // Plafond : deux analyses par jour, la troisième est refusée.
        $this->reponseIA(self::NOTE);
        $this->connecte($u)->postJson(route('audiences.pilotage.analyser'), ['periode' => '30j'])->assertOk();
        $this->connecte($u)->postJson(route('audiences.pilotage.analyser'), ['periode' => '30j'])->assertOk();
        $this->assertSame(0, RedacteurIA::quotaRestant());
        $this->connecte($u)->postJson(route('audiences.pilotage.analyser'), ['periode' => '30j'])->assertStatus(422);
        // L'analyse automatique reste là.
        $this->connecte($u)->getJson(route('audiences.pilotage.donnees'))->assertOk()->assertJsonStructure(['analyse' => ['texte']]);
    }

    public function test_les_notes_d_un_autre_ne_se_lisent_que_si_on_voit_toutes_les_chambres(): void
    {
        $this->activerIA();
        $autre = $this->presidente(['tableau.ia']);
        $note = SynthesePilotage::create(['user_id' => $autre->id, 'source' => 'ia', 'periode_type' => '30j', 'periode_debut' => '2026-10-12', 'periode_fin' => '2026-11-10', 'perimetre' => 'Toutes', 'contenu' => self::NOTE]);

        $this->connecte($this->presidente())->getJson(route('audiences.pilotage.synthese', $note->id))->assertOk();       // voit toutes les chambres
        $this->connecte($this->avecDroits(['tableau.voir']))->getJson(route('audiences.pilotage.synthese', $note->id))->assertNotFound();   // périmètre limité : seulement les siennes
    }
}
