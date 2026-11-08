<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reglages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActesImpressionTest extends TestCase
{
    use RefreshDatabase;

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    /** Tous les champs de la rubrique « Impression des actes », avec leurs valeurs actuelles, puis des modifications. */
    private function formulaire(array $changements = []): array
    {
        $champs = [];
        foreach (Reglages::catalogue()['actes']['champs'] as $cle => $def) {
            $champs[Reglages::nomChamp($cle)] = config($cle);
        }

        return array_merge($champs, $changements);
    }

    public function test_reglages_de_l_impression_des_actes(): void
    {
        $this->assertSame('Centaur', config('tpi.actes.police_corps'));
        $this->assertSame(16, config('tpi.actes.taille_corps'));
        $this->assertSame('Centaur', config('tpi.actes.police_timbre'));
        $this->assertSame(12, config('tpi.actes.taille_timbre'));
        $this->assertSame(45, config('tpi.actes.retrait_qualite_mm'));   // retrait relevé dans le modèle Word de la qualité
        $this->assertSame(57, config('tpi.actes.timbre_largeur_mm'));
        $this->assertSame(100, config('tpi.actes.interligne_qualite_pct'));
        $this->assertArrayHasKey('actes', Reglages::catalogue());

        $admin = User::factory()->create();
        $champs = $this->formulaire(['tpi__actes__police_corps' => 'Garamond', 'tpi__actes__taille_corps' => 14, 'tpi__actes__police_timbre' => 'Garamond', 'tpi__actes__taille_timbre' => 11, 'tpi__actes__retrait_qualite_mm' => 55]);
        $this->connecte($admin)->post('/parametres/reglages/actes', $champs)->assertSessionHasNoErrors()->assertRedirect(route('reglages.index', ['groupe' => 'actes']));
        app(Reglages::class)->appliquer();
        $this->assertSame('Garamond', config('tpi.actes.police_corps'));
        $this->assertSame(14, (int) config('tpi.actes.taille_corps'));
        $this->assertSame(55, (int) config('tpi.actes.retrait_qualite_mm'));
    }

    public function test_un_nom_de_police_ne_peut_pas_contenir_de_code(): void
    {
        $admin = User::factory()->create();
        $champs = $this->formulaire(['tpi__actes__police_corps' => 'a;}</style><script>']);

        $this->connecte($admin)->post('/parametres/reglages/actes', $champs)->assertSessionHasErrors();
        $this->assertSame('Centaur', config('tpi.actes.police_corps'));
    }

    public function test_les_tailles_sont_bornees(): void
    {
        $admin = User::factory()->create();
        $champs = $this->formulaire(['tpi__actes__taille_corps' => 200]);

        $this->connecte($admin)->post('/parametres/reglages/actes', $champs)->assertSessionHasErrors();
    }
}
