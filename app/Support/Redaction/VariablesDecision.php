<?php

namespace App\Support\Redaction;

use Illuminate\Support\Arr;

/**
 * Catalogue des variables que les modèles de décision peuvent utiliser : les données d'une affaire (numéros, dates, chambre),
 * la composition de la formation et les parties. Chaque modèle peut en ajouter d'autres qui lui sont propres (héritiers, défunt…).
 *
 * Une donnée composée s'écrit avec un point : {{ demandeur.nom }}, {{ demandeur.genre|ne }}.
 * Les exemples sont fictifs : ils servent à l'aperçu d'un modèle et ne viennent d'aucun dossier réel.
 * type : texte | date | nombre | genre (m ou f) | liste | personne (nom + genre) | personnes (liste de personnes)
 * | paragraphes (texte long) | parties (demandeurs, défendeurs) | sections (rubriques de motifs)
 */
class VariablesDecision
{
    /** Types de variable et filtre conseillé à l'insertion. */
    public const TYPES = [
        'texte' => 'Texte', 'date' => 'Date', 'nombre' => 'Nombre', 'genre' => 'Genre (m ou f)', 'liste' => 'Liste',
        'personne' => 'Personne', 'personnes' => 'Plusieurs personnes',
        'paragraphes' => 'Texte long (un paragraphe par ligne)', 'parties' => 'Parties (identité complète)', 'sections' => 'Rubriques (titre, texte, dispositif)',
    ];

    public const FILTRE_CONSEILLE = ['date' => 'date', 'nombre' => 'nombre_chiffres', 'liste' => 'liste', 'personne' => 'personne', 'personnes' => 'personnes', 'genre' => 'civilite'];

    /** Groupe du formulaire où l'on range ce qui sert rarement : il reste replié tant qu'on ne l'ouvre pas. */
    public const OPTIONS = 'Options particulières';

    /** Variables rangées dans « Options particulières » : déjà préremplies (conseils), facultatives ou rares (fin de non-recevoir, incidents, texte de renvoi…). */
    private const VERS_OPTIONS = [
        'formation', 'chambre_specialite', 'demandeurs_conseil', 'defendeurs_conseil', 'deroulement_libre', 'mp_date_conclusions', 'mp_sens_conclusions', 'caractere_motif',
        'fnr_objet', 'fnr_auteur', 'fnr_rappel', 'fnr_motifs', 'fnr_issue', 'reconventionnelle', 'incidents', 'sections_fond', 'complement_dispositif', 'ep_portee', 'depens_distraction',
        'rappel_1315', 'loyers_bail',
    ];

