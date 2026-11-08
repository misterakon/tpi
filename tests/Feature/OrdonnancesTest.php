<?php

namespace Tests\Feature;

use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\Ordonnance;
use App\Models\Profil;
use App\Models\Section;
use App\Models\User;
use App\Support\TexteRiche;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrdonnancesTest extends TestCase
{
    use RefreshDatabase;

    private function avecDroits(array $droits): User
    {
        $profil = Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits]);

        return User::factory()->create(['profil_id' => $profil->id]);
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function annee(): AnneeJudiciaire
    {
        return AnneeJudiciaire::create(['libelle' => '2026-2027', 'debut' => '2026-10-12', 'fin' => '2027-10-11', 'courante' => true]);
    }

    private function pdf(string $nom = 'ordonnance.pdf', string $contenu = '%PDF-1.4 un contenu'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nom, $contenu);
    }

    private function donnees(AnneeJudiciaire $annee, array $plus = []): array
    {
        return $plus + [
            'annee_judiciaire_id' => $annee->id, 'type' => 'annuelle', 'numero' => '600/2026', 'objet' => 'Répartition',
            'date_signature' => '2026-10-10', 'date_effet' => '2026-10-12', 'observations' => '<p>Texte <strong>fort</strong></p>',
            'fichier' => $this->pdf(),
        ];
    }

    public function test_archivage_lecture_telechargement_et_suppression(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $annee = $this->annee();

        $this->connecte($admin)->post(route('organisation.ordonnances.store'), $this->donnees($annee))->assertRedirect(route('organisation.ordonnances'));

        $o = Ordonnance::firstOrFail();
        $this->assertSame('600/2026', $o->numero);
        Storage::disk('local')->assertExists('ordonnances/'.$o->chemin);
        $this->assertStringEndsWith('.pdf', $o->chemin);
        $this->assertNotSame('ordonnance.pdf', $o->chemin);
        $this->assertDatabaseHas('journal_audit', ['action' => 'ordonnance_archivee']);

        $this->connecte($admin)->get(route('organisation.ordonnances'))->assertOk()->assertSee('Ordonnance n° 600/2026');
        $this->connecte($admin)->get(route('organisation.ordonnances.consulter', $o->id))->assertOk()->assertSee('<iframe', false);

        $lire = $this->connecte($admin)->get(route('organisation.ordonnances.lire', $o->id))->assertOk();
        $this->assertStringContainsString('application/pdf', $lire->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $lire->headers->get('Content-Disposition'));
        $this->assertSame('SAMEORIGIN', $lire->headers->get('X-Frame-Options'));

        $dl = $this->connecte($admin)->get(route('organisation.ordonnances.telecharger', $o->id))->assertOk();
        $this->assertStringContainsString('attachment', $dl->headers->get('Content-Disposition'));
        $this->assertDatabaseHas('journal_audit', ['action' => 'ordonnance_telechargee']);

        // Le reste de l'application garde l'interdiction d'être encadré.
        $this->connecte($admin)->get(route('organisation.ordonnances'))->assertHeader('X-Frame-Options', 'DENY');

        $this->connecte($admin)->deleteJson(route('organisation.ordonnances.delete', $o->id))->assertJson(['status' => true]);
        Storage::disk('local')->assertMissing('ordonnances/'.$o->chemin);
        $this->assertDatabaseCount('ordonnances', 0);
    }

    public function test_un_faux_pdf_ou_un_doublon_est_refuse(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $annee = $this->annee();

        $this->connecte($admin)->post(route('organisation.ordonnances.store'), $this->donnees($annee, ['fichier' => $this->pdf('faux.pdf', '<?php echo 1;')]))->assertSessionHasErrors('fichier');
        $this->connecte($admin)->post(route('organisation.ordonnances.store'), $this->donnees($annee, ['fichier' => UploadedFile::fake()->createWithContent('image.png', 'xx')]))->assertSessionHasErrors('fichier');
        $this->assertDatabaseCount('ordonnances', 0);

        $this->connecte($admin)->post(route('organisation.ordonnances.store'), $this->donnees($annee))->assertSessionHasNoErrors();
        $this->connecte($admin)->post(route('organisation.ordonnances.store'), $this->donnees($annee, ['numero' => '601/2026']))->assertSessionHasErrors('fichier');
        $this->assertDatabaseCount('ordonnances', 1);
    }

    public function test_les_informations_se_modifient_mais_pas_le_pdf(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $annee = $this->annee();
        $this->connecte($admin)->post(route('organisation.ordonnances.store'), $this->donnees($annee));
        $o = Ordonnance::firstOrFail();
        $chemin = $o->chemin;

        $this->connecte($admin)->post(route('organisation.ordonnances.store'), ['id_' => $o->id, 'annee_judiciaire_id' => $annee->id, 'type' => 'modificative', 'numero' => '650/2026', 'date_signature' => '2026-10-20', 'observations' => '<script>alert(1)</script><p>ok</p>'])->assertSessionHasNoErrors();
        $o->refresh();
        $this->assertSame('modificative', $o->type);
        $this->assertSame('650/2026', $o->numero);
        $this->assertSame($chemin, $o->chemin);
        $this->assertSame('<p>ok</p>', $o->observations);

        $this->connecte($admin)->post(route('organisation.ordonnances.store'), ['id_' => $o->id, 'annee_judiciaire_id' => $annee->id, 'type' => 'annuelle', 'numero' => '650/2026', 'date_signature' => '2026-10-20', 'fichier' => $this->pdf('autre.pdf', '%PDF-1.4 autre')])->assertSessionHasErrors('fichier');
    }

    public function test_droits_de_lecture_depot_et_suppression(): void
    {
        Storage::fake('local');
        $annee = $this->annee();
        $this->connecte(User::factory()->create())->post(route('organisation.ordonnances.store'), $this->donnees($annee));
        $o = Ordonnance::firstOrFail();

        $sans = $this->avecDroits([]);
        $this->connecte($sans)->get(route('organisation.ordonnances'))->assertForbidden();
        $this->connecte($sans)->get(route('organisation.ordonnances.lire', $o->id))->assertForbidden();
        $this->connecte($sans)->get(route('organisation.ordonnances.telecharger', $o->id))->assertForbidden();

        $lecteur = $this->avecDroits(['ordonnances.voir']);
        $this->connecte($lecteur)->get(route('organisation.ordonnances'))->assertOk()->assertDontSee('onclick="add_form()"', false)->assertDontSee('onclick="delete_ordonnance(', false);
        $this->connecte($lecteur)->get(route('organisation.ordonnances.lire', $o->id))->assertOk();
        $this->connecte($lecteur)->post(route('organisation.ordonnances.store'), $this->donnees($annee, ['numero' => '1/2027', 'fichier' => $this->pdf('b.pdf', '%PDF-1.4 b')]))->assertForbidden();
        $this->connecte($lecteur)->deleteJson(route('organisation.ordonnances.delete', $o->id))->assertForbidden();
    }

    public function test_l_editeur_est_present_et_les_textes_sont_nettoyes_dans_les_formulaires(): void
    {
        $admin = User::factory()->create();
        $annee = $this->annee();
        $section = Section::create(['code' => 'civile', 'libelle' => 'Civile', 'ordre' => 1]);

        $this->connecte($admin)->get(route('organisation.chambres'))->assertOk()->assertSee('data-riche', false)->assertSee('editeur-riche.js');

        $this->connecte($admin)->post(route('organisation.chambres.store'), [
            'annee_judiciaire_id' => $annee->id, 'section_id' => $section->id, 'libelle' => '1re chambre civile',
            'attributions' => '<p onclick="x()">Litiges <b>fonciers</b></p><img src=x onerror=alert(1)><script>alert(1)</script>', 'actif' => 1,
        ])->assertSessionHasNoErrors();

        $chambre = Chambre::firstOrFail();
        $this->assertSame('<p>Litiges <strong>fonciers</strong></p>', $chambre->attributions);
        $this->assertNull($chambre->remarque);
    }

    public function test_nettoyage_du_texte_riche(): void
    {
        $this->assertNull(TexteRiche::nettoyer('<p><br></p>'));
        $this->assertNull(TexteRiche::nettoyer('   '));
        $this->assertSame('Texte brut', TexteRiche::nettoyer('Texte brut'));
        $this->assertSame('<p>a <em>b</em></p>', TexteRiche::nettoyer('<p class="x" style="color:red">a <i>b</i></p><p><br></p>'));
        $this->assertSame('<ul><li>un</li><li>deux</li></ul>', TexteRiche::nettoyer('<ul><li>un</li><li>deux</li></ul>'));

        $lien = TexteRiche::nettoyer('<p><a href="javascript:alert(1)">piège</a> <a href="https://exemple.ci" onclick="x()">bon</a></p>');
        $this->assertStringNotContainsString('javascript', $lien);
        $this->assertStringNotContainsString('onclick', $lien);
        $this->assertStringContainsString('href="https://exemple.ci"', $lien);
        $this->assertStringContainsString('rel="noopener noreferrer"', $lien);

        $this->assertStringNotContainsString('<script', TexteRiche::nettoyer('<p>x</p><script>alert(1)</script><iframe src="//x"></iframe>'));
        $this->assertStringContainsString('sûr', TexteRiche::nettoyer('<p>sûr</p>'));

        // Affichage : ancien texte brut conservé avec ses retours à la ligne, échappé.
        $this->assertSame("ligne 1<br />\nligne &lt;2&gt;", TexteRiche::html("ligne 1\nligne <2>"));
        $this->assertSame('un deux', TexteRiche::texte('<p>un</p><p>deux</p>'));
    }
}
