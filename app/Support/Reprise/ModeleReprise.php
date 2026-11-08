<?php

namespace App\Support\Reprise;

use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use App\Models\NatureAffaire;
use App\Support\RegistreAffaire;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/*
 | Le modèle Excel de la reprise. Un classeur par registre (RG, RP, RI, RE) avec quatre onglets : « Mode d'emploi », « Registre » (à remplir),
 | « Exemple » et « Listes » (chambres et natures reconnues). Seul un classeur dont l'onglet « Registre » porte exactement les titres du modèle
 | est accepté : n'importe quel autre fichier Excel est refusé avant toute lecture des lignes.
 */
class ModeleReprise
{
    public const FEUILLE = 'Registre';

    /** @return array<string, array{titre: string, obligatoire: bool, aide: string}> champ => colonne, dans l'ordre du classeur */
    public static function colonnes(string $serie): array
    {
        $def = RegistreAffaire::depuisSerie($serie);
        [$roleA, $roleB] = $def['roles'];
        $libellesA = ['demandeur' => 'Demandeur(s)', 'prevenu' => 'Prévenu(s)', 'inculpe' => 'Inculpé(s)'];
        $libellesB = ['defendeur' => 'Défendeur(s)', 'victime' => 'Victime(s) ou partie civile'];

        return [
            'numero' => ['titre' => 'N° '.$serie, 'obligatoire' => true, 'aide' => 'Numéro du registre, avec l\'année : 1391/25. Le préfixe « '.$serie.' » est accepté.'],
            'date' => ['titre' => "Date d'enrôlement", 'obligatoire' => true, 'aide' => 'jj/mm/aaaa. Pas de date dans le futur.'],
            'objet' => ['titre' => $def['objet'], 'obligatoire' => true, 'aide' => 'Texte libre, 1 000 caractères au plus.'],
            'demandeur' => ['titre' => $libellesA[$roleA], 'obligatoire' => true, 'aide' => 'Plusieurs parties : séparez-les par un point-virgule. Exemple : KOUASSI Yao ; Mme TRAORE Awa.'],
            'defendeur' => ['titre' => $libellesB[$roleB] ?? 'Autres parties', 'obligatoire' => false, 'aide' => 'Facultatif. Même règle que la colonne précédente.'],
            'chambre' => ['titre' => $serie === 'RI' || $serie === 'RE' ? 'Cabinet' : 'Chambre', 'obligatoire' => true, 'aide' => 'Exactement comme dans l\'onglet « Listes ».'],
            'nature' => ['titre' => "Nature de l'affaire", 'obligatoire' => false, 'aide' => 'Facultatif. Exactement comme dans l\'onglet « Listes ».'],
            'audience' => ['titre' => 'Date de première audience', 'obligatoire' => false, 'aide' => 'Facultatif. jj/mm/aaaa.'],
            'ancien_numero' => ['titre' => 'Ancien numéro', 'obligatoire' => false, 'aide' => "Facultatif. Un numéro d'avant l'application, conservé tel quel."],
        ];
    }

    /** Les titres attendus, avec une étoile sur les colonnes obligatoires. @return list<string> */
    public static function titres(string $serie): array
    {
        return array_values(array_map(fn ($c) => $c['titre'].($c['obligatoire'] ? ' *' : ''), self::colonnes($serie)));
    }

    /** Index de colonne de chaque champ (l'ordre du modèle est fixe). @return array<string, int> */
    public static function correspondance(string $serie): array
    {
        return array_flip(array_keys(self::colonnes($serie)));
    }

