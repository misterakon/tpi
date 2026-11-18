<?php

namespace Tests\Feature;

use App\Models\Affaire;
use App\Models\BlocTexte;
use App\Models\Chambre;
use App\Models\Matiere;
use App\Models\ModeleDecision;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\Profil;
use App\Models\RoleAudience;
use App\Models\RoleLigne;
use App\Models\User;
use App\Support\Redaction\ChampsDecision;
use App\Support\Redaction\ChoixAffaire;
use App\Support\Redaction\Gabarit;
use App\Support\Redaction\ModelePropose;
use App\Support\Redaction\Preremplissage;
use App\Support\Redaction\VariablesDecision;
use Database\Seeders\ModelesJugementsCivilsSeeder;
use Database\Seeders\OrdonnanceBingerville2026Seeder;
use Database\Seeders\ReferentielsJudiciairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Jugements civils : conditions du gabarit, parties et textes longs, trois modèles (paiement, foncier, bail) et proposition du modèle. */
class JugementsCivilsTest extends TestCase
{
    use RefreshDatabase;

    private const MODELES = ['Jugement civil : paiement et contrats', 'Jugement civil : foncier', "Jugement civil : bail d'habitation"];

    private function rendre(string $texte, array $valeurs, array $blocs = []): array
    {
        return Gabarit::rendre($texte, $valeurs, $blocs, true);
    }

    private function parties(): array
    {
        return [
            'demandeurs' => [['nom' => 'CHEHADE Kassem', 'genre' => 'm', 'naissance' => ['mode' => 'date', 'date' => '1980-12-12'], 'lieu_naissance' => 'Abengourou', 'nationalite' => 'ivoirienne', 'profession' => 'gérant de société', 'domicile' => 'Abidjan']],
            'defendeurs' => [['nom' => 'DOUDOU Charlène', 'genre' => 'f'], ['nom' => 'KOUA Marie', 'genre' => 'f']],
        ];
    }

    /* ---------- le gabarit : conditions ---------- */

    public function test_une_condition_garde_le_passage_vrai_et_ecarte_l_autre(): void
    {
        $texte = '<p>{{#si nature = par défaut}}</p><p>Défaut</p><p>{{sinon}}</p><p>Contradictoire</p><p>{{/si}}</p>';

        $this->assertSame('<p>Défaut</p>', $this->rendre($texte, ['nature' => 'par défaut'])['texte']);
        $this->assertSame('<p>Contradictoire</p>', $this->rendre($texte, ['nature' => 'contradictoire'])['texte']);
        $this->assertSame('<p>Contradictoire</p>', $this->rendre($texte, [])['texte'], 'une variable absente ne déclenche pas la branche « = »');
    }

    public function test_les_conditions_s_imbriquent_et_une_valeur_non_compte_pour_vide(): void
    {
        $texte = '<p>A{{#si a}}B{{#si b = x}}C{{sinon}}D{{/si}}E{{/si}}F</p>';

        $this->assertSame('<p>ABCEF</p>', $this->rendre($texte, ['a' => 'oui', 'b' => 'x'])['texte']);
        $this->assertSame('<p>ABDEF</p>', $this->rendre($texte, ['a' => 'oui', 'b' => 'y'])['texte']);
        $this->assertSame('<p>AF</p>', $this->rendre($texte, ['a' => 'non', 'b' => 'x'])['texte'], '« non » est une valeur vide');
        $this->assertSame('<p>AF</p>', $this->rendre($texte, [])['texte']);
    }

    public function test_une_condition_compte_les_parties_et_exige_signale_ce_qui_manque(): void
    {
        $texte = '<p>{{#si demandeurs > 1}}plusieurs{{sinon}}un seul{{/si}}</p><p>{{#exige execution_provisoire}}</p><p>suite</p>';

        $r = $this->rendre($texte, ['demandeurs' => [['nom' => 'A', 'genre' => 'm'], ['nom' => 'B', 'genre' => 'f']]]);
        $this->assertSame('<p>plusieurs</p><p>suite</p>', $r['texte']);
        $this->assertSame(['execution_provisoire'], $r['manquantes']);

        $r = $this->rendre($texte, ['demandeurs' => [['nom' => 'A', 'genre' => 'm']], 'execution_provisoire' => 'ordonnée']);
        $this->assertSame('<p>un seul</p><p>suite</p>', $r['texte']);
        $this->assertSame([], $r['manquantes']);
    }