    /**
     * Quand un champ du formulaire s'affiche : il n'apparaît que si sa condition est vraie (« code = valeur », « code != valeur », « code in a|b », « code » renseigné).
     * Plusieurs conditions se joignent par « && ». Un champ sans condition est toujours demandé. Le formulaire se réduit ainsi à ce que le jugement exige.
     */
    public const SI = [
        'moyens_defense' => 'defendeurs_comparution = Comparaissant',
        'fnr_auteur' => 'fnr_objet', 'fnr_rappel' => 'fnr_objet', 'fnr_motifs' => 'fnr_objet', 'fnr_issue' => 'fnr_objet',
        'ep_motif' => 'execution_provisoire = ordonnée', 'ep_portee' => 'execution_provisoire = ordonnée', 'ep_refus_motif' => 'execution_provisoire = refusée',
        'depens_distraction' => 'depens != réservés',
        'di' => 'type_jugement != avant-dire-droit', 'issue_fond' => 'type_jugement != avant-dire-droit', 'execution_provisoire' => 'type_jugement != avant-dire-droit', 'depens' => 'type_jugement != avant-dire-droit',
        'di_fondement' => 'di in accordés|accordés en partie|rejetés', 'di_demande' => 'di in accordés|accordés en partie|rejetés', 'di_motifs' => 'di in accordés|accordés en partie|rejetés',
        'di_alloue' => 'di = accordés en partie',
        'nature_expertise' => 'type_jugement = avant-dire-droit', 'motifs_expertise' => 'type_jugement = avant-dire-droit', 'expert_nom' => 'type_jugement = avant-dire-droit',
        'expert_qualite' => 'type_jugement = avant-dire-droit', 'mission_expertise' => 'type_jugement = avant-dire-droit', 'delai_expertise' => 'type_jugement = avant-dire-droit',
        'expertise_frais_charge' => 'type_jugement = avant-dire-droit', 'juge_controleur' => 'type_jugement = avant-dire-droit', 'date_renvoi_expertise' => 'type_jugement = avant-dire-droit',
        // Jugement foncier
        'propriete' => 'type_jugement != avant-dire-droit', 'deguerpissement' => 'type_jugement != avant-dire-droit', 'demolition' => 'type_jugement != avant-dire-droit',
        'designation_parcelle' => 'propriete in accordée|rejetée', 'titre_propriete' => 'propriete = accordée', 'propriete_motifs' => 'propriete in accordée|rejetée',
        'occupation_constat' => 'deguerpissement = ordonné', 'deguerpissement_motifs' => 'deguerpissement in ordonné|rejeté',
        'constructions' => 'demolition in ordonnée|rejetée', 'demolition_motifs' => 'demolition in ordonnée|rejetée',
        // Jugement de paiement et contrats
        'paiement' => 'type_jugement != avant-dire-droit', 'resolution' => 'type_jugement != avant-dire-droit', 'grace' => 'type_jugement != avant-dire-droit',
        'objet_creance' => 'paiement in accordé|accordé en partie|rejeté', 'montant_reclame' => 'paiement in accordé|accordé en partie|rejeté', 'paiement_motifs' => 'paiement in accordé|accordé en partie|rejeté',
        'montant_alloue' => 'paiement = accordé en partie', 'paiement_titre' => 'paiement in accordé|accordé en partie',
        'contrat_resolu' => 'resolution in prononcée|rejetée', 'resolution_motifs' => 'resolution in prononcée|rejetée',
        'grace_mois' => 'grace = accordé', 'grace_motifs' => 'grace in accordé|refusé',
        // Jugement de bail d'habitation
        'conge' => 'type_jugement != avant-dire-droit', 'expulsion' => 'type_jugement != avant-dire-droit', 'quittances' => 'type_jugement != avant-dire-droit',
        'date_conge' => 'conge in validé|déclaré nul|sans objet|rejeté', 'motif_conge' => 'conge in validé|déclaré nul|sans objet|rejeté', 'conge_motifs' => 'conge in validé|déclaré nul|sans objet|rejeté',
        'expulsion_motifs' => 'expulsion in ordonnée|rejetée', 'quittances_motifs' => 'quittances in ordonnées|rejetées',
    ];

    /** Ajoute à une variable sa condition d'affichage. */
    private static function avecCondition(array $v): array
    {
        return isset(self::SI[$v['code']]) ? $v + ['si' => self::SI[$v['code']]] : $v;
    }

    /** @return array<string, list<array{code: string, libelle: string, type: string, exemple: mixed}>> groupe => variables */
    public static function standard(): array
    {
        return self::ranger(self::catalogue());
    }

    /** Range les groupes des jugements civils (l'essentiel d'abord, le rare dans les options) et attache les conditions d'affichage. */
    private static function ranger(array $groupes): array
    {
        $options = [];
        foreach ($groupes as $nom => $variables) {
            $reste = [];
            foreach ($variables as $v) {
                $v = self::avecCondition($v);
                if (in_array($v['code'], self::VERS_OPTIONS, true)) {
                    $options[] = $v;
                } else {
                    $reste[] = $v;
                }
            }
            $groupes[$nom] = $reste;
        }
        // La décision finale vient après les dommages-intérêts et l'expertise ; les options, tout à la fin.
        if (isset($groupes['La décision (jugement civil)'])) {
            $decision = $groupes['La décision (jugement civil)'];
            unset($groupes['La décision (jugement civil)']);
            $groupes['La décision (jugement civil)'] = $decision;
        }
        $groupes[self::OPTIONS] = $options;

        return $groupes;
    }

