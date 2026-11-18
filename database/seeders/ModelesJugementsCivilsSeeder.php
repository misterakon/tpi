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
 | Trois modèles de jugements civils, construits d'après dix jugements rendus par la 2e chambre civile (chambre immobilière et des
 | paiements) le 26 octobre 2026 :
 |   - « Jugement civil : paiement et contrats » (remboursements, loyers impayés, résolution de contrat, délai de grâce) ;
 |   - « Jugement civil : foncier » (revendication de propriété, déguerpissement, démolition, dommages-intérêts, expertise) ;
 |   - « Jugement civil : bail d'habitation » (validation de congé, expulsion, quittances).
 | Tous trois partagent la même trame (timbre, qualités, faits et procédure, motifs en la forme, exécution provisoire, dépens,
 | dispositif) écrite une seule fois dans des blocs de texte ; chaque modèle y ajoute ses motifs propres, qu'une variable fait
 | apparaître ou disparaître (par exemple « Déguerpissement : ordonné »). Les noms des dossiers réels n'y figurent pas.
 | Peut être relancé sans danger : ce qui existe déjà n'est jamais écrasé ; un modèle déjà chargé reçoit une nouvelle version
 | seulement si personne ne l'a modifié depuis le dernier chargement.
 |   php artisan db:seed --class=ModelesJugementsCivilsSeeder
 */
class ModelesJugementsCivilsSeeder extends Seeder
{
    private const V = '<p><br></p>';

    public function run(): void
    {
        DB::transaction(function () {
            // Les blocs communs aux modèles d'hérédité et de jugements civils (clôture, réserves) viennent du premier seeder.
            $this->call(ModelesDecisionSeeder::class);

            foreach ($this->blocs() as [$code, $nom, $categorie, $contenu]) {
                $contenu = TexteRiche::nettoyer($contenu);
                $bloc = BlocTexte::where('code', $code)->first();
                if (! $bloc) {
                    BlocTexte::create(['code' => $code, 'nom' => $nom, 'categorie' => $categorie, 'contenu' => $contenu, 'actif' => true]);
                } elseif ($bloc->contenu !== $contenu && $bloc->updated_at?->equalTo($bloc->created_at)) {
                    // Un bloc que personne n'a modifié suit les textes de départ ; un bloc retouché dans l'application n'est jamais écrasé.
                    DB::table('blocs_texte')->where('id', $bloc->id)->update(['contenu' => $contenu, 'updated_at' => $bloc->created_at]);
                }
            }
            $this->charger(
                'Jugement civil : paiement et contrats',
                'Remboursement de sommes, loyers impayés, résolution de contrat, délai de grâce, dommages-intérêts (d\'après quatre jugements, à relire avec les juges)',
                $this->variablesPaiement(), ['Contrats civils', 'Créances civiles', 'Paiement (intérêt pécuniaire inférieur ou égal à 100 000 000 FCFA)', 'Expulsion et paiement de loyers'],
                $this->factumPaiement(),
            );
            $this->charger(
                'Jugement civil : foncier',
                'Revendication de propriété, déguerpissement, démolition des constructions, dommages-intérêts, expertise avant-dire-droit (d\'après cinq jugements, à relire avec les juges)',
                $this->variablesFoncier(), ['Déguerpissement', 'Revendication de propriété', 'Domaine foncier rural et urbain', 'Spoliation foncière'],
                $this->factumFoncier(),
            );
            $this->charger(
                "Jugement civil : bail d'habitation",
                "Validation de congé, expulsion, quittances de loyer, demandes reconventionnelles (d'après un jugement, à relire avec les juges)",
                $this->variablesBail(), ['Validation de congé', 'Validation de congé avec expulsion', 'Renouvellement des baux et révision des loyers', 'Expulsion et paiement de loyers'],
                $this->factumBail(),
            );
        });
    }

    /* ---------------------------------------------------------------- aides d'écriture */

    /** Un paragraphe suivi d'une ligne vide. */
    private function p(string $texte): string
    {
        return '<p>'.$texte.'</p>'.self::V;
    }

    /** Un paragraphe sans ligne vide après lui. */
    private function pn(string $texte): string
    {
        return '<p>'.$texte.'</p>';
    }

    /** Un titre en gras souligné, suivi d'une ligne vide. */
    private function t(string $texte): string
    {
        return '<p><strong><u>'.$texte.'</u></strong></p>'.self::V;
    }

    /** Un passage qui ne paraît que si la condition est vraie. */
    private function si(string $condition, string ...$contenu): string
    {
        return '<p>{{#si '.$condition.'}}</p>'.implode('', $contenu).'<p>{{/si}}</p>';
    }

    /** Deux passages : l'un si la condition est vraie, l'autre sinon. */
    private function alt(string $condition, string $alors, string $sinon): string
    {
        return '<p>{{#si '.$condition.'}}</p>'.$alors.'<p>{{sinon}}</p>'.$sinon.'<p>{{/si}}</p>';
    }

    /** Un bloc de texte appelé seul. */
    private function bloc(string $code): string
    {
        return '<p>{{> '.$code.' }}</p>';
    }

    private function droite(string $texte): string
    {
        return '<p class="ql-align-right"><strong><u>'.$texte.'</u> ;</strong></p>'.self::V;
    }

    /* ---------------------------------------------------------------- blocs de texte */

    /** @return list<array{0: string, 1: string, 2: string, 3: string}> */
    private function blocs(): array
    {
        return [
            ['civ_formule_jugement', 'Formule : a rendu le jugement (jugement civil)', 'formule', '<p>A rendu le jugement dont la teneur suit dans la cause :</p>'],
            ['civ_parties', 'Les parties : entre… et… (jugement civil)', 'formule', $this->blocParties()],
            ['civ_faits_droit', 'Faits, droit et déroulement de la procédure (jugement civil)', 'formule', $this->blocFaitsDroit()],
            ['civ_visas', 'Visas : le tribunal vu les pièces (jugement civil)', 'visas', $this->blocVisas()],
            ['civ_faits_pretentions', 'Faits, procédure, prétentions et moyens des parties (jugement civil)', 'motifs', $this->blocFaitsPretentions()],
            ['civ_forme', 'Motifs en la forme : nature de la décision et recevabilité (jugement civil)', 'motifs', $this->blocForme()],
            ['civ_di', 'Motifs : dommages-intérêts (jugement civil)', 'motifs', $this->blocDommagesInterets()],
            ['civ_add_motifs', 'Motifs : avant-dire-droit, expertise (jugement civil)', 'motifs', $this->blocAddMotifs()],
            ['civ_execution_provisoire', 'Motifs : exécution provisoire (jugement civil)', 'motifs', $this->blocExecutionProvisoire()],
            ['civ_depens', 'Motifs : dépens (jugement civil)', 'depens', $this->blocDepens()],
            ['civ_dispo_debut', 'Dispositif : début, recevabilité (jugement civil)', 'dispositif', $this->blocDispoDebut()],
            ['civ_dispo_issue', "Dispositif : sort de l'action au fond (jugement civil)", 'dispositif', $this->blocDispoIssue()],
            ['civ_di_dispositif', 'Dispositif : dommages-intérêts (jugement civil)', 'dispositif', $this->blocDiDispositif()],
            ['civ_dispo_fin_fond', 'Dispositif : surplus et exécution provisoire (jugement civil)', 'dispositif', $this->blocDispoFinFond()],
            ['civ_dispo_depens', 'Dispositif : dépens (jugement civil)', 'dispositif', $this->blocDispoDepens()],
            ['civ_add_dispositif', 'Dispositif : avant-dire-droit, expertise (jugement civil)', 'dispositif', $this->blocAddDispositif()],
        ];
    }