    public function test_le_pluriel_d_un_mot_en_s_ne_change_pas(): void
    {
        $this->assertSame('<p>douze (12) mois, un (01) mois, trois (03) jours</p>', $this->rendre('<p>{{ a|nombre_chiffres:mois }}, {{ b|nombre_chiffres:mois }}, {{ c|nombre_chiffres:jour }}</p>', ['a' => 12, 'b' => 1, 'c' => 3])['texte']);
    }

    public function test_les_variables_des_conditions_sont_demandees_au_formulaire(): void
    {
        $variables = Gabarit::variables('<p>{{#si depens = réservés}}</p><p>{{ defendeurs|parties_noms }}</p><p>{{sinon}}</p><p>{{/si}}</p>');

        $this->assertEqualsCanonicalizing(['depens', 'defendeurs'], $variables);
    }

    public function test_un_signe_superieur_ecrit_par_l_editeur_reste_une_condition(): void
    {
        $this->assertSame('<p>plusieurs</p>', $this->rendre('<p>{{#si demandeurs &gt; 1}}plusieurs{{/si}}</p>', ['demandeurs' => [['nom' => 'A', 'genre' => 'm'], ['nom' => 'B', 'genre' => 'm']]])['texte']);
    }

    /* ---------- le gabarit : parties, accords et textes longs ---------- */

    public function test_les_accords_suivent_les_parties(): void
    {
        $v = $this->parties();

        $r = $this->rendre('<p>{{ defendeurs|accord:défendeur }} {{ defendeurs|sg_pl:a,ont }} {{ defendeurs|pronom }} {{ defendeurs|accord:constructeur }}</p>', $v);
        $this->assertSame('<p>défenderesses ont elles constructrices</p>', $r['texte']);
        $r = $this->rendre('<p>{{ demandeurs|accord:demandeur }} {{ demandeurs|m_f:il,elle }} {{ numero_rg|numero }} {{ date_rg|annee }}</p>', $v + ['numero_rg' => 'RG-1145/26', 'date_rg' => '2026-06-10']);
        $this->assertSame('<p>demandeur il 1145 2026</p>', $r['texte']);
    }

    public function test_une_societe_s_accorde_au_feminin_et_sans_civilite(): void
    {
        $r = $this->rendre('<p>{{ p|parties_noms }} {{ p|accord:demandeur }} {{ p|parties_timbre }}</p>', ['p' => [['nom' => 'LA SOCIETE VICTOIRE SA', 'genre' => 's']]]);

        $this->assertSame('<p>LA SOCIETE VICTOIRE SA demanderesse LA SOCIETE VICTOIRE SA</p>', $r['texte']);
    }

    public function test_les_qualites_des_parties_s_ecrivent_comme_dans_un_jugement(): void
    {
        $v = $this->parties();
        $r = $this->rendre('<p>{{ demandeurs|parties_qualites }}</p>', $v);

        $this->assertSame('<p><strong>Monsieur CHEHADE KASSEM</strong>, né le 12 décembre 1980 à Abengourou, de nationalité ivoirienne, gérant de société, domicilié à Abidjan ;</p>', $r['texte']);

        // Plusieurs parties : une qualité par paragraphe, numérotées, séparées par une ligne vide.
        $r = $this->rendre('<p>{{ defendeurs|parties_qualites }}</p>', $v);
        $this->assertSame('<p><strong>1-Madame DOUDOU CHARLÈNE</strong> ;</p><p><br></p><p><strong>2-Madame KOUA MARIE</strong> ;</p>', $r['texte']);

        $societe = [['nom' => 'La société X SA', 'genre' => 's', 'profession' => 'au capital de 50.000.000 FCFA', 'domicile' => 'Abidjan Cocody', 'details' => 'représentée par son Directeur Général']];
        $this->assertSame('<p><strong>LA SOCIÉTÉ X SA</strong>, au capital de 50.000.000 FCFA, ayant son siège social à Abidjan Cocody, représentée par son Directeur Général ;</p>', $this->rendre('<p>{{ s|parties_qualites }}</p>', ['s' => $societe])['texte']);

        // Une femme née vers une année : accords au féminin.
        $f = [['nom' => 'KONE Awa', 'genre' => 'f', 'naissance' => ['mode' => 'annee', 'annee' => '1970'], 'domicile' => 'Bingerville']];
        $this->assertSame('<p><strong>Madame KONE AWA</strong>, née vers 1970, domiciliée à Bingerville ;</p>', $this->rendre('<p>{{ f|parties_qualites }}</p>', ['f' => $f])['texte']);
    }

