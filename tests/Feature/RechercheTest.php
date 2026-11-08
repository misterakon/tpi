<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\User;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Recherche plein texte : tous les mots, droits, périmètre, familles protégées, saisies hostiles. */
class RechercheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
    }

    private function utilisateur(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'P'.uniqid(), 'droits' => $droits])->id]);
    }

    private function connecte(User $u): static
    {
        return $this->actingAs($u)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function affaire(string $serie, string $objet, string $nom, string $chambre = '%chambre civile%'): Affaire
    {
        static $n = 0;
        $n++;
        $a = Affaire::create([
            'serie' => $serie, 'annee' => 2026, 'numero' => 6000 + $n, 'numero_complet' => $serie.'-'.(6000 + $n).'/26', 'numero_saisi' => true, 'date_rg' => '2026-02-01', 'objet' => $objet,
            'mode_saisine_id' => ModeSaisine::where('registre', $serie === 'RI' ? 'instruction' : 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', $serie)->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => Chambre::where('libelle', 'like', $chambre)->orderBy('id')->value('id'), 'statut' => 'enrolee',
        ]);
        Partie::create(['affaire_id' => $a->id, 'role' => $serie === 'RI' ? 'inculpe' : 'demandeur', 'nature' => 'homme', 'nom' => $nom, 'prenoms' => 'Awa', 'cle' => 'p'.$n]);

        return $a;
    }

    private function tout(): User
    {
        return $this->utilisateur(['affaires.voir', 'affaires.toutes_chambres']);
    }

    public function test_tous_les_mots_doivent_se_retrouver_dans_n_importe_quel_champ(): void
    {
        $this->affaire('RG', 'Paiement de loyers', 'KONE');
        $this->affaire('RG', 'Divorce pour faute', 'KONE');
        $this->affaire('RG', 'Paiement de loyers', 'DIALLO');
        $u = $this->tout();

        $this->connecte($u)->get(route('recherche', ['q' => 'KONE loyers']))->assertOk()->assertSeeText('Paiement de loyers')->assertDontSeeText('DIALLO')->assertDontSeeText('Divorce pour faute');   // les deux mots, l'un dans la partie, l'autre dans l'objet
        $this->connecte($u)->get(route('recherche', ['q' => 'kone']))->assertOk()->assertSeeText('2 résultat(s)');                                                                 // sans tenir compte de la casse
        $this->connecte($u)->get(route('recherche', ['q' => 'introuvablexyz']))->assertOk()->assertSee('Aucun résultat');
        $this->connecte($u)->get(route('recherche', ['q' => 'a']))->assertOk()->assertSee('au moins un mot');
        $this->connecte($u)->get(route('recherche'))->assertOk()->assertSee('Astuce');
        $this->assertDatabaseHas('journal_audit', ['action' => 'recherche']);
    }

    public function test_droits_perimetre_et_familles_protegees(): void
    {
        $this->affaire('RG', 'Loyers impayés', 'KONE');
        $this->affaire('RI', 'Meurtre', 'KONE', '%1er cabinet%');
        $this->connecte($this->utilisateur(['dossiers.voir']))->get(route('recherche', ['q' => 'KONE']))->assertOk()->assertSee('Aucun résultat');      // sans le droit « affaires », pas de rubrique Affaires
        $this->connecte($this->tout())->get(route('recherche', ['q' => 'KONE']))->assertOk()->assertSeeText('Loyers impayés')->assertDontSeeText('Meurtre');   // l'instruction est une famille protégée
        $avec = $this->utilisateur(['affaires.voir', 'affaires.toutes_chambres', 'affaires.famille.instruction']);
        $this->connecte($avec)->get(route('recherche', ['q' => 'KONE']))->assertOk()->assertSeeText('Meurtre');
        $limite = $this->utilisateur(['affaires.voir']);                                                                                              // aucune chambre
        $this->connecte($limite)->get(route('recherche', ['q' => 'KONE']))->assertOk()->assertSee('Aucun résultat');
    }

    public function test_la_recherche_demande_une_connexion(): void
    {
        $this->get(route('recherche', ['q' => 'KONE']))->assertRedirect();
    }

    public function test_saisies_hostiles(): void
    {
        $this->affaire('RG', 'Loyers 100% impayés', 'KONE');
        $this->affaire('RG', 'Loyers impayés', 'DIALLO');
        $u = $this->tout();
        // Le joker SQL « % » est traité comme un simple caractère.
        $this->connecte($u)->get(route('recherche', ['q' => '100%']))->assertOk()->assertSeeText('1 résultat(s)');
        $this->connecte($u)->get(route('recherche', ['q' => '%%']))->assertOk()->assertSee('Aucun résultat');
        $this->connecte($u)->get(route('recherche', ['q' => "' OR 1=1 --"]))->assertOk()->assertSee('Aucun résultat');
        // Du code dans la recherche n'est jamais exécuté à l'affichage.
        $html = $this->connecte($u)->get(route('recherche', ['q' => '"><script>alert(1)</script>']))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)', $html);
    }
}
