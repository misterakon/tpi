<?php

namespace Tests\Feature;

use App\Models\Affectation;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Creneau;
use App\Models\DelaiEnrolement;
use App\Models\Fermeture;
use App\Models\Formation;
use App\Models\Profil;
use App\Models\Section;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendrierJudiciaireTest extends TestCase
{
    use RefreshDatabase;

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function avecDroits(array $droits): User
    {
        $profil = Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits]);

        return User::factory()->create(['profil_id' => $profil->id]);
    }

    private function annee(string $libelle = '2026-2027', string $debut = '2026-10-12', string $fin = '2027-10-11', bool $courante = true): AnneeJudiciaire
    {
        return AnneeJudiciaire::create(['libelle' => $libelle, 'debut' => $debut, 'fin' => $fin, 'courante' => $courante]);
    }

    /* ---------- Audience supplémentaire ---------- */

    public function test_audience_supplementaire_a_date_unique(): void
    {
        $annee = $this->annee();
        $section = Section::create(['code' => 'civile', 'libelle' => 'Civile', 'ordre' => 1]);
        $chambre = Chambre::create(['annee_judiciaire_id' => $annee->id, 'section_id' => $section->id, 'libelle' => '1re chambre', 'ordre' => 1, 'actif' => true]);
        $f = Formation::create(['chambre_id' => $chambre->id, 'ordre' => 1, 'actif' => true]);
        $admin = User::factory()->create();

        $base = ['formation_id' => $f->id, 'type' => 'Audience spéciale', 'frequence' => 'ponctuelle', 'heure' => '14:00'];
        $this->connecte($admin)->post(route('organisation.creneaux.store'), $base)->assertSessionHasErrors('debut');
        $this->connecte($admin)->post(route('organisation.creneaux.store'), $base + ['debut' => '2026-11-12'])->assertSessionHasNoErrors();

        $c = Creneau::firstOrFail();
        $this->assertSame(4, $c->jour);   // jeudi
        $this->assertSame('2026-11-12', $c->fin->format('Y-m-d'));
        $this->assertSame('Jeudi 12/11/2026 à 14:00', $c->quand());
    }

    /* ---------- Jours fériés et vacances ---------- */

    public function test_fermetures_ajout_modification_suppression_et_feries_fixes(): void
    {
        $annee = $this->annee();
        $admin = User::factory()->create();

        $this->connecte($admin)->post(route('organisation.fermetures.store'), ['annee_judiciaire_id' => $annee->id, 'type' => 'vacances', 'libelle' => 'Vacances de fin d\'année', 'debut' => '2026-12-20', 'fin' => '2027-01-05'])->assertSessionHasNoErrors();
        $v = Fermeture::firstOrFail();
        $this->assertSame(17, $v->jours());
        $this->assertNotNull(Fermeture::couvre('2026-12-25'));
        $this->assertNull(Fermeture::couvre('2027-02-01'));

        $this->connecte($admin)->post(route('organisation.fermetures.store'), ['annee_judiciaire_id' => $annee->id, 'type' => 'ferie', 'libelle' => 'X', 'debut' => '2026-12-01', 'fin' => '2026-11-01'])->assertSessionHasErrors('fin');
        $this->connecte($admin)->post(route('organisation.fermetures.store'), ['id_' => $v->id, 'annee_judiciaire_id' => $annee->id, 'type' => 'vacances', 'libelle' => 'Vacances', 'debut' => '2026-12-21'])->assertSessionHasNoErrors();
        $this->assertSame(1, $v->refresh()->jours());

        $this->connecte($admin)->post(route('organisation.fermetures.feries'), ['annee_judiciaire_id' => $annee->id])->assertSessionHasNoErrors();
        // 2026-10-12 → 2027-10-11 : 1er nov, 15 nov, 25 déc, 1er janv, 1er mai, 7 août, 15 août = 7 fêtes fixes.
        $this->assertSame(7, Fermeture::where('type', 'ferie')->count());
        $this->connecte($admin)->post(route('organisation.fermetures.feries'), ['annee_judiciaire_id' => $annee->id]);
        $this->assertSame(7, Fermeture::where('type', 'ferie')->count());

        $this->connecte($admin)->get(route('organisation.fermetures'))->assertOk()->assertSee('Toussaint');
        $this->connecte($admin)->deleteJson(route('organisation.fermetures.delete', $v->id))->assertJson(['status' => true]);
        $this->assertDatabaseMissing('fermetures', ['id' => $v->id]);
    }

    public function test_fermetures_droits(): void
    {
        $annee = $this->annee();
        $this->connecte($this->avecDroits([]))->get(route('organisation.fermetures'))->assertForbidden();
        $lecteur = $this->avecDroits(['fermetures.voir']);
        $this->connecte($lecteur)->get(route('organisation.fermetures'))->assertOk()->assertDontSee('onclick="add_form(', false);
        $this->connecte($lecteur)->post(route('organisation.fermetures.store'), ['annee_judiciaire_id' => $annee->id, 'type' => 'ferie', 'libelle' => 'X', 'debut' => '2026-12-01'])->assertForbidden();
    }

    /* ---------- Dates limites d'enrôlement ---------- */

    public function test_regles_d_enrolement_de_l_ordonnance_et_calcul(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->assertSame(5, DelaiEnrolement::count());

        // Audience du lundi 2 novembre 2026 → mercredi précédent 28 octobre, 16 h ; jeudi 5 novembre → lundi 2 novembre.
        $this->assertSame('2026-10-28 16:00', DelaiEnrolement::limitePour('2026-11-02')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-02 16:00', DelaiEnrolement::limitePour('2026-11-05')->format('Y-m-d H:i'));
        $this->assertNull(DelaiEnrolement::limitePour('2026-11-07'));   // samedi : pas de règle

        $admin = User::factory()->create();
        $this->connecte($admin)->getJson(route('organisation.enrolement.calcul', ['date' => '2026-11-02']))->assertJson(['status' => true])
            ->assertJsonPath('message', "Enrôlement jusqu'au Mercredi 28/10/2026 à 16h00.");
        $this->connecte($admin)->getJson(route('organisation.enrolement.calcul', ['date' => '2026-11-07']))->assertJson(['status' => false]);

        // Une règle par jour d'audience.
        $this->connecte($admin)->post(route('organisation.enrolement.store'), ['jour_audience' => 1, 'jour_limite' => 5, 'heure' => '12:00'])->assertSessionHasErrors('jour_audience');
        $regle = DelaiEnrolement::where('jour_audience', 1)->firstOrFail();
        $this->connecte($admin)->post(route('organisation.enrolement.store'), ['id_' => $regle->id, 'jour_audience' => 1, 'jour_limite' => 5, 'heure' => '12:00'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-30 12:00', DelaiEnrolement::limitePour('2026-11-02')->format('Y-m-d H:i'));

        // Le seeder relancé n'écrase pas la règle modifiée.
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->assertSame(5, $regle->refresh()->jour_limite);

        $this->connecte($admin)->get(route('organisation.enrolement'))->assertOk()->assertSee('Mercredi précédent');
        $this->connecte($this->avecDroits([]))->get(route('organisation.enrolement'))->assertForbidden();
    }

    /* ---------- Reconduction ---------- */

    public function test_reconduction_copie_la_structure_sans_rien_ecraser(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $source = AnneeJudiciaire::courante();
        $cible = $this->annee('2027-2028', '2027-10-12', '2028-10-11', false);
        $admin = User::factory()->create();

        // Une audience supplémentaire et une audience terminée ne doivent pas être reconduites.
        $formation = Formation::whereHas('chambre', fn ($q) => $q->where('annee_judiciaire_id', $source->id))->has('creneaux')->firstOrFail();
        Creneau::create(['formation_id' => $formation->id, 'type' => 'Spéciale', 'frequence' => 'ponctuelle', 'jour' => 4, 'debut' => '2026-11-12', 'fin' => '2026-11-12']);
        Creneau::create(['formation_id' => $formation->id, 'type' => 'Ancien horaire', 'frequence' => 'hebdomadaire', 'jour' => 2, 'heure' => '08:00', 'fin' => '2026-12-31']);

        $attendus = [
            'chambres' => Chambre::where('annee_judiciaire_id', $source->id)->count(),
            'formations' => Formation::whereHas('chambre', fn ($q) => $q->where('annee_judiciaire_id', $source->id))->count(),
        ];

        $this->connecte($admin)->post(route('organisation.reconduction.executer'), ['source_id' => $source->id, 'cible_id' => $cible->id, 'composition' => 1, 'audiences' => 1, 'services' => 1])->assertSessionHasNoErrors();

        $this->assertSame($attendus['chambres'], Chambre::where('annee_judiciaire_id', $cible->id)->count());
        $this->assertSame($attendus['formations'], Formation::whereHas('chambre', fn ($q) => $q->where('annee_judiciaire_id', $cible->id))->count());
        $this->assertSame(Service::where('annee_judiciaire_id', $source->id)->count(), Service::where('annee_judiciaire_id', $cible->id)->count());

        $nouvelles = Affectation::whereHas('formation.chambre', fn ($q) => $q->where('annee_judiciaire_id', $cible->id));
        $this->assertGreaterThan(0, $nouvelles->count());
        $this->assertSame(['2027-10-12'], $nouvelles->get()->map(fn ($a) => $a->debut->format('Y-m-d'))->unique()->values()->all());
        $this->assertNull($nouvelles->whereNotNull('fin')->first());

        $creneaux = Creneau::whereHas('formation.chambre', fn ($q) => $q->where('annee_judiciaire_id', $cible->id));
        $this->assertSame(0, (clone $creneaux)->where('frequence', 'ponctuelle')->count());
        $this->assertSame(0, (clone $creneaux)->where('type', 'Ancien horaire')->count());
        $this->assertSame(27, (clone $creneaux)->count());

        // Relancer ne duplique rien.
        $this->connecte($admin)->post(route('organisation.reconduction.executer'), ['source_id' => $source->id, 'cible_id' => $cible->id, 'composition' => 1, 'audiences' => 1, 'services' => 1]);
        $this->assertSame($attendus['chambres'], Chambre::where('annee_judiciaire_id', $cible->id)->count());
        $this->assertDatabaseHas('journal_audit', ['action' => 'annee_reconduite']);
    }

    public function test_reconduction_validations_et_droits(): void
    {
        $a = $this->annee();
        $admin = User::factory()->create();
        $this->connecte($admin)->post(route('organisation.reconduction.executer'), ['source_id' => $a->id, 'cible_id' => $a->id])->assertSessionHasErrors('cible_id');

        $this->connecte($this->avecDroits([]))->get(route('organisation.reconduction'))->assertForbidden();
        $lecteur = $this->avecDroits(['reconduction.voir']);
        $this->connecte($lecteur)->get(route('organisation.reconduction'))->assertOk();
        $b = $this->annee('2027-2028', '2027-10-12', '2028-10-11', false);
        $this->connecte($lecteur)->post(route('organisation.reconduction.executer'), ['source_id' => $a->id, 'cible_id' => $b->id])->assertForbidden();
    }
}