    private function blocParties(): string
    {
        $cote = fn (string $partie, string $role, string $fin) => implode('', [
            $this->p('{{ '.$partie.'|parties_qualites }}'),
            $this->droite('{{ '.$partie.'|accord:'.$role.'|majuscule }}'),
            $this->si($partie.'_representation', $this->p('{{ '.$partie.'_representation|paragraphes }}')),
            $this->p('{{ '.$partie.'_comparution }} ;'),
            $this->droite($fin),
        ]);

        return implode('', [
            $this->t('ENTRE :'),
            $cote('demandeurs', 'demandeur', "D'UNE PART"),
            '<p><strong>ET</strong></p>'.self::V,
            $cote('defendeurs', 'défendeur', "D'AUTRE PART"),
        ]);
    }

    private function blocFaitsDroit(): string
    {
        return implode('', [
            $this->p("<strong><u>FAITS</u> :</strong> Par exploit en date du {{ date_exploit|date_courte }} de {{ commissaire_justice }}, {{ demandeurs|parties_noms }} {{ demandeurs|sg_pl:a,ont }} assigné {{ defendeurs|parties_noms }}, à comparaître devant la {{ chambre }}{{#si chambre_specialite}}, la {{ chambre_specialite }}{{/si}} du Tribunal de ce siège, à l'audience du {{ date_premiere_evocation|date_chiffres }}, pour entendre :"),
            $this->pn('{{ demandes|lignes }}').self::V,
            $this->p("Sur cette assignation, la cause a été inscrite au Rôle Général du Greffe du Tribunal sous le numéro {{ numero_rg|numero }} de l'année {{ date_rg|annee }} ;"),
            $this->p("<strong><u>DROIT</u></strong> : En cet état, la cause présentait à juger les points de droit résultant des pièces, des conclusions écrites et orales des parties ;"),
            $this->p("L'affaire a été distribuée à la {{#si formation}}{{ formation }} de la {{/si}}{{ chambre }}{{#si chambre_specialite}}, la {{ chambre_specialite }}{{/si}} ;"),
            $this->alt(
                'deroulement_libre',
                $this->p('{{ deroulement_libre|paragraphes }}'),
                $this->p("A l'audience du {{ date_premiere_evocation|date_courte }}, première date d'évocation, la cause a été renvoyée au {{ date_premier_renvoi|date_courte }} {{ motif_premier_renvoi }} ; à partir de cette date d'audience, la cause a connu plusieurs autres renvois avant d'être mise en délibéré pour l'audience du {{ date_audience|date_courte }} ;"),
            ),
            $this->p("Advenue l'audience de ce jour, {{ date_audience|date_chiffres }}, le Tribunal, vidant son délibéré conformément à la loi, a rendu le jugement suivant :"),
        ]);
    }

    private function blocVisas(): string
    {
        return implode('', [
            '<p><strong><u>LE TRIBUNAL,</u></strong></p>'.self::V,
            $this->p('Vu les pièces du dossier de la procédure ;'),
            $this->p('Ouï les parties en leurs fins, demandes et conclusions ;'),
            $this->si('mp_date_conclusions', $this->p('Vu les conclusions écrites du Ministère Public du {{ mp_date_conclusions|date_courte }} ;')),
            $this->p('Et après en avoir délibéré conformément à la loi ;'),
        ]);
    }

    private function blocFaitsPretentions(): string
    {
        return implode('', [
            $this->t('FAITS, PROCEDURE, PRETENTIONS ET MOYENS DES PARTIES'),
            $this->p("Par exploit de commissaire de justice en date du {{ date_exploit|date_courte }}, {{ demandeurs|parties_noms }} {{ demandeurs|sg_pl:a,ont }} assigné {{ defendeurs|parties_noms }}, à comparaître devant le Tribunal civil de ce siège, pour entendre :"),
            $this->pn('{{ demandes|tirets }}').self::V,
            $this->p('{{ expose_demandeur|paragraphes }}'),
            $this->alt(
                'moyens_defense',
                $this->p('{{ moyens_defense|paragraphes }}'),
                $this->si('defendeurs_comparution = Ne comparaissant pas', $this->p("{{ defendeurs|accord:le|initiale }} {{ defendeurs|accord:défendeur }} {{ defendeurs|sg_pl:n'a,n'ont }} ni conclu ni comparu pour faire valoir {{ defendeurs|sg_pl:ses,leurs }} moyens de défense ;")),
            ),
            $this->si('mp_sens_conclusions', $this->p("Conformément à l'article 106 du code de procédure civile, commerciale et administrative, la cause a été communiquée au Ministère Public, qui a conclu qu'il plaise au tribunal {{ mp_sens_conclusions }} ;")),
        ]);
    }

    private function blocForme(): string
    {
        return implode('', [
            $this->t('EN LA FORME'),
            $this->t('Sur le caractère de la décision'),
            // Le motif se déduit de la comparution et de la nature de la décision ; « caractere_motif » (options) le remplace si besoin.
            $this->p("Attendu que {{ defendeurs|parties_noms }} {{#si caractere_motif}}{{ caractere_motif }}{{sinon}}{{#si nature_decision = par défaut}}n'ayant ni comparu ni conclu, il n'est pas certain qu'{{ defendeurs|pronom }} {{ defendeurs|sg_pl:ait,aient }} eu connaissance de la présente procédure{{sinon}}{{#si defendeurs_comparution = Comparaissant}}{{ defendeurs|sg_pl:a,ont }} eu connaissance de la présente procédure pour avoir comparu et conclu{{sinon}}{{ defendeurs|sg_pl:a,ont }} eu connaissance de la présente procédure pour avoir été {{ defendeurs|accord:assigné }} à personne{{/si}}{{/si}}{{/si}} ;"),
            $this->p("Qu'il y a lieu de statuer {{#si nature_decision = par défaut}}par défaut{{sinon}}{{#si nature_decision = réputé contradictoire}}par décision réputée contradictoire{{sinon}}par décision contradictoire{{/si}}{{/si}} ;"),
            $this->t("Sur la recevabilité de l'action"),
            $this->alt(
                'fnr_objet',
                implode('', [
                    '<p>{{#exige fnr_issue}}</p>',
                    $this->p('Attendu que {{ fnr_auteur }} soulève la fin de non-recevoir tirée de {{ fnr_objet }} ;'),
                    $this->si('fnr_rappel = article 1351 (chose jugée)', $this->p("Attendu qu'aux termes de l'article 1351 du code civil, « l'autorité de la chose jugée n'a lieu qu'à l'égard de ce qui a fait l'objet du jugement. Il faut que la chose demandée soit la même ; que la demande soit fondée sur la même cause ; que la demande soit entre les mêmes parties, et formée par elles et contre elles en la même qualité » ;")),
                    $this->si('fnr_rappel = article 3 (qualité et intérêt à agir)', $this->p("Attendu qu'aux termes de l'article 3 du code de procédure civile, commerciale et administrative, l'action n'est recevable que si le demandeur justifie d'un intérêt légitime juridiquement protégé, direct et personnel, a la qualité pour agir en justice et possède la capacité d'agir en justice ;")),
                    $this->p('{{ fnr_motifs|paragraphes }}'),
                    $this->si('fnr_issue = rejetée', $this->p("Qu'il convient, en conséquence, de rejeter la fin de non-recevoir tirée de {{ fnr_objet }} et de déclarer l'action de {{ demandeurs|parties_noms }} recevable, pour avoir été introduite dans le respect des exigences légales de forme et de délai ;")),
                    $this->si('fnr_issue = accueillie', $this->p("Qu'il convient, en conséquence, d'accueillir la fin de non-recevoir tirée de {{ fnr_objet }} et de déclarer l'action de {{ demandeurs|parties_noms }} irrecevable ;")),
                ]),
                $this->p("Attendu que l'action de {{ demandeurs|parties_noms }} a été exercée dans le respect des exigences légales de forme et de délai ;").$this->p("Qu'il sied de la déclarer recevable ;"),
            ),
            $this->si('incidents', $this->p('{{ incidents|sections }}')),
            $this->si(
                'reconventionnelle = oui',
                $this->t('Sur la recevabilité de la demande reconventionnelle'),
                $this->p("Attendu que la demande reconventionnelle de {{ defendeurs|parties_noms }} a été formulée conformément aux prescriptions des articles 101 et 103 du code de procédure civile, commerciale et administrative, étant connexe à l'action principale et lui servant de défense ;"),
                $this->p("Qu'elle est également recevable ;"),
            ),
        ]);
    }