    public function test_un_texte_long_une_liste_a_tirets_et_des_rubriques_donnent_des_paragraphes(): void
    {
        $r = $this->rendre('<p>{{ t|paragraphes }}</p><p>{{ d|tirets }}</p><p>{{ d|lignes }}</p><p>{{ s|sections }}</p><p>{{ s|dispositifs }}</p>', [
            't' => "Premier paragraphe\nSecond <b>paragraphe</b>",
            'd' => "Déclarer son action recevable.\nCONDAMNER la défenderesse ;\nMadame X doit payer",
            's' => [['titre' => 'Sur le sursis', 'texte' => "Attendu que A ;\nQue B ;", 'dispositif' => 'Rejette le sursis ;'], ['titre' => 'Sans dispositif', 'texte' => 'Attendu que C ;']],
        ]);

        $this->assertStringContainsString('<p>Premier paragraphe</p><p><br></p><p>Second &lt;b&gt;paragraphe&lt;/b&gt;</p>', $r['texte'], 'les balises sont neutralisées');
        $this->assertStringContainsString('<p>-déclarer son action recevable ;</p><p>-CONDAMNER la défenderesse ;</p><p>-Madame X doit payer ;</p>', $r['texte'], 'seul un infinitif prend une minuscule');
        $this->assertStringContainsString('<p>Déclarer son action recevable ;</p><p>CONDAMNER la défenderesse ;</p>', $r['texte']);
        $this->assertStringContainsString('<p><strong><u>Sur le sursis</u></strong></p><p><br></p><p>Attendu que A ;</p><p><br></p><p>Que B ;</p><p><br></p><p><strong><u>Sans dispositif</u></strong></p>', $r['texte']);
        $this->assertStringEndsWith('<p>Rejette le sursis ;</p>', $r['texte'], 'les lignes de dispositif sont reprises');
        $this->assertSame([], $r['manquantes']);
    }

    public function test_un_texte_long_vide_est_signale_et_des_rubriques_vides_disparaissent(): void
    {
        $r = $this->rendre('<p>Avant</p><p>{{ t|paragraphes }}</p><p>{{ s|sections }}</p><p>Après</p>', []);

        $this->assertSame(['t'], $r['manquantes']);
        $this->assertStringNotContainsString('{{ s', $r['texte'], 'des rubriques facultatives vides ne laissent aucune trace');
    }

    public function test_un_bloc_seul_dans_un_paragraphe_n_est_pas_emboite(): void
    {
        $r = $this->rendre('<p>{{> visa }}</p><p>Suite</p>', [], ['visa' => '<p>Vu la loi ;</p>']);

        $this->assertSame('<p>Vu la loi ;</p><p>Suite</p>', $r['texte']);
    }

    /* ---------- la saisie des nouveaux types de champ ---------- */

    private function champs(string $type): array
    {
        return [['code' => 'x', 'libelle' => 'Champ x', 'type' => $type]];
    }

    public function test_les_parties_saisies_sont_nettoyees(): void
    {
        $valeurs = ChampsDecision::nettoyer($this->champs('parties'), ['x' => [
            ['nom' => '  CHEHADE   Kassem ', 'genre' => 'm', 'naissance' => ['mode' => 'annee', 'annee' => '1980'], 'domicile' => '<b>Abidjan</b>', 'lieu_naissance' => ''],
            ['nom' => '', 'genre' => ''],
        ]]);

        $this->assertSame([['nom' => 'CHEHADE Kassem', 'genre' => 'm', 'naissance' => ['mode' => 'annee', 'annee' => '1980'], 'domicile' => 'Abidjan']], $valeurs['x']);
    }

    public function test_une_partie_sans_genre_est_refusee(): void
    {
        $this->expectException(ValidationException::class);
        ChampsDecision::nettoyer($this->champs('parties'), ['x' => [['nom' => 'KONE Awa', 'genre' => '']]]);
    }

    public function test_les_textes_longs_et_les_rubriques_saisis_sont_nettoyes(): void
    {
        $valeurs = ChampsDecision::nettoyer([['code' => 'a', 'libelle' => 'A', 'type' => 'paragraphes'], ['code' => 'b', 'libelle' => 'B', 'type' => 'sections']], [
            'a' => "  Un <script>x</script>\r\n\r\n   deux  \n", 'b' => [['titre' => 'T', 'texte' => "L1\n\nL2", 'dispositif' => ''], ['titre' => '', 'texte' => ''], 'n\'importe quoi'],
        ]);

        $this->assertSame("Un x\ndeux", $valeurs['a']);
        $this->assertSame([['titre' => 'T', 'texte' => "L1\nL2"]], $valeurs['b']);
    }

    /* ---------- les trois modèles ---------- */

