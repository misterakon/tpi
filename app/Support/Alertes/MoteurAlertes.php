<?php

namespace App\Support\Alertes;

use App\Mail\RecapAlertes;
use App\Models\Alerte;
use App\Models\AlerteEnvoi;
use App\Models\AlerteEvaluation;
use App\Models\RegleAlerte;
use App\Models\User;
use App\Notifications\AlerteNotification;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/*
 | Moteur d'alertes, appelé chaque jour par la commande « alertes:evaluer ».
 |
 |  1. Il calcule les échéances du circuit (CollecteEcheances) : tout ce qui doit être fait, et pour quand.
 |  2. Il referme les alertes dont l'action a été faite (l'échéance a disparu) et rafraîchit le texte des autres.
 |  3. Pour chaque règle active et chaque échéance visée, l'alerte part le jour exact où « échéance + décalage = jour évalué » (J-3, J+1…).
 |  4. Les destinataires viennent des affectations : greffier de la chambre, président de formation, Présidente…
 |  5. Notification dans l'application tout de suite ; e-mail : un seul récapitulatif par personne.
 |
 | Une alerte ne part jamais deux fois à la même personne par le même canal (table alertes_envois). Une alerte justifiée (retard expliqué)
 | est suspendue jusqu'à la date indiquée. Un serveur resté éteint ne fait rien perdre : les jours manqués sont rattrapés (14 jours au plus).
 */
class MoteurAlertes
{
    public const RATTRAPAGE_MAX_JOURS = 14;

    /** Destinataires ajoutés à ceux des règles pour certains types d'échéance. */
    private const DESTINATAIRES_DU_TYPE = ['repartition_requete' => ['secretariat_presidence']];

    private const ORDRE_NIVEAU = ['info' => 1, 'attention' => 2, 'critique' => 3];

    private Destinataires $destinataires;

    /** @var array<int, array{user: User, items: list<array<string, string>>, envois: list<array<string, mixed>>}> récapitulatifs e-mail en attente */
    private array $courriels = [];

    public function __construct(private readonly bool $simuler = false, private readonly ?Closure $journal = null)
    {
        $this->destinataires = new Destinataires;
    }

    /**
     * Évalue tous les jours depuis la dernière évaluation jusqu'à $jusqu (au plus 14 jours en arrière), ou seulement $jusqu s'il n'y en a pas.
     *
     * @return array<string, int>  totaux
     */
    public function rattraper(Carbon $jusqu): array
    {
        $jusqu = $jusqu->copy()->startOfDay();
        $derniere = AlerteEvaluation::max('jour');
        $debut = $derniere ? Carbon::parse($derniere)->startOfDay()->addDay() : $jusqu->copy();
        if ($debut->gt($jusqu)) {
            $debut = $jusqu->copy();   // déjà évalué : on refait ce jour (sans risque de doublon)
        }
        if ($jusqu->diffInDays($debut) > self::RATTRAPAGE_MAX_JOURS) {
            $debut = $jusqu->copy()->subDays(self::RATTRAPAGE_MAX_JOURS);
        }

        $total = [];
        for ($jour = $debut->copy(); $jour->lte($jusqu); $jour->addDay()) {
            foreach ($this->evaluer($jour) as $cle => $valeur) {
                $total[$cle] = ($total[$cle] ?? 0) + $valeur;
            }
        }

        return $total;
    }