    private function blocDommagesInterets(): string
    {
        return $this->si(
            'di',
            $this->si(
                'di != non demandés',
                $this->t('Sur la demande en paiement de dommages-intérêts'),
                $this->p('Attendu que {{ demandeurs|parties_noms }} {{ demandeurs|sg_pl:sollicite,sollicitent }} la condamnation de {{ defendeurs|parties_noms }} à {{ demandeurs|sg_pl:lui,leur }} payer la somme de {{ di_demande|montant }} à titre de dommages-intérêts ;'),
                $this->si('di_fondement = inexécution du contrat (article 1184)', $this->p("Attendu qu'au sens de l'article 1184 du code civil, la partie envers laquelle l'engagement n'a point été exécuté peut, outre la résolution du contrat, demander des dommages et intérêts ;")),
                $this->si('di_fondement = responsabilité délictuelle (article 1382)', $this->p("Attendu qu'il ressort des dispositions de l'article 1382 du code civil que la responsabilité civile délictuelle suppose pour sa mise en œuvre la réunion d'une faute, d'un préjudice et d'un lien de causalité entre la faute et le préjudice ;")),
                $this->si('di_fondement = retard de paiement (article 1153)', $this->p("Mais attendu qu'aux termes de l'article 1153 du Code civil, les dommages-intérêts résultant du retard dans l'exécution d'une obligation portant sur le paiement d'une somme d'argent consistent, en principe, dans la condamnation aux intérêts fixés par la loi ;")),
                $this->si('di_motifs', $this->p('{{ di_motifs|paragraphes }}')),
                $this->si('di = accordés', $this->p("Qu'il y a lieu de faire droit à cette demande et de condamner {{ defendeurs|parties_noms }} à payer à {{ demandeurs|parties_noms }} la somme de {{ di_demande|montant }} à titre de dommages-intérêts ;")),
                $this->si('di = accordés en partie', $this->p("Que le montant de {{ di_demande|montant }} réclamé apparaît excessif ;"), $this->p("Qu'il y a lieu, accueillant partiellement ce chef de demande, de le ramener à de justes proportions en l'arbitrant à {{ di_alloue|montant }} et de condamner {{ defendeurs|parties_noms }} à payer ladite somme à {{ demandeurs|parties_noms }} à titre de dommages-intérêts ;")),
                $this->si('di = rejetés', $this->p("Qu'il s'ensuit que la demande en paiement de dommages-intérêts n'est pas fondée et qu'il y a lieu de la rejeter ;")),
            ),
        );
    }

    private function blocAddMotifs(): string
    {
        return implode('', [
            $this->t('Avant-dire-droit'),
            $this->p('{{ motifs_expertise|paragraphes }}'),
            $this->p("Attendu qu'il résulte de la combinaison des dispositions des articles 65, 67 et 140 du Code de procédure civile, commerciale et administrative, que le tribunal peut, par jugement avant-dire-droit, ordonner une mesure d'instruction, lorsqu'il estime exceptionnellement devoir y recourir s'il est confronté à des questions techniques ;"),
            $this->p("Qu'il convient, avant tout examen au fond, pour la manifestation de la vérité, dans l'intérêt d'une bonne administration de la justice, d'ordonner une expertise {{ nature_expertise }} et de désigner pour y procéder {{ expert_nom }}, {{ expert_qualite }} ;"),
            $this->p("Attendu que l'article 67 in fine du code de procédure civile, commerciale et administrative dispose que « La partie qui sollicite l'expertise est tenue de faire l'avance des frais. » ;"),
            $this->p("Qu'il convient, conformément audit texte, de mettre l'avance des frais d'expertise à la charge de {{ expertise_frais_charge }}, qui a sollicité cette mesure ;"),
            $this->t('Sur les dépens'),
            $this->p('Attendu que la procédure suit son cours ; qu\'il y a lieu de réserver les dépens ;'),
        ]);
    }

    private function blocExecutionProvisoire(): string
    {
        return implode('', [
            $this->si(
                'execution_provisoire = ordonnée',
                $this->t("Sur l'exécution provisoire"),
                $this->p("Attendu que, selon l'article 145 du code de procédure civile, commerciale et administrative, « Outre les cas où elle est prescrite par la loi, et sauf dispositions contraires de celle-ci, l'exécution provisoire doit être ordonnée d'office nonobstant opposition ou appel, s'il y a titre authentique ou privé non contesté, aveu ou promesse reconnue. » ;"),
                $this->p('Attendu que {{ ep_motif }} ;'),
                $this->p("Qu'il y a lieu d'assortir la présente décision de l'exécution provisoire{{#si ep_portee}} {{ ep_portee }}{{/si}} ;"),
            ),
            $this->si(
                'execution_provisoire = refusée',
                $this->t("Sur l'exécution provisoire"),
                $this->p("Attendu que {{ demandeurs|parties_noms }} {{ demandeurs|sg_pl:sollicite,sollicitent }} l'exécution provisoire de la présente décision ;"),
                $this->alt(
                    'ep_refus_motif',
                    $this->p('{{ ep_refus_motif|paragraphes }}'),
                    $this->p("Attendu qu'aux termes de l'article 146 du Code de procédure civile, commerciale et administrative, l'exécution provisoire peut être ordonnée, sur demande, dans les cas présentant un caractère d'extrême urgence ;").$this->p("Qu'en l'espèce, aucune circonstance particulière de nature à établir le caractère d'extrême urgence de la demande n'est invoquée, ni aucun élément justifiant la nécessité d'une exécution immédiate de la décision ;"),
                ),
                $this->p("Qu'il convient, dès lors, de rejeter la demande d'exécution provisoire ;"),
            ),
        ]);
    }

    private function blocDepens(): string
    {
        return implode('', [
            $this->t('Sur les dépens'),
            '<p>{{#exige depens}}</p>',
            $this->si('depens = à la charge du défendeur', $this->p('Attendu que {{ defendeurs|parties_noms }} {{ defendeurs|sg_pl:succombe,succombent }} ;'), $this->p("Qu'il y a lieu de mettre les entiers dépens de l'instance à {{ defendeurs|sg_pl:sa,leur }} charge ;")),
            $this->si('depens = à la charge du demandeur', $this->p('Attendu que {{ demandeurs|parties_noms }} {{ demandeurs|sg_pl:succombe,succombent }} ;'), $this->p("Qu'il y a lieu de mettre les entiers dépens de l'instance à {{ demandeurs|sg_pl:sa,leur }} charge ;")),
            $this->si('depens = réservés', $this->p("Attendu que la procédure suit son cours ; qu'il y a lieu de réserver les dépens ;")),
        ]);
    }

