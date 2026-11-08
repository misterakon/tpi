<?php

namespace App\Support\Accueil;

use App\Models\Affaire;
use App\Models\Chambre;
use App\Models\RoleAudience;
use App\Models\User;
use App\Support\PerimetreChambres;
use App\Support\Pilotage\DonneesPilotage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/*
 | Le tableau de bord de chaque utilisateur : seulement ce qui le concerne. Chaque bloc dépend d'un droit (affaires, rôles, alertes, rédaction) et
 | les chiffres suivent son périmètre (PerimetreChambres : sa chambre, ou tout pour la Présidente et les administrateurs ; les familles protégées
 | exigent leur droit). Les chiffres sont ceux de « Analyse et pilotage » (mêmes calculs, mis en mémoire une minute) : aucune divergence possible.
 */
class DonneesAccueil
{
    /** @return array<string, mixed> */
    public static function pour(User $user, int $mois = 12): array
    {
        $mois = $mois === 6 ? 6 : 12;
        $droits = [
            'affaires' => $user->peut('affaires.voir'), 'roles' => $user->peut('roles.voir'), 'alertes' => $user->peut('alertes.voir'),
            'redaction' => $user->peut('redaction.voir'), 'tableau' => $user->peut('tableau.voir'),
        ];

        $chiffres = null;
        if ($droits['affaires'] || $droits['alertes'] || $droits['redaction']) {
            $periode = DonneesPilotage::periode('30j');
            $chiffres = Cache::remember('pilotage:'.$user->id.':'.$periode['type'], 60, fn () => DonneesPilotage::calculer($user, $periode));
        }
        $k = $chiffres['kpis'] ?? null;

        $audiences = $droits['roles'] ? self::audiences($user) : null;
        $semaine = $audiences !== null ? collect($audiences)->where('dans_la_semaine', true)->count() : null;

        // Les quatre indicateurs les plus utiles pour ce profil, dans cet ordre.
        $candidats = [];
        if ($droits['affaires'] && $k) {
            $candidats[] = ['code' => 'en_cours', 'libelle' => 'Affaires en cours', 'valeur' => $k['stock'], 'aide' => $k['entrees'].' enrôlée'.($k['entrees'] > 1 ? 's' : '').' en 30 jours', 'icone' => 'fa-folder-open', 'ton' => 'vert', 'lien' => route('affaires.liste')];
        }
        if ($droits['roles'] && $audiences !== null) {
            $candidats[] = ['code' => 'audiences', 'libelle' => 'Audiences cette semaine', 'valeur' => $semaine, 'aide' => $audiences ? 'Prochaine : '.$audiences[0]['quand'] : 'Aucun rôle publié', 'icone' => 'fa-gavel', 'ton' => 'sarcelle', 'lien' => route('audiences.roles')];
        }
        if ($droits['alertes'] && $k) {
            $ratio = $k['stock'] > 0 ? $k['retards'] / $k['stock'] : 0;
            $candidats[] = ['code' => 'retards', 'libelle' => 'Affaires en retard', 'valeur' => $k['retards'], 'aide' => $k['retards'] ? 'Échéance dépassée' : 'Tout est à jour', 'icone' => 'fa-clock', 'ton' => $k['retards'] <= 0 ? 'vert' : ($ratio >= 0.3 ? 'rouge' : 'orange'), 'lien' => route('alertes.suivi'), 'pct' => $k['stock'] > 0 ? (int) round($ratio * 100) : null];
        }
        if ($droits['redaction'] && $k) {
            $candidats[] = ['code' => 'a_rediger', 'libelle' => 'Décisions à rédiger', 'valeur' => $k['a_rediger'], 'aide' => $k['a_rediger'] ? 'La plus ancienne : '.$k['plus_ancienne_decision'].' j' : 'Rien en attente', 'icone' => 'fa-pen-nib', 'ton' => $k['a_rediger'] > 0 ? 'orange' : 'vert', 'lien' => route('decisions.redaction')];
        }

        $perimetre = PerimetreChambres::de($user);

        return [
            'accueil' => [
                'nom' => $user->name, 'profil' => $user->profil?->nom,
                'perimetre' => $perimetre === null ? 'Toutes les chambres' : self::chambres($perimetre),
                'date' => ucfirst(now()->locale('fr')->translatedFormat('l j F Y')), 'moment' => now()->hour < 18 ? 'Bonjour' : 'Bonsoir',
            ],
            'droits' => $droits,
            'kpis' => array_slice($candidats, 0, 4),
            'courbe' => $droits['affaires'] && $chiffres ? self::courbe($chiffres['mensuel'], $mois) : null,
            'suites' => ($droits['affaires'] || $droits['roles']) && $chiffres ? array_values(array_filter($chiffres['suites'], fn ($s) => $s['n'] > 0)) : null,
            'chambres' => $droits['affaires'] && $chiffres ? self::chambresActives($chiffres['par_chambre']) : null,
            'audiences' => $audiences,
            'recentes' => $droits['affaires'] ? self::recentes($user) : null,
            'retards' => $droits['alertes'] && $chiffres ? self::retards($chiffres) : null,
            'liens' => [
                'affaires' => $droits['affaires'] ? route('affaires.liste') : null, 'roles' => $droits['roles'] ? route('audiences.roles') : null,
                'alertes' => $droits['alertes'] ? route('alertes.suivi') : null, 'pilotage' => $droits['tableau'] ? route('audiences.pilotage') : null,
            ],
            'genere_le' => now()->format('H:i'),
        ];
    }

