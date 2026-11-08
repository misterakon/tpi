<?php

namespace App\Models;

use App\Support\PerimetreChambres;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/*
 | Rôle d'une audience : une chambre, une date. Brouillon tant qu'il n'est pas publié ; chaque publication crée une version figée.
 */
class RoleAudience extends Model
{
    public const STATUTS = ['brouillon' => 'Brouillon', 'publie' => 'Publié'];

    /** Types de rôle des modèles du tribunal : le rôle pénal est composé et signé par le parquet. */
    public const TYPES = ['civil' => 'Rôle civil', 'refere' => 'Rôle de référé', 'penal' => 'Rôle pénal (parquet)'];

    /** Intitulés d'audience proposés (le client peut saisir le sien). */
    public const INTITULES = [
        'refere' => ['REFERE EXPULSION – ORDINAIRE – HEURE A HEURE', 'REFERE ORDINAIRE', 'REFERE EXPULSION'],
        'penal' => ['AUDIENCE PUBLIQUE ORDINAIRE DES DELITS DIVERS', 'AUDIENCE PUBLIQUE ORDINAIRE DES FLAGRANTS DELITS', 'AUDIENCE PUBLIQUE ORDINAIRE DES DELITS EN MATIERE DE POLICE DE LA CIRCULATION'],
    ];

    /** Deux rôles dans la même salle le même jour à moins de cet écart (minutes) sont en conflit. */
    public const ECART_MINIMUM = 180;

    protected $table = 'roles_audience';

    protected $guarded = [];

    /** Un rôle neuf est civil tant qu'on n'a pas choisi un autre type (la base a la même valeur par défaut). */
    protected $attributes = ['type' => 'civil'];

    protected function casts(): array
    {
        return ['date_audience' => 'date', 'date_signature' => 'date', 'publie_le' => 'datetime', 'avec_avocats' => 'boolean', 'additif' => 'boolean', 'audience_ouverte_le' => 'datetime', 'audience_terminee_le' => 'datetime', 'composition_tenue' => 'array'];
    }

    /** Les rôles des chambres que l'utilisateur a le droit de voir. */
    public function scopeVisiblesPour(Builder $requete, ?User $user): Builder
    {
        $chambres = PerimetreChambres::de($user);

        return $chambres === null ? $requete : $requete->whereIn('chambre_id', $chambres ?: [0]);
    }