    private function blocDispoDebut(): string
    {
        return implode('', [
            $this->t('PAR CES MOTIFS'),
            $this->p('Statuant publiquement, {{ nature_decision|adverbe }}, en matière civile et en premier ressort ;'),
            $this->p('En la forme'),
            $this->si('fnr_issue = rejetée', $this->p('Rejette la fin de non-recevoir tirée de {{ fnr_objet }} soulevée par {{ fnr_auteur }} ;')),
            $this->si('incidents', $this->p('{{ incidents|dispositifs }}')),
            $this->alt(
                'fnr_issue = accueillie',
                $this->p("Déclare irrecevable l'action de {{ demandeurs|parties_noms }} ;"),
                $this->p('Déclare {{ demandeurs|parties_noms }} {{ demandeurs|accord:recevable }} en {{ demandeurs|sg_pl:son,leur }} action ;'),
            ),
            $this->si('reconventionnelle = oui', $this->p('Déclare {{ defendeurs|parties_noms }} {{ defendeurs|accord:recevable }} en {{ defendeurs|sg_pl:sa,leur }} demande reconventionnelle ;')),
        ]);
    }

    private function blocDispoIssue(): string
    {
        return implode('', [
            '<p>{{#exige issue_fond}}</p>',
            $this->si('issue_fond = bien fondé', $this->p('Dit {{ demandeurs|parties_noms }} bien {{ demandeurs|accord:fondé }} en {{ demandeurs|sg_pl:son,leur }} action ;')),
            $this->si('issue_fond = partiellement fondé', $this->p('Dit {{ demandeurs|parties_noms }} partiellement {{ demandeurs|accord:fondé }} en {{ demandeurs|sg_pl:son,leur }} action ;')),
            $this->si(
                'issue_fond = mal fondé',
                $this->p('Dit {{ demandeurs|parties_noms }} mal {{ demandeurs|accord:fondé }} en {{ demandeurs|sg_pl:son,leur }} action ;'),
                $this->p('Déboute {{ demandeurs|parties_noms }} de {{ demandeurs|sg_pl:sa,leur }} demande ;'),
            ),
        ]);
    }

    private function blocDiDispositif(): string
    {
        return implode('', [
            $this->si('di = accordés', $this->p('Condamne {{ defendeurs|parties_noms }} à payer à {{ demandeurs|parties_noms }} la somme de {{ di_demande|montant }} à titre de dommages-intérêts ;')),
            $this->si('di = accordés en partie', $this->p('Condamne {{ defendeurs|parties_noms }} à payer à {{ demandeurs|parties_noms }} la somme de {{ di_alloue|montant }} à titre de dommages-intérêts ;')),
        ]);
    }

    private function blocDispoFinFond(): string
    {
        return implode('', [
            $this->si('complement_dispositif', $this->p('{{ complement_dispositif|paragraphes }}')),
            $this->si('issue_fond = partiellement fondé', $this->p('Déboute {{ demandeurs|parties_noms }} du surplus de {{ demandeurs|sg_pl:ses,leurs }} prétentions ;')),
            $this->si('execution_provisoire = ordonnée', $this->p("Ordonne l'exécution provisoire de la présente décision{{#si ep_portee}} {{ ep_portee }}{{/si}} ;")),
            $this->si('execution_provisoire = refusée', $this->p("Dit n'y avoir lieu à exécution provisoire ;")),
        ]);
    }

    private function blocDispoDepens(): string
    {
        return implode('', [
            $this->si('depens = à la charge du défendeur', $this->p("Condamne {{ defendeurs|parties_noms }} aux entiers dépens de l'instance{{#si depens_distraction}}, distraits au profit de {{ depens_distraction }}, aux offres de droit{{/si}} ;")),
            $this->si('depens = à la charge du demandeur', $this->p("Condamne {{ demandeurs|parties_noms }} aux entiers dépens de l'instance{{#si depens_distraction}}, distraits au profit de {{ depens_distraction }}, aux offres de droit{{/si}} ;")),
            $this->si('depens = réservés', $this->p('Réserve les dépens ;')),
        ]);
    }

    private function blocAddDispositif(): string
    {
        return implode('', [
            $this->p('Avant-dire-droit'),
            $this->p('Ordonne une expertise {{ nature_expertise }}, à l\'effet de :'),
            $this->pn('{{ mission_expertise|tirets }}').self::V,
            $this->p('Désigne pour y procéder {{ expert_nom }}, {{ expert_qualite }} ;'),
            $this->p("Lui impartit un délai de {{ delai_expertise|nombre_chiffres:jour }} pour réaliser sa mission et déposer son rapport au Greffe du Tribunal de céans, et ce, à compter de la notification à lui faite du présent jugement ;"),
            $this->p("Met l'avance des frais de l'expertise à la charge de {{ expertise_frais_charge }} ;"),
            $this->p("Dit que l'expert susnommé procédera à sa mission sous le contrôle de {{ juge_controleur }}, Juge au Tribunal de ce siège ;"),
            $this->p("Réserve l'examen des demandes ;"),
            $this->p('Réserve les dépens ;'),
            $this->p('Renvoie la cause et les parties à l\'audience du {{ date_renvoi_expertise|date_courte }} pour le dépôt du rapport d\'expertise ;'),
        ]);
    }

    /* ---------------------------------------------------------------- trame commune des trois modèles */

    private function entete(): string
    {
        return implode('', [
            "<p>MINISTERE DE LA JUSTICE ET DES DROITS DE L'HOMME</p>",
            "<p>{{ cour_appel|majuscule }}</p>",
            '<p>{{> trait_pointille }}</p>',
            '<p>{{ juridiction|majuscule }}</p>',
            '<p><br></p>',
            '<p>R.G N° {{ numero_rg|numero }} du {{ date_rg|date_numerique }}</p>',
            '<p>{{> trait_plein }}</p>',
            '<p>{{ chambre|majuscule }}</p>',
            $this->si('chambre_specialite', '<p>{{ chambre_specialite|majuscule }}</p>'),
            $this->si('formation', '<p>{{ formation|majuscule }}</p>'),
            '<p>JUGEMENT CIVIL {{#si type_jugement = avant-dire-droit}}ADD{{sinon}}{{#si nature_decision = par défaut}}DE DEFAUT{{sinon}}{{ nature_decision|majuscule }}{{/si}}{{/si}}</p>',
            '<p>N° {{ numero_decision }} du {{ date_decision|date_numerique }}</p>',
            '<p>AFFAIRE</p>',
            '<p>{{ demandeurs|parties_timbre }}</p>',
            $this->si('demandeurs_conseil', '<p>({{ demandeurs_conseil }})</p>'),
            '<p>C/</p>',
            '<p>{{ defendeurs|parties_timbre }}</p>',
            $this->si('defendeurs_conseil', '<p>({{ defendeurs_conseil }})</p>'),
        ]);
    }

