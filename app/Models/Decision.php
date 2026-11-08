<?php

namespace App\Models;

use App\Support\PerimetreChambres;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Décision en cours de rédaction ou validée, écrite à partir d'un modèle de décision. */
class Decision extends Model
{
    /** Le cycle de vie : on rédige, on valide (texte figé), on signe (numéro attribué), on délivre. */
    public const STATUTS = ['brouillon' => 'Brouillon', 'redigee' => 'Rédigée', 'validee' => 'Validée', 'signee' => 'Signée', 'delivree' => 'Délivrée'];

    /** Les statuts où le texte est figé. */
    public const STATUTS_VALIDES = ['validee', 'signee', 'delivree'];

    public const TYPES_DELIVRANCE = ['expedition' => 'Expédition', 'grosse' => 'Grosse (copie exécutoire)', 'copie' => 'Copie simple'];

    protected $table = 'decisions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'valeurs' => 'array', 'retouches' => 'array', 'rendu' => 'array', 'validee_le' => 'datetime', 'date_decision' => 'date', 'numero_le' => 'datetime', 'redigee_le' => 'datetime',
            'date_signature' => 'date', 'signee_le' => 'datetime', 'delivree_le' => 'datetime',
        ];
    }

    /**
     * Les décisions des chambres que l'utilisateur a le droit de voir (voir PerimetreChambres). Une décision sans chambre (ancienne saisie
     * à la main) n'est visible que de celui qui l'a créée, ou de qui voit toutes les chambres.
     */
    public function scopeVisiblesPour(Builder $requete, ?User $user): Builder
    {
        $chambres = PerimetreChambres::de($user);
        if ($chambres === null) {
            return $requete;
        }

        return $requete->where(fn ($q) => $q->whereIn('chambre_id', $chambres ?: [0])->orWhere(fn ($s) => $s->whereNull('chambre_id')->where('cree_par', $user?->id ?? 0)));
    }

    public function estVisiblePour(?User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visiblesPour($user)->exists();
    }

    public function modele(): BelongsTo
    {
        return $this->belongsTo(ModeleDecision::class, 'modele_decision_id');
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

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }

    /** Le texte est figé : validée, signée ou délivrée. */
    public function estValidee(): bool
    {
        return in_array($this->statut, self::STATUTS_VALIDES, true);
    }

    public function estRedigee(): bool
    {
        return $this->statut === 'redigee';
    }

    public function estSignee(): bool
    {
        return in_array($this->statut, ['signee', 'delivree'], true);
    }

    /** « D-145/2026 » (D, numéro dans l'année, année), ou null tant que la décision n'a pas de numéro. */
    public function numeroAffichage(): ?string
    {
        return $this->numero ? \App\Services\NumeroDecision::libelle((int) ($this->numero_seq ?: $this->numero), (int) $this->numero_annee) : null;
    }

    public function certificats(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CertificatRecours::class);
    }

    public function delivrances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Delivrance::class)->orderBy('delivre_le')->orderBy('id');
    }

    /** Les étapes du cycle, avec ce qui est fait et quand (pour la frise de la page de rédaction). @return list<array{code: string, libelle: string, fait: bool, date: ?string}> */
    public function etapes(): array
    {
        $valide = $this->estValidee();
        $jour = fn ($d) => $d?->format('d/m/Y');

        $numerotee = ['code' => 'numerotee', 'libelle' => 'Numérotée', 'fait' => $this->numero !== null, 'date' => $this->numeroAffichage()];
        $parLeGreffier = \App\Services\NumeroDecision::moment() === 'redaction';

        return array_values(array_filter([
            ['code' => 'brouillon', 'libelle' => 'Rédaction', 'fait' => true, 'date' => $jour($this->created_at)],
            ['code' => 'redigee', 'libelle' => 'Rédigée', 'fait' => $valide || $this->estRedigee(), 'date' => $jour($this->redigee_le)],
            $parLeGreffier ? $numerotee : null,
            ['code' => 'validee', 'libelle' => 'Validée', 'fait' => $valide, 'date' => $jour($this->validee_le)],
            ['code' => 'signee', 'libelle' => 'Signée', 'fait' => $this->estSignee(), 'date' => $jour($this->date_signature)],
            $parLeGreffier ? null : $numerotee,
            ['code' => 'delivree', 'libelle' => 'Délivrée', 'fait' => $this->statut === 'delivree', 'date' => $jour($this->delivree_le)],
        ]));
    }

    /** La version du modèle utilisée : un brouillon suit toujours la dernière version du modèle, une décision validée garde celle de sa validation. */
    public function version(): ?ModeleDecisionVersion
    {
        if (! $this->estValidee() && ($courante = $this->modele?->versionCourante())) {
            return $courante;
        }

        return $this->modele?->versions()->where('numero', $this->version_numero)->first();
    }

    /** Les valeurs saisies complétées par celles que l'application connaît (juridiction, cour d'appel, type de décision, numéro du RG). */
    public function valeursCompletes(?array $valeurs = null): array
    {
        $v = array_merge($valeurs ?? $this->valeurs ?? [], \App\Support\Redaction\Preremplissage::automatiques($this));
        if (! empty($v['president.fonction'])) {
            $v['president.fonction'] = \App\Support\Redaction\Preremplissage::fonctionEnTexte((string) $v['president.fonction'], $v['president.genre'] ?? null);
        }

        if (is_array($v['demandeur.naissance'] ?? null)) {
            $n = $v['demandeur.naissance'];
            $v['demandeur.naissance'] = ($n['mode'] ?? '') === 'annee'
                ? 'vers '.$n['annee']
                : 'le '.\Carbon\Carbon::parse($n['date'])->locale('fr')->translatedFormat('d F Y');
        }

        return $v;
    }

    public function numeroVersion(): int
    {
        return $this->version()?->numero ?? (int) $this->version_numero;
    }

    public function libelle(): string
    {
        return ($this->modele?->nom ?? 'Décision').' — '.$this->reference;
    }
}