    private function charger(): void
    {
        $this->seed(OrdonnanceBingerville2026Seeder::class);
        $this->seed(ReferentielsJudiciairesSeeder::class);
        $this->seed(ModelesJugementsCivilsSeeder::class);
    }

    public function test_les_trois_modeles_sont_charges_une_seule_fois(): void
    {
        $this->charger();
        $this->seed(ModelesJugementsCivilsSeeder::class);   // relancé : rien n'est dupliqué

        foreach (self::MODELES as $nom) {
            $modele = ModeleDecision::where('nom', $nom)->first();
            $this->assertNotNull($modele, $nom);
            $this->assertSame(1, $modele->versions()->count(), "$nom : une seule version");
            $this->assertSame('brouillon', $modele->etat);
            $this->assertGreaterThan(0, $modele->objets()->count(), "$nom : rattaché à des objets du litige");
        }
        $this->assertSame(1, BlocTexte::where('code', 'civ_parties')->count());
    }

    public function test_un_bloc_modifie_dans_l_application_n_est_jamais_ecrase(): void
    {
        $this->charger();
        $bloc = BlocTexte::where('code', 'civ_depens')->firstOrFail();
        $this->travel(1)->minutes();
        $bloc->update(['contenu' => '<p>Texte retouché par le greffe</p>']);

        $this->seed(ModelesJugementsCivilsSeeder::class);

        $this->assertSame('<p>Texte retouché par le greffe</p>', $bloc->fresh()->contenu);
    }

    /** @return array<string, mixed> les exemples du catalogue et du modèle, sur lesquels l'aperçu du modèle s'appuie */
    private function exemples(ModeleDecision $modele): array
    {
        $propres = $modele->load('variables')->variables->map(fn ($v) => ['code' => $v->code, 'libelle' => $v->libelle, 'type' => $v->type, 'exemple' => $v->exemple])->all();

        return VariablesDecision::exemples($propres);
    }

    public function test_chaque_modele_se_rend_sans_variable_manquante_avec_ses_exemples(): void
    {
        $this->charger();
        $blocs = BlocTexte::where('actif', true)->pluck('contenu', 'code')->all();

        foreach (self::MODELES as $nom) {
            $modele = ModeleDecision::where('nom', $nom)->firstOrFail();
            $version = $modele->versionCourante();
            $exemples = $this->exemples($modele);
            foreach (['entete', 'qualite', 'factum'] as $partie) {
                $r = Gabarit::rendre((string) $version->$partie, $exemples, $blocs, true);
                $this->assertSame([], $r['manquantes'], "$nom / $partie");
                $this->assertStringNotContainsString('{{', $r['texte'], "$nom / $partie : une balise est restée dans le texte");
                $this->assertStringNotContainsString('<p></p>', $r['texte'], "$nom / $partie : paragraphe vide");
                $this->assertDoesNotMatchRegularExpression('~<p>\s*<p>~', $r['texte'], "$nom / $partie : paragraphes emboîtés");
            }
        }
    }

    public function test_les_textes_cles_des_dix_jugements_sont_dans_les_modeles(): void
    {
        $this->charger();
        $blocs = BlocTexte::where('actif', true)->pluck('contenu', 'code')->all();
        $tout = collect(self::MODELES)->map(function ($nom) use ($blocs) {
            $m = ModeleDecision::where('nom', $nom)->firstOrFail();
            $v = $m->versionCourante();

            return implode(' ', [$v->entete, $v->qualite, $v->factum]).' '.implode(' ', $blocs);
        })->implode(' ');
        $tout = html_entity_decode(strip_tags($tout));

        foreach ([
            'article 1315', 'article 1153', 'article 1184', 'article 1183', 'article 1244', 'article 1382', 'article 1351', 'article 544', 'article 555', 'article 550',
            'article 220 de la loi n° 2020-624', 'article 435 de la loi n° 2019-576', 'article 425', 'article 145 du code de procédure civile', 'article 146 du Code de procédure civile',
            'article 106 du code de procédure civile', 'articles 101 et 103', 'articles 65, 67 et 140', 'article 67 in fine', 'article 3 du code de procédure civile',
            'Union - Discipline', 'Sans que les présentes qualités', 'D\'UNE PART', 'D\'AUTRE PART', 'DROIT', 'PAR CES MOTIFS', 'Réserve les dépens', 'distraits au profit',
        ] as $extrait) {
            $this->assertStringContainsString($extrait, $tout, $extrait);
        }
    }

