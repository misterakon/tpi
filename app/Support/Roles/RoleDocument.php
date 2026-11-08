<?php

namespace App\Support\Roles;

use App\Models\RoleAudience;
use App\Models\RoleLigne;
use Illuminate\Support\Str;

/*
 | Le rôle tel qu'il s'imprime : un tableau de données (titre, rubriques, lignes numérotées, signature) que l'écran, le PDF, le Word et les
 | versions figées utilisent tous. Une version publiée est ce tableau, enregistré tel quel.
 */
class RoleDocument
{
    /** Colonnes de chaque type de rôle : titre, largeur en vingtièmes de point (total 14 743, comme le modèle Word) et en pourcentage. */
    public static function colonnes(string $type, bool $mandat = false): array
    {
        if ($type === 'penal') {
            return $mandat
                ? [['titre' => "N° d'ordre", 'twips' => 1500, 'pct' => 10], ['titre' => 'IDENTITE DES PREVENUS', 'twips' => 4200, 'pct' => 28], ['titre' => 'DATE DU MANDAT DE DEPOT', 'twips' => 1900, 'pct' => 13],
                    ['titre' => 'NATURE DE LA PREVENTION', 'twips' => 4900, 'pct' => 33], ['titre' => 'OBSERVATION', 'twips' => 2243, 'pct' => 16]]
                : [['titre' => "N° d'ordre", 'twips' => 1500, 'pct' => 10], ['titre' => 'IDENTITE DES PREVENUS', 'twips' => 4600, 'pct' => 31], ['titre' => 'NATURE DE LA PREVENTION', 'twips' => 5400, 'pct' => 36],
                    ['titre' => 'DECISION DU TRIBUNAL', 'twips' => 3243, 'pct' => 23]];
        }

        return [['titre' => "N° d'ordre", 'twips' => 1050, 'pct' => 8], ['titre' => 'N° RG', 'twips' => 1400, 'pct' => 9], ['titre' => 'DEMANDEUR(S)', 'twips' => 3700, 'pct' => 25],
            ['titre' => 'DEFENDEUR(S)', 'twips' => 3700, 'pct' => 25], ['titre' => 'OBJET', 'twips' => 2894, 'pct' => 20], ['titre' => 'OBSERVATIONS', 'twips' => 1999, 'pct' => 13]];
    }

    /** Une ligne de texte avec sa mise en forme : gras (numéros, noms, dates, articles) et italique (avocats, précisions). */
    private static function t(string $texte, bool $gras = false, bool $italique = false): array
    {
        return ['t' => $texte, 'b' => $gras, 'i' => $italique];
    }

    /**
     * Une ligne du rôle en cellules : une liste de lignes de texte par colonne. Comme sur les modèles du tribunal, le numéro d'ordre,
     * le nom des parties, la date du mandat et les articles visés sont en gras ; les précisions (âge, profession) et les avocats en italique.
     */
    public static function cellules(RoleLigne $l, string $type, int $n, bool $avocats, bool $mandat): array
    {
        $texte = [self::class, 'lignes'];
        $gras = fn (array $lignes) => array_map(fn ($x) => self::t($x, true), $lignes);
        if ($type === 'penal') {
            $identite = array_map(fn ($x) => preg_match('/\bans\b|domicili|^MD\b/iu', $x) ? self::t($x, false, true) : self::t($x, true), $texte($l->demandeurs));
            $nature = array_map(fn ($x) => preg_match('/^(arts?|articles?)\b/iu', $x) ? self::t($x, true, true) : self::t($x), $texte($l->objet));
            $cellules = [$gras([sprintf('%02d', $n), ...$texte($l->numero)]), $identite];
            if ($mandat) {
                $cellules[] = $gras($texte($l->defendeurs));
            }

            return [...$cellules, $nature, $texte($l->observations)];
        }
        $avocat = fn (array $noms) => array_map(fn ($x) => self::t(self::avocat($x), true, true), $noms);

        return [
            $gras([sprintf('%02d', $n)]), $texte($l->numero),
            [...$gras($texte($l->demandeurs)), ...($avocats ? $avocat($texte($l->avocats_demandeurs)) : [])],
            [...$gras($texte($l->defendeurs)), ...($avocats ? $avocat($texte($l->avocats_defendeurs)) : [])],
            $texte($l->objet), $texte($l->observations),
        ];
    }

    public static function depuis(RoleAudience $role): array
    {
        $role->loadMissing('chambre', 'salle', 'lignes');
        $penal = $role->estPenal();
        $mandat = $penal && $role->lignes->contains(fn ($l) => trim((string) $l->defendeurs) !== '');
        $n = 0;
        $rubriques = [];
        foreach (RoleLigne::TITRES as $code => $titre) {
            $lignes = [];
            foreach ($role->lignes->where('rubrique', $code) as $l) {
                $n++;
                $lignes[] = ['id' => $l->id, 'n' => sprintf('%02d', $n), 'cellules' => self::cellules($l, $role->type, $n, (bool) $role->avec_avocats, $mandat)];
            }
            if ($lignes) {
                $rubriques[] = ['code' => $code, 'titre' => $titre, 'lignes' => $lignes];
            }
        }
        $h = $role->heure ? substr((string) $role->heure, 0, 2) : null;
        $heure = $h ? $h.' HEURES'.(substr((string) $role->heure, 3, 2) !== '00' ? ' '.substr((string) $role->heure, 3, 2) : '') : null;

        return [
            'version' => (int) $role->version,
            'statut' => $role->statut,
            'type' => $role->type,
            'salle' => $role->salleTitre(),
            'titre' => $role->titre(),
            'titre_chambre' => $role->titreChambre(),
            'titre_quand' => $role->titreQuand(),
            'entete_gauche' => $penal ? self::enteteParquet() : self::enteteGauche(),
            'entete_droite' => self::enteteDroite(),
            'equipe' => $penal ? [['PRESIDENT :', $role->president], ['ASSESSEURS :', $role->assesseurs], ['PROCUREUR :', $role->procureur], ['GREFFIER :', $role->greffier]] : [],
            'heure_droite' => $penal ? $heure : null,
            'colonnes' => self::colonnes($role->type, $mandat),
            'avec_avocats' => (bool) $role->avec_avocats,
            'rubriques' => $rubriques,
            'total' => $n,
            'signe_prefixe' => $penal ? 'Fait au ' : 'Fait à ',
            'fait_a' => $role->fait_a,
            'date_signature' => $role->date_signature?->format('d/m/Y'),
            'signataire' => $role->signataire,
            'date_audience' => $role->date_audience->format('Y-m-d'),
        ];
    }

