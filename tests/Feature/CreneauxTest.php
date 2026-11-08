<?php

namespace Tests\Feature;

use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Creneau;
use App\Models\Formation;
use App\Models\Profil;
use App\Models\Salle;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreneauxTest extends TestCase
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

    private function formation(): Formation
    {
        $annee = AnneeJudiciaire::create(['libelle' => '2026-2027', 'debut' => '2026-10-12', 'fin' => '2027-10-11', 'courante' => true]);
        $section = Section::create(['code' => 'civile', 'libelle' => 'Section civile', 'ordre' => 1]);
        $chambre = Chambre::create(['annee_judiciaire_id' => $annee->id, 'section_id' => $section->id, 'libelle' => '2e chambre civile', 'ordre' => 1, 'actif' => true]);

        return Formation::create(['chambre_id' => $chambre->id, 'ordre' => 1, 'actif' => true]);
    }

    private function donnees(Formation $f, array $plus = []): array
    {
        return $plus + ['formation_id' => $f->id, 'type' => 'Audience publique', 'frequence' => 'hebdomadaire', 'jour' => 1, 'heure' => '09:00'];
    }

    public function test_ajout_modification_et_suppression(): void
    {
        $f = $this->formation();
        $salle = Salle::create(['nom' => 'Salle n°4', 'type' => 'audience', 'actif' => true]);
        $admin = User::factory()->create();

        $this->connecte($admin)->post(route('organisation.creneaux.store'), $this->donnees($f, ['salle_id' => $salle->id]))->assertSessionHasNoErrors();
        $c = Creneau::firstOrFail();
        $this->assertSame('Lundi à 09:00', $c->quand());
        $this->assertDatabaseHas('journal_audit', ['action' => 'creneau_cree']);

        $this->connecte($admin)->getJson(route('organisation.creneaux.get', $c->id))->assertJson(['jour' => 1, 'heure' => '09:00', 'salle_id' => $salle->id]);

        $this->connecte($admin)->post(route('organisation.creneaux.store'), $this->donnees($f, ['id_' => $c->id, 'jour' => 3, 'heure' => '10:30']))->assertSessionHasNoErrors();
        $this->assertSame('Mercredi à 10:30', $c->refresh()->quand());
        $this->assertDatabaseHas('journal_audit', ['action' => 'creneau_modifie']);

        $page = $this->connecte($admin)->get(route('organisation.affectations', $f->chambre_id))->assertOk();
        $page->assertSee('Mercredi à 10:30')->assertSee('onclick="add_creneau(', false);

        $this->connecte($admin)->deleteJson(route('organisation.creneaux.delete', $c->id))->assertJson(['status' => true]);
        $this->assertDatabaseCount('creneaux', 0);
    }

    public function test_validations(): void
    {
        $f = $this->formation();
        $admin = User::factory()->create();

        $this->connecte($admin)->post(route('organisation.creneaux.store'), $this->donnees($f, ['jour' => null]))->assertSessionHasErrors('jour');
        $this->connecte($admin)->post(route('organisation.creneaux.store'), $this->donnees($f, ['heure' => '25:99']))->assertSessionHasErrors('heure');
        $this->connecte($admin)->post(route('organisation.creneaux.store'), $this->donnees($f, ['debut' => '2026-12-01', 'fin' => '2026-11-01']))->assertSessionHasErrors('fin');
        $this->connecte($admin)->post(route('organisation.creneaux.store'), $this->donnees($f, ['frequence' => 'a_la_demande', 'jour' => null, 'heure' => null]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('creneaux', 1);
    }

    public function test_doublon_refuse_sauf_periodes_distinctes(): void
    {
        $f = $this->formation();
        $admin = User::factory()->create();

        $this->connecte($admin)->post(route('organisation.creneaux.store'), $this->donnees($f))->assertSessionHasNoErrors();
        $this->connecte($admin)->post(route('organisation.creneaux.store'), $this->donnees($f))->assertSessionHasErrors('type');

        // Changement d'horaire : l'ancien se termine, le même créneau reprend après sans chevauchement.
        $ancien = Creneau::firstOrFail();
        $ancien->update(['fin' => '2027-01-31']);
        $this->connecte($admin)->post(route('organisation.creneaux.store'), $this->donnees($f, ['debut' => '2027-02-01']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('creneaux', 2);
    }

    public function test_droits(): void
    {
        $f = $this->formation();
        $c = Creneau::create(['formation_id' => $f->id, 'type' => 'Audience publique', 'frequence' => 'hebdomadaire', 'jour' => 2, 'heure' => '09:00']);

        $lecteur = $this->avecDroits(['affectations.voir', 'creneaux.voir']);
        $page = $this->connecte($lecteur)->get(route('organisation.affectations', $f->chambre_id))->assertOk();
        $page->assertDontSee('onclick="add_creneau(', false)->assertDontSee('onclick="delete_creneau(', false);

        $this->connecte($lecteur)->post(route('organisation.creneaux.store'), $this->donnees($f))->assertForbidden();
        $this->connecte($lecteur)->deleteJson(route('organisation.creneaux.delete', $c->id))->assertForbidden();
        $this->connecte($this->avecDroits([]))->getJson(route('organisation.creneaux.get', $c->id))->assertForbidden();
    }
}