    public function chambre(): BelongsTo
    {
        return $this->belongsTo(Chambre::class);
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(RoleLigne::class, 'role_id')->orderBy('ordre')->orderBy('id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RoleVersion::class, 'role_id')->orderByDesc('version');
    }

    public function estPublie(): bool
    {
        return $this->statut === 'publie';
    }

    /** Où en est l'audience : à tenir, en cours (ouverte par le greffier) ou terminée. */
    public function etatAudience(): string
    {
        return $this->audience_terminee_le ? 'terminee' : ($this->audience_ouverte_le ? 'en_cours' : 'a_tenir');
    }

    /**
     * Les membres de la composition à présenter à l'ouverture : ceux de la formation (hors suppléants) avec leur présence, ou ceux déjà enregistrés.
     *
     * @return list<array{agent_id: int, nom: string, role: string, present: bool, remplacant: ?string}>
     */
    public function compositionTenue(): array
    {
        if ($this->composition_tenue) {
            return $this->composition_tenue;
        }
        $ordre = array_flip(['Président', 'Juge', 'Juge délégué', 'Assesseur', 'Assesseur SPJEJ', 'Assesseur employeur', 'Assesseur travailleur', 'Représentant SPJEJ', 'Greffier']);

        return collect($this->composition())->map(fn ($x, $id) => ['agent_id' => (int) $id, 'nom' => $x['agent']->nomComplet(), 'role' => $x['role'], 'present' => true, 'remplacant' => null])
            ->sortBy(fn ($m) => $ordre[$m['role']] ?? 99)->values()->all();
    }

    /** « 09h00 » ou null. */
    public function heureCourte(): ?string
    {
        return $this->heure ? str_replace(':', 'h', substr((string) $this->heure, 0, 5)) : null;
    }

    /** Minutes depuis minuit, ou null. */
    public function minutes(): ?int
    {
        return $this->heure ? ((int) substr((string) $this->heure, 0, 2)) * 60 + (int) substr((string) $this->heure, 3, 2) : null;
    }

    /** « DE LA CHAMBRE PRESIDENTIELLE (1ERE CHAMBRE CIVILE) » : l'intitulé de la chambre puis son numéro, en capitales sans accent comme sur les rôles. */
    public function chambreTitre(): string
    {
        $c = $this->chambre;
        $libelle = mb_strtoupper(Str::ascii($c->libelle));
        $article = preg_match('/^(CABINET|BUREAU)/', $libelle) ? 'DU' : 'DE LA';
        if (! $c->intitule) {
            return $article.' '.$libelle;
        }
        $intitule = mb_strtoupper(Str::ascii($c->intitule));

        return $article.' '.(preg_match('/^(CHAMBRE|CABINET)/', $intitule) ? '' : 'CHAMBRE ').$intitule.' ('.$libelle.')';
    }

    /** « JEUDI 04 JUIN 2026 A 09 HEURES » (« A 09 HEURES 30 » si l'heure n'est pas ronde). */
    public function quand(bool $avecHeure = true): string
    {
        $jour = mb_strtoupper(Str::ascii($this->date_audience->copy()->locale('fr')->translatedFormat('l d F Y')));
        if (! $avecHeure || ! $this->heure) {
            return $jour;
        }
        [$h, $m] = [substr((string) $this->heure, 0, 2), substr((string) $this->heure, 3, 2)];

        return $jour.' A '.$h.' HEURES'.($m !== '00' ? ' '.$m : '');
    }

    public function estPenal(): bool
    {
        return $this->type === 'penal';
    }

    public function typeLibelle(): string
    {
        return self::TYPES[$this->type] ?? self::TYPES['civil'];
    }

    private function ascii(?string $texte): string
    {
        return str_replace('QQTIRETQQ', '–', mb_strtoupper(Str::ascii(str_replace('–', 'QQTIRETQQ', (string) $texte))));
    }

    /**
     * Première partie du titre. Civil : « ROLE D'AUDIENCE DE LA CHAMBRE PRESIDENTIELLE (1ERE CHAMBRE CIVILE) » ;
     * référé : « ROLE D'AUDIENCE DE REFERE » ; pénal : « ROLE » puis l'intitulé de l'audience.
     */
    public function titreChambre(): string
    {
        return match ($this->type) {
            'refere' => "ROLE D'AUDIENCE DE REFERE",
            'penal' => trim('ROLE'."\n".($this->intitule ? $this->ascii($this->intitule) : 'AUDIENCE PUBLIQUE ORDINAIRE')),
            default => "ROLE D'AUDIENCE ".$this->chambreTitre(),
        };
    }

    /** « DU JEUDI 04 JUIN 2026 A 09 HEURES », puis l'intitulé entre parenthèses pour un référé et « (Rôle additif) » s'il y a lieu. */
    public function titreQuand(): string
    {
        // Rôle pénal : l'heure est imprimée à droite (« 10 HEURES »), pas dans le titre.
        $ligne = 'DU '.$this->quand(! $this->estPenal());
        if ($this->type === 'refere' && $this->intitule) {
            $ligne .= "\n(".$this->ascii($this->intitule).')';
        }

        return $ligne.($this->additif ? "\n(Rôle additif)" : '');
    }

    /** Le titre complet, sur une seule chaîne. */
    public function titre(): string
    {
        return str_replace("\n", ' ', $this->titreChambre().' '.$this->titreQuand());
    }

    /** « SALLE 4 (KONE MAMADOU) » : « Salle d'audience KONE Mamadou (n°4) » devient le libellé des rôles du tribunal ; un autre nom est mis en capitales. */
    public function salleTitre(): ?string
    {
        if (! $this->salle) {
            return null;
        }
        $nom = trim($this->salle->nom);
        if (preg_match("/^salle(?:\s+d['’]audience)?\s+(.+?)\s*\(\s*n\s*°?\s*(\d+)\s*\)$/iu", $nom, $m)) {
            return mb_strtoupper(Str::ascii('SALLE '.$m[2].' ('.$m[1].')'));
        }

        return mb_strtoupper(Str::ascii($nom));
    }

    /** Autres rôles le même jour dont l'heure est à moins de ECART_MINIMUM minutes (sans heure d'un côté ou de l'autre : tout le jour si $jourEntier). */
    private function voisins(bool $jourEntier)
    {
        return static::with('chambre')->whereDate('date_audience', $this->date_audience->toDateString())->where('id', '!=', $this->id ?? 0)->get()
            ->filter(function (self $autre) use ($jourEntier) {
                [$a, $b] = [$this->minutes(), $autre->minutes()];
                if ($a === null || $b === null) {
                    return $jourEntier;
                }

                return abs($a - $b) < self::ECART_MINIMUM;
            })->values();
    }

    /** Autres rôles dans la même salle le même jour à moins de ECART_MINIMUM minutes (ou sans heure : tout le jour). */
    public function conflits()
    {
        if (! $this->salle_id) {
            return collect();
        }

        return $this->voisins(true)->filter(fn (self $autre) => (int) $autre->salle_id === (int) $this->salle_id)->values();
    }

    /**
     * Les magistrats et greffiers (hors suppléants) de la composition de la chambre à la date du rôle, d'après les affectations des formations.
     *
     * @return array<int, array{agent: Agent, role: string}> agent_id => agent et fonction dans la formation
     */
    public function composition(): array
    {
        $jour = $this->date_audience->toDateString();

        return Affectation::with('agent')->whereIn('formation_id', Formation::where('chambre_id', $this->chambre_id)->select('id'))->where('suppleant', false)
            ->where(fn ($q) => $q->whereNull('debut')->orWhereDate('debut', '<=', $jour))->where(fn ($q) => $q->whereNull('fin')->orWhereDate('fin', '>=', $jour))
            ->get()->filter(fn ($x) => $x->agent && ! $x->agent->trashed() && $x->agent->actif)
            ->mapWithKeys(fn ($x) => [$x->agent_id => ['agent' => $x->agent, 'role' => Affectation::ROLES[$x->role] ?? $x->role]])->all();
    }

    /**
     * Avertissement (jamais bloquant) : un magistrat ou un greffier de la composition figure aussi dans celle d'un autre rôle du même jour
     * à moins de ECART_MINIMUM minutes. Sans heure sur l'un des deux rôles, on ne dit rien.
     *
     * @return \Illuminate\Support\Collection<int, array{agent: Agent, role: string, autre: self}>
     */
    public function agentsDejaPris()
    {
        if ($this->minutes() === null) {
            return collect();
        }
        $miens = $this->composition();
        if (! $miens) {
            return collect();
        }
        $resultat = collect();
        foreach ($this->voisins(false) as $autre) {
            foreach (array_intersect_key($miens, $autre->composition()) as $x) {
                $resultat->push($x + ['autre' => $autre]);
            }
        }

        return $resultat;
    }
}
