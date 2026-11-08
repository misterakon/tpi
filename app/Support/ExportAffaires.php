<?php

namespace App\Support;

use App\Models\Affaire;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/*
 | Export de la liste des affaires (Excel et PDF) : mêmes filtres que l'écran, uniquement les affaires que l'utilisateur a le droit de voir.
 | Le PDF est produit sans accès réseau (pas de fichier distant), le tableur n'écrit que du texte (une cellule « =… » reste du texte, jamais une formule).
 */
class ExportAffaires
{
    public const COLONNES = ['Numéro', 'Registre', 'Date', 'Parties', 'Objet', 'Nature', 'Chambre', 'Statut'];

    /** Les filtres reconnus, nettoyés. @return array{chambre: ?int, du: ?string, au: ?string, statut: ?string, serie: ?string} */
    public static function filtres(array $entree): array
    {
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false ? $v : null;
        $statut = $entree['statut'] ?? null;
        $serie = $entree['serie'] ?? null;

        return [
            'chambre' => ! empty($entree['chambre']) && ctype_digit((string) $entree['chambre']) ? (int) $entree['chambre'] : null,
            'du' => $date($entree['du'] ?? null),
            'au' => $date($entree['au'] ?? null),
            'statut' => is_string($statut) && array_key_exists($statut, Affaire::STATUTS) ? $statut : null,
            'serie' => is_string($serie) && in_array($serie, ['RG', 'RP', 'RI', 'RE'], true) ? $serie : null,
        ];
    }

    public static function appliquer(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['chambre'] ?? null, fn ($q, $v) => $q->where('chambre_id', $v))
            ->when($f['du'] ?? null, fn ($q, $v) => $q->whereDate('date_rg', '>=', $v))
            ->when($f['au'] ?? null, fn ($q, $v) => $q->whereDate('date_rg', '<=', $v))
            ->when($f['statut'] ?? null, fn ($q, $v) => $q->where('statut', $v))
            ->when($f['serie'] ?? null, fn ($q, $v) => $q->where('serie', $v));
    }

    /** @return list<list<string>> */
    public static function lignes(iterable $affaires): array
    {
        $lignes = [];
        foreach ($affaires as $a) {
            $lignes[] = [
                $a->numeroAffichage(), $a->serie, $a->date_rg->format('d/m/Y').($a->created_at ? ' '.$a->created_at->format('H:i') : ''),
                $a->intitule(), (string) $a->objet, (string) $a->nature?->libelle, (string) $a->chambre?->libelle, Affaire::STATUTS[$a->statut] ?? $a->statut,
            ];
        }

        return $lignes;
    }

    /** Écrit le classeur et renvoie son chemin (fichier temporaire, à supprimer après envoi). */
    public static function excel(array $lignes): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'aff');
        $w = new Writer;
        $w->getOptions()->setColumnWidth(14, 1);
        $w->getOptions()->setColumnWidth(10, 2);
        $w->getOptions()->setColumnWidth(18, 3);
        $w->getOptions()->setColumnWidth(48, 4);
        $w->getOptions()->setColumnWidth(48, 5);
        $w->getOptions()->setColumnWidth(26, 6);
        $w->getOptions()->setColumnWidth(30, 7);
        $w->getOptions()->setColumnWidth(14, 8);
        $w->openToFile($chemin);
        $entete = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor('00794C');
        $w->addRow(Row::fromValuesWithStyles(self::COLONNES, array_fill(0, count(self::COLONNES), $entete)));
        foreach ($lignes as $ligne) {
            $w->addRow(new Row(array_map(fn ($v) => new StringCell((string) $v, null), $ligne)));   // cellules de texte explicites : OpenSpout transformerait sinon « =… » en formule
        }
        $w->close();

        return $chemin;
    }

    public static function pdf(array $lignes, array $criteres, string $titre): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('affaires.liste.export_pdf', ['lignes' => $lignes, 'colonnes' => self::COLONNES, 'criteres' => $criteres, 'titre' => $titre, 'date' => now()])->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }
}