    /** @param  array{mois: list<string>, entrees: list<int>, sorties: list<int>}  $m */
    private static function courbe(array $m, int $mois): array
    {
        return ['mois' => array_slice($m['mois'], -$mois), 'entrees' => array_slice($m['entrees'], -$mois), 'sorties' => array_slice($m['sorties'], -$mois)];
    }

    /** Les chambres où il y a des affaires en cours, avec celles qui sont en retard (les plus chargées d'abord). @param  list<array<string, mixed>>  $parChambre  @return list<array<string, mixed>> */
    private static function chambresActives(array $parChambre): array
    {
        return collect($parChambre)->filter(fn ($c) => $c['stock'] > 0 || $c['retards'] > 0)->sortByDesc('stock')->take(6)
            ->map(fn ($c) => ['libelle' => $c['libelle'], 'stock' => $c['stock'], 'retards' => $c['retards']])->values()->all();
    }

    /** Les prochains rôles publiés. @return list<array<string, mixed>> */
    private static function audiences(User $user): array
    {
        return RoleAudience::visiblesPour($user)->where('statut', 'publie')->whereDate('date_audience', '>=', today()->toDateString())
            ->with('chambre:id,libelle')->withCount('lignes')->orderBy('date_audience')->orderBy('heure')->limit(12)->get()
            ->map(fn (RoleAudience $r) => [
                'id' => $r->id, 'date' => $r->date_audience->toDateString(), 'chambre' => (string) $r->chambre?->libelle, 'type' => $r->typeLibelle(), 'affaires' => (int) $r->lignes_count,
                'jour' => $r->date_audience->format('d/m'), 'quand' => $r->date_audience->isToday() ? "aujourd'hui" : ($r->date_audience->isTomorrow() ? 'demain' : ucfirst($r->date_audience->locale('fr')->translatedFormat('l j F'))),
                'heure' => $r->heure ? substr((string) $r->heure, 0, 5) : null, 'dans_la_semaine' => $r->date_audience->lte(today()->addDays(6)),
                'aujourdhui' => $r->date_audience->isToday(), 'url' => route('audiences.roles.show', $r->id),
            ])->all();
    }

    /** Les dernières affaires enrôlées (numéro, objet, chambre) : pas de nom de partie sur l'accueil. @return list<array<string, mixed>> */
    private static function recentes(User $user): array
    {
        return Affaire::visiblesPour($user)->where('statut', 'enrolee')->with('chambre:id,libelle')->orderByDesc('date_rg')->orderByDesc('id')->limit(4)->get()
            ->map(fn (Affaire $a) => [
                'id' => $a->id, 'numero' => $a->numeroAffichage(), 'objet' => Str::limit((string) $a->objet, 70), 'chambre' => (string) $a->chambre?->libelle,
                'date' => $a->date_rg?->toDateString(), 'date_libelle' => $a->date_rg?->format('d/m/Y'), 'url' => route('affaires.liste.fiche', $a->id),
            ])->all();
    }

    /** Les alertes les plus urgentes. @param  array<string, mixed>  $chiffres  @return list<array<string, mixed>> */
    private static function retards(array $chiffres): array
    {
        return array_map(fn (array $a) => [
            'type' => $a['type_libelle'], 'niveau' => $a['niveau'], 'titre' => $a['titre'], 'chambre' => $a['chambre'], 'echeance' => $a['echeance'], 'retard' => $a['retard'], 'url' => $a['url'],
        ], array_slice($chiffres['alertes_recentes'] ?? [], 0, 6));
    }

    /** @param  list<int>  $ids */
    private static function chambres(array $ids): string
    {
        $noms = Chambre::whereIn('id', $ids)->orderBy('id')->pluck('libelle');

        return $noms->isEmpty() ? 'Aucune chambre' : ($noms->count() > 3 ? $noms->take(3)->implode(', ').' et '.($noms->count() - 3).' autre(s)' : $noms->implode(', '));
    }
}
