<?php

namespace App\Support\Organisation;

use App\Models\Affectation;
use App\Models\Agent;
use App\Models\AnneeJudiciaire;
use App\Models\ArticleOrdonnance;
use App\Models\Chambre;
use App\Models\Creneau;
use App\Models\DelaiEnrolement;
use App\Models\Formation;
use App\Models\Ordonnance;
use App\Models\Salle;
use App\Models\Section;
use App\Models\Service;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/*
 | Classeur Excel de l'ordonnance en vigueur (voir docs/FORMAT_EXCEL_ORDONNANCE.md) : une feuille par notion, les codes relient les feuilles.
 | Les colonnes grises (« information ») aident à lire le fichier mais sont ignorées à l'import : seules les colonnes vertes comptent.
 | Sert de modèle de départ : on le corrige puis on le recharge.
 */
class ExportOrdonnance
{
    private const VERT = '00794C';

    private const GRIS = '6B7280';

    private const JOURS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

    /** Écrit le classeur et renvoie son chemin (fichier temporaire à supprimer après envoi). */
    public static function generer(AnneeJudiciaire $annee): string
    {
        $feuilles = self::feuilles($annee);

        $chemin = tempnam(sys_get_temp_dir(), 'ord');
        $w = new Writer;
        $w->openToFile($chemin);

        $entete = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor(self::VERT)->withShouldWrapText(true)->withCellAlignment(CellAlignment::LEFT);
        $info = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor(self::GRIS)->withShouldWrapText(true);
        $texte = (new Style)->withShouldWrapText(true);
        $titre = (new Style)->withFontBold(true)->withFontSize(15)->withFontColor(self::VERT);
        $gras = (new Style)->withFontBold(true);

        $premiere = true;
        foreach ($feuilles as $nom => $f) {
            $feuille = $premiere ? $w->getCurrentSheet() : $w->addNewSheetAndMakeItCurrent();
            $premiere = false;
            $feuille->setName($nom);

            if (isset($f['lisez'])) {   // feuille d'explications : texte libre
                $feuille->setColumnWidth(120, 1);
                foreach ($f['lisez'] as $i => $l) {
                    $w->addRow(Row::fromValuesWithStyles([$l], [$i === 0 ? $titre : (str_starts_with($l, '■') ? $gras : $texte)]));
                }

                continue;
            }

            foreach ($f['largeurs'] as $i => $largeur) {
                $feuille->setColumnWidth($largeur, $i + 1);
            }
            $feuille->setSheetView((new SheetView)->withFreezeRow(2));
            $w->addRow(Row::fromValuesWithStyles(array_column($f['colonnes'], 0), array_map(fn ($c) => ($c[1] ?? false) ? $info : $entete, $f['colonnes'])));
            foreach ($f['lignes'] as $l) {
                $w->addRow(Row::fromValuesWithStyles(array_map(fn ($v) => $v ?? '', $l), array_fill(0, count($l), $texte)));
            }
        }
        $w->close();

        return $chemin;
    }

    /** Nom du fichier proposé au téléchargement. */
    public static function nomFichier(AnneeJudiciaire $annee): string
    {
        return 'ordonnance_'.str_replace('/', '-', $annee->libelle).'_'.now()->format('Ymd').'.xlsx';
    }

