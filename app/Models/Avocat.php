<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/*
 | Référentiel des avocats et cabinets. À l'enrôlement on choisit l'avocat dans la liste ; s'il n'y figure pas, on tape son nom et une fiche
 | « à compléter » est créée (si l'utilisateur a le droit avocats.creer). La clé normalisée évite les doublons : « Me YAO Aminata »,
 | « YAO Aminata » et « Aminata Yao » désignent la même fiche.
 */
class Avocat extends Model
{
    public const TYPES = ['avocat' => 'Avocat', 'cabinet' => 'Cabinet'];

    protected $table = 'avocats';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'a_completer' => 'boolean'];
    }

    public function parties(): HasMany
    {
        return $this->hasMany(Partie::class);
    }

    /** « Maître YAO Aminata » ou « Cabinet ABI & Associés » : l'écriture reprise dans les affaires. */
    public function libelle(): string
    {
        if ($this->type === 'cabinet') {
            return 'Cabinet '.$this->nom;
        }

        return trim('Maître '.mb_strtoupper((string) $this->nom).' '.$this->prenoms);
    }

    /** Clé de comparaison : sans titre (Maître, Me, Cabinet…), sans accents ni ponctuation, mots triés. */
    public static function cle(string $texte): string
    {
        $mots = preg_split('/\s+/', trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii($texte))))) ?: [];
        $mots = array_values(array_filter($mots, fn ($m) => $m !== '' && ! in_array($m, ['maitre', 'me', 'mtre', 'cabinet', 'cab', 'scp', 'scpa', 'avocat', 'avocats'], true)));
        sort($mots);

        return implode(' ', $mots);
    }

    /** Une fiche déjà connue sous ce nom, ou null. */
    public static function trouver(string $texte): ?self
    {
        $cle = self::cle($texte);

        return $cle === '' ? null : self::where('cle', $cle)->orderByDesc('actif')->orderBy('id')->first();
    }

    /**
     * Rattache le texte saisi à une fiche. Renvoie [avocat_id, texte à conserver dans l'affaire].
     * Sans fiche connue, une fiche « à compléter » n'est créée que si $peutCreer ; sinon le texte reste libre, sans fiche.
     *
     * @return array{0: ?int, 1: ?string}
     */
    public static function rattacher(?string $texte, bool $peutCreer): array
    {
        $texte = trim((string) $texte);
        if ($texte === '') {
            return [null, null];
        }
        $fiche = self::trouver($texte);
        if (! $fiche && $peutCreer && self::cle($texte) !== '') {
            $fiche = self::create(self::decouper($texte) + ['cle' => self::cle($texte), 'actif' => true, 'a_completer' => true]);
        }

        return $fiche ? [$fiche->id, $fiche->libelle()] : [null, $texte];
    }

    /** « Maître YAO Aminata » → nom YAO, prénoms Aminata ; « Cabinet ABI & Associés » → cabinet. */
    public static function decouper(string $texte): array
    {
        $texte = trim((string) preg_replace('/^\s*(ma[iî]tre|me|m\.?e\.?)\s+/iu', '', $texte));
        if (preg_match('/^(cabinet|cab\.?|scpa?)\s+(.+)$/iu', $texte, $m)) {
            return ['type' => 'cabinet', 'nom' => Str::limit(trim($m[2]), 160, ''), 'prenoms' => null];
        }
        $mots = preg_split('/\s+/', $texte) ?: [$texte];
        $nom = [];
        $prenoms = [];
        foreach ($mots as $mot) {
            $majuscules = mb_strtoupper($mot) === $mot && preg_match('/\p{L}/u', $mot);
            if ($majuscules && $prenoms === []) {
                $nom[] = $mot;
            } else {
                $prenoms[] = $mot;
            }
        }
        if ($nom === []) {
            $nom = [array_shift($prenoms)];
        }

        return ['type' => 'avocat', 'nom' => Str::limit(implode(' ', $nom), 160, ''), 'prenoms' => $prenoms ? Str::limit(implode(' ', $prenoms), 160, '') : null];
    }
}