    /** @return array<string, int> */
    public function evaluer(Carbon $jour): array
    {
        $jour = $jour->copy()->startOfDay();
        $stats = ['echeances' => 0, 'declenchees' => 0, 'envoyees' => 0, 'deja_envoyees' => 0, 'justifiees' => 0, 'sans_destinataire' => 0, 'refermees' => 0, 'courriels' => 0, 'echecs' => 0];
        $this->courriels = [];

        $echeances = CollecteEcheances::toutes($jour)->keyBy(fn (Echeance $e) => $e->cle());
        $stats['echeances'] = $echeances->count();
        $this->ligne("Évaluation du {$jour->format('d/m/Y')} : {$stats['echeances']} échéance(s) suivie(s).");

        $stats['refermees'] = $this->refermer($echeances);

        $regles = RegleAlerte::where('actif', true)->get()->filter(fn (RegleAlerte $r) => ! empty($r->canaux) && ! empty($r->destinataires))->values();
        foreach ($echeances as $e) {
            foreach ($regles as $regle) {
                if (! $regle->vise($e->type) || ! $e->date->copy()->addDays($regle->decalage_jours)->isSameDay($jour)) {
                    continue;
                }
                $stats['declenchees']++;
                $this->declencher($e, $regle, $jour, $stats);
            }
        }

        $this->envoyerLesCourriels($stats);

        if (! $this->simuler) {
            // whereDate : le jour est comparé comme une date, quel que soit le format que la base lui donne.
            $evaluation = AlerteEvaluation::whereDate('jour', $jour->toDateString())->first() ?? new AlerteEvaluation(['jour' => $jour->toDateString()]);
            $evaluation->fill(['declenchees' => $stats['declenchees'], 'envoyees' => $stats['envoyees'], 'refermees' => $stats['refermees'], 'echecs' => $stats['echecs']])->save();
        }

        return $stats;
    }

    /** Referme les alertes dont l'échéance a disparu (action faite) ; met à jour le texte des autres. Renvoie le nombre refermé. */
    private function refermer(Collection $echeances): int
    {
        $ouvertes = Alerte::ouvertes()->get();
        $aFermer = $ouvertes->reject(fn (Alerte $a) => $echeances->has($a->cle));
        if (! $this->simuler) {
            foreach ($aFermer->chunk(500) as $lot) {
                Alerte::whereIn('id', $lot->pluck('id'))->update(['resolue_le' => now()]);
            }
            foreach ($ouvertes->filter(fn (Alerte $a) => $echeances->has($a->cle)) as $a) {
                $e = $echeances->get($a->cle);
                if ($a->titre !== $e->titre || $a->action !== $e->action || $a->url !== $e->url) {
                    $a->forceFill(['titre' => $e->titre, 'action' => $e->action, 'url' => $e->url])->save();
                }
            }
        }
        if ($aFermer->isNotEmpty()) {
            $this->ligne($aFermer->count().' alerte(s) refermée(s) : l\'action a été faite.');
        }

        return $aFermer->count();
    }

    /** @param  array<string, int>  $stats */
    private function declencher(Echeance $e, RegleAlerte $regle, Carbon $jour, array &$stats): void
    {
        $alerte = $this->alerte($e, $regle);
        if ($alerte->estJustifiee($jour)) {
            $stats['justifiees']++;
            $this->ligne("  {$regle->code} {$e->titre} : retard justifié, suspendue.");

            return;
        }

        // Certaines échéances ont leurs destinataires propres, en plus de ceux de la règle (une requête non répartie : le secrétariat de la Présidence).
        $roles = array_values(array_unique(array_merge($regle->destinataires, self::DESTINATAIRES_DU_TYPE[$e->type] ?? [])));
        $users = $this->destinataires->pour($e->chambreId, $roles);
        $reorientee = false;
        // Les personnes visées par métier (hors administrateurs) : si aucune n'a de compte, l'alerte est signalée comme « sans destinataire désigné ».
        $metier = array_values(array_diff($roles, ['administrateur']));
        if ($metier !== [] && $this->destinataires->pour($e->chambreId, $metier)->isEmpty()) {
            $stats['sans_destinataire']++;
            $this->ligne("  {$regle->code} {$e->titre} : aucun destinataire désigné (agent sans compte), transmise aux administrateurs.", 'warn');
            $reorientee = true;
            // Les administrateurs la reçoivent même si la règle ne les nomme pas : l'alerte ne doit jamais se perdre.
            $users = $users->concat($this->destinataires->pour(null, ['administrateur']))->unique('id')->values();
        }

        foreach ($users as $user) {
            foreach (array_intersect($regle->canaux, array_keys(RegleAlerte::CANAUX)) as $canal) {
                if ($alerte->exists && AlerteEnvoi::where(['alerte_id' => $alerte->id, 'regle_alerte_id' => $regle->id, 'canal' => $canal, 'user_id' => $user->id])->exists()) {
                    $stats['deja_envoyees']++;

                    continue;
                }
                $this->ligne("  {$regle->code} → {$user->name} ({$canal}) : {$e->titre}");
                if ($canal === 'application') {
                    if (! $this->simuler) {
                        $user->notify(new AlerteNotification($alerte, $regle, $reorientee));
                        $this->noter($alerte, $regle, $canal, $user);
                    }
                    $stats['envoyees']++;
                } elseif ($user->email) {
                    $this->courriels[$user->id]['user'] = $user;
                    $this->courriels[$user->id]['items'][] = ['niveau' => $regle->niveau, 'titre' => ($reorientee ? '[Sans destinataire désigné] ' : '').$e->titre, 'action' => $e->action, 'echeance' => $e->date->format('d/m/Y'), 'regle' => $regle->code];
                    $this->courriels[$user->id]['envois'][] = ['alerte' => $alerte, 'regle' => $regle];
                }
            }
        }
    }

