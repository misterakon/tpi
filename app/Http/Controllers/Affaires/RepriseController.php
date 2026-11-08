<?php

namespace App\Http\Controllers\Affaires;

use App\Http\Controllers\Controller;
use App\Models\Affaire;
use App\Models\ImportReprise;
use App\Models\NatureAffaire;
use App\Models\PieceDossier;
use App\Services\Journal;
use App\Services\NumerotationRegistre;
use App\Support\Reprise\FichierTableur;
use App\Support\Reprise\LigneReprise;
use App\Support\Reprise\ModeleReprise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/*
 | Reprise de l'existant (lot T) : importer les registres tenus sous Excel, à partir d'un MODÈLE à télécharger. Seul un classeur dont l'onglet
 | « Registre » porte exactement les titres du modèle est accepté ; tout autre fichier Excel est refusé avant la lecture des lignes.
 | Le traitement est piloté par l'écran, paquet par paquet, pour montrer une vraie progression. L'import est « tout ou rien » :
 | une seule ligne en erreur le bloque (les doublons, eux, sont ignorés), rien n'est écrit tant que le fichier n'est pas corrigé.
 |
 | Sécurité : droits séparés (préparer / importer / annuler), fichier vérifié avant lecture (vraie archive, sans macros, sans bombe de
 | décompression), stocké sous un nom généré hors du site, cellules lues comme du texte (jamais de formule), adresses par référence
 | aléatoire, un seul paquet à la fois par import, chaque étape vérifie l'état de l'import, tout est tracé au journal.
 | Droits : reprise.voir / creer / modifier / annuler, et le droit spécial reprise.importer (écriture définitive dans le registre).
 */
class RepriseController extends Controller
{
    private const PAQUET_CONTROLE = 250;

    private const PAQUET_IMPORT = 100;

    public function index(): View
    {
        Gate::authorize('reprise.voir');

        return view('affaires.reprise.index', [
            'imports' => ImportReprise::with('auteur')->latest('id')->limit(30)->get(),
            'taille_max' => $this->tailleMaxOctets(),
            'lignes_max' => FichierTableur::MAX_LIGNES,
            'colonnes' => ['RG' => ModeleReprise::colonnes('RG'), 'RP' => ModeleReprise::colonnes('RP'), 'RI' => ModeleReprise::colonnes('RI'), 'RE' => ModeleReprise::colonnes('RE')],
        ]);
    }

    /** Télécharge le modèle Excel du registre choisi, avec les chambres et natures de l'application. */
    public function modele(string $serie)
    {
        Gate::authorize('reprise.voir');
        abort_unless(in_array($serie, ['RG', 'RP', 'RI', 'RE'], true), 404);

        return response()->download(ModeleReprise::generer($serie), 'modele-reprise-'.$serie.'.xlsx', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true);
    }