    private function qualite(): string
    {
        $v = self::V;

        return implode('', [
            '<p class="ql-align-center"><strong>REPUBLIQUE DE COTE D\'IVOIRE</strong></p>',
            '<p class="ql-align-center"><strong>Union - Discipline – Travail</strong></p>', $v,
            '<p class="ql-align-center"><strong><u>AUDIENCE PUBLIQUE</u></strong></p>',
            '<p class="ql-align-center"><strong><u>DU {{ date_audience|date_chiffres|majuscule }}</u></strong></p>', $v,
            $this->p('Le {{ juridiction }}, statuant en matière {{ matiere }}, en son audience publique{{#si type_audience != ordinaire}} {{ type_audience }}{{/si}} du {{ date_audience|date }}, tenue au palais de justice de ladite ville à laquelle siégeaient :'),
            '<p><strong>{{ president|personne }}, {{ president.fonction }} ;</strong></p>',
            '<p class="ql-align-right"><strong>PRESIDENT ;</strong></p>', $v,
            '<p><strong>{{ assesseurs|personnes }}, {{ assesseurs|sg_pl:Juge,Juges }} ;</strong></p>',
            '<p class="ql-align-right"><strong>ASSESSEURS ;</strong></p>', $v,
            $this->p('{{ greffier.genre|accord:Assisté }} de {{ greffier|personne:Maître }}, {{ greffier.genre|accord:Greffier }} ;'),
            $this->bloc('civ_formule_jugement'), $v,
            $this->bloc('civ_parties'),
            $this->bloc('reserves_qualites'), $v,
            $this->bloc('civ_faits_droit'),
        ]);
    }

    /** Le factum : la trame commune, avec les motifs, puis le dispositif propres au modèle. */
    private function factum(string $motifsFond, string $dispositifFond): string
    {
        return implode('', [
            $this->bloc('civ_visas'),
            $this->bloc('civ_faits_pretentions'),
            $this->t('DES MOTIFS'),
            $this->bloc('civ_forme'),
            $this->alt(
                'type_jugement = avant-dire-droit',
                $this->bloc('civ_add_motifs'),
                $this->si('fnr_issue != accueillie', $this->t('AU FOND'), $motifsFond, $this->bloc('civ_di'), $this->si('sections_fond', $this->p('{{ sections_fond|sections }}')), $this->bloc('civ_execution_provisoire')).$this->bloc('civ_depens'),
            ),
            $this->bloc('civ_dispo_debut'),
            $this->alt(
                'type_jugement = avant-dire-droit',
                $this->p('Au fond').$this->bloc('civ_add_dispositif'),
                $this->si('fnr_issue != accueillie', $this->p('Au fond'), $this->bloc('civ_dispo_issue'), $dispositifFond, $this->bloc('civ_di_dispositif'), $this->bloc('civ_dispo_fin_fond')).$this->bloc('civ_dispo_depens'),
            ),
            $this->bloc('cloture_jugement'),
        ]);
    }

    /* ---------------------------------------------------------------- modèle : paiement et contrats */

    private function factumPaiement(): string
    {
        $motifs = implode('', [
            $this->si(
                'resolution = prononcée',
                $this->t('Sur la demande de résolution du contrat'),
                $this->p("Attendu que l'article 1184 du code civil dispose que « La condition résolutoire est toujours sous entendue dans les contrats synallagmatiques pour le cas où l'une des deux parties ne satisfera point à son engagement. Dans ce cas, le contrat n'est point résolu de plein droit. La partie envers laquelle l'engagement n'a point été exécuté, a le choix ou de forcer l'autre à l'exécution de la convention lorsqu'elle est possible, ou d'en demander la résolution avec des dommages et intérêts. » ;"),
                $this->p("Attendu que le contrat est un accord de volontés par lequel les parties déterminent les effets du lien juridique qu'elles créent, de sorte qu'ayant librement consenti à s'obliger, elles sont tenues de s'exécuter de bonne foi conformément aux termes de leur engagement, à défaut de quoi la possibilité est ouverte à la partie victime de demander la résolution dudit contrat en justice ;"),
                $this->p('{{ resolution_motifs|paragraphes }}'),
                $this->p("Qu'il convient donc de déclarer la demande en résolution bien fondée et d'y faire droit, l'inexécution reprochée à {{ defendeurs|parties_noms }} étant suffisamment grave ;"),
                $this->p("Attendu qu'aux termes de l'article 1183, alinéa 1 du code civil, « La condition résolutoire est celle qui, lorsqu'elle s'accompli, opère la révocation de l'obligation, et qui remet les choses au même état que si l'obligation n'avait pas existé » ;"),
                $this->p("Qu'il en résulte que la résolution d'un contrat synallagmatique a pour effet de l'anéantir rétroactivement, comme s'il n'avait jamais été conclu, et de remettre les choses dans leur état initial, de telle manière que les parties se doivent la restitution des prestations réciproques ;"),
            ),
            $this->si(
                'resolution = rejetée',
                $this->t('Sur la demande de résolution du contrat'),
                $this->p('{{ resolution_motifs|paragraphes }}'),
                $this->p('Qu\'il convient de rejeter la demande en résolution du {{ contrat_resolu }} ;'),
            ),
            '<p>{{#exige paiement}}</p>',
            $this->t('Sur la demande en paiement'),
            $this->p('Attendu que {{ demandeurs|parties_noms }} {{ demandeurs|sg_pl:réclame,réclament }} la condamnation de {{ defendeurs|parties_noms }} à {{ demandeurs|sg_pl:lui,leur }} payer la somme de {{ montant_reclame|montant }}, {{ objet_creance }} ;'),
            $this->si(
                'loyers_bail = oui',
                $this->p("Attendu qu'aux termes de l'article 435 de la loi n° 2019-576 du 26 juin 2019 portant Code de la construction et de l'habitat, « Le locataire est tenu d'utiliser l'immeuble conformément aux stipulations du contrat de bail et de payer le loyer convenu. » ;"),
                $this->p("Qu'il résulte également des dispositions de l'article 425 du même Code qu'il appartient au locataire de rapporter la preuve du paiement des loyers dont il est redevable ;"),
            ),
            $this->si('rappel_1315 = oui', $this->p("Que selon l'article 1315 du code civil, s'il appartient au débiteur de justifier de son paiement, c'est d'abord à celui qui réclame l'exécution d'une obligation d'en prouver l'existence ;")),
            $this->p('{{ paiement_motifs|paragraphes }}'),
            $this->alt(
                'paiement = rejeté',
                $this->p("Qu'il convient, en conséquence, de rejeter la demande en paiement ;"),
                $this->p("Qu'il convient de condamner {{ defendeurs|parties_noms }} à payer à {{ demandeurs|parties_noms }} la somme de {{#si montant_alloue}}{{ montant_alloue|montant }}{{sinon}}{{ montant_reclame|montant }}{{/si}} ;"),
            ),
            $this->si(
                'grace = accordé',
                $this->t('Sur la demande de délai de grâce'),
                $this->p('Attendu que {{ defendeurs|parties_noms }} {{ defendeurs|sg_pl:sollicite,sollicitent }} un délai de grâce de {{ grace_mois|nombre_chiffres:mois }} pour s\'acquitter du solde de {{ defendeurs|sg_pl:sa,leur }} dette ;'),
                $this->p("Attendu qu'aux termes de l'article 1244 alinéa 2 du code civil, « les juges peuvent néanmoins, en considération de la position du débiteur et en usant de ce pouvoir avec une grande réserve, accorder des délais modérés pour le payement, et surseoir à l'exécution des poursuites, toutes choses demeurant en état » ;"),
                $this->p('{{ grace_motifs|paragraphes }}'),
                $this->p("Qu'ainsi, au regard de la situation {{ defendeurs|sg_pl:du débiteur,des débiteurs }} et des besoins {{ demandeurs|sg_pl:du créancier,des créanciers }}, il y a lieu de faire droit à la demande de délai de grâce et d'accorder à {{ defendeurs|parties_noms }} un délai de {{ grace_mois|nombre_chiffres:mois }}, à compter de la signification de la présente décision, pour s'acquitter du reliquat de {{ defendeurs|sg_pl:sa,leur }} dette ;"),
            ),
            $this->si(
                'grace = refusé',
                $this->t('Sur la demande de délai de grâce'),
                $this->p('{{ grace_motifs|paragraphes }}'),
                $this->p('Qu\'il convient de rejeter la demande de délai de grâce ;'),
            ),
        ]);
        $dispositif = implode('', [
            $this->si('resolution = prononcée', $this->p('Prononce la résolution du {{ contrat_resolu }} liant les parties ;')),
            $this->si('resolution = rejetée', $this->p('Rejette la demande en résolution du {{ contrat_resolu }} ;')),
            $this->si(
                'paiement = accordé',
                $this->p('Condamne {{ defendeurs|parties_noms }} à payer à {{ demandeurs|parties_noms }} la somme de {{ montant_reclame|montant }}{{#si paiement_titre}} {{ paiement_titre }}{{/si}} ;'),
            ),
            $this->si(
                'paiement = accordé en partie',
                $this->p('Condamne {{ defendeurs|parties_noms }} à payer à {{ demandeurs|parties_noms }} la somme de {{ montant_alloue|montant }}{{#si paiement_titre}} {{ paiement_titre }}{{/si}} ;'),
            ),
            $this->si('paiement = rejeté', $this->p('Rejette la demande en paiement ;')),
            $this->si(
                'grace = accordé',
                $this->p('Dit {{ defendeurs|parties_noms }} bien {{ defendeurs|accord:fondé }} en {{ defendeurs|sg_pl:sa,leur }} demande de délai de grâce ;'),
                $this->p('Accorde à {{ defendeurs|parties_noms }} un délai de grâce de {{ grace_mois|nombre_chiffres:mois }}, à compter de la signification du présent jugement, pour s\'acquitter du reliquat de {{ defendeurs|sg_pl:sa,leur }} dette ;'),
            ),
            $this->si('grace = refusé', $this->p('Rejette la demande de délai de grâce ;')),
        ]);

        return $this->factum($motifs, $dispositif);
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: mixed}> */
    private function variablesPaiement(): array
    {
        return [
            ['paiement', 'Demande en paiement', 'texte', 'accordé'],
            ['objet_creance', "Ce que la somme représente (ex. : solde d'un prêt consenti pour un billet d'avion)", 'texte', "représentant le reliquat du prix d'un billet d'avion avancé à la défenderesse"],
            ['montant_reclame', 'Somme réclamée (francs CFA)', 'nombre', '445000'],
            ['montant_alloue', "Somme accordée, si elle diffère de la somme réclamée (francs CFA)", 'nombre', null],
            ['paiement_titre', 'Au dispositif : à quel titre la somme est due (ex. : au titre des loyers échus et impayés)', 'texte', 'à titre de remboursement du montant avancé pour le billet d\'avion'],
            ['paiement_motifs', 'Paiement : preuves et raisonnement du tribunal (un paragraphe par ligne)', 'paragraphes', "Attendu que les allégations du demandeur sont étayées en l'espèce par la production du reçu d'achat du billet d'avion et par l'exploit de sommation interpellative, par suite duquel la défenderesse lui a réglé une partie de la somme réclamée ;\nQu'il y a lieu de dire que les pièces produites établissent l'existence de la créance ;"],
            ['rappel_1315', "Rappeler l'article 1315 du code civil (charge de la preuve)", 'texte', 'oui'],
            ['loyers_bail', 'Loyers : rappeler les articles 435 et 425 (Code de la construction et de l\'habitat)', 'texte', 'non'],
            ['resolution', 'Demande en résolution du contrat', 'texte', 'non demandée'],
            ['contrat_resolu', 'Contrat résolu (ex. : contrat de réservation du 07 juillet 2020)', 'texte', 'contrat de réservation du 07 juillet 2020'],
            ['resolution_motifs', 'Résolution : inexécution et raisonnement du tribunal (un paragraphe par ligne)', 'paragraphes', null],
            ['grace', 'Délai de grâce (article 1244 du code civil)', 'texte', 'non demandé'],
            ['grace_mois', 'Délai de grâce : nombre de mois', 'nombre', '12'],
            ['grace_motifs', 'Délai de grâce : situation du débiteur et bonne foi (un paragraphe par ligne)', 'paragraphes', null],
        ];
    }

