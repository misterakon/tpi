<?php

namespace App\Support\Alertes;

use App\Models\Affaire;
use App\Models\DelaiEtape;
use App\Models\DelaiProcedure;
use App\Models\Partie;
use App\Models\RoleAudience;
use App\Support\Audiences\SuiviAffaires;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/*
 | Les échéances du circuit, calculées d'après ce que l'application sait déjà (enrôlement, rôles, feuilles d'audience, délibérés, décisions,
 | délais de procédure, détention). Rien n'est saisi à part : si l'action est faite, l'échéance disparaît de cette liste et l'alerte se referme.
 |
 |   mise_au_role     affaire enrôlée dont la première audience approche sans figurer sur un rôle
 |   publication_role rôle d'audience non publié
 |   feuille_a_saisir audience passée dont des affaires n'ont pas de suite notée (une alerte par audience)
 |   renvoi           affaire renvoyée : elle doit revenir sur un rôle à la date de renvoi
 |   delibere         délibéré à rendre (date de prononcé, ou délai par défaut)
 |   redaction        affaire jugée sans décision validée
 |   jamais_portee    première audience passée sans passage sur un rôle
 |   delai_procedure  délai global de la procédure (civil 6 mois, correctionnel 3 mois, référé-expulsion 15 jours)
 |   limite_renvois   nombre de renvois autorisé dépassé (un seul en référé-expulsion)
 |   detention        échéance du mandat de dépôt d'un détenu
 |   repartition_requete requête déposée et non répartie entre les formations (délai réglé dans Réglages > Affaires)
 */
class CollecteEcheances
{
    /** Chemin relatif d'une page (sans le domaine) : il reste juste même si l'adresse du site change, et se complète à l'affichage. */
    private static function chemin(string $nom, mixed $parametres = []): string
    {
        return route($nom, $parametres, false);
    }

    /** @return Collection<int, Echeance> */
    public static function toutes(?Carbon $jour = null): Collection
    {
        $jour = ($jour ?? today())->copy()->startOfDay();
        DelaiEtape::oublier();
        $etats = SuiviAffaires::etats(null, $jour);

        return collect()
            ->concat(self::depuisLesEtats($etats))
            ->concat(self::delaisDeProcedure($etats))
            ->concat(self::limiteDeRenvois($etats))
            ->concat(self::rolesNonPublies($jour))
            ->concat(self::detentions())
            ->concat(self::requetesARepartir())
            ->concat(self::decisionsEnAttente())
            ->concat(self::minutesPenales())
            ->concat(self::certificatsARecours($jour))
            ->values();
    }

