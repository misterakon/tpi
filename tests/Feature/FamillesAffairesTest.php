<?php

namespace Tests\Feature;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Chambre;
use App\Models\Profil;
use App\Models\User;
use App\Support\FamillesAffaires;
use App\Support\PerimetreChambres;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lot G : droits par type d'affaire. Les mineurs et l'instruction sont protégés : il faut un droit spécial, même avec accès à toutes les chambres. */
class FamillesAffairesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrdonnanceBingerville2026Seeder::class);
    }

    private function utilisateur(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits])->id]);
    }

    private function chambre(string $libelle): Chambre
    {
        return Chambre::where('libelle', 'like', '%'.$libelle.'%')->firstOrFail();
    }

    public function test_chaque_chambre_a_sa_famille(): void
    {
        $this->assertSame('civil', FamillesAffaires::deChambre($this->chambre('3e chambre civile')));
        $this->assertSame('civil', FamillesAffaires::deChambre($this->chambre('Section sociale')));
        $this->assertSame('penal', FamillesAffaires::deChambre($this->chambre('1re chambre correctionnelle')));
        $this->assertSame('penal', FamillesAffaires::deChambre($this->chambre('Tribunal criminel')->where('libelle', 'Tribunal criminel')->firstOrFail()));
        $this->assertSame('mineurs', FamillesAffaires::deChambre($this->chambre('Tribunal criminel pour mineurs')));
        $this->assertSame('mineurs', FamillesAffaires::deChambre($this->chambre('juge des enfants')));
        $this->assertSame('mineurs', FamillesAffaires::deChambre($this->chambre('4e chambre correctionnelle')), 'Le tribunal pour enfants siège dans la 4e chambre correctionnelle.');
        $this->assertSame('instruction', FamillesAffaires::deChambre($this->chambre('1er cabinet')));
    }

    public function test_la_presidente_sans_droit_protege_ne_voit_ni_les_mineurs_ni_l_instruction(): void
    {
        $presidente = $this->utilisateur(['affaires.toutes_chambres']);
        $chambres = PerimetreChambres::de($presidente);

        $this->assertIsArray($chambres, 'Ce n\'est plus « toutes les chambres ».');
        $this->assertContains($this->chambre('3e chambre civile')->id, $chambres);
        $this->assertContains($this->chambre('1re chambre correctionnelle')->id, $chambres);
        foreach (['juge des enfants', '4e chambre correctionnelle', 'Tribunal criminel pour mineurs', '1er cabinet', '2e cabinet'] as $protegee) {
            $this->assertNotContains($this->chambre($protegee)->id, $chambres, $protegee);
        }
        $this->assertFalse(PerimetreChambres::voit($presidente, $this->chambre('1er cabinet')->id));
    }

    public function test_avec_les_droits_protege_elle_voit_tout(): void
    {
        $this->assertNull(PerimetreChambres::de($this->utilisateur(['affaires.toutes_chambres', 'affaires.famille.mineurs', 'affaires.famille.instruction'])));

        $seulementInstruction = PerimetreChambres::de($this->utilisateur(['affaires.toutes_chambres', 'affaires.famille.instruction']));
        $this->assertContains($this->chambre('1er cabinet')->id, $seulementInstruction);
        $this->assertNotContains($this->chambre('juge des enfants')->id, $seulementInstruction);
    }

    public function test_l_administrateur_du_systeme_voit_tout(): void
    {
        $this->assertNull(PerimetreChambres::de(User::factory()->create()));   // profil Admin du système
    }

    public function test_un_greffier_ne_voit_sa_chambre_d_instruction_qu_avec_le_droit(): void
    {
        $cabinet = $this->chambre('1er cabinet');
        $civile = $this->chambre('3e chambre civile');

        $sans = $this->utilisateur(['affaires.voir']);
        $agent = Agent::create(['nom' => 'GREFFIER', 'prenoms' => 'Test', 'fonction' => 'greffier', 'actif' => true, 'user_id' => $sans->id]);
        Affectation::create(['formation_id' => $cabinet->formations()->value('id'), 'agent_id' => $agent->id, 'role' => 'greffier']);
        Affectation::create(['formation_id' => $civile->formations()->value('id'), 'agent_id' => $agent->id, 'role' => 'greffier']);
        $this->assertSame([$civile->id], PerimetreChambres::de($sans->refresh()), 'Affecté aux deux : il ne voit que le civil.');

        $sans->profil->update(['droits' => ['affaires.voir', 'affaires.famille.instruction']]);
        $this->assertEqualsCanonicalizing([$civile->id, $cabinet->id], PerimetreChambres::de($sans->refresh()));
    }

    public function test_la_migration_donne_les_droits_protege_aux_profils_qui_voient_deja_tout(): void
    {
        $presidente = Profil::create(['nom' => 'Présidente', 'droits' => ['affaires.toutes_chambres']]);
        $greffier = Profil::create(['nom' => 'Greffier', 'droits' => ['affaires.voir']]);
        (require database_path('migrations/2026_11_26_100000_droits_familles_protegees.php'))->up();

        $this->assertContains('affaires.famille.mineurs', $presidente->refresh()->droits);
        $this->assertContains('affaires.famille.instruction', $presidente->droits);
        $this->assertNotContains('affaires.famille.mineurs', $greffier->refresh()->droits, 'À donner expressément aux autres profils.');
    }

    public function test_les_droits_apparaissent_dans_le_catalogue(): void
    {
        $this->assertContains('affaires.famille.mineurs', \App\Support\Droits::tous());
        $this->assertContains('affaires.famille.instruction', \App\Support\Droits::tous());
    }
}