    /* ---------------------------------------------------------------- modèle : foncier */

    private function factumFoncier(): string
    {
        $motifs = implode('', [
            $this->si(
                'propriete = accordée',
                $this->t('Sur la demande en revendication de propriété'),
                $this->p("Attendu qu'aux termes de l'article 220 de la loi n° 2020-624 du 14 août 2020 instituant Code de l'urbanisme et du domaine foncier urbain, la pleine propriété des terrains urbains immatriculés au nom de l'État est conférée par un arrêté de concession définitive délivré par le ministre chargé de la Construction et de l'Urbanisme ;"),
                $this->p('Que pour revendiquer la propriété de {{ designation_parcelle }}, {{ demandeurs|parties_noms }} {{ demandeurs|sg_pl:produit,produisent }} {{ titre_propriete }}, délivré à {{ demandeurs|sg_pl:son,leur }} profit ;'),
                $this->si('propriete_motifs', $this->p('{{ propriete_motifs|paragraphes }}')),
                $this->p("Qu'il convient, dès lors, accueillant la demande de revendication de la propriété de la parcelle sus indiquée, de déclarer {{ demandeurs|parties_noms }} {{ demandeurs|accord:propriétaire }} de cette parcelle conformément au texte précité ;"),
            ),
            $this->si(
                'propriete = rejetée',
                $this->t('Sur la demande en revendication de propriété'),
                $this->p("Attendu que {{ demandeurs|parties_noms }} {{ demandeurs|sg_pl:sollicite,sollicitent }} qu'il plaise au tribunal de dire {{ demandeurs|pronom }} {{ demandeurs|accord:propriétaire }} de {{ designation_parcelle }} ;"),
                $this->p('{{ propriete_motifs|paragraphes }}'),
                $this->p('Qu\'il convient en conséquence de rejeter cette demande comme mal fondée ;'),
            ),
            $this->si(
                'deguerpissement = ordonné',
                $this->t('Sur la demande en déguerpissement'),
                $this->p("Attendu que {{ demandeurs|parties_noms }} {{ demandeurs|sg_pl:sollicite,sollicitent }} le déguerpissement de {{ defendeurs|parties_noms }} et de tous occupants de {{ defendeurs|sg_pl:son,leur }} chef de la parcelle litigieuse, au motif qu'{{ defendeurs|pronom }} {{ defendeurs|sg_pl:l'occupe,l'occupent }} sans titre ni droit ;"),
                $this->p("Attendu que selon l'article 544 du code civil, la propriété est le droit de jouir et de disposer des choses de la manière la plus absolue, pourvu qu'on n'en fasse pas un usage prohibé par les lois ou par les règlements ;"),
                $this->p("Qu'il est constant que l'occupation de la parcelle par {{ defendeurs|parties_noms }}{{#si occupation_constat}}, {{ occupation_constat }},{{/si}} sans qu'{{ defendeurs|pronom }} {{ defendeurs|sg_pl:puisse,puissent }} justifier d'un titre ni d'un droit l'y habilitant, constitue une atteinte au droit de propriété de {{ demandeurs|parties_noms }}, en {{ demandeurs|sg_pl:l'empêchant,les empêchant }} de jouir librement de {{ demandeurs|sg_pl:sa,leur }} parcelle ;"),
                $this->si('deguerpissement_motifs', $this->p('{{ deguerpissement_motifs|paragraphes }}')),
                $this->p("Qu'il convient donc, en vue de rétablir {{ demandeurs|parties_noms }} dans l'exercice de {{ demandeurs|sg_pl:son,leur }} droit de propriété, de dire bien fondée la demande en déguerpissement dirigée contre {{ defendeurs|parties_noms }} et d'ordonner {{ defendeurs|sg_pl:son,leur }} déguerpissement ainsi que celui de tout occupant de {{ defendeurs|sg_pl:son,leur }} chef de la parcelle litigieuse ;"),
            ),
            $this->si(
                'deguerpissement = rejeté',
                $this->t('Sur la demande en déguerpissement'),
                $this->p('{{ deguerpissement_motifs|paragraphes }}'),
                $this->p('Qu\'il convient en conséquence de rejeter la demande en déguerpissement ;'),
            ),
            $this->si(
                'demolition = ordonnée',
                $this->t('Sur la demande de démolition des constructions'),
                $this->p("Attendu qu'il résulte de l'économie des dispositions de l'article 555 du Code civil que la démolition des constructions érigées sur une parcelle de terrain par un tiers ne peut être fondée que sous la réunion d'une double condition, à savoir, que celui qui formule une telle demande soit le propriétaire du fonds concerné et qu'en outre, le constructeur soit de mauvaise foi ;"),
                $this->p("Attendu que la bonne foi s'entend par référence à l'article 550 du Code civil et vise le possesseur qui possède comme propriétaire, en vertu d'un titre translatif de propriété dont il ignore les vices ;"),
                $this->p("Qu'en l'espèce, {{ defendeurs|parties_noms }}, qui ne {{ defendeurs|sg_pl:détient,détiennent }} ni titre translatif de propriété ni titre putatif dont {{ defendeurs|pronom }} ignorai{{ defendeurs|sg_pl:t,ent }} les vices, {{ defendeurs|sg_pl:a,ont }} néanmoins édifié {{ constructions }} sur la parcelle appartenant à {{ demandeurs|parties_noms }} ;"),
                $this->si('demolition_motifs', $this->p('{{ demolition_motifs|paragraphes }}')),
                $this->p("Qu'il y a lieu de juger {{ defendeurs|parties_noms }} {{ defendeurs|accord:constructeur }} de mauvaise foi et, par application de l'article 555 du code civil, sur les mêmes motifs que ceux retenus pour le déguerpissement, d'ordonner la démolition de {{ constructions }}, aux frais de {{ defendeurs|parties_noms }} ;"),
            ),
            $this->si(
                'demolition = rejetée',
                $this->t('Sur la demande de démolition des constructions'),
                $this->p('{{ demolition_motifs|paragraphes }}'),
                $this->p('Qu\'il convient de rejeter cette demande ;'),
            ),
        ]);
        $dispositif = implode('', [
            $this->si('propriete = accordée', $this->p('Dit que {{ demandeurs|parties_noms }} {{ demandeurs|sg_pl:est,sont }} {{ demandeurs|accord:propriétaire }} de {{ designation_parcelle }} ;')),
            $this->si('deguerpissement = ordonné', $this->p('Ordonne le déguerpissement de {{ defendeurs|parties_noms }}, tant de {{ defendeurs|sg_pl:sa,leur }} personne que de tous occupants de {{ defendeurs|sg_pl:son,leur }} chef, de ladite parcelle ;')),
            $this->si('deguerpissement = rejeté', $this->p('Rejette la demande en déguerpissement ;')),
            $this->si('demolition = ordonnée', $this->p('Ordonne la démolition, aux frais de {{ defendeurs|parties_noms }}, de {{ constructions }} ;')),
            $this->si('demolition = rejetée', $this->p('Rejette la demande de démolition ;')),
        ]);

        return $this->factum($motifs, $dispositif);
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: mixed}> */
    private function variablesFoncier(): array
    {
        return [
            ['propriete', 'Revendication de propriété', 'texte', 'accordée'],
            ['designation_parcelle', 'Parcelle : désignation complète (commence par « la parcelle de terrain… »)', 'texte', "la parcelle de terrain formant le lot n° 355 de l'îlot n° 41, d'une superficie de six cent cinquante-sept (657) mètres carrés, issue du lotissement « BINGERVILLE AYOPOUMIN EX-MITTERRAND », commune de Bingerville, objet du titre foncier n° 208.818 de la circonscription foncière d'Allobé"],
            ['titre_propriete', 'Titre de propriété produit', 'texte', 'un arrêté de concession définitive n° 16-6702/MCLU/DGUF/DDU/COD-AE2/TBT du 19 juillet 2016'],
            ['propriete_motifs', 'Propriété : raisonnement du tribunal (obligatoire si la demande est rejetée ; un paragraphe par ligne)', 'paragraphes', null],
            ['deguerpissement', 'Demande en déguerpissement', 'texte', 'ordonné'],
            ['occupation_constat', "Occupation : comment elle est établie (ex. : établie par procès-verbal de constat de commissaire de justice)", 'texte', 'établie par procès-verbal de constat de commissaire de justice'],
            ['deguerpissement_motifs', 'Déguerpissement : raisonnement du tribunal (un paragraphe par ligne)', 'paragraphes', null],
            ['demolition', 'Demande de démolition des constructions', 'texte', 'ordonnée'],
            ['constructions', 'Constructions concernées, avec leur article (ex. : la baraque, les constructions érigées)', 'texte', 'la baraque'],
            ['demolition_motifs', 'Démolition : raisonnement du tribunal (un paragraphe par ligne)', 'paragraphes', null],
        ];
    }

