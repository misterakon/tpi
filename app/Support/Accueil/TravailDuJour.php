<?php

namespace App\Support\Accueil;

use App\Http\Controllers\NotificationsController;
use App\Models\Decision;
use App\Models\Requete;
use App\Models\RoleAudience;
use App\Models\User;
use App\Services\NumeroDecision;
use App\Support\Audiences\SuiviAffaires;
use App\Support\PerimetreChambres;
use Illuminate\Support\Facades\Cache;

/*
 | « Mon travail du jour » : ce que CETTE personne a à faire aujourd'hui, en une page. Chaque tâche dépend d'un droit du profil et ses chiffres
 | suivent son périmètre (sa chambre, ou tout pour la Présidente et les administrateurs). Une tâche à zéro n'encombre pas l'écran.
 | Les chiffres viennent des mêmes calculs que les alertes et l'accueil (SuiviAffaires, décisions) : aucune divergence possible.
 | Le résultat est gardé 45 secondes par personne (l'en-tête en lit le total sur chaque page).
 */
class TravailDuJour
{
    public const GROUPES = [
        'audiences' => ['Audiences et rôles', 'fa-gavel'],
        'decisions' => ['Décisions', 'fa-file-signature'],
        'suivi' => ['Suivi et alertes', 'fa-bell'],
    ];

    /** @return array{jour: string, audiences: list<array<string, mixed>>, taches: list<array<string, mixed>>, a_jour: list<string>, total: int, elements: int, alertes: list<array<string, mixed>>, non_lues: int} */
    public static function pour(User $user): array
    {
        return Cache::remember('travail_du_jour.v2:'.$user->id, 45, fn () => self::calculer($user));
    }

    public static function total(User $user): int
    {
        return self::pour($user)['total'];
    }

