<?php

namespace Tests\Feature;

use App\Models\Profil;
use App\Models\User;
use App\Support\Accueil\TravailDuJour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** « Mon travail du jour » : chaque tâche dépend d'un droit, la page et le total de l'en-tête sont réservés aux connectés. */
class TravailDuJourTest extends TestCase
{
    use RefreshDatabase;

    private function avecDroits(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'P'.uniqid(), 'droits' => $droits])->id]);
    }

    private function connecte(User $u): static
    {
        Cache::flush();

        return $this->actingAs($u)->withSession(['derniere_activite' => now()->timestamp]);
    }

    public function test_la_page_et_le_total_demandent_une_connexion(): void
    {
        $this->get(route('travail.jour'))->assertRedirect();
        $this->getJson(route('travail.jour.compte'))->assertUnauthorized();
    }

    public function test_un_profil_sans_droit_n_a_aucune_tache_de_decision(): void
    {
        $u = $this->avecDroits(['affaires.voir']);
        $t = TravailDuJour::pour($u);
        $this->assertSame(0, $t['total']);
        $this->assertSame([], $t['a_jour']);   // ni « à transmettre » ni « à signer » : ces tâches n'existent pas pour lui
        $this->connecte($u)->get(route('travail.jour'))->assertOk()->assertSee('Rien ne vous attend');
    }

    public function test_une_tache_apparait_selon_le_droit_et_le_total_se_lit_en_json(): void
    {
        $juge = $this->avecDroits(['redaction.voir', 'redaction.signer', 'redaction.transmettre', 'redaction.valider']);
        $titres = collect(TravailDuJour::pour($juge)['a_jour']);
        foreach (['Décisions à valider', 'Décisions à signer', 'À transmettre au greffe'] as $t) {
            $this->assertTrue($titres->contains($t), $t);
        }
        $this->assertFalse($titres->contains('Requêtes à répartir'));
        $this->connecte($juge)->getJson(route('travail.jour.compte'))->assertOk()->assertJsonStructure(['n']);
        $this->connecte($juge)->get(route('travail.jour'))->assertOk()->assertSee('Travail du jour', false);
    }
}