    /* ---------------------------------------------------------------- modèle : bail d'habitation */

    private function factumBail(): string
    {
        $motifs = implode('', [
            '<p>{{#exige conge}}</p>',
            $this->t("Sur l'action principale en validation du congé du {{ date_conge|date_courte }}"),
            $this->p("Attendu que {{ demandeurs|parties_noms }} {{ demandeurs|sg_pl:sollicite,sollicitent }} la validation du congé qu'{{ demandeurs|pronom }} {{ demandeurs|sg_pl:a,ont }} servi, le {{ date_conge|date_courte }}, à {{ defendeurs|parties_noms }}{{#si motif_conge}}, au motif {{ motif_conge }}{{/si}} ;"),
            $this->p('{{ conge_motifs|paragraphes }}'),
            $this->si('conge = validé', $this->p("Qu'il convient, en conséquence, de valider le congé du {{ date_conge|date_courte }} ;")),
            $this->si('conge = déclaré nul', $this->p("Qu'il convient, en conséquence, de déclarer nul le congé du {{ date_conge|date_courte }} ;")),
            $this->si('conge = sans objet', $this->p("Qu'il convient, en conséquence, de déclarer sans objet la demande de validation du congé du {{ date_conge|date_courte }} et de dire que ce congé ne peut valablement fonder la demande en expulsion ;")),
            $this->si('conge = rejeté', $this->p('Qu\'il convient, en conséquence, de rejeter la demande de validation du congé ;')),
            $this->si(
                'expulsion = ordonnée',
                $this->t("Sur la demande d'expulsion"),
                $this->si('expulsion_motifs', $this->p('{{ expulsion_motifs|paragraphes }}')),
                $this->p("Qu'il y a lieu, en conséquence, d'ordonner l'expulsion de {{ defendeurs|parties_noms }}, tant de {{ defendeurs|sg_pl:sa,leur }} personne que de {{ defendeurs|sg_pl:ses,leurs }} biens et de tous occupants de {{ defendeurs|sg_pl:son,leur }} chef, du local qu'{{ defendeurs|pronom }} {{ defendeurs|sg_pl:occupe,occupent }} ;"),
            ),
            $this->si(
                'expulsion = rejetée',
                $this->t("Sur la demande d'expulsion"),
                $this->si('expulsion_motifs', $this->p('{{ expulsion_motifs|paragraphes }}')),
                $this->p("Qu'il convient, dès lors, de débouter {{ demandeurs|parties_noms }} de {{ demandeurs|sg_pl:sa,leur }} demande d'expulsion de {{ defendeurs|parties_noms }} et de tous occupants de {{ defendeurs|sg_pl:son,leur }} chef ;"),
            ),
            $this->si(
                'quittances = ordonnées',
                $this->t('Sur la demande tendant à la délivrance des quittances de loyers'),
                $this->p("Attendu que {{ defendeurs|parties_noms }} {{ defendeurs|sg_pl:sollicite,sollicitent }} qu'il soit ordonné à {{ demandeurs|parties_noms }} de {{ defendeurs|sg_pl:lui,leur }} délivrer les quittances de loyers correspondant aux paiements effectués ;"),
                $this->p("Attendu que la délivrance d'une quittance permettant au locataire de justifier du règlement du loyer correspondant à la période concernée, elle reste et demeure une obligation qui pèse sur le bailleur ;"),
                $this->si('quittances_motifs', $this->p('{{ quittances_motifs|paragraphes }}')),
                $this->p("Qu'il convient, en conséquence, d'ordonner à {{ demandeurs|parties_noms }} la délivrance des quittances correspondant aux loyers effectivement payés ;"),
            ),
            $this->si(
                'quittances = rejetées',
                $this->t('Sur la demande tendant à la délivrance des quittances de loyers'),
                $this->p('{{ quittances_motifs|paragraphes }}'),
                $this->p('Qu\'il convient de rejeter cette demande ;'),
            ),
        ]);
        $dispositif = implode('', [
            $this->si('conge = validé', $this->p('Valide le congé du {{ date_conge|date_courte }} servi à {{ defendeurs|parties_noms }} ;')),
            $this->si('conge = déclaré nul', $this->p('Déclare nul le congé du {{ date_conge|date_courte }} ;')),
            $this->si('conge = sans objet', $this->p('Dit sans objet la demande de validation du congé du {{ date_conge|date_courte }} ;')),
            $this->si('conge = rejeté', $this->p('Rejette la demande de validation du congé du {{ date_conge|date_courte }} ;')),
            $this->si('expulsion = ordonnée', $this->p("Ordonne l'expulsion de {{ defendeurs|parties_noms }}, tant de {{ defendeurs|sg_pl:sa,leur }} personne que de {{ defendeurs|sg_pl:ses,leurs }} biens et de tous occupants de {{ defendeurs|sg_pl:son,leur }} chef, du local qu'{{ defendeurs|pronom }} {{ defendeurs|sg_pl:occupe,occupent }} ;")),
            $this->si('expulsion = rejetée', $this->p("Déboute {{ demandeurs|parties_noms }} de {{ demandeurs|sg_pl:sa,leur }} demande d'expulsion ;")),
            $this->si('quittances = ordonnées', $this->p('Ordonne à {{ demandeurs|parties_noms }} de délivrer à {{ defendeurs|parties_noms }} les quittances correspondant aux loyers dont le paiement effectif est établi et pour lesquels aucune quittance n\'a déjà été remise ;')),
            $this->si('quittances = rejetées', $this->p('Rejette la demande de délivrance de quittances de loyers ;')),
        ]);

        return $this->factum($motifs, $dispositif);
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: mixed}> */
    private function variablesBail(): array
    {
        return [
            ['conge', 'Sort de la demande de validation du congé', 'texte', 'sans objet'],
            ['date_conge', 'Date du congé', 'date', '2025-12-05'],
            ['motif_conge', "Motif du congé (commence par « qu'il » ou « qu'elle », ex. : qu'elle voulait reprendre le logement loué)", 'texte', "qu'elle voulait reprendre le logement loué pour y entreprendre des travaux de réhabilitation"],
            ['conge_motifs', 'Congé : raisonnement du tribunal (un paragraphe par ligne)', 'paragraphes', "Que, cependant, il est constant que, par jugement civil de défaut du 16 février 2026, le Tribunal de céans a estimé que le motif invoqué à l'appui du congé ne reposait pas sur un motif légitime et l'a, conséquemment, déclaré nul ;\nQu'un congé déjà déclaré nul par une décision judiciaire ne peut plus servir de fondement à la résiliation du bail ni à la libération forcée des lieux, étant censé n'avoir jamais existé ;"],
            ['expulsion', "Demande d'expulsion", 'texte', 'rejetée'],
            ['expulsion_motifs', "Expulsion : raisonnement du tribunal (un paragraphe par ligne)", 'paragraphes', null],
            ['quittances', 'Demande de délivrance des quittances de loyer (demande reconventionnelle)', 'texte', 'ordonnées'],
            ['quittances_motifs', 'Quittances : raisonnement du tribunal (un paragraphe par ligne)', 'paragraphes', null],
        ];
    }