    public function test_le_jugement_avant_dire_droit_remplace_le_fond_et_les_depens(): void
    {
        $this->charger();
        $blocs = BlocTexte::where('actif', true)->pluck('contenu', 'code')->all();
        $modele = ModeleDecision::where('nom', 'Jugement civil : foncier')->firstOrFail();
        $exemples = $this->exemples($modele);
        $exemples['type_jugement'] = 'avant-dire-droit';

        $entete = Gabarit::rendre((string) $modele->versionCourante()->entete, $exemples, $blocs, true)['texte'];
        $factum = Gabarit::rendre((string) $modele->versionCourante()->factum, $exemples, $blocs, true)['texte'];

        $this->assertStringContainsString('JUGEMENT CIVIL ADD', $entete);
        $this->assertStringContainsString('Ordonne une expertise immobilière', $factum);
        $this->assertStringContainsString('Renvoie la cause et les parties à l\'audience du 30 novembre 2026', $factum);
        $this->assertStringNotContainsString('Sur la demande en déguerpissement', $factum);
        $this->assertStringNotContainsString("Ordonne l'exécution provisoire", $factum);
    }

    public function test_une_fin_de_non_recevoir_accueillie_arrete_l_examen_du_fond(): void
    {
        $this->charger();
        $blocs = BlocTexte::where('actif', true)->pluck('contenu', 'code')->all();
        $modele = ModeleDecision::where('nom', 'Jugement civil : paiement et contrats')->firstOrFail();
        $exemples = $this->exemples($modele) + [];
        $exemples['fnr_objet'] = "la prescription de l'action";
        $exemples['fnr_auteur'] = 'Madame DOUDOU Charlène';
        $exemples['fnr_motifs'] = "Attendu que l'action est prescrite.";
        $exemples['fnr_issue'] = 'accueillie';

        $factum = Gabarit::rendre((string) $modele->versionCourante()->factum, $exemples, $blocs, true)['texte'];

        $this->assertStringContainsString("Déclare irrecevable l'action de Monsieur CHEHADE Kassem", $factum);
        $this->assertStringNotContainsString('AU FOND', $factum);
        $this->assertStringContainsString('Condamne Madame DOUDOU Charlène aux entiers dépens', $factum);
    }

    public function test_une_variable_obligatoire_non_renseignee_est_signalee_a_completer(): void
    {
        $this->charger();
        $blocs = BlocTexte::where('actif', true)->pluck('contenu', 'code')->all();
        $modele = ModeleDecision::where('nom', 'Jugement civil : paiement et contrats')->firstOrFail();
        $exemples = $this->exemples($modele);
        unset($exemples['execution_provisoire'], $exemples['depens'], $exemples['issue_fond'], $exemples['paiement']);

        $r = Gabarit::rendre((string) $modele->versionCourante()->factum, $exemples, $blocs, true);

        $this->assertEqualsCanonicalizing(['depens', 'issue_fond', 'paiement'], $r['manquantes']);
    }

    public function test_le_formulaire_demande_les_champs_des_modeles_dans_leur_groupe(): void
    {
        $this->charger();
        $modele = ModeleDecision::where('nom', 'Jugement civil : foncier')->firstOrFail();
        $champs = collect(ChampsDecision::pour($modele->load('variables'), $modele->versionCourante()));
        $codes = $champs->pluck('code');

        foreach (['demandeurs', 'defendeurs', 'demandes', 'expose_demandeur', 'fnr_issue', 'incidents', 'issue_fond', 'execution_provisoire', 'depens', 'di', 'expert_nom', 'propriete', 'designation_parcelle', 'deguerpissement', 'demolition', 'constructions'] as $code) {
            $this->assertTrue($codes->contains($code), "Le formulaire demande « $code »");
        }
        $this->assertSame('parties', $champs->firstWhere('code', 'demandeurs')['type']);
        $this->assertSame('sections', $champs->firstWhere('code', 'incidents')['type']);
        $this->assertSame(['accordés', 'accordés en partie', 'rejetés', 'non demandés'], $champs->firstWhere('code', 'di')['options']);
        $this->assertFalse($codes->contains('numero_rg'), 'le numéro du RG est connu de l\'application');
        $this->assertSame(1, $codes->filter(fn ($c) => $c === 'demandeurs')->count());
    }

    /* ---------- un formulaire réduit à ce que le jugement exige ---------- */

