<?php

namespace App\Support\Pilotage;

use App\Models\DelaiEtape;

/*
 | Analyse automatique du pilotage : des règles simples et des modèles de phrases, sans appel externe, toujours disponibles et déterministes
 | (les mêmes chiffres donnent la même analyse). Elle produit un indice de santé de 0 à 100 avec le détail de ce qui le fait baisser,
 | les points forts, les points d'attention classés par gravité, et des recommandations reliées à un chiffre précis.
 | L'analyse par l'IA reprend les mêmes chiffres : voir RedacteurIA.
 */
class AnalyseAuto
{
    /** Comment l'indice de santé est calculé : on part de 100 et on retire des points. Affiché dans la page pour que chacun comprenne. */
    public const REGLES_INDICE = [
        ['critere' => 'Taux de traitement', 'explication' => "Affaires terminées pour 100 enrôlées sur la période. En dessous de 100 %, le stock grossit.", 'retrait' => "1 point par tranche de 5 points manquants, jusqu'à -20"],
        ['critere' => 'Affaires en retard', 'explication' => "Renvoi ou délibéré dépassé, suite non notée, affaire jamais portée au rôle : part du stock concernée.", 'retrait' => "50 points × la part en retard, jusqu'à -25"],
        ['critere' => 'Décisions à rédiger', 'explication' => "Affaires jugées sans décision validée.", 'retrait' => "3 points par décision, jusqu'à -15, et 5 de plus si la plus ancienne attend depuis plus de 15 jours"],
        ['critere' => "Feuilles d'audience non saisies", 'explication' => "Audiences passées dont les suites n'ont pas été notées.", 'retrait' => "1 point par tranche de 10 % non saisis, jusqu'à -8"],
        ['critere' => 'Alertes critiques', 'explication' => "Alertes montées jusqu'à la hiérarchie (retard grave).", 'retrait' => "5 points par alerte, jusqu'à -20"],
        ['critere' => 'Taux de renvoi', 'explication' => "Part des renvois parmi les suites notées.", 'retrait' => "5 points au-delà de 40 %"],
        ['critere' => 'Détenus à échéance proche', 'explication' => "Mandat de dépôt qui arrive à échéance.", 'retrait' => "3 points par détenu, jusqu'à -10"],
    ];

    /** Seuils d'attention (à ajuster avec le tribunal). */
    public const DELAI_CIVIL_JOURS = 183;      // six mois
    public const SEUIL_RENVOIS_PCT = 40;
    public const SEUIL_RETARD_RATIO = 0.15;    // part des affaires en cours dont une échéance est dépassée