    /** L'alerte de cette situation : créée au premier déclenchement, rouverte si elle avait été refermée, niveau le plus haut atteint. */
    private function alerte(Echeance $e, RegleAlerte $regle): Alerte
    {
        $alerte = Alerte::firstOrNew(['cle' => $e->cle()]);
        $niveau = (self::ORDRE_NIVEAU[$regle->niveau] ?? 2) >= (self::ORDRE_NIVEAU[$alerte->niveau ?? 'info'] ?? 1) ? $regle->niveau : $alerte->niveau;
        $alerte->fill([
            'type' => $e->type, 'affaire_id' => $e->affaireId, 'chambre_id' => $e->chambreId, 'objet_id' => $e->objetId, 'echeance' => $e->date->toDateString(),
            'titre' => $e->titre, 'action' => $e->action, 'url' => $e->url, 'niveau' => $niveau, 'resolue_le' => null,
        ]);
        if (! $alerte->exists) {
            $alerte->ouverte_le = now();
        }
        if (! $this->simuler) {
            $alerte->save();
        }

        return $alerte;
    }

    private function noter(Alerte $alerte, RegleAlerte $regle, string $canal, User $user): void
    {
        AlerteEnvoi::firstOrCreate(['alerte_id' => $alerte->id, 'regle_alerte_id' => $regle->id, 'canal' => $canal, 'user_id' => $user->id], ['envoye_le' => now()]);
    }

    /** Un seul courriel par personne : le plus grave d'abord. */
    private function envoyerLesCourriels(array &$stats): void
    {
        foreach ($this->courriels as $lot) {
            /** @var User $user */
            $user = $lot['user'];
            $items = collect($lot['items'])->sortByDesc(fn ($i) => self::ORDRE_NIVEAU[$i['niveau']] ?? 0)->values()->all();
            if ($this->simuler) {
                $stats['courriels']++;
                $this->ligne("  e-mail récapitulatif à {$user->name} : ".count($items).' alerte(s).');

                continue;
            }
            try {
                Mail::to($user->email)->send(new RecapAlertes($user->name, $items, route('notifications.index')));
                foreach ($lot['envois'] as $envoi) {
                    $this->noter($envoi['alerte'], $envoi['regle'], 'email', $user);
                }
                $stats['courriels']++;
                $stats['envoyees'] += count($items);
            } catch (Throwable $e) {
                $stats['echecs']++;
                Log::warning("Alertes : récapitulatif non envoyé (utilisateur {$user->id}) : ".$e->getMessage());
                $this->ligne("  échec de l'e-mail récapitulatif (utilisateur {$user->id}).", 'warn');
            }
        }
        $this->courriels = [];
    }

    private function ligne(string $texte, string $niveau = 'info'): void
    {
        if ($this->journal) {
            ($this->journal)($texte, $niveau);
        }
    }
}