    private static function calculer(User $user): array
    {
        $taches = [];
        $ajouter = function (string $groupe, string $code, string $titre, string $aide, int $n, string $icone, string $ton, string $url, string $verbe = 'Ouvrir') use (&$taches) {
            $taches[] = compact('groupe', 'code', 'titre', 'aide', 'n', 'icone', 'ton', 'url', 'verbe');
        };

        // ---------- audiences et rôles : l'état des affaires de son périmètre
        $veutEtats = $user->peut('roles.voir') || $user->peut('suites.voir') || $user->peut('redaction.creer');
        $etats = $veutEtats ? SuiviAffaires::etats(PerimetreChambres::de($user)) : collect();
        $compte = fn (callable $f) => $etats->filter($f)->count();

        if ($user->peut('roles.voir')) {
            $ajouter('audiences', 'non_portee', 'Affaires à mettre au rôle', 'La première audience est passée sans passage sur un rôle.', $compte(fn ($e) => $e['etat'] === 'non_portee'), 'fa-calendar-plus', 'rouge', route('audiences.roles'));
        }
        if ($user->peut('suites.voir')) {
            $retard = route('audiences.suites', ['suite' => 'retard']);
            $ajouter('audiences', 'sans_suite', "Suites d'audience à noter", "L'audience a eu lieu, la suite n'est pas saisie.", $compte(fn ($e) => $e['etat'] === 'sans_suite'), 'fa-pen-to-square', 'rouge', $retard);
            $ajouter('audiences', 'renvois', 'Renvois échus', 'La date de renvoi est dépassée : à remettre au rôle.', $compte(fn ($e) => $e['etat'] === 'renvoyee' && $e['retard']), 'fa-rotate-right', 'orange', $retard);
            $ajouter('audiences', 'delibere', 'Délibérés échus', 'La date du délibéré est arrivée.', $compte(fn ($e) => $e['etat'] === 'deliberee' && $e['retard']), 'fa-hourglass-end', 'orange', $retard);
        }

        // ---------- décisions
        if ($user->peut('redaction.creer')) {
            $ajouter('decisions', 'a_rediger', 'Décisions à rédiger', 'Affaires jugées dont la décision n\'est pas encore commencée.', $compte(fn ($e) => $e['a_rediger']), 'fa-file-pen', 'orange', route('decisions.redaction'));
        }
        if ($user->peut('redaction.voir')) {
            $decisions = Decision::visiblesPour($user);
            if ($user->peut('redaction.modifier')) {
                $ajouter('decisions', 'brouillons', 'Mes brouillons', 'Décisions que vous avez commencées.', (clone $decisions)->where('statut', 'brouillon')->where('cree_par', $user->id)->count(), 'fa-file-lines', 'bleu', route('decisions.redaction'));
            }
            if ($user->peut('redaction.numeroter') && NumeroDecision::moment() === 'redaction') {
                $ajouter('decisions', 'numeroter', 'Numéros à attribuer', 'Décisions rédigées qui attendent leur numéro.', (clone $decisions)->where('statut', 'redigee')->whereNull('numero')->count(), 'fa-hashtag', 'orange', route('decisions.redaction'));
            }
            if ($user->peut('redaction.valider')) {
                $ajouter('decisions', 'a_valider', 'Décisions à valider', 'Rédigées : le texte attend votre validation.', (clone $decisions)->where('statut', 'redigee')->count(), 'fa-circle-check', 'orange', route('decisions.redaction'));
            }
            if ($user->peut('redaction.signer')) {
                $ajouter('decisions', 'a_signer', 'Décisions à signer', 'Validées : elles attendent la signature.', (clone $decisions)->where('statut', 'validee')->count(), 'fa-signature', 'orange', route('decisions.redaction'));
            }
            if ($user->peut('redaction.transmettre')) {
                $ajouter('decisions', 'a_transmettre', 'À transmettre au greffe', 'Signées, pas encore transmises : la date de transmission vaut délivrance.', (clone $decisions)->where('statut', 'signee')->whereNull('transmise_le')->count(), 'fa-share', 'rouge', route('decisions.transmission'), 'Transmettre');
            }
        }

        // ---------- suivi
        if ($user->peut('requetes.repartir')) {
            $ajouter('suivi', 'requetes', 'Requêtes à répartir', 'Reçues, pas encore réparties entre les formations.', Requete::where('statut', 'recue')->count(), 'fa-inbox', 'orange', route('affaires.requetes'));
        }
        $nonLues = $user->unreadNotifications()->count();
        $ajouter('suivi', 'alertes', 'Alertes non lues', 'Ce qui demande votre action, avec l\'écran où la faire.', $nonLues, 'fa-bell', $nonLues ? 'rouge' : 'bleu', route('notifications.index'), 'Voir');

        // ---------- audiences d'aujourd'hui
        $audiences = [];
        if ($user->peut('roles.voir')) {
            $audiences = RoleAudience::visiblesPour($user)->where('statut', 'publie')->whereDate('date_audience', today()->toDateString())
                ->with('chambre:id,libelle')->withCount('lignes')->orderBy('heure')->get()
                ->map(fn (RoleAudience $r) => [
                    'id' => $r->id, 'chambre' => (string) $r->chambre?->libelle, 'type' => $r->typeLibelle(), 'heure' => $r->heure ? substr((string) $r->heure, 0, 5) : null,
                    'affaires' => (int) $r->lignes_count, 'url' => route('audiences.roles.show', $r->id),
                    'feuille' => $user->peut('feuille.voir') ? route('audiences.feuille') : null,
                ])->all();
        }

        $actives = array_values(array_filter($taches, fn ($t) => $t['n'] > 0));
        usort($actives, fn ($a, $b) => [['rouge' => 0, 'orange' => 1, 'bleu' => 2][$a['ton']] ?? 3] <=> [['rouge' => 0, 'orange' => 1, 'bleu' => 2][$b['ton']] ?? 3]);

        return [
            'jour' => now()->locale('fr')->isoFormat('dddd D MMMM YYYY'),
            'audiences' => $audiences,
            'taches' => $actives,
            'a_jour' => array_values(array_map(fn ($t) => $t['titre'], array_filter($taches, fn ($t) => $t['n'] === 0 && $t['code'] !== 'alertes'))),
            'total' => count($actives), 'elements' => array_sum(array_column($actives, 'n')),
            'alertes' => NotificationsController::recentes($user, 5),
            'non_lues' => $nonLues,
        ];
    }
}
