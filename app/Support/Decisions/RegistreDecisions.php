<?php

namespace App\Support\Decisions;

use App\Models\Decision;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/*
 | Registre des décisions : toutes les décisions dont le texte est figé (validées, signées, délivrées), avec leur numéro, leur date, leur chambre
 | et leurs remises, à consulter avec des filtres et à exporter (Excel, PDF avec l'en-tête du tribunal). Chacun ne voit que les chambres de son périmètre.
 */
class RegistreDecisions
{
    public const COLONNES = ['N° de décision', 'Date de la décision', 'Date de signature', 'Référence', 'Type de décision', 'Chambre', 'Statut', 'Remises'];

    /** @return array{annee: ?int, chambre: ?int, statut: ?string, q: string} */
    public static function filtres(array $entree): array
    {
        $statut = $entree['statut'] ?? null;

        return [
            'annee' => ! empty($entree['annee']) && ctype_digit((string) $entree['annee']) && (int) $entree['annee'] >= 2000 && (int) $entree['annee'] <= 2100 ? (int) $entree['annee'] : null,
            'chambre' => ! empty($entree['chambre']) && ctype_digit((string) $entree['chambre']) ? (int) $entree['chambre'] : null,
            'statut' => is_string($statut) && in_array($statut, Decision::STATUTS_VALIDES, true) ? $statut : null,
            'q' => mb_substr(trim((string) ($entree['q'] ?? '')), 0, 80),
        ];
    }

    public static function requete(User $user, array $f): Builder
    {
        return Decision::visiblesPour($user)->with(['modele:id,nom', 'chambre:id,libelle'])->withCount('delivrances')->whereIn('statut', Decision::STATUTS_VALIDES)
            ->when($f['annee'], fn ($q, $v) => $q->where(fn ($w) => $w->where('numero_annee', $v)->orWhere(fn ($x) => $x->whereNull('numero_annee')->whereYear('validee_le', $v))))
            ->when($f['chambre'], fn ($q, $v) => $q->where('chambre_id', $v))
            ->when($f['statut'], fn ($q, $v) => $q->where('statut', $v))
            ->when($f['q'] !== '', function ($q) use ($f) {
                $mot = '%'.addcslashes($f['q'], '%_\\').'%';
                // « D-145/2026 » ou « 145/2026 » : le numéro et l'année ; sinon une partie du numéro ou de la référence de l'affaire.
                if (preg_match('#^\s*(?:D\s*-?\s*)?(\d{1,6})\s*/\s*(\d{4})\s*$#i', $f['q'], $m)) {
                    $q->where('numero', $m[1])->where('numero_annee', (int) $m[2]);
                } else {
                    $q->where(fn ($w) => $w->where('numero', 'like', $mot)->orWhere('reference', 'like', $mot));
                }
            })
            ->orderByDesc('numero_annee')->orderByDesc('numero_seq')->orderByDesc('validee_le')->orderByDesc('id');
    }

    /** Les années où il y a des décisions. @return list<int> */
    public static function annees(User $user): array
    {
        $a = Decision::visiblesPour($user)->whereIn('statut', Decision::STATUTS_VALIDES)->whereNotNull('numero_annee')->distinct()->pluck('numero_annee')->map(fn ($v) => (int) $v)->all();
        $a[] = (int) today()->format('Y');
        rsort($a);

        return array_values(array_unique($a));
    }

    /** @return array<string, mixed> */
    public static function ecran(Decision $d): array
    {
        return [
            'id' => $d->id, 'numero' => $d->numeroAffichage(), 'date_decision' => $d->date_decision?->format('d/m/Y'), 'date_signature' => $d->date_signature?->format('d/m/Y'),
            'reference' => $d->reference, 'type' => (string) $d->modele?->nom, 'chambre' => (string) $d->chambre?->libelle, 'statut' => $d->statut, 'statut_libelle' => Decision::STATUTS[$d->statut] ?? $d->statut,
            'remises' => (int) $d->delivrances_count, 'validee_le' => $d->validee_le?->format('d/m/Y'),
        ];
    }

    /** @param  iterable<Decision>  $decisions  @return list<list<string>> */
    public static function lignes(iterable $decisions): array
    {
        $sortie = [];
        foreach ($decisions as $d) {
            $e = self::ecran($d);
            $sortie[] = [(string) $e['numero'], (string) ($e['date_decision'] ?? $e['validee_le']), (string) $e['date_signature'], $e['reference'], $e['type'], $e['chambre'], $e['statut_libelle'], (string) $e['remises']];
        }

        return $sortie;
    }

    public static function excel(array $lignes): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'reg');
        $w = new Writer;
        foreach ([16, 18, 18, 22, 40, 30, 14, 10] as $i => $largeur) {
            $w->getOptions()->setColumnWidth($largeur, $i + 1);
        }
        $w->openToFile($chemin);
        $w->getCurrentSheet()->setName('Registre des décisions');
        $w->addRow(Row::fromValuesWithStyles(self::COLONNES, array_fill(0, count(self::COLONNES), (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor('00794C'))));
        foreach ($lignes as $ligne) {
            $w->addRow(new Row(array_map(fn ($v) => new StringCell((string) $v, null), $ligne)));   // du texte seulement : jamais de formule
        }
        $w->close();

        return $chemin;
    }

    public static function pdf(array $lignes, array $criteres): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('decisions.registre.export_pdf', [
            'lignes' => $lignes, 'colonnes' => self::COLONNES, 'criteres' => $criteres, 'date' => now(), 'titre' => 'REGISTRE DES DÉCISIONS',
            'logo' => \App\Support\Roles\RoleExport::filigraneDonnees(), 'armoiries' => \App\Support\Roles\RoleExport::armoiriesDonnees(),
            'gauche' => \App\Support\Roles\RoleDocument::enteteGauche(), 'droite' => \App\Support\Roles\RoleDocument::enteteDroite(),
        ])->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }
}
