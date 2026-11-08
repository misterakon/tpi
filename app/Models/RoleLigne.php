<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne du rôle : une affaire (ou une ligne libre) dans une des trois rubriques. */
class RoleLigne extends Model
{
    public const RUBRIQUES = ['deliberes' => 'Affaires en délibéré', 'anciennes' => 'Affaires anciennes', 'nouvelles' => 'Affaires nouvelles'];

    /** Titres imprimés, tels qu'ils figurent sur les rôles du tribunal. */
    public const TITRES = ['deliberes' => 'AFFAIRES EN DELIBERE', 'anciennes' => 'AFFAIRES ANCIENNES', 'nouvelles' => 'AFFAIRES NOUVELLES'];

    public const ISSUES = ['renvoi' => 'Renvoyée', 'delibere' => 'Mise en délibéré', 'juge' => 'Jugée', 'radie' => 'Radiée', 'joint' => 'Jointe à une autre', 'desiste' => 'Désistement'];

    /** Les suites qui terminent le parcours de l'affaire à l'audience : elle ne revient plus sur un rôle. */
    public const ISSUES_FINALES = ['juge', 'radie', 'joint', 'desiste'];

    /** Qui était présent à l'appel de l'affaire. */
    public const PRESENCES = ['toutes' => 'Toutes les parties', 'avocats' => 'Les avocats seulement', 'demandeur' => 'Demandeur seul', 'defendeur' => 'Défendeur seul', 'absentes' => 'Aucune partie'];

    /** Motifs de renvoi courants, proposés à la saisie (le greffier peut écrire le sien). */
    public const MOTIFS_RENVOI = [
        'Pour conclusions du demandeur', 'Pour conclusions du défendeur', 'Pour réplique', 'Pour production de pièces', 'Pour communication au ministère public',
        'Pour citation ou assignation de la partie adverse', 'Pour tentative de conciliation', 'À la demande des parties', 'Absence de la partie citée', 'Absence du conseil', 'Empêchement de la composition',
    ];

    protected $table = 'role_lignes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['renvoi_au' => 'date', 'date_delibere' => 'date', 'suite_le' => 'datetime'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(RoleAudience::class, 'role_id');
    }

    public function affaire(): BelongsTo
    {
        return $this->belongsTo(Affaire::class);
    }
}