    private static function normal(string $t): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii($t))));
    }

    /** Compare la première ligne du fichier aux titres du modèle. Renvoie un message d'erreur clair, ou null si tout correspond. */
    public static function verifierEntete(array $cellules, string $serie): ?string
    {
        $attendus = self::titres($serie);
        foreach ($attendus as $i => $titre) {
            $trouve = trim((string) ($cellules[$i] ?? ''));
            if (self::normal($trouve) !== self::normal($titre)) {
                $lettre = chr(65 + $i);

                return 'Ce fichier ne respecte pas le modèle du registre '.$serie.' : la colonne '.$lettre.' devrait s\'intituler « '.$titre.' »'.($trouve !== '' ? ', elle s\'intitule « '.Str::limit($trouve, 40).' »' : ' et elle est vide').'. Téléchargez le modèle et recopiez-y vos lignes.';
            }
        }
        if (count(array_filter(array_slice($cellules, count($attendus)), fn ($c) => trim((string) $c) !== '')) > 0) {
            return 'Ce fichier contient des colonnes en plus de celles du modèle du registre '.$serie.'. Retirez-les ou recopiez vos lignes dans le modèle.';
        }

        return null;
    }

    /** Chambres que le registre peut recevoir (section civile, pénale ou instruction). @return \Illuminate\Support\Collection<int, Chambre> */
    public static function chambres(string $serie)
    {
        $sections = match ($serie) {
            'RP' => ['penale'],
            'RI', 'RE' => ['instruction'],
            default => ['civile', 'urgence', 'sociale'],
        };

        return Chambre::with('section')->whereIn('annee_judiciaire_id', AnneeJudiciaire::where('courante', true)->select('id'))->where('actif', true)->orderBy('ordre')->get()
            ->filter(fn (Chambre $c) => in_array($c->section?->code, $sections, true))->values();
    }

    /** Écrit le classeur modèle et renvoie son chemin (fichier temporaire, à supprimer après envoi). */
    public static function generer(string $serie): string
    {
        $def = RegistreAffaire::depuisSerie($serie);
        $colonnes = self::colonnes($serie);
        $chemin = tempnam(sys_get_temp_dir(), 'mod');
        $titre = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor('00794C')->withShouldWrapText(true)->withCellAlignment(CellAlignment::LEFT);
        $gras = (new Style)->withFontBold(true);
        $texte = fn (string $t, ?Style $s = null) => new StringCell($t, $s);
        $ligne = fn (array $valeurs, ?Style $s = null) => new Row(array_map(fn ($v) => $texte((string) $v, $s), $valeurs));
        $largeurs = [14, 18, 46, 40, 36, 28, 28, 22, 18];

        $w = new Writer;
        $w->openToFile($chemin);

        // 1. Mode d'emploi
        $feuille = $w->getCurrentSheet();
        $feuille->setName("Mode d'emploi");
        $feuille->setColumnWidth(120, 1);
        $w->addRow($ligne(["MODÈLE DE REPRISE DU REGISTRE {$serie} : ".mb_strtoupper($def['libelle'])], $gras));
        $w->addRow($ligne(['']));
        foreach ([
            "1. Remplissez l'onglet « Registre » : une ligne par affaire, sans changer les titres de la première ligne ni l'ordre des colonnes.",
            '2. Les colonnes marquées d\'une étoile (*) sont obligatoires.',
            '3. Le numéro s\'écrit avec l\'année : 1391/25 (le préfixe « '.$serie.' » est accepté).',
            '4. Les dates s\'écrivent jj/mm/aaaa.',
            '5. Plusieurs parties dans une même cellule : séparez-les par un point-virgule (;).',
            "6. La chambre (ou le cabinet) et la nature s'écrivent exactement comme dans l'onglet « Listes ».",
            "7. L'onglet « Exemple » montre deux lignes remplies : ne l'envoyez pas, seul l'onglet « Registre » est lu.",
            "8. Un fichier dont les titres ne correspondent pas à ce modèle est refusé. Si une seule ligne est en erreur, rien n'est importé : corrigez, puis relancez l'analyse.",
            '9. Limites : 20 000 lignes, classeur .xlsx sans macros.',
        ] as $t) {
            $w->addRow($ligne([$t]));
        }
        $w->addRow($ligne(['']));
        $w->addRow($ligne(['Colonnes du registre '.$serie], $gras));
        foreach ($colonnes as $c) {
            $w->addRow($ligne([($c['obligatoire'] ? '* ' : '  ').$c['titre'].' : '.$c['aide']]));
        }

        // 2. Registre (à remplir) : seulement les titres
        $feuille = $w->addNewSheetAndMakeItCurrent();
        $feuille->setName(self::FEUILLE);
        $feuille->setColumnWidth($largeurs[0], 1);
        foreach (array_slice($largeurs, 1, null, true) as $i => $l) {
            $feuille->setColumnWidth($l, $i + 1);
        }
        $w->addRow($ligne(self::titres($serie), $titre));

        // 3. Exemple (fictif)
        $feuille = $w->addNewSheetAndMakeItCurrent();
        $feuille->setName('Exemple');
        foreach ($largeurs as $i => $l) {
            $feuille->setColumnWidth($l, $i + 1);
        }
        $chambre = self::chambres($serie)->first()?->libelle ?? '';
        $nature = NatureAffaire::where('registre', $serie)->where('actif', true)->orderBy('libelle')->value('libelle') ?? '';
        $exemples = match ($def['cle']) {
            'penal' => [['1334/26', '12/10/2026', 'Destruction volontaire de biens', 'JOHNSON Wedha', 'Société EXEMPLE SARL', $chambre, $nature, '17/11/2026', 'A-12'], ['1340/26', '03/11/2026', 'Vol simple', 'DOSSO Ibrahim ; KONE Sékou', '', $chambre, '', '', '']],
            'instruction' => [['45/26', '12/10/2026', 'Meurtre', 'DOSSO Ibrahim', 'Famille KONAN', $chambre, $nature, '', ''], ['46/26', '03/11/2026', 'Escroquerie', 'KONE Sékou', '', $chambre, '', '', '']],
            default => [['1391/25', '12/03/2025', 'Paiement de loyers impayés', 'Mme KOFFI Adjoua', 'Société EXEMPLE IMMOBILIER SARL', $chambre, $nature, '20/05/2025', 'A-77'], ['1392/25', '14/03/2025', 'Expulsion', 'DIABY Karamoko ; TRAORE Salif', 'GNAGNE Brice', $chambre, '', '', '']],
        };
        $w->addRow($ligne(self::titres($serie), $titre));
        foreach ($exemples as $e) {
            $w->addRow($ligne($e));
        }

        // 4. Listes : valeurs reconnues
        $feuille = $w->addNewSheetAndMakeItCurrent();
        $feuille->setName('Listes');
        $feuille->setColumnWidth(48, 1);
        $feuille->setColumnWidth(40, 2);
        $chambres = self::chambres($serie)->pluck('libelle')->all();
        $natures = NatureAffaire::where('registre', $serie)->where('actif', true)->orderBy('libelle')->pluck('libelle')->all();
        $w->addRow($ligne([$serie === 'RI' || $serie === 'RE' ? 'Cabinets reconnus' : 'Chambres reconnues', "Natures d'affaire reconnues"], $titre));
        for ($i = 0; $i < max(count($chambres), count($natures)); $i++) {
            $w->addRow($ligne([$chambres[$i] ?? '', $natures[$i] ?? '']));
        }

        $w->setCurrentSheet($w->getSheets()[1]);   // le classeur s'ouvre sur l'onglet à remplir
        $w->close();

        return $chemin;
    }
}