    /** @return array<string, array{colonnes?: list<array{0: string, 1?: bool}>, largeurs?: list<float>, lignes?: list<list<mixed>>, lisez?: list<string>}> */
    public static function feuilles(AnneeJudiciaire $annee): array
    {
        $date = fn ($d) => $d?->format('d/m/Y');
        $oui = fn ($b) => $b ? 'oui' : 'non';
        $ordo = Ordonnance::where('annee_judiciaire_id', $annee->id)->orderByDesc('date_signature')->first();

        $sections = Section::orderBy('ordre')->get();
        $salles = Salle::orderBy('nom')->get();
        $agents = Agent::orderBy('nom')->orderBy('prenoms')->get();
        $chambres = Chambre::with('section')->where('annee_judiciaire_id', $annee->id)->orderBy('ordre')->get();
        $formations = Formation::with('chambre')->whereIn('chambre_id', $chambres->pluck('id'))->orderBy('chambre_id')->orderBy('ordre')->get();
        $affectations = Affectation::with(['formation', 'agent'])->whereIn('formation_id', $formations->pluck('id'))->orderBy('formation_id')->orderBy('id')->get();
        $creneaux = Creneau::with(['formation', 'salle'])->whereIn('formation_id', $formations->pluck('id'))->orderBy('formation_id')->orderBy('jour')->orderBy('heure')->get();
        $services = Service::with(['agents.agent', 'membresExterieurs'])->where('annee_judiciaire_id', $annee->id)->orderBy('ordre')->get();
        $articles = ArticleOrdonnance::where('annee_judiciaire_id', $annee->id)->orderBy('ordre')->get();
        $jours = DelaiEnrolement::orderBy('jour_audience')->get();

        $nomAgent = fn (?Agent $a) => $a ? trim($a->nom.' '.$a->prenoms) : '';

        return [
            'Lisez-moi' => ['lisez' => self::explications($annee)],

            'Ordonnance' => [
                'colonnes' => [['Rubrique'], ['Valeur']], 'largeurs' => [34, 80],
                'lignes' => [
                    ['Année judiciaire', $annee->libelle],
                    ["N° de l'ordonnance", $ordo?->numero ?? $annee->ordonnance],
                    ['Type (annuelle ou modificative)', $ordo?->type ?? 'annuelle'],
                    ['Objet', $ordo?->objet],
                    ['Date de signature', $date($ordo?->date_signature)],
                    ["Date d'effet", $date($ordo?->date_effet ?? $ordo?->date_signature)],
                    ["Début de l'année judiciaire", $date($annee->debut)],
                    ["Fin de l'année judiciaire", $date($annee->fin)],
                    ['Observations', $ordo?->observations],
                ],
            ],

            'Sections' => [
                'colonnes' => [['N°'], ['Libellé'], ['Ordre'], ['Code interne (information)', true]], 'largeurs' => [8, 60, 8, 22],
                'lignes' => $sections->map(fn (Section $s) => [$s->id, $s->libelle, $s->ordre, $s->code])->all(),
            ],

            'Salles' => [
                'colonnes' => [['N°'], ['Nom'], ['Type (audience, cabinet, reunion)'], ['Emplacement']], 'largeurs' => [8, 46, 20, 46],
                'lignes' => $salles->map(fn (Salle $s) => [$s->id, $s->nom, $s->type, $s->emplacement])->all(),
            ],

            'Agents' => [
                'colonnes' => [['N°'], ['Nom'], ['Prénoms'], ['Sexe (M ou F)'], ['Fonction'], ['Catégorie'], ['Matricule'], ['Téléphone'], ['E-mail']],
                'largeurs' => [8, 22, 40, 10, 24, 16, 14, 16, 30],
                'lignes' => $agents->map(fn (Agent $a) => [$a->id, $a->nom, $a->prenoms, $a->sexe, $a->fonction, $a->categorie, $a->matricule, $a->telephone, $a->email])->all(),
            ],

            'Chambres' => [
                'colonnes' => [['N°'], ['Section (N° ou nom)'], ['Groupe'], ['Libellé'], ['Intitulé'], ['Attributions'], ['Remarque'], ['Ordre'], ['Section (information)', true]],
                'largeurs' => [8, 18, 28, 26, 36, 90, 50, 8, 40],
                'lignes' => $chambres->map(fn (Chambre $c) => [$c->id, $c->section_id, $c->groupe, $c->libelle, $c->intitule, $c->attributions, $c->remarque, $c->ordre, $c->section?->libelle])->all(),
            ],

            'Formations' => [
                'colonnes' => [['N°'], ['Chambre (N° ou nom)'], ['Nom (vide si une seule formation)'], ['Intitulé'], ['Attributions propres'], ['Ordre'], ['Chambre (information)', true]],
                'largeurs' => [8, 18, 24, 36, 60, 8, 28],
                'lignes' => $formations->map(fn (Formation $f) => [$f->id, $f->chambre_id, $f->nom, $f->intitule, $f->attributions, $f->ordre, $f->chambre?->libelle])->all(),
            ],

            'Composition' => [
                'colonnes' => [['N°'], ['Formation (N°)'], ['Rôle'], ['Suppléant (oui/non)'], ['Agent (N° ou nom)'], ['Chef de section (oui/non)'], ['Du (jj/mm/aaaa)'], ['Au (jj/mm/aaaa)'], ['Remarque'], ['Agent (information)', true], ['Chambre (information)', true]],
                'largeurs' => [8, 12, 22, 12, 14, 12, 14, 14, 30, 40, 28],
                'lignes' => $affectations->map(fn (Affectation $a) => [
                    $a->id, $a->formation_id, $a->role, $oui($a->suppleant), $a->agent_id, $oui($a->chef_section), $date($a->debut), $date($a->fin), $a->remarque,
                    $nomAgent($a->agent), $a->formation?->chambre?->libelle,
                ])->all(),
            ],

            'Audiences' => [
                'colonnes' => [['N°'], ['Formation (N°)'], ['Type'], ['Jour'], ['Heure (hh:mm)'], ['Fréquence'], ['Salle (N° ou nom)'], ['Précision'], ['Du (jj/mm/aaaa)'], ['Au (jj/mm/aaaa)'], ['Chambre (information)', true]],
                'largeurs' => [8, 12, 40, 12, 12, 16, 14, 40, 14, 14, 28],
                'lignes' => $creneaux->map(fn (Creneau $c) => [
                    $c->id, $c->formation_id, $c->type, self::JOURS[$c->jour] ?? $c->jour, $c->heure ? substr($c->heure, 0, 5) : null, $c->frequence, $c->salle_id, $c->precision,
                    $date($c->debut), $date($c->fin), $c->formation?->chambre?->libelle,
                ])->all(),
            ],

            'Services' => [
                'colonnes' => [['N°'], ['Catégorie'], ['Libellé'], ['Ordre'], ['Remarque ou modalité']],
                'largeurs' => [8, 16, 70, 8, 90],
                'lignes' => $services->map(fn (Service $s) => [$s->id, $s->categorie, $s->libelle, $s->ordre, $s->remarque])->all(),
            ],

            'Responsables' => [
                'colonnes' => [['Service (N° ou nom)'], ['Agent (N° ou nom)'], ['Fonction exercée'], ['Ordre'], ['Agent (information)', true], ['Service (information)', true]],
                'largeurs' => [14, 14, 50, 8, 40, 50],
                'lignes' => $services->flatMap(fn (Service $s) => $s->agents->map(fn ($sa) => [$s->id, $sa->agent_id, $sa->fonction, $sa->ordre, $nomAgent($sa->agent), $s->libelle]))->all(),
            ],

            'Membres extérieurs' => [
                'colonnes' => [['Service (N° ou nom)'], ['Nom'], ['Qualité'], ['Institution'], ['Ordre'], ['Service (information)', true]],
                'largeurs' => [14, 40, 70, 40, 8, 50],
                'lignes' => $services->flatMap(fn (Service $s) => $s->membresExterieurs->map(fn ($m) => [$s->id, $m->nom, $m->qualite, $m->institution, $m->ordre, $s->libelle]))->all(),
            ],

            'Dates limites' => [
                'colonnes' => [["Jour d'audience"], ['Jour limite'], ['Heure (hh:mm)'], ['Chambres concernées'], ['Exclusions'], ['Remarque']],
                'largeurs' => [18, 18, 12, 50, 50, 40],
                'lignes' => $jours->map(fn (DelaiEnrolement $d) => [
                    self::JOURS[$d->jour_audience] ?? $d->jour_audience, self::JOURS[$d->jour_limite] ?? $d->jour_limite, substr((string) $d->heure, 0, 5),
                    'Chambres civiles, commerciales et administratives', "Référés et voies d'exécution", $d->remarque,
                ])->all(),
            ],

            'Articles' => [
                'colonnes' => [['Numéro'], ['Intitulé'], ['Texte complet'], ['Rattaché à (sections, composition, services, dates_limites, audiences ou vide)']],
                'largeurs' => [20, 40, 120, 30],
                'lignes' => $articles->map(fn (ArticleOrdonnance $a) => [$a->numero, $a->intitule, $a->texte, $a->rattache_a])->all(),
            ],

            'Remarques' => [
                'colonnes' => [['Remarque (variantes d\'orthographe, points à confirmer, dispositions sans équivalent)']],
                'largeurs' => [160],
                'lignes' => [],
            ],
        ];
    }