    public function test_un_champ_n_est_demande_que_si_son_choix_l_exige(): void
    {
        $this->assertTrue(ChampsDecision::visible(['code' => 'x'], []), 'sans condition : toujours demandé');
        $occupation = ['code' => 'occupation_constat', 'si' => 'deguerpissement = ordonné'];
        $this->assertFalse(ChampsDecision::visible($occupation, []));
        $this->assertFalse(ChampsDecision::visible($occupation, ['deguerpissement' => 'non demandé']));
        $this->assertTrue(ChampsDecision::visible($occupation, ['deguerpissement' => 'Ordonné']), 'sans tenir compte des majuscules');

        $this->assertTrue(ChampsDecision::visible(['si' => 'di in accordés|rejetés'], ['di' => 'rejetés']));
        $this->assertFalse(ChampsDecision::visible(['si' => 'di in accordés|rejetés'], ['di' => 'non demandés']));
        $this->assertTrue(ChampsDecision::visible(['si' => 'type_jugement != avant-dire-droit'], ['type_jugement' => 'au fond']));
        $this->assertFalse(ChampsDecision::visible(['si' => 'type_jugement != avant-dire-droit'], ['type_jugement' => 'avant-dire-droit']));
        $this->assertTrue(ChampsDecision::visible(['si' => 'fnr_objet'], ['fnr_objet' => 'la chose jugée']));
        $this->assertFalse(ChampsDecision::visible(['si' => 'fnr_objet'], ['fnr_objet' => '']));
        $this->assertFalse(ChampsDecision::visible(['si' => 'a = x && b = y'], ['a' => 'x', 'b' => 'z']), 'toutes les conditions doivent être vraies');
    }

    public function test_les_choix_courants_sont_preremplis_et_les_motifs_non_choisis_ne_demandent_rien(): void
    {
        $this->charger();
        $modele = ModeleDecision::where('nom', 'Jugement civil : foncier')->firstOrFail();
        $champs = collect(ChampsDecision::pour($modele->load('variables'), $modele->versionCourante()));
        $valeurs = ChampsDecision::nettoyer($champs->all(), Preremplissage::pour($modele, null, null));

        $this->assertSame('au fond', $valeurs['type_jugement']);
        $this->assertSame('à la charge du défendeur', $valeurs['depens']);
        $this->assertSame('non demandé', $valeurs['deguerpissement']);

        $visibles = fn (array $v) => $champs->filter(fn ($c) => ChampsDecision::visible($c, $v))->pluck('code');
        $depart = $visibles($valeurs);
        foreach (['occupation_constat', 'constructions', 'demolition_motifs', 'designation_parcelle', 'di_demande', 'ep_motif', 'expert_nom', 'fnr_auteur'] as $code) {
            $this->assertFalse($depart->contains($code), "« $code » ne se demande pas tant que son choix n'est pas fait");
        }
        $this->assertTrue($depart->contains('deguerpissement'));
        $this->assertTrue($depart->contains('issue_fond'));

        $choisi = $visibles(['deguerpissement' => 'ordonné', 'demolition' => 'ordonnée', 'propriete' => 'accordée', 'di' => 'accordés', 'execution_provisoire' => 'ordonnée'] + $valeurs);
        foreach (['occupation_constat', 'constructions', 'designation_parcelle', 'titre_propriete', 'di_demande', 'ep_motif'] as $code) {
            $this->assertTrue($choisi->contains($code), "« $code » apparaît quand son choix le demande");
        }

        $avant = $visibles(['type_jugement' => 'avant-dire-droit'] + $valeurs);
        $this->assertTrue($avant->contains('expert_nom'));
        $this->assertFalse($avant->contains('issue_fond'), 'un avant-dire-droit ne tranche pas le fond');
        $this->assertFalse($avant->contains('depens'));
    }

    public function test_l_essentiel_vient_d_abord_et_le_rare_est_range_dans_les_options(): void
    {
        $this->charger();
        $modele = ModeleDecision::where('nom', 'Jugement civil : foncier')->firstOrFail();
        $propres = $modele->load('variables')->variables->map(fn ($v) => ['code' => $v->code, 'libelle' => $v->libelle, 'type' => $v->type, 'exemple' => $v->exemple])->all();
        $groupes = array_keys(VariablesDecision::toutes($propres));

        $this->assertLessThan(array_search('Propres à ce modèle', $groupes, true), array_search('La procédure (jugement civil)', $groupes, true));
        $this->assertLessThan(array_search('Dommages-intérêts', $groupes, true), array_search('Propres à ce modèle', $groupes, true));
        $this->assertLessThan(array_search('La décision (jugement civil)', $groupes, true), array_search('Dommages-intérêts', $groupes, true));
        $this->assertSame(VariablesDecision::OPTIONS, end($groupes), 'les options sont tout à la fin');

        $champs = collect(ChampsDecision::pour($modele->load('variables'), $modele->versionCourante()));
        $options = $champs->where('groupe', VariablesDecision::OPTIONS)->pluck('code');
        foreach (['demandeurs_conseil', 'defendeurs_conseil', 'fnr_objet', 'incidents', 'sections_fond', 'complement_dispositif', 'depens_distraction', 'mp_sens_conclusions', 'reconventionnelle', 'formation'] as $code) {
            $this->assertTrue($options->contains($code), "« $code » est rangé dans les options");
        }
        $this->assertFalse($champs->contains('code', 'deboute_surplus'), "le surplus se déduit du sort de l'action");
        $this->assertSame('Les parties (jugement civil)', $champs->first(fn ($c) => $c['code'] === 'demandeurs')['groupe']);
    }

