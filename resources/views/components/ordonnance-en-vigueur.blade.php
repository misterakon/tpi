@props(['annee' => null])

{{-- Ligne « Ordonnance en vigueur » pour l'en-tête d'une page (à placer dans <x-slot:extra>) : rappelle l'acte dont découlent
     les chambres, compositions, audiences et services affichés. annee : identifiant de l'année affichée (à défaut, l'année en cours). --}}
@php
    $anneeId = $annee ?: \App\Models\AnneeJudiciaire::courante()?->id;
    $ordonnances = $anneeId
        ? \App\Models\Ordonnance::where('annee_judiciaire_id', $anneeId)->orderByDesc('date_signature')->get()
        : collect();
    $principale = $ordonnances->firstWhere('type', 'annuelle');
    $modificatives = $ordonnances->where('type', 'modificative');
@endphp

@if ($principale)
  <span title="Ordonnance de l'année, en vigueur depuis le {{ ($principale->date_effet ?? $principale->date_signature)->format('d/m/Y') }}">
    <i class="fa-solid fa-file-signature"></i>
    @can('ordonnances.voir')
      <a href="{{ route('organisation.ordonnances.consulter', $principale->id) }}" style="color:inherit; text-decoration:underline; text-underline-offset:3px">Ordonnance n° {{ $principale->numero }} du {{ $principale->date_signature->format('d/m/Y') }}</a>
    @else
      Ordonnance n° {{ $principale->numero }} du {{ $principale->date_signature->format('d/m/Y') }}
    @endcan
    @if ($modificatives->isNotEmpty())· {{ $modificatives->count() }} modificative(s)@endif
  </span>
@else
  <span><i class="fa-solid fa-triangle-exclamation"></i> Aucune ordonnance enregistrée pour cette année</span>
@endif