    /** @return array<string, list<array{code: string, libelle: string, type: string, exemple: mixed}>> groupe => variables */
    private static function catalogue(): array
    {
        return [
            "L'affaire et la décision" => [
                self::v('juridiction', 'Juridiction', 'texte', 'Tribunal de Première Instance de Bingerville'),
                self::v('cour_appel', "Cour d'appel", 'texte', "Cour d'appel d'Abidjan"),
                self::v('numero_rg', 'Numéro du RG (sans « RG »)', 'texte', '017/2026'),
                self::v('date_rg', 'Date du RG', 'date', '2026-02-02'),
                self::v('chambre', 'Chambre (écriture courte)', 'texte', '3ème chambre civile'),
                self::v('chambre_lettres', 'Chambre (en lettres)', 'texte', 'troisième chambre civile'),
                self::v('type_decision', 'Type de décision', 'texte', "Jugement d'hérédité"),
                self::v('numero_decision', 'Numéro de la décision', 'texte', '000'),
                self::v('date_decision', 'Date de la décision', 'date', '2026-11-06'),
                self::v('nature_decision', 'Nature de la décision (contradictoire, par défaut…)', 'texte', 'contradictoire'),
                self::v('matiere', 'Matière', 'texte', "civile d'état des personnes"),
                self::v('type_audience', "Type d'audience", 'texte', 'ordinaire'),
                self::v('date_audience', "Date de l'audience", 'date', '2026-11-06'),
            ],
            'Composition du tribunal' => [
                self::v('president', 'Président de la formation', 'personne', null),
                self::v('president.nom', 'Président : nom', 'texte', 'ADJOUA Prisca'),
                self::v('president.genre', 'Président : genre', 'genre', 'f'),
                self::v('president.fonction', 'Président : fonction', 'texte', 'Vice-Président du Tribunal'),
                self::v('assesseurs', 'Assesseurs', 'personnes', '[{"nom":"KOFFI Marie","genre":"f"},{"nom":"DOSSO Ibrahim","genre":"m"}]'),
                self::v('assesseurs.0.nom', 'Premier assesseur : nom', 'texte', 'KOFFI Marie'),
                self::v('assesseurs.0.genre', 'Premier assesseur : genre', 'genre', 'f'),
                self::v('assesseurs.1.nom', 'Second assesseur : nom', 'texte', 'DOSSO Ibrahim'),
                self::v('assesseurs.1.genre', 'Second assesseur : genre', 'genre', 'm'),
                self::v('greffier', 'Greffier', 'personne', null),
                self::v('greffier.nom', 'Greffier : nom', 'texte', 'YAO Aminata'),
                self::v('greffier.genre', 'Greffier : genre', 'genre', 'f'),
            ],
            'Le demandeur' => [
                self::v('demandeur', 'Demandeur', 'personne', null),
                self::v('demandeur.nom', 'Demandeur : nom et prénoms', 'texte', 'TRAORE Salif'),
                self::v('demandeur.genre', 'Demandeur : genre', 'genre', 'm'),
                self::v('demandeur.naissance', 'Demandeur : naissance', 'texte', 'le 01 mars 1970'),
                self::v('demandeur.lieu_naissance', 'Demandeur : lieu de naissance', 'texte', 'Bouaké'),
                self::v('demandeur.nationalite', 'Demandeur : nationalité', 'texte', 'ivoirienne'),
                self::v('demandeur.domicile', 'Demandeur : domicile', 'texte', 'Bingerville'),
                self::v('demandeur.comparution', 'Demandeur : mode de comparution', 'texte', 'en personne'),
            ],
            // Variables des jugements civils (paiement, foncier, bail) : parties, procédure, motifs et dispositif communs.
            'Les parties (jugement civil)' => [
                self::v('demandeurs', 'Demandeurs (une ligne par partie)', 'parties', '[{"nom":"CHEHADE Kassem","genre":"m","naissance":{"mode":"date","date":"1980-12-12"},"lieu_naissance":"Abengourou","nationalite":"ivoirienne","profession":"gérant de société","domicile":"Abidjan-Cocody Riviera"}]'),
                self::v('demandeurs_conseil', 'Conseil des demandeurs, pour le timbre (ex. : Me KONE Catherine)', 'texte', 'Me COULIBALY Soungalo'),
                self::v('demandeurs_representation', 'Demandeurs : représentation (avocat, mandataire)', 'paragraphes', "Représenté par son conseil, Maître COULIBALY Soungalo, Avocat à la Cour, demeurant à Abidjan-Cocody."),
                self::v('demandeurs_comparution', 'Demandeurs : comparution', 'texte', 'Comparaissant'),
                self::v('defendeurs', 'Défendeurs (une ligne par partie)', 'parties', '[{"nom":"DOUDOU Charlène","genre":"f","naissance":{"mode":"date","date":"1998-06-10"},"lieu_naissance":"Abidjan","nationalite":"ivoirienne","profession":"commerciale","domicile":"Abidjan-Cocody Riviera M\'Badon"}]'),
                self::v('defendeurs_conseil', 'Conseil des défendeurs, pour le timbre', 'texte', null),
                self::v('defendeurs_representation', 'Défendeurs : représentation (avocat, mandataire)', 'paragraphes', null),
                self::v('defendeurs_comparution', 'Défendeurs : comparution', 'texte', 'Ne comparaissant pas'),
            ],
            'La procédure (jugement civil)' => [
                self::v('type_jugement', 'Jugement au fond ou avant-dire-droit', 'texte', 'au fond'),
                self::v('formation', 'Formation', 'texte', 'Formation A'),
                self::v('chambre_specialite', 'Spécialité de la chambre (ex. : chambre immobilière et des paiements)', 'texte', 'chambre immobilière et des paiements'),
                self::v('date_exploit', "Date de l'exploit d'assignation", 'date', '2026-06-03'),
                self::v('commissaire_justice', 'Commissaire de justice', 'texte', "Maître AKE ESSOH Christophe, Commissaire de Justice près la Cour d'Appel et le Tribunal de Première Instance d'Abidjan"),
                self::v('date_premiere_evocation', "Date de la première audience (première date d'évocation)", 'date', '2026-06-15'),
                self::v('date_premier_renvoi', 'Date du premier renvoi', 'date', '2026-06-29'),
                self::v('motif_premier_renvoi', 'Motif du premier renvoi (ex. : pour la défenderesse)', 'texte', 'pour la défenderesse'),
                self::v('deroulement_libre', 'Déroulement de la procédure, si les renvois sont à écrire à la main', 'paragraphes', null),
                self::v('demandes', "Demandes de l'assignation (une par ligne)", 'paragraphes', "Déclarer son action recevable et bien fondée\nCondamner Madame DOUDOU Charlène à payer la somme de quatre cent quarante-cinq mille (445.000) F CFA\nOrdonner l'exécution provisoire de la décision à intervenir\nCondamner la défenderesse aux entiers dépens de l'instance"),
                self::v('expose_demandeur', 'Exposé des demandeurs : faits et moyens (un paragraphe par ligne)', 'paragraphes', "Au soutien de son action, Monsieur CHEHADE Kassem expose qu'il a avancé le prix d'un billet d'avion à la défenderesse, laquelle devait le rembourser à son retour.\nIl indique que, malgré plusieurs relances, la défenderesse n'a pas procédé au remboursement convenu."),
                self::v('moyens_defense', 'Moyens de défense des défendeurs (un paragraphe par ligne)', 'paragraphes', null),
                self::v('mp_date_conclusions', 'Conclusions du Ministère public : date', 'date', null),
                self::v('mp_sens_conclusions', 'Conclusions du Ministère public : ce qu\'il demande au tribunal', 'texte', null),
                self::v('caractere_motif', 'Pourquoi cette nature de décision (ex. : assignée à personne, a comparu et conclu)', 'texte', "n'ayant pas été assignée à personne ni comparu, il n'est pas certain qu'elle ait eu connaissance de la présente procédure"),
                self::v('fnr_objet', 'Fin de non-recevoir : sur quoi elle porte (ex. : l\'autorité de la chose jugée)', 'texte', null),
                self::v('fnr_auteur', 'Fin de non-recevoir : qui la soulève', 'texte', null),
                self::v('fnr_motifs', 'Fin de non-recevoir : raisonnement du tribunal (un paragraphe par ligne)', 'paragraphes', null),
                self::v('fnr_rappel', 'Fin de non-recevoir : texte de loi à rappeler', 'texte', null),
                self::v('fnr_issue', 'Fin de non-recevoir : sort', 'texte', null),
                self::v('reconventionnelle', 'Demande reconventionnelle des défendeurs', 'texte', 'non'),
                self::v('incidents', 'Incidents et demandes avant le fond (sursis à statuer, mise en état…)', 'sections', null),
            ],
            'La décision (jugement civil)' => [
                self::v('issue_fond', "Sort de l'action au fond", 'texte', 'partiellement fondé'),
                self::v('sections_fond', 'Autres motifs au fond (rubriques libres)', 'sections', null),
                self::v('complement_dispositif', 'Autres dispositions à ajouter au dispositif (une par ligne)', 'paragraphes', null),
                self::v('execution_provisoire', "Exécution provisoire", 'texte', 'ordonnée'),
                self::v('ep_motif', "Exécution provisoire : motif (ex. : un titre authentique est produit)", 'texte', "Monsieur CHEHADE Kassem produit au soutien de son action un billet d'avion délivré par l'agence de voyage, corroboré par un paiement partiel après sommation de payer"),
                self::v('ep_portee', "Exécution provisoire : étendue (ex. : à concurrence de 42.900.000 F CFA)", 'texte', null),
                self::v('ep_refus_motif', "Exécution provisoire refusée : motif (un paragraphe par ligne)", 'paragraphes', null),
                self::v('depens', 'Dépens', 'texte', 'à la charge du défendeur'),
                self::v('depens_distraction', 'Dépens distraits au profit de (ex. : la SCPA N\'DRI & WOGNIN, Société d\'Avocats)', 'texte', null),
            ],
            'Dommages-intérêts' => [
                self::v('di', 'Demande de dommages-intérêts', 'texte', 'rejetés'),
                self::v('di_fondement', 'Fondement des dommages-intérêts', 'texte', 'retard de paiement (article 1153)'),
                self::v('di_demande', 'Dommages-intérêts demandés (francs CFA)', 'nombre', '500000'),
                self::v('di_alloue', 'Dommages-intérêts accordés (francs CFA)', 'nombre', '2000000'),
                self::v('di_motifs', 'Dommages-intérêts : raisonnement du tribunal (un paragraphe par ligne)', 'paragraphes', null),
            ],
            'Avant-dire-droit (expertise)' => [
                self::v('nature_expertise', "Nature de l'expertise (ex. : immobilière)", 'texte', 'immobilière'),
                self::v('motifs_expertise', "Pourquoi une expertise (un paragraphe par ligne)", 'paragraphes', "Attendu que les parties, qui détiennent chacune des titres de propriété, s'achoppent sur les limites de leurs parcelles ;\nQu'il résulte de la contrariété de leurs déclarations la nécessité de recourir à une expertise, étant entendu qu'elles induisent des considérations d'ordre technique ;"),
                self::v('expert_nom', "Expert désigné", 'texte', 'Monsieur TRAORE Yelakan Michel'),
                self::v('expert_qualite', "Expert : qualité et adresse", 'texte', "géomètre-expert agréé, demeurant à Abidjan Cocody"),
                self::v('mission_expertise', "Mission de l'expert (une par ligne)", 'paragraphes', "Dire si la partie occupe ou non les parcelles de terrain litigieuses\nDonner tout avis technique utile au tribunal"),
                self::v('delai_expertise', "Délai de l'expert (jours)", 'nombre', '30'),
                self::v('expertise_frais_charge', "Avance des frais d'expertise à la charge de", 'texte', 'la demanderesse'),
                self::v('juge_controleur', "Juge qui contrôle l'expertise", 'texte', 'Monsieur N\'GUESSAN BODO'),
                self::v('date_renvoi_expertise', "Date de renvoi pour le dépôt du rapport", 'date', '2026-11-30'),
            ],
        ];
    }

