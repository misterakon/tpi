<?php

namespace App\Http\Controllers;

use App\Models\Affaire;
use App\Models\AssistanceJudiciaire;
use App\Models\Decision;
use App\Models\DecisionPenale;
use App\Models\PieceDossier;
use App\Models\Requete;
use App\Services\Journal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/*
 | Recherche plein texte : un champ, tous les mots saisis doivent se retrouver (dans n'importe quel champ de l'élément), dans les affaires (numéro, objet, articles, parties),
 | les décisions, les requêtes, les décisions pénales, les pièces du dossier numérique (titre et nom du fichier) et l'assistance judiciaire.
 | Chacun ne trouve que ce qu'il a le droit de voir : droits de chaque rubrique, chambres du périmètre, familles protégées.
 */
class RechercheController extends Controller
{
    private const PAR_RUBRIQUE = 12;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $termes = $this->termes($q);
        $user = $request->user();
        $rubriques = [];

        if ($termes !== []) {
            Journal::audit('recherche', null, mb_substr($q, 0, 120));
            $rubriques = array_filter([
                Gate::allows('affaires.voir') ? $this->rubrique('Affaires', 'fa-folder-open', Affaire::visiblesPour($user)->with(['parties', 'chambre:id,libelle']), $termes, ['numero_complet', 'numero_parquet', 'objet', 'articles', 'ancien_numero'], 'parties', ['nom', 'prenoms', 'denomination'],
                    fn (Affaire $a) => ['titre' => $a->numeroAffichage().' · '.\App\Support\Audiences\SuiviAffaires::parties($a), 'sous' => $a->objet.($a->chambre ? ' · '.$a->chambre->libelle : ''), 'url' => route('affaires.liste.fiche', $a->id), 'texte' => $a->numeroAffichage().' '.$a->objet.' '.$a->articles.' '.$a->parties->map->libelle()->implode(' ')]) : null,
                Gate::allows('redaction.voir') ? $this->rubrique('Décisions', 'fa-file-signature', Decision::visiblesPour($user), $termes, ['reference', 'numero', 'valeurs'], null, [],
                    fn (Decision $d) => ['titre' => ($d->numeroAffichage() ?? $d->reference), 'sous' => (Decision::STATUTS[$d->statut] ?? $d->statut).($d->date_decision ? ' · '.$d->date_decision->format('d/m/Y') : ''), 'url' => route('decisions.redaction.edition', $d->id), 'texte' => ($d->numeroAffichage() ?? '').' '.$d->reference]) : null,
                Gate::allows('requetes.voir') ? $this->rubrique('Requêtes', 'fa-file-lines', Requete::visiblesPour($user), $termes, ['numero_complet', 'objet', 'requerants', 'defunt_nom', 'reference_decision'], null, [],
                    fn (Requete $r) => ['titre' => $r->numeroAffichage().' · '.$r->objet, 'sous' => 'Requérants : '.str_replace("\n", ', ', (string) $r->requerants), 'url' => route('affaires.requetes'), 'texte' => $r->numeroAffichage().' '.$r->objet.' '.$r->requerants]) : null,
                Gate::allows('repertoire_penal.voir') ? $this->rubrique('Décisions pénales', 'fa-gavel', DecisionPenale::visiblesPour($user)->with('affaire'), $termes, ['infraction_retenue', 'numero_decision', 'dispositif', 'autres_peines'], null, [],
                    fn (DecisionPenale $d) => ['titre' => $d->affaire->numeroAffichage().' · '.($d->infraction_retenue ?: $d->affaire->objet), 'sous' => (DecisionPenale::ISSUES[$d->issue] ?? '').' · '.$d->date_decision->format('d/m/Y'), 'url' => route('decisions.repertoire_penal'), 'texte' => $d->affaire->numeroAffichage().' '.$d->infraction_retenue]) : null,
                Gate::allows('dossiers.voir') ? $this->rubrique('Pièces du dossier numérique', 'fa-paperclip', PieceDossier::whereIn('affaire_id', Affaire::visiblesPour($user)->select('affaires.id'))->with('affaire'), $termes, ['titre', 'nom_original'], null, [],
                    fn (PieceDossier $p) => ['titre' => $p->titre, 'sous' => $p->affaire?->numeroAffichage().' · '.$p->nom_original, 'url' => $p->affaire ? route('affaires.liste.fiche', $p->affaire_id).'#dossier' : '#', 'texte' => $p->titre.' '.$p->nom_original]) : null,
                Gate::allows('assistance.voir') ? $this->rubrique('Assistance judiciaire', 'fa-hand-holding-heart', AssistanceJudiciaire::query(), $termes, ['numero', 'demandeur', 'objet', 'numero_decision', 'observation'], null, [],
                    fn (AssistanceJudiciaire $a) => ['titre' => $a->numero.' · '.$a->demandeur, 'sous' => (string) $a->objet, 'url' => route('assistance'), 'texte' => $a->numero.' '.$a->demandeur.' '.$a->objet]) : null,
            ]);
        }

        return view('recherche.index', ['q' => $q, 'termes' => $termes, 'rubriques' => array_values($rubriques), 'trop_court' => $q !== '' && $termes === []]);
    }

    /** Les mots de la recherche : au moins deux caractères chacun, six au plus. @return list<string> */
    private function termes(string $q): array
    {
        return array_slice(array_values(array_filter(preg_split('/\s+/u', mb_substr($q, 0, 120)) ?: [], fn ($t) => mb_strlen($t) >= 2)), 0, 6);
    }

    /**
     * Une rubrique de résultats : tous les mots doivent se retrouver dans l'un des champs (ou, pour une relation, dans l'un de ses champs).
     *
     * @param  list<string>  $termes
     * @param  list<string>  $colonnes
     * @param  list<string>  $colonnesRelation
     * @return array{titre: string, icone: string, total: int, items: list<array<string, string>>}|null
     */
    private function rubrique(string $titre, string $icone, Builder|\Illuminate\Database\Eloquent\Relations\Relation $requete, array $termes, array $colonnes, ?string $relation, array $colonnesRelation, callable $ligne): ?array
    {
        foreach ($termes as $t) {
            $motif = '%'.str_replace(['|', '%', '_'], ['||', '|%', '|_'], $t).'%';   // « | » protège les jokers SQL : « 100% » se cherche tel quel
            $requete->where(function ($w) use ($colonnes, $relation, $colonnesRelation, $motif) {
                foreach ($colonnes as $c) {
                    $w->orWhereRaw(DB::getQueryGrammar()->wrap($c)." like ? escape '|'", [$motif]);
                }
                if ($relation) {
                    $w->orWhereHas($relation, function ($r) use ($colonnesRelation, $motif) {
                        $r->where(function ($x) use ($colonnesRelation, $motif) {
                            foreach ($colonnesRelation as $c) {
                                $x->orWhereRaw(DB::getQueryGrammar()->wrap($c)." like ? escape '|'", [$motif]);
                            }
                        });
                    });
                }
            });
        }
        $total = (clone $requete)->toBase()->getCountForPagination();
        if ($total === 0) {
            return null;
        }

        return ['titre' => $titre, 'icone' => $icone, 'total' => $total, 'items' => $requete->orderByDesc('id')->limit(self::PAR_RUBRIQUE)->get()->map($ligne)->all()];
    }
}