    /** @return list<string> */
    private static function explications(AnneeJudiciaire $annee): array
    {
        return [
            'Organisation du tribunal : fichier de l\'ordonnance '.$annee->libelle,
            'Édité le '.now()->format('d/m/Y à H:i').' à partir de l\'organisation enregistrée dans l\'application.',
            '',
            '■ À quoi sert ce fichier',
            'Il contient toute l\'organisation de l\'ordonnance : sections, chambres, formations, compositions, audiences, services, comités, délégations, fonctions, bureaux locaux, dates limites et articles. Corrigez-le ici, puis rechargez-le dans l\'application (Organisation > Ordonnances > Charger un fichier Excel).',
            '',
            '■ Comment le remplir',
            '• Une feuille par notion ; une ligne = une chose (une personne dans une formation, un créneau d\'audience…). Pas de cellule fusionnée.',
            '• Les NUMÉROS (colonne « N° », nombres entiers) sont ceux de la base de données et relient les feuilles : la colonne « Formation (N°) » de Composition renvoie au N° de la feuille Formations. Ne changez jamais un N° existant. Pour une NOUVELLE chambre, formation, salle, agent ou service : laissez son N° vide et, dans les feuilles qui s\'y rapportent, écrivez son NOM à la place du numéro.',
            '• Les colonnes à en-tête VERT sont lues. Les colonnes à en-tête GRIS (« information ») ne servent qu\'à vous aider à lire : elles sont ignorées.',
            '• Dans Composition et Responsables, la colonne « Agent » accepte le N° OU le nom écrit comme dans l\'ordonnance. Un nom douteux ne sera pas deviné : l\'application vous demandera de choisir la bonne personne, et s\'en souviendra.',
            '• Une cellule vide veut dire « non renseigné », jamais « effacer ».',
            '• Dates : jj/mm/aaaa. Heures : hh:mm. Oui ou non : oui / non.',
            '',
            '■ Valeurs admises',
            '• Rôle : président, assesseur, greffier, juge, juge délégué, représentant SPJEJ, assesseur SPJEJ, assesseur employeur, assesseur travailleur (écrits sans accent et avec _ : president, juge_delegue, representant_spjej…).',
            '• Catégorie de service : coordination, comite, delegation, service, fonction, bureau.',
            '• Fréquence : hebdomadaire, trimestrielle, a_la_demande, ponctuelle.',
            '• Jour : lundi, mardi, mercredi, jeudi, vendredi, samedi, dimanche.',
            '',
            '■ Ce qui se passe au chargement',
            '1. Le fichier est lu et contrôlé : rien n\'est encore modifié.',
            '2. Les noms incertains vous sont proposés pour confirmation.',
            '3. Un écran « ce qui va changer » montre les ajouts, modifications et retraits ; vous pouvez écarter un changement.',
            '4. Vous appliquez. Rien n\'est jamais supprimé : ce qui disparaît du fichier est clôturé avec une date de fin. L\'import peut être annulé.',
            '',
            '■ Les articles',
            'La feuille Articles garde le texte complet de chaque article. Le numéro d\'article n\'est qu\'une étiquette : l\'application se fie à la colonne « Rattaché à », jamais au numéro.',
        ];
    }
}
