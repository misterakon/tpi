<?php

namespace App\Models;

use App\Support\PerimetreChambres;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/*
 | Affaire enrôlée. Son numéro (RG-0001/26) est attribué une fois pour toutes : il ne se modifie pas, l'affaire ne se supprime pas
 | (une annulation est un statut) et le numéro n'est jamais réutilisé.
 */
class Affaire extends Model
{
    public const STATUTS = ['enrolee' => 'Enregistrée', 'annulee' => 'Annulée'];

    protected $table = 'affaires';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_rg' => 'date', 'date_premiere_audience' => 'date', 'annulee_le' => 'datetime', 'numero_saisi' => 'boolean'];
    }

    /** Vrai seulement le temps d'une correction de numéro autorisée (voir corrigerLeNumero). */
    private bool $correctionAutorisee = false;

    /** La seule porte pour changer le numéro d'une affaire : réservée à la correction d'un numéro saisi à la main (droit « affaires.corriger_numero »). */
    public function corrigerLeNumero(int $numero, int $annee): void
    {
        $this->correctionAutorisee = true;
        try {
            $this->forceFill(['numero' => $numero, 'annee' => $annee, 'numero_complet' => \App\Services\NumerotationRegistre::formater($this->serie, $numero, $annee)])->save();
        } finally {
            $this->correctionAutorisee = false;
        }
    }

    protected static function booted(): void
    {
        static::updating(function (Affaire $a) {
            if (! $a->correctionAutorisee && $a->isDirty(['serie', 'annee', 'numero', 'numero_complet'])) {
                throw new LogicException("Le numéro d'une affaire ne peut pas être modifié.");
            }
        });
        static::deleting(function () {
            throw new LogicException("Une affaire ne se supprime pas : elle s'annule (le numéro n'est jamais réutilisé).");
        });
    }

    /** Les affaires des chambres que l'utilisateur a le droit de voir (voir PerimetreChambres). */
    public function scopeVisiblesPour(Builder $requete, ?User $user): Builder
    {
        $chambres = PerimetreChambres::de($user);

        return $chambres === null ? $requete : $requete->whereIn('chambre_id', $chambres ?: [0]);
    }

    /** Les passages de l'affaire en mise en état (une seule « en cours » à la fois). */
    public function misesEnEtat(): HasMany
    {
        return $this->hasMany(MiseEnEtat::class)->orderByDesc('id');
    }

    public function miseEnEtatOuverte(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MiseEnEtat::class)->where('statut', 'en_cours');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(Partie::class)->orderByRaw("CASE role WHEN 'demandeur' THEN 0 WHEN 'prevenu' THEN 0 WHEN 'inculpe' THEN 0 WHEN 'defendeur' THEN 1 ELSE 2 END")->orderBy('ordre');
    }

    /** Pièces du dossier numérique (celles qui ne sont pas retirées). */
    public function pieces(): HasMany
    {
        return $this->hasMany(PieceDossier::class)->whereNull('retiree_le')->orderBy('date_piece')->orderBy('id');
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(AffaireAttribution::class)->orderByDesc('id');
    }

    public function scelles(): HasMany
    {
        return $this->hasMany(Scelle::class)->orderBy('numero');
    }

    /** Les corrections de numéro (ancien numéro, motif, auteur), de la plus récente à la plus ancienne. */
    public function numerosCorriges(): HasMany
    {
        return $this->hasMany(NumeroCorrige::class)->orderByDesc('id');
    }

    /** L'affaire principale quand celle-ci est jointe à une autre (jonction des causes). */
    public function principale(): BelongsTo
    {
        return $this->belongsTo(self::class, 'jointe_a_id');
    }

    /** Les affaires jointes à celle-ci (elle en est la principale). */
    public function jointes(): HasMany
    {
        return $this->hasMany(self::class, 'jointe_a_id')->orderBy('annee')->orderBy('numero');
    }

    /** Qui a accordé la dérogation à la date limite d'enrôlement. */
    public function derogant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'derogation_par');
    }

    public function chambre(): BelongsTo
    {
        return $this->belongsTo(Chambre::class);
    }

    public function nature(): BelongsTo
    {
        return $this->belongsTo(NatureAffaire::class, 'nature_affaire_id');
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function dossierInstruction(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DossierInstruction::class);
    }

    public function modeSaisine(): BelongsTo
    {
        return $this->belongsTo(ModeSaisine::class, 'mode_saisine_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /** « 09h00 », ou null si l'heure n'est pas renseignée. */
    public function heureAudience(): ?string
    {
        return $this->heure_premiere_audience ? str_replace(':', 'h', substr((string) $this->heure_premiere_audience, 0, 5)) : null;
    }

    public function estAnnulee(): bool
    {
        return $this->statut === 'annulee';
    }

    /** « KOUASSI Yao c/ SOCIETE X et 2 autres » : demandeurs puis défendeurs, pour les listes (parties chargées). */
    public function intitule(): string
    {
        $cote = fn (string $role) => $this->parties->where('role', $role)->map(fn (Partie $p) => $p->libelle())->implode(', ');
        if ($poursuivis = $cote('prevenu') ?: $cote('inculpe')) {
            return $poursuivis;   // affaire pénale ou d'instruction : les personnes poursuivies
        }
        $d = $cote('demandeur');
        $f = $cote('defendeur');

        return $f === '' ? $d : $d.' c/ '.$f;
    }

    /** Le numéro tel que le greffe l'écrit : 1391/25, sans zéros. Le format du cahier des charges (RG-1391/25) reste en base dans « numero_complet ». */
    public function numeroAffichage(): string
    {
        // Au civil le numéro s'écrit seul (1391/25) ; les autres séries gardent leur sigle (RP 1334/26).
        $numero = $this->numero.'/'.sprintf('%02d', $this->annee % 100);

        return $this->serie === 'RG' ? $numero : $this->serie.' '.$numero;
    }

    public function libelle(): string
    {
        return $this->numeroAffichage();
    }
}
