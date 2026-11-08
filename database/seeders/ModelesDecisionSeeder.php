<?php

namespace Database\Seeders;

use App\Models\BlocTexte;
use App\Models\Matiere;
use App\Models\ModeleDecision;
use App\Models\ModeleDecisionVersion;
use App\Models\ObjetLitige;
use App\Support\TexteRiche;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 | Premier modèle de décision : le jugement d'hérédité, construit d'après les trois documents Word reçus (deux qualités et le factum
 | du dossier RG 136/2025) et l'annexe C du cahier des charges. Il reprend leur mise en page : le timbre dans la colonne de gauche de
 | la première page, la qualité en retrait, le factum sur toute la largeur, une ligne vide entre les paragraphes.
 | Les noms de ces dossiers n'y figurent pas : tout ce qui varie d'une affaire à l'autre est une variable. À relire avec les juges.
 | Peut être relancé sans danger : ce qui existe déjà n'est jamais écrasé (le modèle déjà chargé reçoit seulement une nouvelle version
 | s'il n'avait pas encore de timbre).
 |   php artisan db:seed --class=ModelesDecisionSeeder
 */
class ModelesDecisionSeeder extends Seeder
{
    private const VIDE = '<p><br></p>';

    private const NOTE_ACTUELLE = 'Mise en page affinée sur le document Word : timbre et ses traits, espacement, titre centré sur deux lignes';

    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->blocs() as [$code, $nom, $categorie, $contenu]) {
                BlocTexte::firstOrCreate(['code' => $code], ['nom' => $nom, 'categorie' => $categorie, 'contenu' => $contenu, 'actif' => true]);
            }
            // Le numéro du RG s'écrit désormais sans « RG » : le texte du visa le porte.
            BlocTexte::where('code', 'visa_pieces_rg')->where('contenu', '<p>Vu les pièces du dossier de la procédure {{ numero_rg }} du {{ date_rg|date_numerique }} ;</p>')
                ->update(['contenu' => '<p>Vu les pièces du dossier de la procédure RG {{ numero_rg }} du {{ date_rg|date_numerique }} ;</p>']);
            $this->heredite();
        });
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: string}> */
    private function blocs(): array
    {
        return [
            ['formule_jugement', 'Formule : a rendu le jugement', 'formule', '<p>A rendu le jugement dont la teneur suit dans la cause ;</p>'],
            ['reserves_qualites', 'Réserves sur les qualités', 'formule', '<p>Sans que les présentes qualités puissent nuire ni préjudicier en quoi que ce soit aux droits et intérêts respectifs des parties en cause, mais au contraire et sous les plus expresses réserves des faits et de droit ;</p>'],
            ['visa_deliberation', 'Visa : après en avoir délibéré', 'visas', '<p>Après en avoir délibéré conformément à la loi ;</p>'],
            ['visa_loi_successions', 'Visa : loi relative aux successions', 'visas', '<p>Vu la loi n°2019-573 du 26 juin 2019 relative aux successions ;</p>'],
            ['visa_pieces_rg', 'Visa : pièces du dossier de la procédure', 'visas', '<p>Vu les pièces du dossier de la procédure RG {{ numero_rg }} du {{ date_rg|date_numerique }} ;</p>'],
            ['depens_requerant', 'Motif : dépens à la charge du requérant', 'depens', '<p>Attendu que la présente décision ayant été rendue au profit du requérant, il sied de mettre les dépens à sa charge ;</p>'],
            ['cloture_jugement', 'Clôture du jugement', 'cloture', '<p>Ainsi fait, jugé et prononcé publiquement les jour, mois et an que dessus ;</p><p><br></p><p><strong>ET ONT SIGNE LE PRESIDENT ET LE GREFFIER./.</strong></p>'],
        ];
    }

    private function heredite(): void
    {
        $nom = "Jugement d'hérédité";
        $modele = ModeleDecision::where('nom', $nom)->first();

        if (! $modele) {
            $modele = ModeleDecision::create([
                'nom' => $nom, 'matiere_id' => Matiere::where('libelle', 'Civile')->value('id'),
                'description' => 'Détermination de la qualité des héritiers (modèle de départ, à relire avec les juges)', 'etat' => 'brouillon',
            ]);
            foreach ($this->variables() as [$code, $libelle, $type, $exemple]) {
                $modele->variables()->create(['code' => $code, 'libelle' => $libelle, 'type' => $type, 'exemple' => $exemple]);
            }
            $modele->objets()->sync(ObjetLitige::where('libelle', 'Hérédité')->pluck('id')->all());
            DB::table('modeles_decision_chambres')->insertOrIgnore(['modele_decision_id' => $modele->id, 'chambre_libelle' => '3e chambre civile']);
        }

        $courante = $modele->versions()->first();
        // On ne remplace que les textes d'un chargement précédent, jamais ceux qu'un utilisateur a réellement modifiés : un simple
        // enregistrement depuis l'éditeur reformate le HTML mais laisse le texte identique.
        $attendu = new ModeleDecisionVersion([
            'entete' => TexteRiche::nettoyer($this->entete()), 'qualite' => TexteRiche::nettoyer($this->qualite()), 'factum' => TexteRiche::nettoyer($this->factum()),
        ]);
        if ($courante) {
            if ($this->forme($courante) === $this->forme($attendu)) {
                return;   // déjà à jour
            }
            $charges = $modele->versions()->whereNull('cree_par')->get();
            $inchange = $charges->contains(fn ($v) => $this->texteBrut($v) === $this->texteBrut($courante));
            if (! $inchange) {
                return;
            }
        }

        ModeleDecisionVersion::create([
            'modele_decision_id' => $modele->id, 'numero' => ($courante?->numero ?? 0) + 1,
            'entete' => $attendu->entete, 'qualite' => $attendu->qualite, 'factum' => $attendu->factum,
            'note' => $courante ? self::NOTE_ACTUELLE : "Première version, d'après les documents reçus",
        ]);
    }

    /** Le texte d'une version avec sa mise en forme, réduit à l'essentiel (entités, espaces) : sert à savoir si elle est déjà à jour. */
    private function forme(ModeleDecisionVersion $v): string
    {
        return implode('|', array_map(
            fn ($t) => preg_replace('/\s+/u', ' ', html_entity_decode(trim((string) TexteRiche::nettoyer((string) $t)))),
            [$v->entete, $v->qualite, $v->factum],
        ));
    }

    /** Le texte d'une version sans sa mise en forme : sert à savoir si quelqu'un l'a réellement modifié. */
    private function texteBrut(ModeleDecisionVersion $v): string
    {
        return implode('|', array_map(
            fn ($t) => preg_replace('/\s+/u', ' ', trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>'], ' ', (string) $t))))),
            [$v->entete, $v->qualite, $v->factum],
        ));
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: ?string}> */
    private function variables(): array
    {
        return [
            ['date_requete', 'Date de la requête', 'date', '2026-10-02'],
            ['defunt.nom', 'Défunt : nom et prénoms', 'texte', 'DIALLO Mariam'],
            ['defunt.genre', 'Défunt : genre', 'genre', 'f'],
            ['date_deces', 'Date du décès', 'date', '2026-08-16'],
            ['lieu_deces', 'Lieu du décès', 'texte', 'la clinique Sainte-Marie de Bingerville'],
            ['acte_deces.numero', 'Acte de décès : numéro', 'texte', '00'],
            ['acte_deces.date', 'Acte de décès : date', 'date', '2026-08-17'],
            ['acte_deces.centre', "Acte de décès : centre d'état civil", 'texte', "centre d'état civil de la commune de Bingerville"],
            ['nombre_enfants', "Nombre d'enfants laissés", 'nombre', '3'],
            ['enfants', 'Enfants (noms)', 'liste', '["Aminata TRAORE", "Moussa TRAORE", "Fanta TRAORE"]'],
            ['regime_matrimonial', 'Régime matrimonial', 'texte', 'la communauté de biens'],
            ['acte_mariage.numero', 'Acte de mariage : numéro', 'texte', '00'],
            ['acte_mariage.date', 'Acte de mariage : date', 'date', '1990-07-12'],
            ['domicile_defunt', 'Domicile du défunt', 'texte', 'Bingerville'],
            ['nombre_temoins', 'Nombre de témoins', 'nombre', '2'],
            ['temoins', 'Témoins (noms)', 'liste', '["Awa KONE", "Issa DIARRA"]'],
            ['date_conclusions_mp', 'Date des conclusions du Ministère public', 'date', '2026-10-20'],
            ['articles_vises', 'Articles de la loi visés', 'texte', '12 et 26'],
        ];
    }

    /** Le timbre : colonne de gauche de la première page (texte centré, en gras). */
    private function entete(): string
    {
        return <<<'HTML'
<p>{{ cour_appel|majuscule }}</p>
<p>{{> trait_pointille }}</p>
<p>{{ juridiction|majuscule }}</p>
<p><br></p>
<p>RG N° {{ numero_rg }} du {{ date_rg|date_numerique }}</p>
<p>{{> trait_plein }}</p>
<p>{{ chambre|majuscule }}</p>
<p>{{ type_decision|majuscule }}</p>
<p>N° {{ numero_decision }} du {{ date_decision|date_numerique }}</p>
<p>AFFAIRE</p>
<p>{{ demandeur.nom|majuscule }}</p>
<p>C/</p>
<p>MINISTERE PUBLIC</p>
HTML;
    }

    /** La qualité, en retrait à droite du timbre ; une ligne vide entre les paragraphes, comme dans le document Word. */
    private function qualite(): string
    {
        $v = self::VIDE;

        return implode('', [
            // Le titre tient sur deux lignes fixes : le début de la qualité ne bouge pas quand la date est plus longue.
            '<p class="ql-align-center"><strong><u>AUDIENCE PUBLIQUE</u></strong></p>',
            '<p class="ql-align-center"><strong><u>DU {{ date_audience|date_chiffres|majuscule }}</u></strong></p>', $v,
            '<p class="ql-align-center">({{ chambre }})</p>',
            '<p class="ql-align-center">--------------------------</p>', $v,
            '<p>Le {{ juridiction }}, {{ chambre_lettres }}, statuant en matière {{ matiere }}, en son audience publique {{ type_audience }} du {{ date_audience|date }}, tenue au palais de justice de ladite ville à laquelle siégeaient :</p>', $v,
            '<p><strong>{{ president|personne }}, {{ president.fonction }} ;</strong></p>',
            '<p class="ql-align-right"><strong>PRESIDENT ;</strong></p>', $v,
            '<p><strong>{{ assesseurs|personnes }}, Juges au siège ;</strong></p>',
            '<p class="ql-align-right"><strong>ASSESSEURS ;</strong></p>', $v,
            "<p>Avec l'assistance de {{ greffier|personne:Maître }}, Greffier ;</p>", $v,
            '<p>{{> formule_jugement }}</p>', $v,
            '<p><strong><u>ENTRE :</u></strong></p>', $v,
            '<p><strong>{{ demandeur|personne }}, {{ demandeur.genre|ne }} {{ demandeur.naissance }} à {{ demandeur.lieu_naissance }}, de nationalité {{ demandeur.nationalite }}, {{ demandeur.genre|accord:domicilié }} à {{ demandeur.domicile }} ;</strong></p>', $v,
            '<p>{{ demandeur.genre|accord:Demandeur }}, comparaissant {{ demandeur.comparution }} ;</p>', $v,
            "<p class=\"ql-align-right\"><strong>D'UNE PART ;</strong></p>", $v,
            '<p><strong>ET</strong></p>', $v,
            '<p><strong>LE MINISTERE PUBLIC, partie jointe ;</strong></p>', $v,
            "<p class=\"ql-align-right\"><strong>D'AUTRE PART ;</strong></p>", $v,
            '<p>{{> reserves_qualites }}</p>', $v,
        ]);
    }

    /** Le factum, sur toute la largeur de la page. */
    private function factum(): string
    {
        $v = self::VIDE;

        return implode('', [
            '<p><strong><u>LE TRIBUNAL</u></strong></p>', $v,
            '<p>{{> visa_loi_successions }}</p>',
            '<p>{{> visa_pieces_rg }}</p>',
            '<p>Vu les conclusions écrites du Ministère Public du {{ date_conclusions_mp|date_courte }} ;</p>',
            '<p>{{> visa_deliberation }}</p>', $v,
            '<p><strong><u>FAITS, PROCEDURE ET PRETENTIONS DES PARTIES</u></strong></p>', $v,
            "<p>Par requête en date du {{ date_requete|date_courte }}, {{ demandeur.nom }} a sollicité du tribunal civil de céans, l'obtention d'un jugement déterminant la qualité des héritiers de {{ defunt.genre|accord:feu }} {{ defunt.nom }} ;</p>", $v,
            "<p>Au soutien de sa requête, {{ demandeur.nom }} expose que {{ defunt.genre|accord:feu }} {{ defunt.nom }} est {{ defunt.genre|accord:décédé }} le {{ date_deces|date_courte }} à {{ lieu_deces }}, comme l'atteste l'acte de décès n°{{ acte_deces.numero }} du {{ acte_deces.date|date_courte }} du {{ acte_deces.centre }}, en laissant à sa survivance {{ nombre_enfants|nombre_chiffres:enfant }} dont les actes de naissance sont produits au dossier ;</p>", $v,
            "<p>{{ demandeur.nom }} ajoute que {{ defunt.nom }} n'a pas fait de donation entre vifs ni laissé de testament, mais avait contracté mariage légal avec lui sous le régime de {{ regime_matrimonial }} ainsi que le prouve l'acte de mariage n°{{ acte_mariage.numero }} du {{ acte_mariage.date|date_numerique }}, également produit au dossier ;</p>", $v,
            '<p>{{ demandeur.nom }} précise que {{ defunt.genre|accord:feu }} {{ defunt.nom }} était {{ defunt.genre|accord:domicilié }} à {{ domicile_defunt }} ;</p>', $v,
            "<p>{{ demandeur.nom }} a fait comparaître {{ nombre_temoins|nombre_chiffres:témoin }} majeurs à savoir {{ temoins|liste }}, qui ont déclaré et attesté sous la foi du serment, avoir parfaitement connu le de cujus et confirmé ses allégations ;</p>", $v,
            "<p>Le Ministère Public a conclu qu'il plaise au Tribunal faire droit à la requête ;</p>", $v,
            '<p><strong><u>SUR CE</u></strong></p>', $v,
            '<p><strong><u>EN LA FORME</u></strong></p>', $v,
            '<p>Attendu que la requête de {{ demandeur.nom }} ayant été introduite dans le respect des prescriptions légales, est recevable ;</p>', $v,
            '<p><strong><u>AU FOND</u></strong></p>', $v,
            "<p>Attendu qu'il résulte des pièces du dossier et des témoignages recueillis à l'audience que la requête de {{ demandeur.nom }} est bien fondée ;</p>", $v,
            "<p>Qu'il convient d'y faire droit par application des dispositions pertinentes des articles {{ articles_vises }} de la loi n°2019-573 du 26 juin 2019 relative aux successions précitée ;</p>", $v,
            '<p>{{> depens_requerant }}</p>', $v,
            '<p><strong><u>PAR CES MOTIFS</u></strong></p>', $v,
            "<p>Statuant publiquement, {{ nature_decision|adverbe }}, en matière d'état des personnes et en premier ressort :</p>", $v,
            '<p>Déclare {{ demandeur.nom }} recevable en sa requête ;</p>', $v,
            "<p>L'y dit bien fondé ;</p>", $v,
            "<p>Constate que {{ defunt.genre|accord:feu }} {{ defunt.nom }} n'a pas fait de donation entre vifs ni laissé de testament et a contracté mariage légal avec {{ demandeur.nom }} sous le régime de {{ regime_matrimonial }} ;</p>", $v,
            '<p>En conséquence, dit que {{ defunt.genre|accord:le }} {{ defunt.genre|accord:défunt }} a laissé pour recueillir sa succession :</p>',
            '<p><strong>- {{ demandeur.genre|accord:son }} {{ demandeur.genre|accord:veuf }}, {{ demandeur.nom }}, en qualité de conjoint survivant et {{ nombre_enfants|nombre_chiffres:enfant }} à savoir :</strong></p>',
            '<p><strong>{{ enfants|liste_numerotee }}</strong></p>', $v,
            "<p>Met les dépens de l'instance à la charge de {{ demandeur.nom }}.</p>", $v,
            '<p>{{> cloture_jugement }}</p>',
        ]);
    }
}
