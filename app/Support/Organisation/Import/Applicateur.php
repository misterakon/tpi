<?php

namespace App\Support\Organisation\Import;

use App\Models\ImportOrdonnance;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Support\Facades\DB;
use Throwable;

/*
 | Applique les changements choisis, en UNE opération (tout ou rien), et sait les défaire.
 |   - les changements sont recalculés juste avant l'écriture (Comparateur) : on n'applique jamais une comparaison périmée ;
 |   - seuls ceux dont la signature est cochée sont écrits ; un changement qui dépend d'un élément non créé est écarté et signalé ;
 |   - pour chaque ligne touchée, l'état d'avant est conservé : créer => on retrouvera l'id, modifier/retirer => les valeurs d'avant ;
 |   - l'annulation défait dans l'ordre inverse. Elle n'est permise que pour le dernier import appliqué (sinon on défait par-dessus du travail plus récent).
 */
class Applicateur
{
    /** @return array{appliques: int, ecartes: list<string>, resume: array<string, array<string, int>>} */
    public static function appliquer(ImportOrdonnance $import, array $signatures, ?User $par): array
    {
        $changements = Comparateur::calculer($import->donnees ?? [], $import->mode);
        $choisis = array_values(array_filter($changements, fn ($c) => in_array($c['sig'], $signatures, true)));

        $ids = [];            // clé du fichier => id créé
        $journal = [];        // ce qui a été écrit, avec l'état d'avant
        $ecartes = [];
        $resume = [];

        DB::transaction(function () use ($choisis, &$ids, &$journal, &$ecartes, &$resume) {
            foreach ($choisis as $c) {
                $champs = [];
                $manque = null;
                foreach ($c['champs'] as $col => $v) {
                    if (is_array($v)) {
                        if (! isset($ids[$v['_ref']])) {
                            $manque = $v['_ref'];
                            break;
                        }
                        $v = $ids[$v['_ref']];
                    }
                    $champs[$col] = $v;
                }
                if ($manque !== null) {
                    $ecartes[] = $c['libelle'].' : écarté, car il dépend d\'un élément que vous n\'avez pas coché (ou qui n\'a pas pu être créé).';

                    continue;
                }

                $maintenant = now();
                if ($c['action'] === 'creer') {
                    $id = DB::table($c['table'])->insertGetId($champs + ['created_at' => $maintenant, 'updated_at' => $maintenant]);
                    if ($c['cle']) {
                        $ids[$c['cle']] = $id;
                    }
                    $journal[] = ['table' => $c['table'], 'id' => $id, 'action' => 'creer', 'bloc' => $c['bloc'], 'libelle' => $c['libelle']];
                } elseif ($c['action'] === 'retirer' && $c['mode'] === 'supprime') {
                    $ligne = (array) DB::table($c['table'])->where('id', $c['id'])->first();
                    DB::table($c['table'])->where('id', $c['id'])->delete();
                    $journal[] = ['table' => $c['table'], 'id' => $c['id'], 'action' => 'supprimer', 'avant' => $ligne, 'bloc' => $c['bloc'], 'libelle' => $c['libelle']];
                } else {
                    $avant = (array) DB::table($c['table'])->where('id', $c['id'])->first(array_keys($champs));
                    DB::table($c['table'])->where('id', $c['id'])->update($champs + ['updated_at' => $maintenant]);
                    $journal[] = ['table' => $c['table'], 'id' => $c['id'], 'action' => $c['action'], 'avant' => $avant, 'bloc' => $c['bloc'], 'libelle' => $c['libelle']];
                }
                $resume[$c['bloc']][$c['action']] = ($resume[$c['bloc']][$c['action']] ?? 0) + 1;
            }
        });

        $import->update([
            'statut' => 'applique', 'applique_le' => now(), 'applique_par' => $par?->id,
            'changements' => ['resume' => $resume, 'ecartes' => $ecartes, 'lignes' => $journal, 'choisis' => count($choisis), 'proposes' => count($changements)],
        ]);
        Journal::audit('import_ordonnance_applique', $import, 'Fichier '.$import->nom_fichier, [], ['mode' => $import->mode, 'changements' => count($journal), 'resume' => $resume]);

        return ['appliques' => count($journal), 'ecartes' => $ecartes, 'resume' => $resume];
    }

    /** Peut-on annuler cet import ? (appliqué, et le plus récent des appliqués non annulés) */
    public static function annulable(ImportOrdonnance $import): bool
    {
        return $import->statut === 'applique'
            && ! ImportOrdonnance::where('statut', 'applique')->where('id', '>', $import->id)->exists();
    }

    /** @return array{defaits: int, echecs: list<string>} */
    public static function annuler(ImportOrdonnance $import, ?User $par): array
    {
        $lignes = array_reverse($import->changements['lignes'] ?? []);
        $echecs = [];
        $defaits = 0;

        foreach ($lignes as $l) {
            try {
                DB::transaction(function () use ($l) {
                    match ($l['action']) {
                        'creer' => DB::table($l['table'])->where('id', $l['id'])->delete(),
                        'supprimer' => DB::table($l['table'])->insert($l['avant']),
                        default => DB::table($l['table'])->where('id', $l['id'])->update($l['avant'] + ['updated_at' => now()]),
                    };
                });
                $defaits++;
            } catch (Throwable $e) {
                $echecs[] = ($l['libelle'] ?? $l['table']).' : '.($l['action'] === 'creer' ? 'cet élément est déjà utilisé ailleurs, il est conservé.' : 'retour impossible.');
            }
        }

        $import->update(['statut' => 'annule', 'annule_le' => now(), 'annule_par' => $par?->id]);
        Journal::audit('import_ordonnance_annule', $import, 'Fichier '.$import->nom_fichier, [], ['defaits' => $defaits, 'echecs' => count($echecs)]);

        return ['defaits' => $defaits, 'echecs' => $echecs];
    }
}
