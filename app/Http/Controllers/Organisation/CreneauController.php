<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Creneau;
use App\Models\Formation;
use App\Services\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/*
 | Jours, heures et salles d'audience d'une formation, modifiables en cours d'année (période de validité facultative).
 | Droits : creneaux.voir / creer / modifier / supprimer.
 */
class CreneauController extends Controller
{
    private const CHAMPS = ['formation_id', 'type', 'jour', 'heure', 'frequence', 'salle_id', 'precision', 'debut', 'fin', 'ordonnance_id'];

    public function show(int $id): JsonResponse
    {
        Gate::authorize('creneaux.voir');
        $c = Creneau::findOrFail($id);

        return response()->json([
            'id' => $c->id, 'formation_id' => $c->formation_id, 'type' => $c->type, 'jour' => $c->jour, 'heure' => $c->heure ? substr($c->heure, 0, 5) : null,
            'frequence' => $c->frequence, 'salle_id' => $c->salle_id, 'precision' => $c->precision,
            'debut' => $c->debut?->format('Y-m-d'), 'fin' => $c->fin?->format('Y-m-d'), 'ordonnance_id' => $c->ordonnance_id,
        ]);
    }

    /** Création (id_ vide) ou modification (id_ renseigné). */
    public function store(Request $request): RedirectResponse
    {
        $c = $request->filled('id_') ? Creneau::findOrFail((int) $request->input('id_')) : null;
        Gate::authorize($c ? 'creneaux.modifier' : 'creneaux.creer');

        $donnees = $request->validate([
            'formation_id' => ['required', 'integer', 'exists:formations,id'],
            'type' => ['required', 'string', 'max:160'],
            'frequence' => ['required', Rule::in(array_keys(Creneau::FREQUENCES))],
            'jour' => ['nullable', 'integer', 'between:1,7', 'required_unless:frequence,a_la_demande,ponctuelle'],
            'heure' => ['nullable', 'date_format:H:i'],
            'salle_id' => ['nullable', 'integer', 'exists:salles,id'],
            'precision' => ['nullable', 'string', 'max:200'],
            'debut' => ['nullable', 'date', 'required_if:frequence,ponctuelle'],
            'fin' => ['nullable', 'date', 'after_or_equal:debut'],
            'ordonnance_id' => ['nullable', 'integer', 'exists:ordonnances,id'],
        ], [
            'formation_id.required' => 'Formation introuvable.', 'type.required' => "Indiquez la nature de l'audience.", 'frequence.required' => 'Choisissez la fréquence.',
            'jour.required_unless' => 'Choisissez le jour.', 'debut.required_if' => 'Indiquez la date de cette audience supplémentaire.', 'jour.between' => 'Jour invalide.', 'heure.date_format' => "L'heure est invalide (exemple : 09:00).",
            'debut.date' => 'La date de début est invalide.', 'fin.date' => 'La date de fin est invalide.', 'fin.after_or_equal' => 'La date de fin ne peut pas précéder la date de début.',
        ]);

        if ($c && (int) $donnees['formation_id'] !== $c->formation_id) {
            abort(422, 'Un créneau ne peut pas changer de formation.');
        }

        $donnees['type'] = trim($donnees['type']);
        $donnees['jour'] = filled($donnees['jour'] ?? null) ? (int) $donnees['jour'] : null;
        $donnees['precision'] = filled($donnees['precision'] ?? null) ? trim($donnees['precision']) : null;
        foreach (['heure', 'salle_id', 'debut', 'fin', 'ordonnance_id'] as $champ) {
            $donnees[$champ] = filled($donnees[$champ] ?? null) ? $donnees[$champ] : null;
        }

        if ($donnees['frequence'] === 'ponctuelle') {   // une seule date : le jour s'en déduit
            $donnees['fin'] = $donnees['debut'];
            $donnees['jour'] = \Illuminate\Support\Carbon::parse($donnees['debut'])->isoWeekday();
        }

        $doublon = Creneau::where('formation_id', $donnees['formation_id'])->where('type', $donnees['type'])->where('frequence', $donnees['frequence'])
            ->where('jour', $donnees['jour'])->where('heure', $donnees['heure'])
            ->when($c, fn ($q) => $q->whereKeyNot($c->id))->exists();
        if ($doublon && ! $this->periodesDistinctes($donnees, $c)) {
            throw ValidationException::withMessages(['type' => 'Ce créneau existe déjà pour cette formation (même jour, même heure).']);
        }

        $avant = $c ? $this->valeurs($c) : [];
        $c ??= new Creneau;
        $c->forceFill($donnees)->save();

        $formation = Formation::with('chambre')->find($c->formation_id);
        Journal::audit($avant ? 'creneau_modifie' : 'creneau_cree', $c, $formation->chambre->libelle.' : '.$c->type, $avant, $this->valeurs($c->refresh()));

        return redirect()->back()->with('success', $avant ? 'Audience modifiée.' : 'Audience ajoutée.');
    }

    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('creneaux.supprimer');
        $c = Creneau::with('formation.chambre')->findOrFail($id);

        Journal::audit('creneau_supprime', $c, $c->formation->chambre->libelle.' : '.$c->type, $this->valeurs($c));
        $c->delete();

        return response()->json(['status' => true, 'message' => 'Audience supprimée.']);
    }

    /** Deux créneaux identiques sont admis s'ils ne se chevauchent pas dans le temps (ex. changement d'horaire en cours d'année). */
    private function periodesDistinctes(array $donnees, ?Creneau $courant): bool
    {
        $debut = $donnees['debut'] ?? '0000-01-01';
        $fin = $donnees['fin'] ?? '9999-12-31';

        return ! Creneau::where('formation_id', $donnees['formation_id'])->where('type', $donnees['type'])->where('frequence', $donnees['frequence'])
            ->where('jour', $donnees['jour'])->where('heure', $donnees['heure'])
            ->when($courant, fn ($q) => $q->whereKeyNot($courant->id))
            ->where(fn ($q) => $q->whereNull('fin')->orWhere('fin', '>=', $debut))
            ->where(fn ($q) => $q->whereNull('debut')->orWhere('debut', '<=', $fin))
            ->exists();
    }

    /** @return array<string, mixed> */
    private function valeurs(Creneau $c): array
    {
        $v = $c->only(self::CHAMPS);
        $v['heure'] = $c->heure ? substr($c->heure, 0, 5) : null;
        $v['debut'] = $c->debut?->format('Y-m-d');
        $v['fin'] = $c->fin?->format('Y-m-d');

        return $v;
    }
}