    /** @param  Collection<int, array<string, mixed>>  $etats  @return Collection<int, Echeance> */
    private static function depuisLesEtats(Collection $etats): Collection
    {
        $sortie = collect();
        $fiche = fn (int $id) => self::chemin('affaires.liste.fiche', $id);

        foreach ($etats as $e) {
            $affaire = (int) $e['affaire_id'];
            $chambre = $e['chambre_id'] ? (int) $e['chambre_id'] : null;
            $n = $e['numero'];

            switch ($e['etat']) {
                case 'a_venir':
                    if ($e['ligne_id'] === null && $e['premiere']) {
                        $sortie->push(new Echeance('mise_au_role', $affaire, $affaire, $chambre, DelaiEtape::echeance('mise_au_role', $e['premiere'], -1),
                            "Affaire $n : première audience le ".$e['premiere']->format('d/m/Y').' sans figurer sur un rôle', "Mettre l'affaire sur le rôle de l'audience", $fiche($affaire)));
                    }
                    break;
                case 'non_portee':
                    $sortie->push(new Echeance('jamais_portee', $affaire, $affaire, $chambre, $e['premiere'],
                        "Affaire $n : première audience du ".$e['premiere']->format('d/m/Y').' passée sans passage sur un rôle', "Mettre l'affaire sur un rôle d'audience", $fiche($affaire)));
                    break;
                case 'renvoyee':
                    if ($e['echeance']) {
                        $sortie->push(new Echeance('renvoi', $affaire, $affaire, $chambre, $e['echeance']->copy(),
                            "Affaire $n : renvoyée au ".$e['echeance']->format('d/m/Y').', elle doit revenir sur un rôle', "Mettre l'affaire sur le rôle de la date de renvoi", $fiche($affaire)));
                    }
                    break;
                case 'deliberee':
                    $date = $e['echeance'] ? $e['echeance']->copy() : ($e['derniere_audience'] ? DelaiEtape::echeance('delibere_sans_date', $e['derniere_audience']) : null);
                    if ($date) {
                        $sortie->push(new Echeance('delibere', $affaire, $affaire, $chambre, $date,
                            "Affaire $n : délibéré à rendre le ".$date->format('d/m/Y'), 'Rendre la décision ou proroger le délibéré', self::chemin('audiences.renvois')));
                    }
                    break;
                case 'jugee':
                    if ($e['a_rediger'] && $e['derniere_audience']) {
                        $date = DelaiEtape::echeance('redaction_decision', $e['derniere_audience']);
                        $sortie->push(new Echeance('redaction', $affaire, $affaire, $chambre, $date,
                            "Affaire $n : jugée le ".$e['derniere_audience']->format('d/m/Y').', décision à rédiger et à valider', 'Rédiger et valider la décision', self::chemin('decisions.redaction', ['affaire' => $affaire])));
                    }
                    break;
            }
        }

        // Les suites non notées : une seule alerte par audience, pas une par affaire.
        foreach ($etats->where('etat', 'sans_suite')->groupBy('role_id') as $roleId => $lignes) {
            $premiere = $lignes->first();
            $quand = $premiere['derniere_audience'];
            if (! $quand) {
                continue;
            }
            $nb = $lignes->count();
            $sortie->push(new Echeance('feuille_a_saisir', (int) $roleId, null, $premiere['chambre_id'] ? (int) $premiere['chambre_id'] : null, DelaiEtape::echeance('saisie_suites', $quand),
                "Audience du ".$quand->format('d/m/Y').' ('.$premiere['chambre'].') : '.$nb.' affaire'.($nb > 1 ? 's' : '').' sans suite notée', "Noter la suite de chaque affaire sur la feuille d'audience", self::chemin('audiences.feuille.show', $roleId)));
        }

        return $sortie;
    }

    /** Délai global de la procédure : le plus précis des délais du référentiel qui s'applique à l'affaire (nature d'abord, puis registre). */
    private static function delaisDeProcedure(Collection $etats): Collection
    {
        $delais = DelaiProcedure::where('actif', true)->get();
        if ($delais->isEmpty()) {
            return collect();
        }
        $affaires = Affaire::whereIn('id', $etats->where('en_cours', true)->pluck('affaire_id'))->get(['id', 'serie', 'nature_affaire_id', 'date_rg'])->keyBy('id');
        $sortie = collect();

        foreach ($etats->where('en_cours', true) as $e) {
            $a = $affaires->get($e['affaire_id']);
            if (! $a) {
                continue;
            }
            $delai = $delais->filter(fn ($d) => ($d->nature_affaire_id === null || (int) $d->nature_affaire_id === (int) $a->nature_affaire_id) && ($d->registre === null || $d->registre === $a->serie)
                && ($d->nature_affaire_id !== null || $d->registre !== null))
                ->sortByDesc(fn ($d) => ($d->nature_affaire_id ? 2 : 0) + ($d->registre ? 1 : 0))->first();
            $depart = $delai ? match ($delai->point_depart) {
                'ouverture_audience' => $e['premiere'], 'enrolement' => $a->date_rg, default => null,
            } : null;
            if (! $delai || ! $depart) {
                continue;
            }
            $date = DelaiEtape::ajouter($depart, (int) $delai->valeur, $delai->unite);
            $sortie->push(new Echeance('delai_procedure', (int) $a->id, (int) $a->id, $e['chambre_id'] ? (int) $e['chambre_id'] : null, $date,
                'Affaire '.$e['numero'].' : délai de procédure ('.$delai->libelle.', '.$delai->valeur.' '.(DelaiProcedure::UNITES[$delai->unite] ?? $delai->unite).') au '.$date->format('d/m/Y'),
                "Faire avancer l'affaire : la décision est attendue avant l'échéance", self::chemin('affaires.liste.fiche', $a->id)));
        }

        return $sortie;
    }

