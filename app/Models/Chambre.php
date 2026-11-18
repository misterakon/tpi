<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
 | Chambre (ou juridiction, ou cabinet d'instruction) d'une section pour une année judiciaire : elle a ses attributions.
 | Ses formations sont facultatives ; sans formation nommée, une formation unique (sans nom) porte la composition.
 */
class Chambre extends Model
{
    protected $table = 'chambres';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function annee(): BelongsTo
    {
        return $this->belongsTo(AnneeJudiciaire::class, 'annee_judiciaire_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class)->orderBy('ordre');
    }

    /**
     * La chambre « présidentielle » (affaires dont l'intérêt pécuniaire excède 100 000 000 FCFA, d'après l'ordonnance) : c'est la Présidente
     * qui y affecte les affaires. Les autres ne peuvent pas la choisir à l'enregistrement d'une affaire ; elles la lui proposent, et elle réattribue.
     */
    public function affectationReservee(): bool
    {
        return (bool) preg_match('/pr[ée]sidentiel/iu', (string) $this->intitule);
    }

    /** Cet utilisateur peut-il affecter une affaire à cette chambre ? (la Présidente et ceux qui ont le droit de réattribuer, pour la chambre réservée) */
    public function affectablePar(?User $user): bool
    {
        return ! $this->affectationReservee() || (bool) $user?->peut('affaires.reattribuer');
    }

    /** « 2e chambre civile – Immobilière et des paiements » */
    public function libelleComplet(): string
    {
        return $this->intitule ? $this->libelle.' – '.$this->intitule : $this->libelle;
    }
}