    public function test_la_nature_de_la_decision_s_explique_toute_seule(): void
    {
        $this->charger();
        $blocs = BlocTexte::where('actif', true)->pluck('contenu', 'code')->all();
        $modele = ModeleDecision::where('nom', 'Jugement civil : paiement et contrats')->firstOrFail();
        $exemples = $this->exemples($modele);
        unset($exemples['caractere_motif']);

        $rendre = fn (array $v) => strip_tags(Gabarit::rendre((string) $modele->versionCourante()->factum, $v + $exemples, $blocs, true)['texte']);

        $this->assertStringContainsString("n'ayant ni comparu ni conclu, il n'est pas certain qu'elle ait eu connaissance de la présente procédure", $rendre(['nature_decision' => 'par défaut']));
        $this->assertStringContainsString('a eu connaissance de la présente procédure pour avoir comparu et conclu', $rendre(['nature_decision' => 'contradictoire', 'defendeurs_comparution' => 'Comparaissant']));
        $this->assertStringContainsString('ont eu connaissance de la présente procédure pour avoir été assignées à personne', $rendre([
            'nature_decision' => 'contradictoire', 'defendeurs_comparution' => 'Ne comparaissant pas', 'defendeurs' => [['nom' => 'A', 'genre' => 'f'], ['nom' => 'B', 'genre' => 'f']],
        ]));
        $this->assertStringContainsString('est un motif écrit à la main', $rendre(['caractere_motif' => 'est un motif écrit à la main']), 'le motif saisi remplace le motif déduit');
    }

    public function test_le_premier_renvoi_se_deduit_des_feuilles_d_audience(): void
    {
        $this->charger();
        $affaire = $this->affaire('Déguerpissement');
        $chambre = $affaire->chambre;
        foreach (['2026-06-15' => ['issue' => 'renvoi', 'motif' => 'Pour conclusions du défendeur'], '2026-06-29' => ['issue' => 'delibere', 'motif' => null]] as $jour => $suite) {
            $role = RoleAudience::create(['chambre_id' => $chambre->id, 'date_audience' => $jour, 'heure' => '09:00', 'fait_a' => 'Bingerville', 'date_signature' => $jour]);
            $role->forceFill(['statut' => 'publie'])->save();
            RoleLigne::create(['role_id' => $role->id, 'rubrique' => 'nouvelles', 'ordre' => 1, 'affaire_id' => $affaire->id, 'numero' => 'RG-1145/26', 'demandeurs' => 'A', 'defendeurs' => 'B', 'objet' => 'x'] + $suite);
        }
        $modele = ModeleDecision::where('nom', 'Jugement civil : foncier')->firstOrFail();

        $connues = Preremplissage::pour($modele, $chambre, '2026-10-26', $affaire);

        $this->assertSame('2026-06-15', $connues['date_premiere_evocation']);
        $this->assertSame('2026-06-29', $connues['date_premier_renvoi']);
        $this->assertSame('pour conclusions du défendeur', $connues['motif_premier_renvoi']);
    }

    /* ---------- le modèle proposé d'après l'affaire ---------- */

    public function test_le_modele_se_propose_d_apres_l_objet_de_l_affaire(): void
    {
        $this->charger();

        $this->assertSame('Jugement civil : foncier', ModelePropose::pour('Déguerpissement et démolition')[0]['nom']);
        $this->assertSame('Jugement civil : foncier', ModelePropose::pour('Revendication de propriétés')[0]['nom'], 'ni majuscule, ni accent, ni pluriel');
        $this->assertSame('Jugement civil : paiement et contrats', ModelePropose::pour('Créances civiles')[0]['nom']);
        $this->assertSame("Jugement civil : bail d'habitation", ModelePropose::pour('Validation de congé')[0]['nom']);
        $this->assertSame([], ModelePropose::pour('Objet sans aucun rapport'));
        $this->assertSame([], ModelePropose::pour(null));

        // « Expulsion et paiement de loyers » convient au bail comme au paiement : les deux sont proposés.
        $noms = array_column(ModelePropose::pour('Expulsion et paiement de loyers'), 'nom');
        $this->assertContains("Jugement civil : bail d'habitation", $noms);
        $this->assertContains('Jugement civil : paiement et contrats', $noms);
    }

