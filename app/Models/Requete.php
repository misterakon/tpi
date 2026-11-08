<?php

namespace App\Models;

use App\Support\PerimetreChambres;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 | Une requête (dont la requête d'hérédité) : déposée au greffe, répartie par le secrétariat de la Présidente entre les formations,
 | puis traitée (ordonnance, jugement) ou classée. Ne se supprime jamais : son numéro RQ n'est jamais réutilisé.
 */
class Requete extends Model
{
    public const TYPES = [
        'heredite' => "Requête en hérédité", 'ordonnance_requete' => 'Ordonnance sur requête (civil)', 'voies_execution' => "Ordonnance sur requête (voies d'exécution)",
        'injonction' => 'Injonction de payer', 'bref_delai' => 'Requête à bref délai (saisie conservatoire, rétractation…)', 'autre' => 'Autre requête',
    ];

    /** Les ordonnances (et injonctions) dont la signature est réservée : on ne les marque « traitées » qu'avec le droit requetes.signer. */
    public const SIGNATURE_RESERVEE = ['ordonnance_requete', 'voies_execution', 'injonction', 'bref_delai'];

    public const STATUTS = ['recue' => 'À répartir', 'repartie' => 'Répartie', 'traitee' => 'Traitée', 'classee' => 'Classée sans suite'];

    protected $table = 'requetes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_depot' => 'date', 'defunt_date_deces' => 'date', 'reparti_le' => 'datetime', 'traitee_le' => 'date'];
    }

    public function chambre(): BelongsTo
    {
        return $this->belongsTo(Chambre::class);
    }

    public function affaire(): BelongsTo
    {
        return $this->belongsTo(Affaire::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function repartiteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reparti_par');
    }

    public function estHeredite(): bool
    {
        return $this->type === 'heredite';
    }

    public function signatureReservee(): bool
    {
        return in_array($this->type, self::SIGNATURE_RESERVEE, true);
    }

    public function signataire(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'signee_par');
    }

    public function estOuverte(): bool
    {
        return in_array($this->statut, ['recue', 'repartie'], true);
    }

    /** « RQ 12/26 ». */
    public function numeroAffichage(): string
    {
        return 'RQ '.$this->numero.'/'.sprintf('%02d', $this->annee % 100);
    }

    /** Requérants, un par ligne. @return list<string> */
    public function listeRequerants(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->requerants) ?: [])));
    }

    /** Jours écoulés depuis le dépôt sans répartition (null si déjà répartie ou traitée). */
    public function joursSansRepartition(): ?int
    {
        return $this->statut === 'recue' ? (int) $this->date_depot->startOfDay()->diffInDays(now()->startOfDay()) : null;
    }

    public function enRetard(): bool
    {
        return ($j = $this->joursSansRepartition()) !== null && $j > (int) config('tpi.requetes.delai_repartition_jours', 2);
    }

    /**
     * Les requêtes visibles par l'utilisateur : toutes pour le secrétariat qui répartit et pour qui voit toutes les chambres ;
     * sinon celles qu'il a déposées et celles réparties vers ses chambres.
     */
    public function scopeVisiblesPour(Builder $q, ?User $user): Builder
    {
        if (! $user || ! $user->actif) {
            return $q->whereRaw('1 = 0');
        }
        $chambres = PerimetreChambres::de($user);
        if ($chambres === null || $user->peut('requetes.repartir')) {
            return $q;
        }

        return $q->where(fn (Builder $w) => $w->where('cree_par', $user->id)->orWhereIn('chambre_id', $chambres));
    }
}
