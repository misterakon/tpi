<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\CodesSecours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Les comptes de test (@tpi.test) se connectent avec un code de secours, leur boîte e-mail étant fictive. */
class ComptesTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_commande_genere_des_codes_utilisables_pour_les_comptes_de_test_seulement(): void
    {
        $test = User::factory()->create(['email' => 'carine.boka@tpi.test', 'name' => 'Carine BOKA', 'password' => 'Tpi@2026']);
        $vrai = User::factory()->create(['email' => 'vrai@tribunal.ci']);
        $fichier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'codes_test_'.uniqid().'.txt';

        $this->artisan('tpi:codes-test', ['--fichier' => $fichier])->assertSuccessful();

        $contenu = (string) file_get_contents($fichier);
        @unlink($fichier);
        $this->assertStringContainsString('carine.boka@tpi.test', $contenu);
        $this->assertStringNotContainsString('vrai@tribunal.ci', $contenu);
        $this->assertSame(8, app(CodesSecours::class)->etat($test)['restants']);
        $this->assertSame(0, app(CodesSecours::class)->etat($vrai)['total']);

        preg_match('/\b(\d{8})\b/', $contenu, $m);
        $this->postJson('/login', ['email' => $test->email, 'password' => 'Tpi@2026'])->assertOk();
        $this->postJson(route('two-factor.secours'), ['code_secours' => $m[1]])->assertOk();
        $this->assertAuthenticatedAs($test);
    }

    public function test_sans_compte_de_test_la_commande_previent(): void
    {
        $this->artisan('tpi:codes-test')->assertFailed();
    }
}