    private function user(): User
    {
        return User::factory()->create(['profil_id' => Profil::create(['nom' => 'Rédacteur '.uniqid(), 'droits' => ['redaction.voir', 'redaction.creer', 'redaction.modifier', 'affaires.voir', 'affaires.toutes_chambres']])->id]);
    }

    private function affaire(string $objet): Affaire
    {
        $chambre = Chambre::where('libelle', 'like', '%chambre civile%')->orderBy('id')->firstOrFail();
        $affaire = Affaire::create([
            'serie' => 'RG', 'annee' => 2026, 'numero' => 1145, 'numero_complet' => 'RG-1145/26', 'numero_saisi' => true, 'date_rg' => '2026-06-10', 'objet' => $objet,
            'mode_saisine_id' => ModeSaisine::where('registre', 'civil')->value('id'), 'nature_affaire_id' => NatureAffaire::where('registre', 'RG')->value('id'),
            'matiere_id' => Matiere::value('id'), 'chambre_id' => $chambre->id, 'statut' => 'enrolee', 'date_premiere_audience' => '2026-06-15',
        ]);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'demandeur', 'nature' => 'homme', 'nom' => 'Chehade', 'prenoms' => 'Kassem', 'naissance_mode' => 'date', 'naissance_date' => '1980-12-12', 'lieu_naissance' => 'Abengourou', 'nationalite' => 'Ivoirienne', 'profession' => 'Gérant de société', 'domicile' => 'Abidjan-Cocody', 'avocat' => 'Maître KONE Catherine', 'ordre' => 1, 'cle' => 'a1']);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'defendeur', 'nature' => 'femme', 'nom' => 'Doudou', 'prenoms' => 'Charlène', 'ordre' => 1, 'cle' => 'b1']);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'defendeur', 'nature' => 'morale', 'denomination' => 'Société EXEMPLE SA', 'ordre' => 2, 'cle' => 'b2']);
        Partie::create(['affaire_id' => $affaire->id, 'role' => 'defendeur', 'nature' => 'mp', 'denomination' => 'Ministère public', 'ordre' => 3, 'cle' => 'b3']);

        return $affaire->load('parties');
    }

    public function test_la_recherche_d_affaire_propose_le_modele(): void
    {
        $this->charger();
        $affaire = $this->affaire('Déguerpissement');

        $fiche = ChoixAffaire::pour($affaire);

        $this->assertSame('Jugement civil : foncier', $fiche['modeles'][0]['nom']);
        $resultats = ChoixAffaire::rechercher($this->user(), 'Chehade');
        $this->assertSame('Jugement civil : foncier', $resultats[0]['modeles'][0]['nom']);
    }

    public function test_les_parties_de_l_affaire_remplissent_le_formulaire(): void
    {
        $this->charger();
        $affaire = $this->affaire('Déguerpissement');
        $modele = ModeleDecision::where('nom', 'Jugement civil : foncier')->firstOrFail();
        $champs = ChampsDecision::pour($modele->load('variables'), $modele->versionCourante());

        $connues = Preremplissage::pour($modele, $affaire->chambre, '2026-10-26', $affaire);
        $valeurs = ChampsDecision::nettoyer($champs, $connues);

        $this->assertSame([['nom' => 'CHEHADE Kassem', 'genre' => 'm', 'naissance' => ['mode' => 'date', 'date' => '1980-12-12'], 'lieu_naissance' => 'Abengourou', 'nationalite' => 'ivoirienne', 'profession' => 'gérant de société', 'domicile' => 'Abidjan-Cocody']], $valeurs['demandeurs']);
        $this->assertSame([['nom' => 'DOUDOU Charlène', 'genre' => 'f'], ['nom' => 'Société EXEMPLE SA', 'genre' => 's']], $valeurs['defendeurs'], 'le Ministère public n\'est pas une partie à désigner');
        $this->assertSame('Me KONE Catherine', $valeurs['demandeurs_conseil']);
        $this->assertSame('Représenté par son conseil, Maître KONE Catherine', $valeurs['demandeurs_representation']);
        $this->assertSame('2026-06-15', $valeurs['date_premiere_evocation']);
        $this->assertArrayNotHasKey('defendeurs_conseil', $valeurs);
    }
}