    /** Le nombre de renvois autorisé (un seul en référé-expulsion) est dépassé. */
    private static function limiteDeRenvois(Collection $etats): Collection
    {
        $limites = DelaiProcedure::where('actif', true)->whereNotNull('renvois_max')->whereNotNull('nature_affaire_id')->get();
        if ($limites->isEmpty()) {
            return collect();
        }
        $natures = Affaire::whereIn('id', $etats->where('en_cours', true)->where('nb_renvois', '>', 0)->pluck('affaire_id'))->pluck('nature_affaire_id', 'id');
        $sortie = collect();

        foreach ($etats->where('en_cours', true)->where('nb_renvois', '>', 0) as $e) {
            $limite = $limites->firstWhere('nature_affaire_id', $natures->get($e['affaire_id']));
            if ($limite && $e['nb_renvois'] > $limite->renvois_max && $e['derniere_audience']) {
                $sortie->push(new Echeance('limite_renvois', (int) $e['affaire_id'], (int) $e['affaire_id'], $e['chambre_id'] ? (int) $e['chambre_id'] : null, $e['derniere_audience']->copy(),
                    'Affaire '.$e['numero'].' : '.$e['nb_renvois'].' renvois, la limite est de '.$limite->renvois_max.' ('.$limite->libelle.')',
                    "Vérifier le renvoi : la limite autorisée est dépassée", self::chemin('affaires.liste.fiche', $e['affaire_id'])));
            }
        }

        return $sortie;
    }

    /** Les rôles d'audience à venir qui ne sont pas publiés. */
    private static function rolesNonPublies(Carbon $jour): Collection
    {
        return RoleAudience::with('chambre:id,libelle')->where('statut', 'brouillon')->whereDate('date_audience', '>=', $jour->toDateString())->get()
            ->map(fn (RoleAudience $r) => new Echeance('publication_role', (int) $r->id, null, (int) $r->chambre_id, DelaiEtape::echeance('publication_role', $r->date_audience, -1),
                'Rôle du '.$r->date_audience->format('d/m/Y').' ('.$r->chambre?->libelle.') non publié', 'Compléter puis publier le rôle', self::chemin('audiences.roles.show', $r->id)));
    }

    /** Requêtes déposées et pas encore réparties : le secrétariat de la Présidente doit les confier à une formation. */
    private static function requetesARepartir(): Collection
    {
        $delai = (int) config('tpi.requetes.delai_repartition_jours', 2);

        return \App\Models\Requete::where('statut', 'recue')->get()->map(fn (\App\Models\Requete $r) => new Echeance(
            'repartition_requete', (int) $r->id, $r->affaire_id ? (int) $r->affaire_id : null, null, $r->date_depot->copy()->addDays($delai)->startOfDay(),
            'Requête '.$r->numero_complet.' déposée le '.$r->date_depot->format('d/m/Y').', à répartir entre les formations', 'Répartir la requête vers une formation', self::chemin('affaires.requetes'),
        ));
    }