    /** Toutes les variables : celles du catalogue puis celles propres à un modèle. @param list<array{code: string, libelle: string, type: string, exemple: mixed}> $propres */
    public static function toutes(array $propres = []): array
    {
        $groupes = self::standard();
        if ($propres !== []) {
            $propres = array_map(fn ($v) => self::avecCondition($v), array_values($propres));
            $groupes[self::OPTIONS] = array_merge($groupes[self::OPTIONS] ?? [], array_values(array_filter($propres, fn ($v) => in_array($v['code'], self::VERS_OPTIONS, true))));
            $propres = array_values(array_filter($propres, fn ($v) => ! in_array($v['code'], self::VERS_OPTIONS, true)));
            // Ce que le modèle décide (déguerpissement, paiement…) se place après la procédure et avant les dommages-intérêts ; sinon à la fin.
            $apres = isset($groupes['Dommages-intérêts']) ? 'Dommages-intérêts' : null;
            $nouveau = [];
            foreach ($groupes as $nom => $variables) {
                if ($nom === $apres) {
                    $nouveau['Propres à ce modèle'] = $propres;
                }
                $nouveau[$nom] = $variables;
            }
            $groupes = $apres ? $nouveau : $groupes + ['Propres à ce modèle' => $propres];
        }

        return $groupes;
    }

