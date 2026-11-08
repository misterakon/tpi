<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\User;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cas pratique de chaque registre : civil (RG), pénal (RP), instruction (RI adultes, RE enfants). */
class AffairesRegistresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tpi.enrolement.numero' => array_fill_keys(['rg', 'rp', 'ri', 're'], 'manuel')]);
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function poster(User $u, array $donnees)
    {
        return $this->actingAs($u)->withSession(['derniere_activite' => now()->timestamp])->post(route('affaires.enrolement.store'), $donnees);
    }

    private function base(string $registre, string $modeRegistre, array $naturesRegistres): array
    {
        return [
            'registre' => $registre, 'numero_rg' => '1334/26', 'date_rg' => '2026-10-20',
            'mode_saisine_id' => ModeSaisine::where('registre', $modeRegistre)->firstOrFail()->id,
            'nature_affaire_id' => NatureAffaire::whereIn('registre', $naturesRegistres)->firstOrFail()->id,
            'matiere_id' => Matiere::firstOrFail()->id, 'chambre_id' => Chambre::firstOrFail()->id,
            'objet' => 'Destruction volontaire de biens', 'articles' => 'Art. 485 du CP',
        ];
    }

    private function penal(array $modifs = []): array
    {
        return array_replace_recursive($this->base('penal', 'penal', ['RP']) + ['parties' => [
            0 => ['role' => 'prevenu', 'nature' => 'homme', 'nom' => 'JHON', 'prenoms' => 'Wedha Depadho', 'naissance_annee' => '2003', 'nationalite' => 'béninoise', 'profession' => 'vigile', 'mandat_depot_date' => '2026-09-23'],
            1 => ['role' => 'prevenu', 'nature' => 'homme', 'nom' => 'KOUADIO', 'prenoms' => 'Kouakou'],
            2 => ['role' => 'victime', 'nature' => 'morale', 'denomination' => 'Société BTP SARL'],
        ]], $modifs);
    }

    private function instruction(array $modifs = []): array
    {
        return array_replace_recursive($this->base('instruction', 'instruction', ['RI']) + ['parties' => [
            0 => ['role' => 'inculpe', 'nature' => 'homme', 'nom' => 'TRAORE', 'prenoms' => 'Moussa', 'mandat_depot_date' => '2026-10-01'],
        ]], $modifs);
    }

    public function test_cas_pratique_penal(): void
    {
        $admin = $this->admin();
        $this->poster($admin, $this->penal(['date_premiere_audience' => '2026-10-27']))->assertSessionHasNoErrors()->assertRedirect();
        $a = Affaire::with('parties')->firstOrFail();

        $this->assertSame('RP', $a->serie);
        $this->assertSame('RP-1334/26', $a->numero_complet);   // le format du cahier des charges reste en base
        $this->assertSame('RP 1334/26', $a->numeroAffichage()); // les autres séries gardent leur sigle à l'écran
        $this->assertSame('Art. 485 du CP', $a->articles);
        $this->assertSame('JHON Wedha Depadho, KOUADIO Kouakou', $a->intitule());   // les personnes poursuivies
        $this->assertSame(2, $a->parties->where('role', 'prevenu')->count());
        $this->assertSame(1, $a->parties->where('role', 'victime')->count());

        $jhon = $a->parties->firstWhere('nom', 'JHON');
        $this->assertTrue($jhon->estDetenu());
        $this->assertSame((int) date('Y') - 2003, $jhon->age());
        $this->assertSame((int) date('Y') - 2003 .' ans, béninoise, vigile', $jhon->identite());
        $this->assertFalse($a->parties->firstWhere('nom', 'KOUADIO')->estDetenu());

        $page = $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->get(route('affaires.liste.fiche', $a->id))->assertOk();
        $page->assertSee('Prévenus')->assertSee('Victimes et parties civiles')->assertSee('Articles visés')->assertSee('Détenu : mandat de dépôt du 23/09/2026')->assertSee('Nature de la prévention');
        $page->assertDontSee('Rédiger une décision');   // pas encore de modèle de décision pénale
    }

    public function test_cas_pratique_instruction_adultes_et_enfants(): void
    {
        $admin = $this->admin();
        $adultes = NatureAffaire::where('registre', 'RI')->firstOrFail();
        $enfants = NatureAffaire::where('registre', 'RE')->firstOrFail();

        $this->poster($admin, $this->instruction(['numero_rg' => '45/26', 'nature_affaire_id' => $adultes->id]))->assertSessionHasNoErrors();
        $this->poster($admin, $this->instruction(['numero_rg' => '45/26', 'nature_affaire_id' => $enfants->id, 'parties' => [0 => ['nom' => 'MINEUR']]]))->assertSessionHasNoErrors();

        // La série suit la nature : le même numéro 45/26 existe dans RI et dans RE sans être un doublon.
        $this->assertSame(['RE', 'RI'], Affaire::orderBy('serie')->pluck('serie')->all());
        $this->assertSame('RI 45/26', Affaire::where('serie', 'RI')->firstOrFail()->numeroAffichage());
        $this->assertSame('RE 45/26', Affaire::where('serie', 'RE')->firstOrFail()->numeroAffichage());

        // Un mode de saisine du pénal, ou une nature du civil, n'est pas admis à l'instruction.
        $this->poster($admin, $this->instruction(['numero_rg' => '46/26', 'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->firstOrFail()->id]))->assertSessionHasErrors('mode_saisine_id');
        $this->poster($admin, $this->instruction(['numero_rg' => '46/26', 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->firstOrFail()->id]))->assertSessionHasErrors('nature_affaire_id');
        $this->assertSame(2, Affaire::count());
    }

    public function test_series_et_numeros_independants(): void
    {
        $admin = $this->admin();
        // Le même numéro dans deux séries (RG civil et RP pénal) n'est pas un doublon.
        $this->poster($admin, array_replace($this->base('civil', 'civil', ['RG']), ['numero_rg' => '1334/26', 'parties' => [0 => ['role' => 'demandeur', 'nature' => 'homme', 'nom' => 'CIVIL']]]))->assertSessionHasNoErrors();
        $this->poster($admin, $this->penal())->assertSessionHasNoErrors();
        $this->assertSame(['RG', 'RP'], Affaire::orderBy('serie')->pluck('serie')->all());

        // Doublon dans la même série : bloqué, sauf jonction.
        $this->poster($admin, $this->penal(['parties' => [0 => ['nom' => 'AUTRE']]]))->assertSessionHasErrors('numero_rg');
        $this->poster($admin, $this->penal(['jonction' => 1, 'parties' => [0 => ['nom' => 'JOINT']]]))->assertSessionHasNoErrors();
        $this->assertSame(3, Affaire::count());
        $jointe = Affaire::orderByDesc('id')->first();
        $this->assertSame(Affaire::where('serie', 'RP')->orderBy('id')->value('id'), $jointe->jointe_a_id);

        // Une jonction ne se fait pas entre registres.
        $civil = Affaire::where('serie', 'RG')->firstOrFail();
        $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->post(route('affaires.liste.joindre', $civil->id), ['affaire_id' => Affaire::where('serie', 'RP')->orderBy('id')->value('id')])->assertSessionHasErrors('affaire_id');

        // Le contrôle du numéro pendant la saisie tient compte de la série.
        $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->postJson(route('affaires.enrolement.numero'), ['numero_rg' => '1334/26', 'serie' => 'RI'])->assertJson(['existe' => false]);
        $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->postJson(route('affaires.enrolement.numero'), ['numero_rg' => '1334/26', 'serie' => 'RP'])->assertJson(['existe' => true]);
    }

    public function test_numeros_automatiques_par_serie(): void
    {
        config(['tpi.enrolement.numero' => array_fill_keys(['rg', 'rp', 'ri', 're'], 'automatique')]);
        $admin = $this->admin();
        $base = fn (array $f) => array_diff_key($f, ['numero_rg' => 1, 'date_rg' => 1]);

        $this->poster($admin, $base($this->penal()))->assertSessionHasNoErrors();
        $this->poster($admin, $base($this->penal(['parties' => [0 => ['nom' => 'SECOND']]])))->assertSessionHasNoErrors();
        $this->poster($admin, $base($this->instruction()))->assertSessionHasNoErrors();

        // Chaque série a son compteur : RP-0001, RP-0002, puis RI-0001.
        $an = sprintf('%02d', now()->year % 100);
        $this->assertSame(["RP-0001/$an", "RP-0002/$an", "RI-0001/$an"], Affaire::orderBy('id')->pluck('numero_complet')->all());
        $this->assertSame('RP 1/'.$an, Affaire::orderBy('id')->first()->numeroAffichage());
    }

    public function test_controles_du_formulaire_penal(): void
    {
        $admin = $this->admin();

        // Au moins un prévenu ; un rôle ou une nature de partie du civil n'est pas admis.
        $this->poster($admin, $this->penal(['parties' => [0 => ['role' => 'victime', 'denomination' => 'V'], 1 => ['role' => 'victime', 'nom' => 'X']]]))->assertSessionHasErrors('parties');
        $this->poster($admin, $this->penal(['parties' => [0 => ['role' => 'demandeur']]]))->assertSessionHasErrors('parties.0.role');
        $this->poster($admin, $this->penal(['parties' => [0 => ['nature' => 'couple']]]))->assertSessionHasErrors('parties.0.nature');
        // Un nom manquant, un mandat de dépôt dans le futur, une nature de la prévention manquante.
        $this->poster($admin, $this->penal(['parties' => [0 => ['nom' => '']]]))->assertSessionHasErrors('parties.0.nom');
        $this->poster($admin, $this->penal(['parties' => [0 => ['mandat_depot_date' => '2999-01-01']]]))->assertSessionHasErrors('parties.0.mandat_depot_date');
        $this->poster($admin, $this->penal(['objet' => '']))->assertSessionHasErrors('objet');
        // Le mode de saisine du civil n'est pas celui du pénal.
        $this->poster($admin, $this->penal(['mode_saisine_id' => ModeSaisine::where('registre', 'civil')->firstOrFail()->id]))->assertSessionHasErrors('mode_saisine_id');
        // La date de première audience n'est pas contrôlée au pénal (le greffe gère son calendrier).
        $this->poster($admin, $this->penal(['date_premiere_audience' => '2020-01-01']))->assertSessionHasNoErrors();
        $this->assertSame(1, Affaire::count());
    }

    public function test_liste_filtres_et_correction_au_penal(): void
    {
        $admin = $this->admin();
        $this->poster($admin, $this->penal());
        $a = Affaire::firstOrFail();

        $liste = $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->get(route('affaires.liste'))->assertOk();
        $this->assertStringContainsString('RP 1334/26', json_encode($this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->getJson(route('affaires.liste.donnees', ['length' => 100]))->assertOk()->json('data'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $liste->assertSee('Pénal (RP)')->assertSee('Instruction (RI)')->assertSee('Affaire pénale')->assertSee('Série RP : prévenus')->assertSee('modal-saisie-penal', false)->assertSee('modal-saisie-instruction', false);

        // Correction : l'objet, les articles et les parties changent, le numéro non.
        $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->get(route('affaires.liste.fiche', $a->id))->assertOk()->assertSee('Correction : RP 1334/26')->assertSee('value="JHON"', false)->assertSee('Articles visés');
        $nouvelles = $this->penal(['objet' => 'Vol aggravé', 'articles' => 'Arts 457 et 458', 'parties' => [0 => ['nom' => 'JHON-CORRIGE']]]);
        unset($nouvelles['chambre_id']);
        $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->post(route('affaires.liste.modifier.enregistrer', $a->id), $nouvelles)->assertSessionHasNoErrors();

        $a->refresh()->load('parties');
        $this->assertSame('Vol aggravé', $a->objet);
        $this->assertSame('Arts 457 et 458', $a->articles);
        $this->assertSame('RP-1334/26', $a->numero_complet);
        $this->assertSame('JHON-CORRIGE', $a->parties->where('role', 'prevenu')->first()->nom);
        $this->assertTrue($a->parties->where('role', 'prevenu')->first()->estDetenu());   // le mandat de dépôt est conservé
    }

    public function test_les_valeurs_d_un_formulaire_ne_passent_pas_dans_un_autre(): void
    {
        $admin = $this->admin();
        // Une saisie pénale refusée (objet manquant) : les valeurs et l'erreur reviennent dans la page, pour le seul formulaire pénal.
        $saisie = $this->penal(['objet' => '', 'numero_rg' => '999/26']);

        $html = $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->from(route('affaires.liste'))->followingRedirects()->post(route('affaires.enrolement.store'), $saisie)->getContent();

        $this->assertSame(1, preg_match('/id="p_numero"[^>]*value="999\/26"/', $html));          // dans le formulaire pénal
        $this->assertSame(0, preg_match('/id="f_numero_rg"[^>]*value="999\/26"/', $html));       // pas dans le civil
        $this->assertSame(0, preg_match('/id="i_numero"[^>]*value="999\/26"/', $html));          // ni dans l'instruction
        // Chaque fenêtre est un morceau de la page : l'erreur n'est que dans la fenêtre pénale.
        $civil = substr($html, strpos($html, 'id="modal-saisie"'), strpos($html, 'id="modal-saisie-penal"') - strpos($html, 'id="modal-saisie"'));
        $penal = substr($html, strpos($html, 'id="modal-saisie-penal"'), strpos($html, 'id="modal-saisie-instruction"') - strpos($html, 'id="modal-saisie-penal"'));
        $instruction = substr($html, strpos($html, 'id="modal-saisie-instruction"'), 60000);
        $this->assertStringContainsString('La nature de la prévention est obligatoire.', $penal);
        $this->assertStringNotContainsString('La nature de la prévention est obligatoire.', $civil);
        $this->assertStringNotContainsString('La nature de la prévention est obligatoire.', $instruction);
        // Un appel dans le choix du registre, plus un appel d'ouverture automatique pour la seule fenêtre pénale.
        $this->assertSame(1, substr_count($html, 'ouvrir_saisie_penal(true);'));          // réouverture après erreur, sans remise à zéro
        $this->assertSame(0, substr_count($html, 'ouvrir_saisie_instruction(true);'));
        $this->assertStringContainsString('JHON', $html);
        $this->assertStringNotContainsString('JHON', $civil);   // les prévenus ressaisis ne passent pas dans le formulaire civil
    }

    public function test_le_mode_du_numero_se_regle_serie_par_serie(): void
    {
        // Le civil est saisi à la main (greffe), le pénal est généré par l'application.
        config(['tpi.enrolement.numero' => ['rg' => 'manuel', 'rp' => 'automatique', 'ri' => 'manuel', 're' => 'automatique']]);
        $admin = $this->admin();
        $an = sprintf('%02d', now()->year % 100);

        $this->poster($admin, array_replace($this->base('civil', 'civil', ['RG']), ['numero_rg' => '1391/25', 'parties' => [0 => ['role' => 'demandeur', 'nature' => 'homme', 'nom' => 'CIVIL']]]))->assertSessionHasNoErrors();
        $this->poster($admin, array_diff_key($this->penal(), ['numero_rg' => 1, 'date_rg' => 1]))->assertSessionHasNoErrors();

        $this->assertSame(['RG-1391/25', "RP-0001/$an"], Affaire::orderBy('id')->pluck('numero_complet')->all());

        // Civil : le numéro est obligatoire ; pénal : un numéro tapé est ignoré et remplacé par le numéro généré.
        $this->poster($admin, array_diff_key($this->base('civil', 'civil', ['RG']), ['numero_rg' => 1]) + ['parties' => [0 => ['role' => 'demandeur', 'nature' => 'homme', 'nom' => 'X']]])->assertSessionHasErrors('numero_rg');
        $this->poster($admin, $this->penal(['numero_rg' => '777/26', 'parties' => [0 => ['nom' => 'AUTRE']]]))->assertSessionHasNoErrors();
        $this->assertSame("RP-0002/$an", Affaire::orderByDesc('id')->value('numero_complet'));

        // À l'instruction, le mode suit la série de la nature : RI saisi à la main, RE généré.
        $enfants = NatureAffaire::where('registre', 'RE')->firstOrFail();
        $this->poster($admin, $this->instruction(['numero_rg' => '88/26', 'nature_affaire_id' => $enfants->id]))->assertSessionHasNoErrors();
        $this->assertSame("RE-0001/$an", Affaire::orderByDesc('id')->value('numero_complet'));
        $this->poster($admin, array_diff_key($this->instruction(), ['numero_rg' => 1]))->assertSessionHasErrors('numero_rg');   // RI : numéro obligatoire

        // Les formulaires suivent leur mode : le champ « Numéro » n'existe que pour les séries saisies à la main.
        $page = $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->get(route('affaires.liste'))->getContent();
        $this->assertStringContainsString('id="f_numero_rg"', $page);
        $this->assertStringNotContainsString('id="p_numero"', $page);
        $this->assertStringContainsString('id="i_numero"', $page);
    }

    public function test_les_reglages_proposent_un_mode_par_serie(): void
    {
        $champs = \App\Services\Reglages::catalogue()['affaires']['champs'];
        $this->assertSame(['tpi.enrolement.numero.rg', 'tpi.enrolement.numero.rp', 'tpi.enrolement.numero.ri', 'tpi.enrolement.numero.re', 'tpi.penal.detention_jours', 'tpi.penal.alerte_jours', 'tpi.requetes.delai_repartition_jours', 'tpi.reprise.taille_max_mo', 'tpi.dossier.taille_max_mo'], array_keys($champs));
        $this->assertSame(['manuel', 'automatique'], array_keys($champs['tpi.enrolement.numero.rp']['options']));
        $this->assertSame('manuel', config('tpi.enrolement.numero.rp'));   // par défaut : Bingerville, saisi à la main
    }

    public function test_les_affaires_d_essai(): void
    {
        $this->artisan('affaires:demo')->assertSuccessful();
        $this->assertGreaterThanOrEqual(9, $total = Affaire::where('ancien_numero', 'DEMO')->count());   // 9 de base, plus les scénarios de rôles quand l'ordonnance est chargée
        $this->artisan('affaires:demo')->assertSuccessful();               // une seule fois
        $this->assertSame($total, Affaire::count());

        // Toutes les pages (liste et fiches) s'affichent avec ces affaires.
        $admin = $this->admin();
        $liste = $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->get(route('affaires.liste'))->assertOk();
        $lignes = json_encode($this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->getJson(route('affaires.liste.donnees', ['length' => 100]))->assertOk()->json('data'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        foreach (['RP 1334/26', 'RI 45/26', 'RE 45/26', '1391/'.sprintf('%02d', now()->year % 100), 'Société BATIMAT SARL'] as $attendu) {
            $this->assertStringContainsString($attendu, $lignes);
        }
        unset($liste);
        foreach (Affaire::pluck('id') as $id) {
            $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->get(route('affaires.liste.fiche', $id))->assertOk();
        }
        $this->assertSame(1, Affaire::whereNotNull('jointe_a_id')->count());
        $this->assertSame('MP', Partie::where('nature', 'mp')->firstOrFail()->libelle());
        $this->assertStringContainsString('et 12 autres', Partie::where('autres', 12)->firstOrFail()->libelle());

        // Retrait : seules les affaires d'essai partent.
        $this->poster($admin, array_replace($this->base('civil', 'civil', ['RG']), ['numero_rg' => '9000/26', 'parties' => [0 => ['role' => 'demandeur', 'nature' => 'homme', 'nom' => 'VRAIE']]]))->assertSessionHasNoErrors();
        $this->artisan('affaires:demo --retirer')->assertSuccessful();
        $this->assertSame(1, Affaire::count());
        $this->assertSame('VRAIE', Partie::firstOrFail()->nom);
    }

    public function test_propositions_selon_le_registre(): void
    {
        $admin = $this->admin();
        $post = fn (string $objet, string $registre) => $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->postJson(route('affaires.enrolement.suggestions'), ['objet' => $objet, 'registre' => $registre])->assertOk();

        // « Abus de confiance » est un délit : proposé au pénal, jamais au civil.
        $this->assertSame([], $post('Abus de confiance', 'civil')->json('propositions'));
        $penal = $post('Abus de confiance', 'penal')->json('propositions');
        $this->assertSame('Abus de confiance', $penal[0]['libelle']);
        $this->assertNotEmpty($penal[0]['nature_id']);

        // Un objet civil n'est pas proposé au pénal ; l'instruction a les siens.
        $this->assertSame([], $post('Déguerpissement', 'penal')->json('propositions'));
        $this->assertNotEmpty($post('Déguerpissement', 'civil')->json('propositions'));
        $this->assertNotEmpty($post('Instruction affaire pénale adultes', 'instruction')->json('propositions'));
    }

    public function test_la_liste_montre_la_date_et_l_heure(): void
    {
        $admin = $this->admin();
        $this->poster($admin, $this->penal())->assertSessionHasNoErrors();
        $a = Affaire::firstOrFail();

        $page = $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->get(route('affaires.liste'))->assertOk();
        unset($page);
        $lignes = json_encode($this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->getJson(route('affaires.liste.donnees', ['length' => 100]))->assertOk()->json('data'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString($a->date_rg->format('d/m/Y'), $lignes);
        $this->assertStringContainsString('à '.$a->created_at->format('H:i'), $lignes);
    }

    public function test_les_attributions_de_l_ordonnance_servent_de_mots_cles(): void
    {
        $proposer = fn (string $objet, string $registre) => collect(\App\Support\RapprochementObjet::proposer($objet, 5, \App\Support\RegistreAffaire::depuis($registre)['natures']));

        // Un mot de l'ordonnance suffit à orienter vers la chambre qui l'énumère dans ses attributions.
        $chambres = fn ($propositions) => $propositions->pluck('chambre')->all();
        $this->assertContains('2e chambre civile', $chambres($proposer('foncier', 'civil')));
        $this->assertContains('2e chambre civile', $chambres($proposer('loyers impayés', 'civil')));
        $this->assertContains('2e chambre civile', $chambres($proposer('hypothèque', 'civil')));
        $this->assertContains('5e chambre civile', $chambres($proposer('ordonnance sur requête', 'civil')));
        $this->assertContains('Section sociale', $chambres($proposer('licenciement abusif contrat de travail', 'civil')));
        $this->assertContains('1re chambre correctionnelle', $chambres($proposer('blanchiment', 'penal')));

        // Chaque famille a ses chambres : un délit n'oriente pas vers une chambre civile, ni l'inverse.
        $this->assertNotContains('1re chambre correctionnelle', $chambres($proposer('foncier', 'civil')));
        $this->assertNotContains('2e chambre civile', $chambres($proposer('blanchiment', 'penal')));

        // Le mot-clé qui vient des attributions le dit (source), un objet connu garde sa nature et sa matière.
        $foncier = $proposer('foncier', 'civil')->firstWhere('chambre', '2e chambre civile');
        $this->assertSame('attributions', $foncier['source']);
        $this->assertNotEmpty($foncier['nature_id']);
        $this->assertSame('objet', $proposer('Hérédité', 'civil')->first()['source']);

        // Plusieurs choix pour le saisisseur : les mots-clés de la chambre qui correspondent, et la chambre du Président (civil seulement).
        $libelles = $proposer('litige foncier', 'civil')->pluck('libelle')->all();
        $this->assertContains('Publicité foncière', $libelles);
        $this->assertContains('Domaine foncier rural et urbain', $libelles);
        $this->assertStringStartsWith('Chambre présidentielle', end($libelles));
        $this->assertSame(1, $proposer('propriété intellectuelle', 'civil')->filter(fn ($p) => $p['libelle'] === 'Propriété intellectuelle')->count());   // « 1re » et « 1ere » ne se répètent pas
        $this->assertSame([], $proposer('litige', 'civil')->all());   // « litige » seul n'oriente vers aucune chambre
        $this->assertStringNotContainsString('Chambre présidentielle', json_encode($proposer('blanchiment', 'penal')->all()));

        // Rien de reconnu : aucune proposition (le greffier choisit lui-même).
        $this->assertSame([], $proposer('zzzz qqqq', 'civil')->all());
    }

    public function test_une_correction_refusee_rouvre_la_fenetre(): void
    {
        $admin = $this->admin();
        $this->poster($admin, $this->penal())->assertSessionHasNoErrors();
        $a = Affaire::firstOrFail();
        $mauvaise = $this->penal(['objet' => '']);
        unset($mauvaise['chambre_id']);

        $html = $this->actingAs($admin)->withSession(['derniere_activite' => now()->timestamp])->from(route('affaires.liste.fiche', $a->id))->followingRedirects()
            ->post(route('affaires.liste.modifier.enregistrer', $a->id), $mauvaise)->getContent();

        $this->assertStringContainsString('Correction : RP 1334/26', $html);
        $this->assertStringContainsString('La nature de la prévention est obligatoire.', $html);
        $this->assertMatchesRegularExpression('/\|\|\s*true\s*\)\s*\{/', $html);      // la fenêtre se rouvre toute seule, avec le message
        $this->assertSame('Destruction volontaire de biens', $a->refresh()->objet);   // rien n'a été enregistré
    }
}
