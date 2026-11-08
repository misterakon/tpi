<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Partie d'une affaire : demandeur ou défendeur, personne physique, couple, personne morale ou État. */
class Partie extends Model
{
    public const ROLES = ['demandeur' => 'Demandeur', 'defendeur' => 'Défendeur', 'prevenu' => 'Prévenu', 'inculpe' => 'Inculpé', 'victime' => 'Victime ou partie civile'];

    public const ROLES_PLURIEL = ['demandeur' => 'Demandeurs', 'defendeur' => 'Défendeurs', 'prevenu' => 'Prévenus', 'inculpe' => 'Inculpés', 'victime' => 'Victimes et parties civiles'];

    public const NATURES = ['homme' => 'Homme', 'femme' => 'Femme', 'couple' => 'Couple', 'morale' => 'Personne morale', 'etat' => 'État ou collectivité', 'mp' => 'Ministère public'];

    protected $table = 'parties';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['naissance_date' => 'date', 'mandat_depot_date' => 'date', 'echeance_detention' => 'date', 'libere_le' => 'date'];
    }

    public function affaire(): BelongsTo
    {
        return $this->belongsTo(Affaire::class);
    }

    public function estPersonnePhysique(): bool
    {
        return in_array($this->nature, ['homme', 'femme'], true);
    }

    /** « KOUASSI Yao » ou la dénomination, avec « et x autres » pour un groupe. */
    public function libelle(): string
    {
        $base = $this->estPersonnePhysique() ? trim(mb_strtoupper((string) $this->nom).' '.$this->prenoms) : (string) $this->denomination;

        return $this->autres > 0 ? $base.' et '.$this->autres.' autre'.($this->autres > 1 ? 's' : '') : $base;
    }

    /** Âge en années, d'après la date de naissance ou l'année seule ; null si on ne le sait pas. */
    public function age(): ?int
    {
        if ($this->naissance_date) {
            return (int) $this->naissance_date->diffInYears(now());
        }

        return $this->naissance_annee ? (int) date('Y') - (int) $this->naissance_annee : null;
    }

    /** « 23 ans, béninois, vigile » : l'identité d'un prévenu telle qu'elle figure sur les rôles pénaux. */
    public function identite(): string
    {
        return collect([$this->age() !== null ? $this->age().' ans' : null, $this->nationalite, $this->profession])->filter()->implode(', ');
    }

    /** Détenu : un mandat de dépôt a été décerné et la personne n'a pas été mise en liberté. */
    public function estDetenu(): bool
    {
        return $this->mandat_depot_date !== null && $this->libere_le === null;
    }

    /** Échéance de la détention : la date saisie (mandat, prolongation) ou, à défaut, le mandat plus la durée réglée. */
    public function echeance(): ?\Illuminate\Support\Carbon
    {
        if (! $this->estDetenu()) {
            return null;
        }

        return $this->echeance_detention ?? $this->mandat_depot_date->copy()->addDays((int) config('tpi.penal.detention_jours', 180));
    }

    public function echeanceCalculee(): bool
    {
        return $this->estDetenu() && $this->echeance_detention === null;
    }

    /** Jours avant l'échéance (négatif : dépassée), ou null si la personne n'est pas détenue. */
    public function joursRestants(): ?int
    {
        $e = $this->echeance();

        return $e ? (int) now()->startOfDay()->diffInDays($e->copy()->startOfDay(), false) : null;
    }

    /** depassee | proche | normal, ou null si la personne n'est pas détenue. */
    public function niveauDetention(): ?string
    {
        $j = $this->joursRestants();

        return $j === null ? null : ($j < 0 ? 'depassee' : ($j <= (int) config('tpi.penal.alerte_jours', 30) ? 'proche' : 'normal'));
    }

    /** « le 01 mars 1970 » ou « vers 1970 ». */
    public function naissance(): ?string
    {
        if ($this->naissance_mode === 'annee' && $this->naissance_annee) {
            return 'vers '.$this->naissance_annee;
        }

        return $this->naissance_date ? 'le '.$this->naissance_date->locale('fr')->translatedFormat('d F Y') : null;
    }

    /** Nom normalisé (sans accents, majuscules ni ponctuation) pour repérer un doublon. */
    public static function cle(string $texte): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii($texte))));
    }
}
