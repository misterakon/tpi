<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Affectation;
use App\Models\Agent;
use App\Models\Chambre;
use App\Models\ConsultationPiece;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\PieceDossier;
use App\Models\Profil;
use App\Models\User;
use App\Support\PerimetreChambres;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Dossier numérique (pièces PDF et images d'une affaire) et périmètre de chambre. */
class DossierNumeriqueTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
    }

    private function connecte(User $user): static
    {
        return $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp]);
    }

    private function avecDroits(array $droits): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Profil '.uniqid(), 'droits' => $droits])->id]);
    }

    private function affaire(?Chambre $chambre = null): Affaire
    {
        static $n = 0;
        $affaire = Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 3000 + ++$n, 'numero_complet' => 'RG-'.(3000 + $n).'/26', 'date_rg' => '2026-10-20', 'objet' => 'Loyers impayés',
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => ($chambre ?? Chambre::firstOrFail())->id, 'statut' => 'enrolee',
        ]);
        $affaire->parties()->create(['role' => 'demandeur', 'nature' => 'homme', 'nom' => 'KOUASSI', 'prenoms' => 'Yao', 'cle' => 'kouassi yao']);

        return $affaire;
    }

    private function pdf(string $nom = 'assignation.pdf', string $plus = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nom, self::PDF.$plus);
    }

    private function ajouter(User $u, Affaire $a, array $fichiers, array $meta = [])
    {
        return $this->connecte($u)->post(route('affaires.dossier.ajouter', $a->id), ['fichiers' => $fichiers, 'meta' => $meta]);
    }

    public function test_ajout_de_pieces_pdf_et_image(): void
    {
        $admin = User::factory()->create();
        $a = $this->affaire();

        $this->ajouter($admin, $a, [$this->pdf('Assignation-KOUASSI.pdf'), UploadedFile::fake()->image('quittance.png', 200, 120)], [1 => ['type' => 'pieces_versees', 'titre' => 'Quittance de mars', 'date' => '2026-03-31']])
            ->assertSessionHasNoErrors()->assertRedirect(route('affaires.liste.fiche', $a->id).'#dossier');

        $pieces = $a->pieces()->get();
        $this->assertCount(2, $pieces);
        $this->assertSame('assignation', $pieces[0]->type);               // type deviné d'après le nom, modifiable ensuite
        $this->assertSame('Assignation-KOUASSI', $pieces[0]->titre);
        $this->assertSame('application/pdf', $pieces[0]->mime);
        $this->assertSame('pieces_versees', $pieces[1]->type);
        $this->assertSame('Quittance de mars', $pieces[1]->titre);
        $this->assertSame('2026-03-31', $pieces[1]->date_piece->toDateString());
        $this->assertSame('image/png', $pieces[1]->mime);

        // Le fichier est rangé sous un nom généré, jamais sous celui de l'utilisateur.
        foreach ($pieces as $p) {
            Storage::disk('local')->assertExists($p->chemin());
            $this->assertStringNotContainsString('quittance', $p->chemin);
            $this->assertMatchesRegularExpression('/^[a-z0-9]{26}\.(pdf|png)$/', $p->chemin);
            $this->assertSame(64, strlen($p->empreinte));
        }
        $this->assertDatabaseHas('journal_audit', ['action' => 'piece_ajoutee']);
    }

    public function test_les_faux_fichiers_sont_refuses_et_rien_n_est_enregistre(): void
    {
        $admin = User::factory()->create();
        $a = $this->affaire();

        // Un « PDF » qui n'en est pas un, une extension interdite, un script déguisé en image.
        $this->ajouter($admin, $a, [UploadedFile::fake()->createWithContent('faux.pdf', 'Ceci est du texte')])->assertSessionHasErrors('fichiers');
        $this->ajouter($admin, $a, [UploadedFile::fake()->createWithContent('programme.exe', 'MZ')])->assertSessionHasErrors();
        $this->ajouter($admin, $a, [UploadedFile::fake()->createWithContent('image.png', '<?php echo 1;')])->assertSessionHasErrors('fichiers');
        // Un fichier valide à côté d'un faux : rien n'est enregistré.
        $this->ajouter($admin, $a, [$this->pdf(), UploadedFile::fake()->createWithContent('faux.pdf', 'texte')])->assertSessionHasErrors('fichiers');
        $this->ajouter($admin, $a, [])->assertSessionHasErrors('fichiers');

        $this->assertSame(0, $a->pieces()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_une_piece_deja_presente_est_ignoree(): void
    {
        $admin = User::factory()->create();
        $a = $this->affaire();
        $this->ajouter($admin, $a, [$this->pdf('a.pdf')]);

        $r = $this->ajouter($admin, $a, [$this->pdf('copie.pdf'), $this->pdf('autre.pdf', "\n% autre contenu")]);
        $this->assertSame(2, $a->pieces()->count());   // la copie identique est ignorée, l'autre est ajoutée
        $this->assertStringContainsString('1 fichier déjà présent a été ignoré', session('success'));
    }

    public function test_ouverture_et_telechargement_sont_journalises(): void
    {
        $admin = User::factory()->create();
        $a = $this->affaire();
        $this->ajouter($admin, $a, [$this->pdf('jugement.pdf')]);
        $p = $a->pieces()->firstOrFail();

        $r = $this->connecte($admin)->get(route('affaires.dossier.fichier', $p->id))->assertOk();
        $this->assertStringContainsString('inline', $r->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $r->headers->get('X-Frame-Options'));   // la visionneuse affiche le PDF dans un cadre de la même application
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertSame(1, ConsultationPiece::where('piece_id', $p->id)->where('action', 'vue')->count());

        $this->connecte($admin)->get(route('affaires.dossier.fichier', $p->id))->assertOk();   // une minute : une seule ligne
        $this->assertSame(1, ConsultationPiece::where('action', 'vue')->count());

        $r = $this->connecte($admin)->get(route('affaires.dossier.telecharger', $p->id))->assertOk();
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        $this->assertSame(1, ConsultationPiece::where('piece_id', $p->id)->where('action', 'telechargee')->count());

        $liste = $this->connecte($admin)->getJson(route('affaires.dossier.consultations', $p->id))->assertOk()->json('consultations');
        $this->assertCount(2, $liste);
    }

    public function test_droits_du_dossier(): void
    {
        $admin = User::factory()->create();
        $a = $this->affaire();
        $this->ajouter($admin, $a, [$this->pdf()]);
        $p = $a->pieces()->firstOrFail();
        $tout = 'affaires.toutes_chambres';

        // Sans le droit de voir : rien, ni l'onglet ni le fichier.
        $aucun = $this->avecDroits(['affaires.voir', $tout]);
        $this->connecte($aucun)->get(route('affaires.dossier.fichier', $p->id))->assertForbidden();
        $this->connecte($aucun)->get(route('affaires.liste.fiche', $a->id))->assertOk()->assertDontSee('data-onglet-contenu="dossier"', false);
        $this->connecte($aucun)->get(route('affaires.dossiers'))->assertForbidden();

        // Voir sans télécharger, sans ajouter, sans modifier, sans retirer.
        $lecteur = $this->avecDroits(['affaires.voir', 'dossiers.voir', $tout]);
        $this->connecte($lecteur)->get(route('affaires.dossier.fichier', $p->id))->assertOk();
        $this->connecte($lecteur)->get(route('affaires.dossier.telecharger', $p->id))->assertForbidden();
        $this->ajouter($lecteur, $a, [$this->pdf('x.pdf', "\n% x")])->assertForbidden();
        $this->connecte($lecteur)->post(route('affaires.dossier.modifier', $p->id), ['type' => 'autre', 'titre' => 'X'])->assertForbidden();
        $this->connecte($lecteur)->post(route('affaires.dossier.retirer', $p->id), ['motif' => 'Essai non autorisé'])->assertForbidden();
        $this->connecte($lecteur)->post(route('affaires.dossier.localisation', $a->id), ['localisation' => 'Armoire'])->assertForbidden();
        $this->connecte($lecteur)->getJson(route('affaires.dossier.consultations', $p->id))->assertForbidden();
        $page = $this->connecte($lecteur)->get(route('affaires.liste.fiche', $a->id))->assertOk();
        $page->assertSee('data-onglet-contenu="dossier"', false)->assertDontSee('onclick="dn_ouvrir_ajout()"', false)->assertDontSee('id="dn-telecharger"', false);

        $this->connecte($this->avecDroits(['dossiers.voir', 'dossiers.telecharger', $tout]))->get(route('affaires.dossier.telecharger', $p->id))->assertOk();
        // Une affaire annulée ne reçoit plus de pièces.
        $a->forceFill(['statut' => 'annulee'])->save();
        $this->ajouter($admin, $a, [$this->pdf('y.pdf', "\n% y")])->assertForbidden();
    }

    public function test_modifier_retirer_et_localiser(): void
    {
        $admin = User::factory()->create();
        $a = $this->affaire();
        $this->ajouter($admin, $a, [$this->pdf('piece.pdf')]);
        $p = $a->pieces()->firstOrFail();

        $this->connecte($admin)->post(route('affaires.dossier.modifier', $p->id), ['type' => 'conclusions', 'titre' => 'Conclusions en défense', 'date_piece' => '2026-10-02'])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(['conclusions', 'Conclusions en défense', '2026-10-02'], [$p->type, $p->titre, $p->date_piece->toDateString()]);
        $this->connecte($admin)->post(route('affaires.dossier.modifier', $p->id), ['type' => 'inconnu', 'titre' => 'X'])->assertSessionHasErrors('type');
        $this->connecte($admin)->post(route('affaires.dossier.modifier', $p->id), ['type' => 'autre', 'titre' => ''])->assertSessionHasErrors('titre');

        $this->connecte($admin)->post(route('affaires.dossier.localisation', $a->id), ['localisation' => 'Greffe, armoire B, rayon 3'])->assertSessionHasNoErrors();
        $this->assertSame('Greffe, armoire B, rayon 3', $a->refresh()->localisation_dossier);
        $this->connecte($admin)->get(route('affaires.liste.fiche', $a->id))->assertSee('Greffe, armoire B, rayon 3');

        // Retrait : le motif est obligatoire ; la pièce disparaît du dossier mais le fichier est conservé.
        $this->connecte($admin)->post(route('affaires.dossier.retirer', $p->id), ['motif' => ''])->assertSessionHasErrors('motif');
        $this->connecte($admin)->post(route('affaires.dossier.retirer', $p->id), ['motif' => 'Déposée par erreur'])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertNotNull($p->retiree_le);
        $this->assertSame('Déposée par erreur', $p->motif_retrait);
        Storage::disk('local')->assertExists($p->chemin());
        $this->assertSame(0, $a->pieces()->count());
        $this->connecte($admin)->get(route('affaires.dossier.fichier', $p->id))->assertNotFound();
        $this->connecte($admin)->post(route('affaires.dossier.modifier', $p->id), ['type' => 'autre', 'titre' => 'X'])->assertNotFound();
        foreach (['piece_modifiee', 'piece_retiree', 'dossier_localise'] as $action) {
            $this->assertDatabaseHas('journal_audit', ['action' => $action]);
        }
    }

    public function test_la_fiche_et_la_page_du_menu(): void
    {
        $admin = User::factory()->create();
        $a = $this->affaire();
        $vide = $this->affaire();
        $this->ajouter($admin, $a, [$this->pdf('Assignation.pdf'), UploadedFile::fake()->image('photo.png')]);

        $page = $this->connecte($admin)->get(route('affaires.liste.fiche', $a->id))->assertOk();
        $page->assertSee('Dossier numérique')->assertSee('Sommaire du dossier')->assertSee('Glissez des fichiers ici')->assertSee('Assignation')->assertSee('Emplacement non renseigné');
        $this->assertStringNotContainsString($a->pieces()->first()->chemin, $page->getContent());   // le chemin du fichier ne sort jamais dans la page

        $this->connecte($admin)->get(route('affaires.dossiers'))->assertOk();
        $menu = $this->lignesDossiers($admin);
        $this->assertStringContainsString($a->numeroAffichage(), $menu);
        $this->assertStringContainsString($vide->numeroAffichage(), $menu);
        $this->assertStringContainsString('title="Ouvrir le dossier numérique"', implode(' ', array_map(fn ($l) => implode(' ', $l), $this->connecte($admin)->getJson(route('affaires.liste.donnees', ['length' => 100]))->json('data'))));
    }

    public function test_le_perimetre_de_chambre(): void
    {
        $chambreA = Chambre::where('libelle', '2e chambre civile')->firstOrFail();
        $chambreB = Chambre::where('libelle', '3e chambre civile')->firstOrFail();
        $a = $this->affaire($chambreA);
        $b = $this->affaire($chambreB);
        $admin = User::factory()->create();
        $this->ajouter($admin, $b, [$this->pdf()]);
        $pieceB = $b->pieces()->firstOrFail();

        // Un greffier affecté à la 2e chambre civile (par l'ordonnance) ne voit que les affaires de sa chambre.
        $greffier = $this->avecDroits(['affaires.voir', 'dossiers.voir', 'dossiers.creer']);
        $agent = Agent::create(['nom' => 'GREFFIER', 'prenoms' => 'Test', 'fonction' => 'greffier', 'actif' => true, 'user_id' => $greffier->id]);
        Affectation::create(['formation_id' => $chambreA->formations()->value('id'), 'agent_id' => $agent->id, 'role' => 'greffier']);

        $this->assertSame([$chambreA->id], PerimetreChambres::de($greffier->fresh()));
        $this->connecte($greffier)->get(route('affaires.liste.fiche', $a->id))->assertOk();
        $this->connecte($greffier)->get(route('affaires.liste.fiche', $b->id))->assertNotFound();
        $this->connecte($greffier)->get(route('affaires.liste'))->assertOk();
        $liste = strip_tags(implode(' ', array_map(fn ($l) => implode(' ', $l), $this->connecte($greffier)->getJson(route('affaires.liste.donnees', ['length' => 100]))->json('data'))));
        $this->assertStringContainsString($a->numeroAffichage(), $liste);
        $this->assertStringNotContainsString($b->numeroAffichage(), $liste);
        $this->connecte($greffier)->get(route('affaires.dossier.fichier', $pieceB->id))->assertNotFound();
        $this->ajouter($greffier, $b, [$this->pdf('z.pdf', "\n% z")])->assertNotFound();
        $this->connecte($greffier)->get(route('affaires.dossiers'))->assertOk();
        $this->assertStringNotContainsString($b->numeroAffichage(), $this->lignesDossiers($greffier));
        $this->assertStringContainsString($a->numeroAffichage(), $this->lignesDossiers($greffier));

        // Sans agent relié au compte : aucune affaire. Avec le droit « toutes les chambres » : toutes.
        $sansAgent = $this->avecDroits(['affaires.voir']);
        $this->assertSame([], PerimetreChambres::de($sansAgent));
        $this->connecte($sansAgent)->get(route('affaires.liste.fiche', $a->id))->assertNotFound();
        $presidente = $this->avecDroits(['affaires.voir', 'dossiers.voir', 'affaires.toutes_chambres', 'affaires.famille.mineurs', 'affaires.famille.instruction']);   // voit tout : toutes les chambres ET les familles protégées
        $this->assertNull(PerimetreChambres::de($presidente));
        $this->connecte($presidente)->get(route('affaires.liste.fiche', $b->id))->assertOk();
        $this->connecte($presidente)->get(route('affaires.dossier.fichier', $pieceB->id))->assertOk();
    }

    public function test_taille_maximale(): void
    {
        $ko = PieceDossier::tailleMaxKo();
        $this->assertGreaterThanOrEqual(1, $ko);
        $this->assertLessThanOrEqual(20 * 1024, $ko);   // le réglage par défaut est de 20 Mo, le serveur peut en accepter moins
        $this->assertSame('2,4 Mo', PieceDossier::tailleLisible(2516582));
        $this->assertSame('310 Ko', PieceDossier::tailleLisible(317440));
    }

    /** Le texte des lignes renvoyées par la liste paginée du dossier numérique (une page de 100 au plus). */
    private function lignesDossiers(User $u): string
    {
        $json = $this->connecte($u)->getJson(route('affaires.dossiers.donnees', ['length' => 100]))->assertOk()->json();

        return strip_tags(implode(' ', array_map(fn ($l) => implode(' ', $l), $json['data'])));
    }
}