    /**
     * Remet à niveau un document figé avant l'arrivée des types de rôle (lignes à clés nommées) : colonnes et cellules du rôle civil.
     * Un document déjà au nouveau format est rendu tel quel.
     */
    public static function normaliser(array $doc): array
    {
        if (isset($doc['colonnes'])) {
            return $doc;
        }
        $doc['type'] = 'civil';
        $doc['colonnes'] = self::colonnes('civil');
        $doc['equipe'] = [];
        $doc['heure_droite'] = null;
        $doc['signe_prefixe'] = 'Fait à ';
        $italique = fn (array $noms) => array_map(fn ($x) => ['t' => $x, 'b' => true, 'i' => true], $noms);
        $gras = fn (array $noms) => array_map(fn ($x) => ['t' => $x, 'b' => true, 'i' => false], $noms);
        foreach ($doc['rubriques'] as &$r) {
            foreach ($r['lignes'] as &$l) {
                $l['cellules'] = [$gras([$l['n']]), $l['numero'], [...$gras($l['demandeurs']), ...($doc['avec_avocats'] ? $italique($l['avocats_demandeurs']) : [])],
                    [...$gras($l['defendeurs']), ...($doc['avec_avocats'] ? $italique($l['avocats_defendeurs']) : [])], $l['objet'], $l['observations']];
            }
            unset($l);
        }
        unset($r);

        return $doc;
    }

    /** Une ligne de texte d'une cellule : chaîne ou ['t' => texte, 'b' => gras, 'i' => italique]. @return array{0: string, 1: bool, 2: bool} texte, italique, gras */
    public static function ligneDeCellule(mixed $ligne): array
    {
        return is_array($ligne) ? [(string) ($ligne['t'] ?? ''), (bool) ($ligne['i'] ?? false), (bool) ($ligne['b'] ?? false)] : [(string) $ligne, false, false];
    }

    /** Bloc de gauche des rôles pénaux : même présentation que le rôle civil, avec l'en-tête du parquet. @return list<string> */
    public static function enteteParquet(): array
    {
        $tribunal = preg_split('/\s+/', mb_strtoupper(Str::ascii((string) config('tpi.juridiction'))), -1, PREG_SPLIT_NO_EMPTY);
        $reste = array_slice($tribunal, 2);                  // « PREMIERE INSTANCE DE BINGERVILLE »
        $moitie = (int) ceil(count($reste) / 2);

        return [
            mb_strtoupper(Str::ascii((string) config('tpi.cour_appel'))), '----------', 'PARQUET PRES LE TRIBUNAL DE',
            implode(' ', array_slice($reste, 0, $moitie)), implode(' ', array_slice($reste, $moitie)), '----------',
        ];
    }

    /** Bloc de gauche du papier à en-tête, saisi en texte : ministère, cour d'appel, tribunal (le nom vient des réglages, coupé en deux lignes). @return list<string> */
    public static function enteteGauche(): array
    {
        $tribunal = preg_split('/\s+/', mb_strtoupper(Str::ascii((string) config('tpi.juridiction'))), -1, PREG_SPLIT_NO_EMPTY);
        $moitie = (int) ceil(count($tribunal) / 2);

        return [
            'MINISTERE DE LA JUSTICE', "ET DES DROITS DE L'HOMME", '----------',
            mb_strtoupper(Str::ascii((string) config('tpi.cour_appel'))), '----------',
            implode(' ', array_slice($tribunal, 0, $moitie)), implode(' ', array_slice($tribunal, $moitie)), '----------',
        ];
    }

    /** Bloc de droite : la République. @return list<string> */
    public static function enteteDroite(): array
    {
        return ["REPUBLIQUE DE COTE D'IVOIRE", 'Union – Discipline – Travail', '------------'];
    }

    /** Un texte en liste de lignes non vides. @return list<string> */
    public static function lignes(?string $texte): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $texte)), fn ($l) => $l !== ''));
    }

    /** « (Me DAGBO) » : le préfixe « Me » est ajouté sauf pour un cabinet ou une société d'avocats. */
    public static function avocat(string $nom): string
    {
        $nom = trim($nom, " ()\t");

        return '('.(preg_match('/^(me\b|maître|cabinet|cab\b|scpa|selarl|société|societe|s\.c\.p)/iu', $nom) ? $nom : 'Me '.$nom).')';
    }

    /** Nom de fichier : role-1re-chambre-civile-2026-06-04-v2 */
    public static function nomFichier(RoleAudience $role, ?int $version = null): string
    {
        return 'role-'.Str::slug($role->chambre->libelle).'-'.$role->date_audience->format('Y-m-d').'-v'.($version ?? $role->version);
    }
}