    /** Décisions rédigées à valider et décisions validées à signer : chaque étape a son délai (Alertes > Délais par étape). */
    private static function decisionsEnAttente(): Collection
    {
        $sortie = collect();
        foreach (\App\Models\Decision::whereIn('statut', ['redigee', 'validee'])->get() as $d) {
            $affaire = $d->affaire_id ? (int) $d->affaire_id : null;
            $chambre = $d->chambre_id ? (int) $d->chambre_id : null;
            if ($d->statut === 'redigee' && $d->redigee_le) {
                $sortie->push(new Echeance('validation_decision', (int) $d->id, $affaire, $chambre, DelaiEtape::echeance('validation_decision', $d->redigee_le),
                    'Décision '.$d->reference.' rédigée le '.$d->redigee_le->format('d/m/Y').', à valider', 'Valider la décision', self::chemin('decisions.redaction.edition', $d->id)));
            } elseif ($d->statut === 'validee' && $d->validee_le) {
                $sortie->push(new Echeance('signature_decision', (int) $d->id, $affaire, $chambre, DelaiEtape::echeance('signature_decision', $d->validee_le),
                    'Décision '.$d->reference.' validée le '.$d->validee_le->format('d/m/Y').', à signer', 'Signer la décision', self::chemin('decisions.redaction.edition', $d->id)));
            }
        }

        return $sortie;
    }

    /** Détenus : l'échéance du mandat de dépôt. */
    private static function detentions(): Collection
    {
        return Partie::with('affaire:id,chambre_id,statut,serie,numero,annee')->whereNotNull('mandat_depot_date')->whereNull('libere_le')->get()
            ->filter(fn (Partie $p) => $p->affaire && $p->affaire->statut === 'enrolee' && $p->echeance())
            ->map(fn (Partie $p) => new Echeance('detention', (int) $p->id, (int) $p->affaire_id, $p->affaire->chambre_id ? (int) $p->affaire->chambre_id : null, $p->echeance()->copy()->startOfDay(),
                'Affaire '.$p->affaire->numeroAffichage().' : échéance du mandat de dépôt au '.$p->echeance()->format('d/m/Y'), 'Prolonger le mandat ou ordonner la mise en liberté', self::chemin('affaires.liste.fiche', $p->affaire_id)))
            ->values();
    }

    /** Décisions pénales dont la minute n'est pas transmise : échéance = date de la décision + délai de vigilance (config tpi.penal.minute_vigilance_jours). */
    private static function minutesPenales(): Collection
    {
        $jours = (int) config('tpi.penal.minute_vigilance_jours', 30);

        return \App\Models\DecisionPenale::whereNull('date_transmission_minute')->with('affaire:id,chambre_id,statut,serie,numero,annee,numero_complet')->get()
            ->filter(fn ($d) => $d->affaire && $d->affaire->statut === 'enrolee')
            ->map(fn ($d) => new Echeance('minute_a_transmettre', (int) $d->id, (int) $d->affaire_id, $d->affaire->chambre_id ? (int) $d->affaire->chambre_id : null, $d->date_decision->copy()->addDays($jours)->startOfDay(),
                'Affaire '.$d->affaire->numeroAffichage().' : minute de la décision du '.$d->date_decision->format('d/m/Y').' à transmettre', 'Transmettre la minute', self::chemin('decisions.repertoire_penal')))
            ->values();
    }

    /** Décisions signifiées dont le délai de recours (confirmé) a expiré sans recours : le certificat peut être délivré. */
    private static function certificatsARecours(Carbon $jour): Collection
    {
        return \App\Support\Recours\DelaisRecours::certificatsAPreparer($jour)
            ->map(fn ($x) => new Echeance('certificat_recours', (int) $x['decision']->id, $x['decision']->affaire_id ? (int) $x['decision']->affaire_id : null, $x['decision']->chambre_id ? (int) $x['decision']->chambre_id : null, $x['etat']['echeance']->copy()->addDay()->startOfDay(),
                'Décision '.($x['decision']->numeroAffichage() ?? $x['decision']->reference).' : délai de recours expiré le '.$x['etat']['echeance']->format('d/m/Y').', certificat à délivrer', 'Délivrer le certificat', self::chemin('decisions.recours')))
            ->values();
    }
}