    /** Valeurs d'exemple (fictives) pour l'aperçu, sous forme d'ensembles : « demandeur.nom » devient demandeur[nom]. */
    public static function exemples(array $propres = []): array
    {
        $valeurs = [];
        foreach (self::toutes($propres) as $variables) {
            foreach ($variables as $v) {
                if ($v['exemple'] !== null && $v['exemple'] !== '') {
                    Arr::set($valeurs, $v['code'], self::decoder($v['type'], $v['exemple']));
                }
            }
        }

        return $valeurs;
    }

    /** Une variable propre à un modèle enregistre son exemple en texte : les listes et personnes sont écrites en JSON. */
    private static function decoder(string $type, mixed $exemple): mixed
    {
        if (! is_string($exemple) || ! in_array($type, ['liste', 'personne', 'personnes', 'parties', 'sections'], true)) {
            return $exemple;
        }
        $json = json_decode($exemple, true);

        return is_array($json) ? $json : array_values(array_filter(array_map('trim', preg_split('/\r?\n|;/', $exemple))));
    }

    /** @return array{code: string, libelle: string, type: string, exemple: mixed} */
    private static function v(string $code, string $libelle, string $type, mixed $exemple): array
    {
        return ['code' => $code, 'libelle' => $libelle, 'type' => $type, 'exemple' => $exemple];
    }
}
