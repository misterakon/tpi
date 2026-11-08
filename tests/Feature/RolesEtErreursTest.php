<?php

namespace Tests\Feature;

use App\Models\Profil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RolesEtErreursTest extends TestCase
{
    use RefreshDatabase;

    private function avecProfil(string $nom, array $droits = []): User
    {
        $profil = Profil::create(['nom' => $nom, 'droits' => $droits]);

        return User::factory()->create(['profil_id' => $profil->id]);
    }

    public function test_has_role_et_has_any_role(): void
    {
        $greffier = $this->avecProfil('Greffier');

        $this->assertTrue($greffier->hasRole('greffier'));
        $this->assertTrue($greffier->hasAnyRole(['Président', 'Greffier']));
        $this->assertTrue($greffier->hasAnyRole('Président|Greffier'));
        $this->assertFalse($greffier->hasRole('Président'));
        $this->assertFalse($greffier->hasAllRoles(['Greffier', 'Président']));
    }

    public function test_les_accents_et_les_majuscules_sont_ignores(): void
    {
        $this->assertTrue($this->avecProfil('Président')->hasRole('President'));
        $this->assertTrue($this->avecProfil('President')->hasAnyRole('Greffier|Président'));
        $this->assertTrue($this->avecProfil("Juge  d'Instruction")->hasRole("juge d'instruction"));
        $this->assertFalse($this->avecProfil('Greffier')->hasRole('Greffière'));
    }

    public function test_un_compte_inactif_n_a_aucun_role(): void
    {
        $inactif = $this->avecProfil('Greffier');
        $inactif->forceFill(['actif' => false])->save();
        $this->assertFalse($inactif->fresh()->hasRole('Greffier'));
    }

    public function test_le_middleware_role_filtre_les_acces(): void
    {
        Route::middleware(['web', 'auth', 'role:Président|Greffier'])->get('/_test_role', fn () => 'ok');

        $this->actingAs($this->avecProfil('Greffier'))->get('/_test_role')->assertOk();
        $this->actingAs($this->avecProfil('Huissier'))->get('/_test_role')->assertForbidden();
    }

    public function test_les_directives_blade_role(): void
    {
        $this->actingAs($this->avecProfil('Greffier'));
        $html = Blade::render("@role('Greffier') A @endrole @role('Président') B @endrole @hasanyrole('Président|Greffier') C @endhasanyrole @admin D @endadmin");

        $this->assertStringContainsString('A', $html);
        $this->assertStringNotContainsString('B', $html);
        $this->assertStringContainsString('C', $html);
        $this->assertStringNotContainsString('D', $html);
    }

    public function test_les_pages_d_erreur_utilisent_la_vue_de_l_application(): void
    {
        $this->actingAs($this->avecProfil('Greffier'))->get('/page-qui-n-existe-pas')->assertNotFound()->assertSee('Page introuvable')->assertSee('TABLEAU DE BORD');
        $this->get('/parametres/reglages')->assertForbidden()->assertSee('Accès refusé');
    }

    public function test_la_note_technique_des_profils_est_reservee_aux_roles_admin_et_president(): void
    {
        $droits = ['profils.voir'];
        $this->actingAs($this->avecProfil('Greffier', $droits))->get('/utilisateurs/profils')->assertOk()->assertDontSee('Droits vérifiés côté serveur');
        $this->actingAs($this->avecProfil('Président', $droits))->get('/utilisateurs/profils')->assertOk()->assertSee('Droits vérifiés côté serveur');
    }
}