    /* ---------------------------------------------------------------- chargement */

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: mixed}>  $variables
     * @param  list<string>  $objets  libellés des objets du litige auxquels le modèle est rattaché
     */
    private function charger(string $nom, string $description, array $variables, array $objets, string $factum): void
    {
        $modele = ModeleDecision::where('nom', $nom)->first();
        if (! $modele) {
            $modele = ModeleDecision::create(['nom' => $nom, 'matiere_id' => Matiere::where('libelle', 'Civile')->value('id'), 'description' => $description, 'etat' => 'brouillon']);
            foreach ($variables as [$code, $libelle, $type, $exemple]) {
                $modele->variables()->create(['code' => $code, 'libelle' => $libelle, 'type' => $type, 'exemple' => $exemple]);
            }
            $modele->objets()->sync(ObjetLitige::whereIn('libelle', $objets)->pluck('id')->all());
            foreach (['1re chambre civile', '2e chambre civile', '3e chambre civile', '4e chambre civile', '5e chambre civile'] as $chambre) {
                DB::table('modeles_decision_chambres')->insertOrIgnore(['modele_decision_id' => $modele->id, 'chambre_libelle' => $chambre]);
            }
        }

        $courante = $modele->versions()->first();
        $attendu = new ModeleDecisionVersion(['entete' => TexteRiche::nettoyer($this->entete()), 'qualite' => TexteRiche::nettoyer($this->qualite()), 'factum' => TexteRiche::nettoyer($factum)]);
        if ($courante) {
            if ($this->forme($courante) === $this->forme($attendu)) {
                return;
            }
            // Seule une version chargée par ce seeder (sans auteur) et jamais retouchée est remplacée.
            if ($courante->cree_par !== null) {
                return;
            }
        }
        ModeleDecisionVersion::create([
            'modele_decision_id' => $modele->id, 'numero' => ($courante?->numero ?? 0) + 1,
            'entete' => $attendu->entete, 'qualite' => $attendu->qualite, 'factum' => $attendu->factum,
            'note' => $courante ? 'Mise à jour des textes de départ' : "Première version, d'après les jugements du 26 octobre 2026",
        ]);
    }

    private function forme(ModeleDecisionVersion $v): string
    {
        return implode('|', array_map(
            fn ($t) => preg_replace('/\s+/u', ' ', html_entity_decode(trim((string) TexteRiche::nettoyer((string) $t)))),
            [$v->entete, $v->qualite, $v->factum],
        ));
    }
}