    /** Téléverse le classeur et vérifie qu'il respecte le modèle (archive saine, onglet « Registre », titres exacts). */
    public function televerser(Request $request): JsonResponse
    {
        Gate::authorize('reprise.creer');
        $max = $this->tailleMaxOctets();
        $v = $request->validate([
            'fichier' => ['required', 'file', 'max:'.(int) floor($max / 1024), 'extensions:xlsx'],
            'serie' => ['required', 'in:RG,RP,RI,RE'],
        ], [
            'fichier.required' => 'Choisissez un fichier Excel (.xlsx).', 'fichier.file' => "Le fichier n'a pas été reçu en entier.", 'fichier.max' => 'Le fichier dépasse la taille permise ('.round($max / 1048576, 1).' Mo).',
            'fichier.extensions' => 'Seuls les classeurs Excel .xlsx sont acceptés (pas de .xls, .xlsm ni .csv).', 'serie.required' => 'Choisissez le registre.', 'serie.in' => 'Registre inconnu.',
        ]);

        $fichier = $request->file('fichier');
        $nom = (string) Str::lower((string) Str::ulid()).'.xlsx';
        $fichier->storeAs(ImportReprise::DOSSIER, $nom, 'local');
        $chemin = Storage::disk('local')->path(ImportReprise::DOSSIER.'/'.$nom);

        try {
            FichierTableur::verifier($chemin, $max);
            $feuilles = FichierTableur::feuilles($chemin);
            if (! in_array(ModeleReprise::FEUILLE, $feuilles, true)) {
                throw new RuntimeException('Ce fichier ne respecte pas le modèle : il n\'a pas d\'onglet « '.ModeleReprise::FEUILLE.' ». Téléchargez le modèle du registre '.$v['serie'].' et recopiez-y vos lignes.');
            }
            $entete = [];
            FichierTableur::parcourir($chemin, ModeleReprise::FEUILLE, function (int $n, array $cellules) use (&$entete) {
                $entete = $cellules;

                return false;   // seule la première ligne (les titres) est lue ici
            });
            if ($erreur = ModeleReprise::verifierEntete($entete, $v['serie'])) {
                throw new RuntimeException($erreur);
            }
        } catch (Throwable $e) {
            @unlink($chemin);
            Journal::audit('reprise_refusee', null, Str::limit($fichier->getClientOriginalName(), 80), [], ['motif' => $e instanceof RuntimeException ? $e->getMessage() : 'fichier illisible']);

            return response()->json(['status' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Le classeur est illisible ou endommagé.'], 422);
        }

        $empreinte = hash_file('sha256', $chemin);
        $import = ImportReprise::create([
            'reference' => (string) Str::ulid(), 'user_id' => $request->user()?->id, 'serie' => $v['serie'], 'nom_fichier' => $this->nomPropre($fichier->getClientOriginalName()),
            'chemin' => $nom, 'taille' => filesize($chemin), 'empreinte' => $empreinte, 'statut' => 'televerse', 'feuille' => ModeleReprise::FEUILLE, 'feuilles' => [ModeleReprise::FEUILLE],
        ]);
        Journal::audit('reprise_televersee', $import, $import->nom_fichier, [], ['registre' => $v['serie'], 'taille' => $import->taille, 'sha256' => $empreinte]);

        return response()->json([
            'status' => true, 'reference' => $import->reference, 'nom' => $import->nom_fichier, 'taille' => $import->taille,
            'deja_importe' => ImportReprise::where('empreinte', $empreinte)->where('statut', 'importe')->exists(),
        ]);
    }

    /** Lit l'onglet « Registre » : les lignes sont mises de côté pour le contrôle. */
    public function analyser(string $reference): JsonResponse
    {
        Gate::authorize('reprise.creer');
        $import = $this->charger($reference, ['televerse', 'colonnes', 'controle', 'pret']);

        return $this->verrouille($import, function () use ($import) {
            $chemin = $import->cheminComplet();
            abort_unless($chemin && is_file($chemin), 410, 'Le fichier a été supprimé : recommencez le téléversement.');
            $import->lignes()->delete();

            $titres = null;
            $total = 0;
            $buffer = [];
            $largeur = count(ModeleReprise::colonnes($import->serie));
            try {
                DB::transaction(function () use ($chemin, $import, &$titres, &$total, &$buffer, $largeur) {
                    FichierTableur::parcourir($chemin, ModeleReprise::FEUILLE, function (int $n, array $cellules) use (&$titres, &$total, &$buffer, $import, $largeur) {
                        if ($titres === null) {
                            $titres = $cellules;   // première ligne : les titres, déjà vérifiés au téléversement

                            return;
                        }
                        if (count(array_filter($cellules, fn ($c) => $c !== '')) === 0) {
                            return;   // ligne vide
                        }
                        $total++;
                        if ($total > FichierTableur::MAX_LIGNES) {
                            throw new RuntimeException('Le fichier dépasse '.number_format(FichierTableur::MAX_LIGNES, 0, ',', ' ').' lignes : découpez-le en plusieurs fichiers.');
                        }
                        $buffer[] = ['import_id' => $import->id, 'ligne' => $n, 'cellules' => json_encode(array_slice(array_pad($cellules, $largeur, ''), 0, $largeur), JSON_UNESCAPED_UNICODE), 'statut' => 'attente'];
                        if (count($buffer) >= 500) {
                            DB::table('imports_reprise_lignes')->insert($buffer);
                            $buffer = [];
                        }
                    });
                    if ($buffer) {
                        DB::table('imports_reprise_lignes')->insert($buffer);
                    }
                });
            } catch (RuntimeException $e) {
                return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
            }
            if ($total === 0) {
                return response()->json(['status' => false, 'message' => "L'onglet « Registre » ne contient aucune ligne remplie sous les titres."], 422);
            }

            $import->forceFill([
                'statut' => 'colonnes', 'ligne_titres' => 1, 'colonnes' => array_values(array_map(fn ($c) => ['titre' => $c['titre']], ModeleReprise::colonnes($import->serie))),
                'correspondance' => ModeleReprise::correspondance($import->serie), 'total' => $total, 'traitees' => 0, 'valides' => 0, 'erreurs' => 0, 'doublons' => 0, 'importees' => 0, 'message' => null,
            ])->save();
            Journal::audit('reprise_analysee', $import, $import->nom_fichier, [], ['lignes' => $total]);

            return response()->json(['status' => true, 'total' => $total]);
        });
    }

    /** Contrôle un paquet de lignes ; l'écran rappelle tant que « termine » est faux. */
    public function controler(string $reference): JsonResponse
    {
        Gate::authorize('reprise.creer');
        $import = $this->charger($reference, ['colonnes', 'controle']);

        return $this->verrouille($import, function () use ($import) {
            $ctx = $this->contexte($import);
            $paquet = $import->lignes()->where('statut', 'attente')->orderBy('ligne')->limit(self::PAQUET_CONTROLE)->get();
            foreach ($paquet as $ligne) {
                $r = LigneReprise::analyser($ligne->cellules, $import->correspondance, $ctx);
                $statut = $r['statut'];
                $messages = $r['messages'];
                if ($statut === 'valide' && $r['cle']) {
                    [$serie, $annee, $numero] = explode('|', $r['cle']);
                    if (LigneReprise::dejaEnregistree($serie, (int) $annee, (int) $numero)) {
                        $statut = 'doublon';
                        $messages[] = ['niveau' => 'doublon', 'texte' => "Ce numéro existe déjà dans l'application : la ligne est ignorée."];
                    } elseif ($autre = $import->lignes()->where('cle', $r['cle'])->where('ligne', '<', $ligne->ligne)->where('statut', 'valide')->first()) {
                        $statut = 'doublon';
                        $messages[] = ['niveau' => 'doublon', 'texte' => 'Même numéro que la ligne '.$autre->ligne.' du fichier : la ligne est ignorée.'];
                    }
                }
                $ligne->forceFill(['statut' => $statut, 'messages' => $messages ?: null, 'resultat' => $r['resultat'], 'cle' => $r['cle']])->save();
            }

            $restantes = $import->lignes()->where('statut', 'attente')->count();
            $compte = $import->lignes()->selectRaw('statut, count(*) n')->groupBy('statut')->pluck('n', 'statut');
            $import->forceFill([
                'statut' => $restantes === 0 ? 'pret' : 'controle', 'traitees' => $import->total - $restantes,
                'valides' => (int) ($compte['valide'] ?? 0), 'erreurs' => (int) ($compte['erreur'] ?? 0), 'doublons' => (int) ($compte['doublon'] ?? 0),
            ])->save();
            if ($restantes === 0) {
                Journal::audit('reprise_controlee', $import, $import->nom_fichier, [], ['valides' => $import->valides, 'erreurs' => $import->erreurs, 'doublons' => $import->doublons]);
            }

            return response()->json(['status' => true] + $this->progression($import, $restantes === 0));
        });
    }

    /** Importe un paquet de lignes valides dans le registre. « Tout ou rien » : refusé tant qu'une ligne est en erreur. Droit spécial reprise.importer. */
    public function importer(string $reference): JsonResponse
    {
        Gate::authorize('reprise.importer');
        $import = $this->charger($reference, ['pret']);
        if ($import->erreurs > 0 && $import->importees === 0) {
            return response()->json(['status' => false, 'message' => $import->erreurs.' ligne(s) sont en erreur : rien ne peut être importé. Corrigez le fichier puis relancez l\'analyse.'], 422);
        }
        abort_if($import->valides < 1, 422, 'Aucune ligne valide à importer.');

        return $this->verrouille($import, function () use ($import) {
            $paquet = $import->lignes()->where('statut', 'valide')->orderBy('ligne')->limit(self::PAQUET_IMPORT)->get();
            foreach ($paquet as $ligne) {
                DB::transaction(function () use ($ligne, $import) {
                    $r = $ligne->resultat;
                    NumerotationRegistre::verrouiller($r['serie'], (int) $r['annee']);
                    if (LigneReprise::dejaEnregistree($r['serie'], (int) $r['annee'], (int) $r['numero'])) {
                        $ligne->forceFill(['statut' => 'doublon', 'messages' => array_merge($ligne->messages ?? [], [['niveau' => 'doublon', 'texte' => "Numéro pris entre-temps dans l'application : ligne ignorée."]])])->save();

                        return;
                    }
                    $affaire = Affaire::create([
                        'serie' => $r['serie'], 'annee' => $r['annee'], 'numero' => $r['numero'], 'numero_complet' => NumerotationRegistre::formater($r['serie'], (int) $r['numero'], (int) $r['annee']),
                        'numero_saisi' => true, 'ancien_numero' => $r['ancien_numero'], 'date_rg' => $r['date_rg'], 'objet' => $r['objet'], 'nature_affaire_id' => $r['nature_affaire_id'],
                        'chambre_id' => $r['chambre_id'], 'date_premiere_audience' => $r['date_premiere_audience'], 'statut' => 'enrolee',
                        'cree_par' => request()->user()?->id, 'reprise_import_id' => $import->id,
                    ]);
                    foreach ($r['parties'] as $i => $p) {
                        $affaire->parties()->create($p + ['ordre' => $i]);
                    }
                    $ligne->forceFill(['statut' => 'importee', 'affaire_id' => $affaire->id])->save();
                });
            }

            $restantes = $import->lignes()->where('statut', 'valide')->count();
            $compte = $import->lignes()->selectRaw('statut, count(*) n')->groupBy('statut')->pluck('n', 'statut');
            $import->forceFill(['importees' => (int) ($compte['importee'] ?? 0), 'doublons' => (int) ($compte['doublon'] ?? 0), 'traitees' => $import->valides - $restantes])->save();
            if ($restantes === 0) {
                $import->forceFill(['statut' => 'importe', 'importe_par' => request()->user()?->id, 'importe_le' => now(), 'message' => null])->save();
                $import->supprimerFichier();
                Journal::audit('reprise_importee', $import, $import->nom_fichier, [], ['affaires' => $import->importees, 'ignorees' => $import->doublons + $import->erreurs, 'registre' => $import->serie]);
            } else {
                $import->forceFill(['statut' => 'pret'])->save();
            }

            return response()->json(['status' => true] + $this->progression($import, $restantes === 0, $import->valides));
        });
    }

    /** Résumé du contrôle et lignes à afficher : toutes les erreurs (jusqu'à 500), puis un échantillon des autres. */
    public function etat(string $reference): JsonResponse
    {
        Gate::authorize('reprise.voir');
        $import = ImportReprise::where('reference', $reference)->firstOrFail();
        $prends = fn (string $statut, int $n) => $import->lignes()->where('statut', $statut)->orderBy('ligne')->limit($n)->get();
        $affiche = fn ($l) => [
            'ligne' => $l->ligne, 'etat' => $l->statut === 'valide' || $l->statut === 'importee' ? 'nouvelle' : $l->statut, 'numero' => $l->cellules[0] ?? '', 'date' => $l->cellules[1] ?? '', 'objet' => Str::limit($l->cellules[2] ?? '', 90),
            'parties' => Str::limit(implode(' ⟷ ', array_filter([$l->cellules[3] ?? '', $l->cellules[4] ?? ''])), 110), 'chambre' => $l->cellules[5] ?? '',
            'messages' => collect($l->messages ?? [])->map(fn ($m) => ['niveau' => $m['niveau'], 'texte' => $m['texte']])->values()->all(),
        ];
        $lignes = collect()->merge($prends('erreur', 500))->merge($prends('doublon', 150))->merge($prends('valide', 250))->sortBy('ligne')->values();

        return response()->json([
            'status' => true, 'import' => $this->resume($import), 'lignes' => $lignes->map($affiche)->values(),
            'tronque' => $import->erreurs > 500 || $import->doublons > 150 || $import->valides > 250,
            'importable' => $import->statut === 'pret' && $import->erreurs === 0 && $import->valides > 0,
        ]);
    }

    /** Rapport Excel des lignes en erreur ou ignorées, à corriger dans le fichier d'origine. */
    public function rapport(string $reference)
    {
        Gate::authorize('reprise.voir');
        $import = ImportReprise::where('reference', $reference)->firstOrFail();
        $chemin = tempnam(sys_get_temp_dir(), 'rep');
        $w = new Writer;
        $w->getOptions()->setColumnWidth(10, 1);
        $w->getOptions()->setColumnWidth(18, 2);
        $w->getOptions()->setColumnWidth(80, 3);
        $w->getOptions()->setColumnWidth(60, 4);
        $w->openToFile($chemin);
        $style = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor('00794C');
        $w->addRow(Row::fromValuesWithStyles(['Ligne', 'Statut', 'Problèmes', 'Début de la ligne'], array_fill(0, 4, $style)));
        foreach ($import->lignes()->whereIn('statut', ['erreur', 'doublon'])->orderBy('ligne')->cursor() as $l) {
            $w->addRow(new Row([
                new StringCell((string) $l->ligne, null), new StringCell($l->statut === 'erreur' ? 'Erreur' : 'Ignorée (doublon)', null),
                new StringCell(collect($l->messages ?? [])->pluck('texte')->implode(' ; '), null), new StringCell(Str::limit(implode(' | ', array_slice($l->cellules, 0, 6)), 200), null),
            ]));
        }
        $w->close();
        Journal::audit('reprise_rapport', $import, $import->nom_fichier);

        return response()->download($chemin, 'rapport-reprise-'.now()->format('Ymd-Hi').'.xlsx', ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true);
    }

    /** Abandonne un import en cours, ou annule un import terminé (les affaires créées sont retirées si rien n'a été fait dessus). */
    public function annuler(string $reference): JsonResponse
    {
        Gate::authorize('reprise.annuler');
        $import = ImportReprise::where('reference', $reference)->firstOrFail();
        abort_if(in_array($import->statut, ['annule'], true), 422, 'Cet import est déjà annulé.');

        if ($import->statut === 'importe') {
            $affaires = $import->affaires()->withCount(['pieces', 'attributions', 'numerosCorriges'])->get();
            $touchees = $affaires->filter(fn ($a) => $a->pieces_count > 0 || $a->attributions_count > 0 || $a->numeros_corriges_count > 0 || $a->jointe_a_id || $a->statut !== 'enrolee' || \App\Models\Decision::where('affaire_id', $a->id)->exists());
            if ($touchees->isNotEmpty()) {
                return response()->json(['status' => false, 'message' => $touchees->count().' affaire(s) reprise(s) ont déjà été travaillées (pièces, décisions, jonction…) : l\'import ne peut plus être annulé en bloc.'], 422);
            }
            // L'application interdit de supprimer une affaire : seul ce retrait en bloc d'une reprise non travaillée y déroge, sous droit « reprise.annuler », et il est tracé.
            DB::transaction(fn () => DB::table('affaires')->whereIn('id', $affaires->pluck('id'))->delete());
        }
        $import->forceFill(['statut' => 'annule', 'annule_le' => now(), 'message' => null])->save();
        $import->supprimerFichier();
        Journal::audit('reprise_annulee', $import, $import->nom_fichier, [], ['affaires_retirees' => $import->importees]);

        return response()->json(['status' => true, 'message' => $import->importees ? 'Import annulé : les affaires reprises ont été retirées du registre.' : 'Import abandonné.']);
    }

    // ------------------------------------------------------------------ outils

    private function charger(string $reference, array $statuts): ImportReprise
    {
        $import = ImportReprise::where('reference', $reference)->firstOrFail();
        abort_unless(in_array($import->statut, $statuts, true), 409, "Cette étape n'est plus possible : l'import est « ".(ImportReprise::STATUTS[$import->statut] ?? $import->statut).' ».');

        return $import;
    }

    /** Un seul paquet à la fois pour un même import : un second appel simultané est refusé. */
    private function verrouille(ImportReprise $import, callable $travail): JsonResponse
    {
        $pris = ImportReprise::whereKey($import->id)->where(fn ($q) => $q->whereNull('verrou_jusqu_a')->orWhere('verrou_jusqu_a', '<', now()))->update(['verrou_jusqu_a' => now()->addSeconds(60)]);
        if (! $pris) {
            return response()->json(['status' => false, 'message' => 'Un traitement est déjà en cours pour cet import. Patientez un instant.'], 409);
        }
        try {
            $import->refresh();

            return $travail();
        } catch (Throwable $e) {
            if ($e instanceof HttpExceptionInterface || $e instanceof ValidationException) {
                throw $e;
            }
            report($e);
            ImportReprise::whereKey($import->id)->update(['message' => 'Erreur technique pendant le traitement : '.Str::limit($e->getMessage(), 150)]);

            return response()->json(['status' => false, 'message' => 'Une erreur technique est survenue : le traitement est interrompu, vous pouvez le reprendre.'], 500);
        } finally {
            ImportReprise::whereKey($import->id)->update(['verrou_jusqu_a' => null]);
        }
    }

    /** Avancement du paquet qui vient d'être traité ; $surImport = nombre de lignes à importer quand on est dans l'importation. */
    private function progression(ImportReprise $import, bool $termine, ?int $surImport = null): array
    {
        $sur = $surImport ?? $import->total;

        return [
            'termine' => $termine, 'pourcentage' => $termine ? 100 : $import->pourcentage($import->traitees, $sur), 'traitees' => $import->traitees, 'total' => $sur,
            'valides' => $import->valides, 'erreurs' => $import->erreurs, 'doublons' => $import->doublons, 'importees' => $import->importees,
        ];
    }

    private function resume(ImportReprise $i): array
    {
        return ['reference' => $i->reference, 'statut' => $i->statut, 'serie' => $i->serie, 'nom' => $i->nom_fichier, 'total' => $i->total, 'valides' => $i->valides, 'erreurs' => $i->erreurs, 'doublons' => $i->doublons, 'importees' => $i->importees];
    }

    /** Chambres du registre, natures et réglages nécessaires au contrôle des lignes. */
    private function contexte(ImportReprise $import): array
    {
        $chambres = [];
        foreach (ModeleReprise::chambres($import->serie) as $c) {
            $chambres[LigneReprise::cleLibelle($c->libelle)] = $c->id;
        }
        $natures = [];
        foreach (NatureAffaire::where('actif', true)->where('registre', $import->serie)->get(['id', 'libelle']) as $n) {
            $natures[LigneReprise::cleLibelle($n->libelle)] = $n->id;
        }

        return ['chambres' => $chambres, 'natures' => $natures, 'serie' => $import->serie];
    }

    private function nomPropre(string $nom): string
    {
        $nom = preg_replace('/[^\p{L}\p{N} ._()\-]+/u', '', basename(str_replace('\\', '/', $nom))) ?: 'classeur.xlsx';

        return Str::limit(trim($nom), 150, '');
    }

    private function tailleMaxOctets(): int
    {
        $php = min(PieceDossier::enOctets((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX, PieceDossier::enOctets((string) ini_get('post_max_size')) ?: PHP_INT_MAX);

        return (int) max(1048576, min((int) config('tpi.reprise.taille_max_mo', 10) * 1048576, $php));
    }
}
