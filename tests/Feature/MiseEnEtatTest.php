<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\AffaireAttribution;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Chambre;
use App\Models\MiseEnEtat;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Profil;
use App\Models\RoleAudience;
use App\Models\User;
use App\Support\Alertes\CollecteEcheances;
use App\Support\Roles\GenerateurRole;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Lot P : mise en état (AUD-07, AUD-08). */
class MiseEnEtatTest extends TestCase
{
    use RefreshDatabase;

    private Chambre $chambre;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->chambre = Chambre::where('libelle', 'like', '%3e chambre civile%')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function utilisateur(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'P'.uniqid(), 'droits' => $droits])->id]);
    }

    private function greffe(array $en_plus = []): User
    {
        return $this->utilisateur(array_merge(['mise_en_etat.voir', 'mise_en_etat.creer', 'mise_en_etat.modifier', 'mise_en_etat.annuler', 'affaires.voir', 'affaires.toutes_chambres'], $en_plus));
    }

    private function connecte(User $u): static
    {
        return $this->actingAs($u)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function affaire(string $serie = 'RG', ?Chambre $chambre = null, string $premiere = '2026-11-12'): Affaire
    {
        static $n = 0;
        $n++;

        return Affaire::create([
            'serie' => $serie, 'annee' => 2026, 'numero' => 500 + $n, 'numero_complet' => $serie.'-'.(500 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-10-20', 'objet' => 'Essai '.$n,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => ($chambre ?? $this->chambre)->id, 'statut' => 'enrolee', 'date_premiere_audience' => $premiere,
        ]);
    }

    private function juge(?Chambre $chambre = null): Agent
    {
        $agent = Agent::create(['nom' => 'JUGE'.uniqid(), 'prenoms' => 'Test', 'fonction' => 'juge', 'actif' => true]);
        Affectation::create(['formation_id' => ($chambre ?? $this->chambre)->formations()->value('id'), 'agent_id' => $agent->id, 'role' => 'president']);

        return $agent;
    }

    private function ouvrir(Affaire $a, array $plus = []): MiseEnEtat
    {
        $this->connecte($this->greffe())->post(route('affaires.mise_en_etat.store'), array_merge(['affaire_id' => $a->id, 'date_reception' => '2026-11-02'], $plus))->assertSessionHasNoErrors();

        return MiseEnEtat::where('affaire_id', $a->id)->firstOrFail();
    }

    public function test_on_confie_une_affaire_civile_a_un_juge_de_sa_chambre(): void
    {
        $a = $this->affaire();
        $juge = $this->juge();
        $ailleurs = $this->juge(Chambre::where('libelle', 'like', '%2e chambre civile%')->firstOrFail());

        // Un juge d'une autre chambre est refusé.
        $this->connecte($this->greffe())->post(route('affaires.mise_en_etat.store'), ['affaire_id' => $a->id, 'date_reception' => '2026-11-02', 'juge_id' => $ailleurs->id])->assertSessionHasErrors('juge_id');
        $this->assertSame(0, MiseEnEtat::count());

        $m = $this->ouvrir($a, ['juge_id' => $juge->id, 'date_premiere_audience' => '2026-11-05', 'observations' => 'Hérédité']);
        $this->assertSame('en_cours', $m->statut);
        $this->assertSame($juge->id, $m->juge_id);
        $this->assertSame('2026-11-05', $m->date_premiere_audience->toDateString());
        $this->assertDatabaseHas('journal_audit', ['action' => 'mise_en_etat_ouverte']);

        // Pas deux mises en état ouvertes pour la même affaire.
        $this->connecte($this->greffe())->post(route('affaires.mise_en_etat.store'), ['affaire_id' => $a->id, 'date_reception' => '2026-11-03'])->assertSessionHasErrors('affaire_id');
        $this->assertSame(1, MiseEnEtat::count());
    }

    public function test_seules_les_affaires_civiles_en_cours_et_non_jointes_passent_en_mise_en_etat(): void
    {
        $penale = $this->affaire('RP');
        $annulee = $this->affaire();
        $annulee->forceFill(['statut' => 'annulee'])->save();
        $principale = $this->affaire();
        $jointe = $this->affaire();
        $jointe->forceFill(['jointe_a_id' => $principale->id])->save();

        foreach ([$penale, $annulee, $jointe] as $a) {
            $this->connecte($this->greffe())->post(route('affaires.mise_en_etat.store'), ['affaire_id' => $a->id, 'date_reception' => '2026-11-02'])->assertSessionHasErrors('affaire_id');
        }
        $this->assertSame(0, MiseEnEtat::count());
    }

    public function test_les_dates_sont_coherentes(): void
    {
        $a = $this->affaire();
        $this->connecte($this->greffe())->post(route('affaires.mise_en_etat.store'), ['affaire_id' => $a->id, 'date_reception' => '2026-12-31'])->assertSessionHasErrors('date_reception');
        $this->connecte($this->greffe())->post(route('affaires.mise_en_etat.store'), ['affaire_id' => $a->id, 'date_reception' => '2026-11-05', 'date_premiere_audience' => '2026-11-01'])->assertSessionHasErrors('date_premiere_audience');
        $this->assertSame(0, MiseEnEtat::count());
    }

    public function test_une_affaire_en_mise_en_etat_n_est_ni_sur_le_role_ni_en_alerte_de_mise_au_role(): void
    {
        $a = $this->affaire('RG', null, '2026-11-12');
        $role = RoleAudience::create(['chambre_id' => $this->chambre->id, 'date_audience' => '2026-11-12', 'heure' => '09:00', 'fait_a' => 'Bingerville', 'date_signature' => '2026-11-12']);

        $this->assertCount(1, GenerateurRole::proposer($role)['nouvelles'], 'Avant : l\'affaire est proposée au rôle.');
        $this->assertNotNull(CollecteEcheances::toutes(today())->firstWhere('type', 'mise_au_role'));

        $this->ouvrir($a);

        $propositions = GenerateurRole::proposer($role);
        $this->assertCount(0, $propositions['nouvelles']);
        $this->assertCount(0, $propositions['anciennes']);
        $this->assertNull(CollecteEcheances::toutes(today())->firstWhere('type', 'mise_au_role'), 'Pas d\'alerte « à mettre au rôle » pendant la mise en état.');
        $this->assertNull(CollecteEcheances::toutes(today()->addDays(30))->firstWhere('type', 'jamais_portee'), 'Pas non plus d\'alerte « jamais portée ».');
    }

    public function test_la_cloture_verse_l_affaire_au_role_de_jugement_et_calcule_la_duree(): void
    {
        $a = $this->affaire('RG', null, '2026-11-12');
        $m = $this->ouvrir($a, ['date_reception' => '2026-10-20', 'date_premiere_audience' => '2026-10-27']);

        // Validations de la clôture.
        $url = route('affaires.mise_en_etat.cloturer', $m->id);
        $this->connecte($this->greffe())->post($url, ['numero_ordonnance_cloture' => '', 'date_cloture' => '2026-11-09', 'date_audience_jugement' => '2026-11-19'])->assertSessionHasErrors('numero_ordonnance_cloture');
        $this->connecte($this->greffe())->post($url, ['numero_ordonnance_cloture' => '12/26', 'date_cloture' => '2026-10-01', 'date_audience_jugement' => '2026-11-19'])->assertSessionHasErrors('date_cloture');
        $this->connecte($this->greffe())->post($url, ['numero_ordonnance_cloture' => '12/26', 'date_cloture' => '2026-11-09', 'date_audience_jugement' => '2026-11-01'])->assertSessionHasErrors('date_audience_jugement');
        $this->assertTrue($m->refresh()->estOuverte());

        $this->connecte($this->greffe())->post($url, ['numero_ordonnance_cloture' => '12/26', 'date_cloture' => '2026-11-09', 'date_audience_jugement' => '2026-11-19'])->assertSessionHas('success');
        $m->refresh();
        $this->assertSame('cloturee', $m->statut);
        $this->assertSame(13, $m->dureeJours(), 'Du 27/10 (première audience) au 09/11 (clôture) : 13 jours.');
        $this->assertSame($this->chambre->id, $m->chambre_jugement_id);
        $this->assertNotNull($m->versee_le);
        $this->assertSame('2026-11-19', $a->refresh()->date_premiere_audience->toDateString());
        $this->assertDatabaseHas('journal_audit', ['action' => 'mise_en_etat_cloturee']);

        // L'affaire figure maintenant parmi les « nouvelles » du rôle du 19/11.
        $role = RoleAudience::create(['chambre_id' => $this->chambre->id, 'date_audience' => '2026-11-19', 'heure' => '09:00', 'fait_a' => 'Bingerville', 'date_signature' => '2026-11-19']);
        $this->assertSame([$a->id], GenerateurRole::proposer($role)['nouvelles']->pluck('id')->all());

        // Une mise en état clôturée ne se modifie plus.
        $this->connecte($this->greffe())->post(route('affaires.mise_en_etat.store'), ['id_' => $m->id, 'date_reception' => '2026-11-03'])->assertForbidden();
    }

    public function test_changer_de_chambre_a_la_cloture_exige_le_droit_de_reattribuer(): void
    {
        $a = $this->affaire();
        $autre = Chambre::where('libelle', 'like', '%2e chambre civile%')->firstOrFail();
        $m = $this->ouvrir($a);
        $url = route('affaires.mise_en_etat.cloturer', $m->id);
        $donnees = ['numero_ordonnance_cloture' => '7/26', 'date_cloture' => '2026-11-09', 'date_audience_jugement' => '2026-11-19', 'chambre_jugement_id' => $autre->id];

        $this->connecte($this->greffe())->post($url, $donnees)->assertForbidden();
        $this->assertTrue($m->refresh()->estOuverte());
        $this->assertSame($this->chambre->id, $a->refresh()->chambre_id);

        $this->connecte($this->greffe(['affaires.reattribuer']))->post($url, $donnees)->assertSessionHas('success');
        $this->assertSame($autre->id, $a->refresh()->chambre_id, 'L\'affaire est versée à la chambre de jugement.');
        $this->assertTrue(AffaireAttribution::where('affaire_id', $a->id)->where('chambre_apres_id', $autre->id)->exists(), 'Le changement de chambre est tracé.');
    }

    public function test_retirer_l_affaire_de_la_mise_en_etat_la_remet_dans_le_circuit(): void
    {
        $a = $this->affaire('RG', null, '2026-11-12');
        $m = $this->ouvrir($a);
        $role = RoleAudience::create(['chambre_id' => $this->chambre->id, 'date_audience' => '2026-11-12', 'heure' => '09:00', 'fait_a' => 'Bingerville', 'date_signature' => '2026-11-12']);

        $this->connecte($this->greffe())->post(route('affaires.mise_en_etat.retirer', $m->id), ['motif' => 'x'])->assertSessionHasErrors('motif');
        $this->connecte($this->greffe())->post(route('affaires.mise_en_etat.retirer', $m->id), ['motif' => 'Saisie par erreur'])->assertSessionHas('success');

        $this->assertSame('retiree', $m->refresh()->statut);
        $this->assertCount(1, GenerateurRole::proposer($role)['nouvelles']);

        // On peut de nouveau la confier à la mise en état.
        $this->ouvrir($a);
        $this->assertSame(2, MiseEnEtat::where('affaire_id', $a->id)->count());
    }

    public function test_les_droits_et_le_perimetre(): void
    {
        $a = $this->affaire();
        $autreChambre = Chambre::where('libelle', 'like', '%2e chambre civile%')->firstOrFail();
        $b = $this->affaire('RG', $autreChambre);
        $this->ouvrir($b);

        // Sans droit : refusé.
        $this->connecte($this->utilisateur(['affaires.voir']))->get(route('affaires.mise_en_etat'))->assertForbidden();
        $lecteur = $this->utilisateur(['mise_en_etat.voir', 'affaires.voir']);
        $this->connecte($lecteur)->post(route('affaires.mise_en_etat.store'), ['affaire_id' => $a->id, 'date_reception' => '2026-11-02'])->assertForbidden();

        // Un greffier de la 3e chambre ne voit pas la mise en état de la 2e.
        $greffier = $this->utilisateur(['mise_en_etat.voir', 'mise_en_etat.creer', 'affaires.voir']);
        $agent = Agent::create(['nom' => 'GREFFIER', 'prenoms' => 'Test', 'fonction' => 'greffier', 'actif' => true, 'user_id' => $greffier->id]);
        Affectation::create(['formation_id' => $this->chambre->formations()->value('id'), 'agent_id' => $agent->id, 'role' => 'greffier']);
        $this->assertStringNotContainsString($b->numeroAffichage(), $this->lignesServeur($greffier, 'affaires.mise_en_etat.donnees'));
        $this->connecte($greffier)->post(route('affaires.mise_en_etat.store'), ['affaire_id' => $b->id, 'date_reception' => '2026-11-02'])->assertSessionHasErrors('affaire_id');
        $this->connecte($greffier)->getJson(route('affaires.mise_en_etat.get', MiseEnEtat::first()->id))->assertNotFound();

        // La page de la présidence montre tout.
        $this->connecte($this->greffe())->get(route('affaires.mise_en_etat'))->assertOk()->assertSee('Mise en état');
        $this->assertStringContainsString($b->numeroAffichage(), $this->lignesServeur($this->greffe(), 'affaires.mise_en_etat.donnees'));
    }

    public function test_une_mise_en_etat_ouverte_trop_longtemps_est_signalee(): void
    {
        $a = $this->affaire();
        $m = $this->ouvrir($a, ['date_reception' => '2026-06-20', 'date_premiere_audience' => '2026-07-01']);

        $this->assertSame(132, $m->dureeJours());
        $this->assertTrue($m->estLongue());
        $this->connecte($this->greffe())->get(route('affaires.mise_en_etat'))->assertOk();
        $this->assertStringContainsString('longue', $this->lignesServeur($this->greffe(), 'affaires.mise_en_etat.donnees'));
    }
}
