<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Sections du siège (civile, pénale, instruction, sociale…), fixées par l'ordonnance. Droits : sections.voir / creer / modifier / supprimer. */
class SectionController extends Controller
{
    public function index(): View
    {
        Gate::authorize('sections.voir');

        return view('organisation.sections.index', [
            'lignes' => Section::withCount('chambres')->orderBy('ordre')->orderBy('libelle')->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        Gate::authorize('sections.voir');
        $section = Section::findOrFail($id);

        return response()->json(['id' => $section->id, 'libelle' => $section->libelle, 'ordre' => $section->ordre]);
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $section = $request->filled('id_') ? Section::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($section ? 'sections.modifier' : 'sections.creer');

        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:120', Rule::unique('sections', 'libelle')->ignore($section?->id)],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'libelle.required' => 'Le nom de la section est obligatoire.', 'libelle.unique' => 'Une section porte déjà ce nom.',
            'ordre.integer' => "L'ordre doit être un nombre.", 'ordre.max' => "L'ordre ne peut pas dépasser 999.",
        ]);

        $avant = $section ? ['libelle' => $section->libelle, 'ordre' => $section->ordre] : [];
        $section ??= new Section(['code' => $this->codeUnique($donnees['libelle'])]);
        $section->forceFill(['libelle' => trim($donnees['libelle']), 'ordre' => (int) ($donnees['ordre'] ?? 0)])->save();

        Journal::audit($avant ? 'section_modifie' : 'section_cree', $section, $section->libelle, $avant, ['libelle' => $section->libelle, 'ordre' => $section->ordre]);

        return redirect()->route('organisation.sections')->with('success', $avant ? 'Section modifiée.' : 'Section créée.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('sections.supprimer');
        $section = Section::findOrFail($id);

        if ($section->chambres()->exists()) {
            return response()->json(['status' => false, 'message' => 'Cette section contient des chambres : supprimez-les ou déplacez-les d\'abord.']);
        }

        Journal::audit('section_supprime', $section, $section->libelle, ['libelle' => $section->libelle, 'ordre' => $section->ordre]);
        $section->delete();

        return response()->json(['status' => true, 'message' => 'Section supprimée.']);
    }

    /** Identifiant technique stable, dérivé du nom (« penale », « penale-2 »…). */
    private function codeUnique(string $libelle): string
    {
        $base = Str::limit(Str::slug($libelle), 24, '') ?: 'section';
        $code = $base;
        for ($i = 2; Section::where('code', $code)->exists(); $i++) {
            $code = $base.'-'.$i;
        }

        return $code;
    }
}