    /**
     * @param  array<string, mixed>  $d  les chiffres de DonneesPilotage::calculer
     * @return array{sante: array<string, mixed>, situation: string, points_forts: list<string>, points_attention: list<array{texte: string, gravite: string}>, recommandations: list<string>, texte: string}
     */
    public static function generer(array $d): array
    {
        $k = $d['kpis'];
        $chambres = collect($d['par_chambre']);
        $fortes = [];
        $attention = [];
        $reco = [];

        $stock = max(0, (int) $k['stock']);
        $delaiRedaction = DelaiEtape::de('redaction_decision');

        // ---- sorties et entrées
        if ($k['taux_sortie'] !== null) {
            if ($k['taux_sortie'] >= 100) {
                $fortes[] = 'Le tribunal termine au moins autant d\'affaires qu\'il en enrôle : '.self::n($k['sorties'], 'affaire terminée').' pour '.self::n($k['entrees'], 'affaire enrôlée').' (taux de traitement de '.$k['taux_sortie'].' %).';
            } elseif ($k['taux_sortie'] < 80) {
                $attention[] = ['gravite' => $k['taux_sortie'] < 50 ? 'critique' : 'attention', 'texte' => 'Le stock augmente : '.self::n($k['entrees'], 'affaire enrôlée').' pour '.self::n($k['sorties'], 'affaire terminée').' sur la période (taux de traitement de '.$k['taux_sortie'].' %).'];
                $reco[] = 'Augmenter le rythme de jugement : '.max(0, $k['entrees'] - $k['sorties']).' affaires terminées de plus sont nécessaires pour ne pas alourdir le stock de '.$stock.' affaires en cours.';
            }
        }

        // ---- retards
        if ($k['retards'] > 0 && $stock > 0) {
            $ratio = $k['retards'] / max(1, $stock + $k['a_rediger']);
            $pires = $chambres->where('retards', '>', 0)->sortByDesc('retards')->take(3);
            $attention[] = ['gravite' => $ratio >= self::SEUIL_RETARD_RATIO * 2 ? 'critique' : 'attention', 'texte' => self::n($k['retards'], 'affaire').' ont une échéance dépassée (renvoi, délibéré, suite non notée ou jamais portées au rôle), soit '.(int) round($ratio * 100).' % du stock. Les plus touchées : '.$pires->map(fn ($c) => $c['libelle'].' ('.$c['retards'].')')->implode(', ').'.'];
            $reco[] = 'Traiter en priorité les '.$k['retards'].' affaires en retard, en commençant par la chambre la plus touchée ('.($pires->first()['libelle'] ?? 'voir le tableau par chambre').').';
        } elseif ($stock > 0) {
            $fortes[] = 'Aucune affaire n\'a d\'échéance dépassée.';
        }

        // ---- décisions à rédiger
        if ($k['a_rediger'] > 0) {
            $limite = $delaiRedaction ? $delaiRedaction->libelleDelai() : null;
            $depasse = $delaiRedaction && $k['plus_ancienne_decision'] > ($delaiRedaction->unite === 'mois' ? $delaiRedaction->valeur * 30 : ($delaiRedaction->unite === 'jours_ouvrables' ? $delaiRedaction->valeur * 7 / 5 : $delaiRedaction->valeur));   // le temps écoulé se compte en jours calendaires
            $attention[] = ['gravite' => $depasse ? 'critique' : 'attention', 'texte' => self::n($k['a_rediger'], 'affaire jugée').' sans décision validée ; la plus ancienne attend depuis '.$k['plus_ancienne_decision'].' jour'.($k['plus_ancienne_decision'] > 1 ? 's' : '').($limite ? ' (délai prévu : '.$limite.')' : '').'.'];
            $reco[] = 'Rédiger et valider les '.$k['a_rediger'].' décisions en attente, la plus ancienne d\'abord'.($depasse ? ' : le délai prévu est dépassé' : '').'.';
        } elseif ($k['decisions_validees'] > 0) {
            $fortes[] = self::n($k['decisions_validees'], 'décision validée').' sur la période et aucune décision en attente de rédaction.';
        }

        // ---- feuilles d'audience
        if ($k['audiences_passees'] > 0 && $k['feuilles_saisies_pct'] !== null) {
            if ($k['feuilles_saisies_pct'] < 100) {
                $attention[] = ['gravite' => $k['feuilles_saisies_pct'] < 70 ? 'critique' : 'attention', 'texte' => 'Seules '.$k['feuilles_saisies_pct'].' % des audiences passées ont une feuille saisie ('.self::n($k['audiences_passees'], 'audience').' sur la période) : les suites des autres ne sont pas connues.'];
                $reco[] = 'Faire saisir les suites des audiences passées sans feuille : sans elles, les renvois et délibérés ne reviennent pas sur les rôles.';
            } else {
                $fortes[] = 'Toutes les feuilles d\'audience de la période sont saisies ('.self::n($k['audiences_passees'], 'audience').').';
            }
        }

        // ---- renvois
        if ($k['renvois_pct'] !== null && $k['renvois_pct'] > self::SEUIL_RENVOIS_PCT) {
            $attention[] = ['gravite' => 'attention', 'texte' => 'Les renvois représentent '.$k['renvois_pct'].' % des suites notées : beaucoup d\'affaires ne progressent pas d\'une audience à l\'autre.'];
            $reco[] = 'Analyser les motifs de renvoi les plus fréquents et limiter les renvois sans motif sérieux, surtout dans les chambres au taux le plus élevé : '.($chambres->whereNotNull('renvois_pct')->sortByDesc('renvois_pct')->take(2)->map(fn ($c) => $c['libelle'].' ('.$c['renvois_pct'].' %)')->implode(', ') ?: 'voir le tableau par chambre').'.';
        }
        if ($k['affaires_renvoyees_3_fois'] > 0) {
            $attention[] = ['gravite' => 'attention', 'texte' => self::n($k['affaires_renvoyees_3_fois'], 'affaire').' en cours ont déjà été renvoyées trois fois ou plus.'];
            $reco[] = 'Examiner '.self::n($k['affaires_renvoyees_3_fois'], 'affaire').' renvoyée'.($k['affaires_renvoyees_3_fois'] > 1 ? 's' : '').' trois fois ou plus pour fixer une date ferme ou trancher.';
        }

        // ---- délais
        if ($k['delai_moyen'] !== null) {
            if ($k['delai_moyen'] > self::DELAI_CIVIL_JOURS) {
                $attention[] = ['gravite' => 'attention', 'texte' => 'Le délai moyen entre l\'enrôlement et la fin de l\'affaire est de '.$k['delai_moyen'].' jours, au-delà de six mois.'];
                $reco[] = 'Réduire le délai moyen de traitement ('.$k['delai_moyen'].' jours) en priorisant les plus anciennes affaires.';
            } else {
                $fortes[] = 'Le délai moyen de traitement est de '.$k['delai_moyen'].' jours, dans le délai de six mois.';
            }
        }
        $lentes = $chambres->filter(fn ($c) => $c['delai_moyen'] !== null && $c['delai_moyen'] > self::DELAI_CIVIL_JOURS)->sortByDesc('delai_moyen')->take(2);
        if ($lentes->isNotEmpty()) {
            $attention[] = ['gravite' => 'attention', 'texte' => 'Délai moyen supérieur à six mois dans : '.$lentes->map(fn ($c) => $c['libelle'].' ('.$c['delai_moyen'].' jours)')->implode(', ').'.'];
        }

        // ---- charge des chambres
        $moyenne = $chambres->where('stock', '>', 0)->avg('stock');
        $chargee = $chambres->sortByDesc('stock')->first();
        if ($moyenne && $chargee && $chargee['stock'] >= 2 * $moyenne && $chargee['stock'] >= 10) {
            $attention[] = ['gravite' => 'attention', 'texte' => $chargee['libelle'].' concentre '.$chargee['stock'].' affaires en cours, plus de deux fois la moyenne des chambres ('.(int) round($moyenne).').'];
            $reco[] = 'Rééquilibrer la charge de '.$chargee['libelle'].' ('.$chargee['stock'].' affaires) vers les chambres les moins chargées.';
        }

        // ---- détenus
        if ($k['detenus_proches'] > 0) {
            $attention[] = ['gravite' => 'critique', 'texte' => self::n($k['detenus_proches'], 'détenu').' dont l\'échéance du mandat de dépôt tombe dans les '.config('tpi.penal.alerte_jours', 30).' prochains jours.'];
            $reco[] = 'Prolonger le mandat ou statuer sur la mise en liberté de '.self::n($k['detenus_proches'], 'détenu').' à échéance proche.';
        }

        // ---- alertes
        if ($k['alertes_critiques'] > 0) {
            $attention[] = ['gravite' => 'critique', 'texte' => self::n($k['alertes_critiques'], 'alerte critique').' en cours, sur '.self::n($k['alertes_ouvertes'], 'alerte').' ouvertes.'];
            $reco[] = 'Traiter les '.$k['alertes_critiques'].' alertes critiques (page Suivi des alertes) : elles sont montées jusqu\'à la hiérarchie.';
        } elseif ($k['alertes_ouvertes'] === 0) {
            $fortes[] = 'Aucune alerte ouverte : toutes les échéances du circuit sont respectées.';
        }
        if ($d['agents_sans_compte'] > 0) {
            $attention[] = ['gravite' => 'attention', 'texte' => self::n($d['agents_sans_compte'], 'agent').' (greffiers ou présidents de formation) sans compte utilisateur : leurs alertes sont redirigées vers les administrateurs.'];
            $reco[] = 'Relier à un compte les '.self::n($d['agents_sans_compte'], 'agent').' sans compte (Utilisateurs), pour que les alertes arrivent à la bonne personne.';
        }

        // ---- indice de santé
        $facteurs = [];
        $retire = function (string $libelle, int $points) use (&$facteurs) { if ($points > 0) { $facteurs[] = ['libelle' => $libelle, 'points' => -$points]; } };
        if ($k['taux_sortie'] !== null && $k['taux_sortie'] < 100) { $retire('Taux de traitement de '.$k['taux_sortie'].' %', min(20, (int) round((100 - $k['taux_sortie']) / 5))); }
        if ($stock > 0) { $retire($k['retards'].' affaires en retard', min(25, (int) round($k['retards'] / max(1, $stock + $k['a_rediger']) * 50))); }
        $retire($k['a_rediger'].' décisions à rédiger', min(15, $k['a_rediger'] * 3) + ($k['plus_ancienne_decision'] > 15 && $k['a_rediger'] > 0 ? 5 : 0));
        if ($k['feuilles_saisies_pct'] !== null) { $retire('Feuilles d\'audience non saisies ('.(100 - $k['feuilles_saisies_pct']).' %)', min(8, (int) round((100 - $k['feuilles_saisies_pct']) / 10))); }
        $retire($k['alertes_critiques'].' alertes critiques', min(20, $k['alertes_critiques'] * 5));
        if ($k['renvois_pct'] !== null && $k['renvois_pct'] > self::SEUIL_RENVOIS_PCT) { $retire('Taux de renvoi de '.$k['renvois_pct'].' %', 5); }
        $retire(self::n($k['detenus_proches'], 'détenu').' à échéance proche', min(10, $k['detenus_proches'] * 3));
        $score = max(0, 100 + array_sum(array_column($facteurs, 'points')));
        $niveau = $score >= 80 ? 'bon' : ($score >= 60 ? 'vigilance' : 'alerte');
        $sante = ['regles' => self::REGLES_INDICE, 'score' => $score, 'niveau' => $niveau, 'libelle' => ['bon' => 'Situation maîtrisée', 'vigilance' => 'Points de vigilance', 'alerte' => 'Situation préoccupante'][$niveau], 'facteurs' => $facteurs];

        usort($attention, fn ($a, $b) => (['critique' => 0, 'attention' => 1][$a['gravite']] ?? 2) <=> (['critique' => 0, 'attention' => 1][$b['gravite']] ?? 2));

        $situation = 'Sur la période « '.mb_strtolower($d['periode']['libelle']).' » ('.$d['perimetre'].'), '.self::n($k['entrees'], 'affaire').' ont été enrôlées et '.self::n($k['sorties'], 'affaire').' sont terminées (jugées, radiées, jointes ou désistées)'
            .($k['taux_sortie'] !== null ? ', soit un taux de traitement de '.$k['taux_sortie'].' %' : '').'. '.self::n($stock, 'affaire').' sont en cours'
            .($k['retards'] > 0 ? ', dont '.$k['retards'].' avec une échéance dépassée' : '').($k['delai_moyen'] !== null ? '. Le délai moyen de traitement est de '.$k['delai_moyen'].' jours' : '')
            .'. L\'indice de santé du tribunal est de '.$score.' sur 100 : '.mb_strtolower($sante['libelle']).'.';

        if (! $fortes) { $fortes[] = 'Aucun point fort notable sur la période.'; }
        if (! $reco) { $reco[] = 'Maintenir le rythme actuel et vérifier chaque semaine les alertes ouvertes.'; }

        $texte = "## Situation d'ensemble\n".$situation."\n\n## Points forts\n".collect($fortes)->map(fn ($t) => '- '.$t)->implode("\n")."\n\n## Points d'attention\n"
            .(collect($attention)->map(fn ($a) => '- '.$a['texte'])->implode("\n") ?: '- Aucun point d\'attention sur la période.')."\n\n## Recommandations\n".collect($reco)->map(fn ($t) => '- '.$t)->implode("\n");

        return ['sante' => $sante, 'situation' => $situation, 'points_forts' => $fortes, 'points_attention' => $attention, 'recommandations' => $reco, 'texte' => $texte];
    }

    /** « 1 affaire », « 3 affaires » : le mot est celui du singulier, l'accord se fait sur le dernier mot. */
    private static function n(int|float $nombre, string $mot): string
    {
        $nombre = (int) $nombre;
        $mots = explode(' ', $mot);
        $premier = array_shift($mots);
        $pluriel = fn (string $m) => str_ends_with($m, 's') || str_ends_with($m, 'x') ? $m : (str_ends_with($m, 'al') ? substr($m, 0, -2).'aux' : $m.'s');

        // « affaire jugée » -> « affaires jugées » ; « décision validée » ; « alerte critique » ; « sortie ».
        $texte = $nombre > 1 ? implode(' ', array_map($pluriel, array_merge([$premier], $mots))) : $mot;

        return $nombre.' '.$texte;
    }
}
