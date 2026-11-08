@extends('layout.master')
@section('title', "Dates limites d'enrôlement")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Organisation" title="Dates limites d'enrôlement" subtitle="Article 7 de l'ordonnance : pour les chambres civiles, commerciales et administratives, l'enrôlement d'une affaire se clôt à un jour et une heure de la semaine précédant l'audience, sauf dérogation du Président. Les référés et les voies d'exécution sont exclus." icon="fa-solid fa-hourglass-half">
        <x-slot:extra>
          <span><i class="fa-solid fa-list"></i> {{ $lignes->count() }} règle(s)</span>
        </x-slot:extra>
        @can('delais.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Règles d'enrôlement</span>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-enrolement">
            <colgroup><col style="width:25%"><col><col style="width:16%"><col style="width:112px"></colgroup>
            <thead>
              <tr><th>Audience le</th><th>Enrôlement clos le</th><th>À</th>@canany(['delais.modifier', 'delais.supprimer'])<th>Actions</th>@endcanany</tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                <tr id="row-{{ $l->id }}">
                  <td data-order="{{ $l->jour_audience }}"><div class="el-person__name">{{ \App\Models\Creneau::JOURS[$l->jour_audience] }}</div></td>
                  <td>
                    <div class="el-person__name">{{ \App\Models\Creneau::JOURS[$l->jour_limite] }} précédent</div>
                    @if ($l->remarque)<div class="el-person__sub" title="{{ $l->remarque }}">{{ $l->remarque }}</div>@endif
                  </td>
                  <td class="el-num">{{ substr($l->heure, 0, 5) }}</td>
                  @canany(['delais.modifier', 'delais.supprimer'])
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('delais.modifier')
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_enrolement({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('delais.supprimer')
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_enrolement({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
                        @endcan
                      </div>
                    </td>
                  @endcanany
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      <div class="el-card">
        <div class="el-card__body" style="padding-top:18px">
          <b>Essayer une date d'audience</b>
          <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:10px">
            <input class="form-control" id="essai-date" type="date" style="max-width:200px">
            <button class="el-btn el-btn--ghost" type="button" onclick="calculer_enrolement()"><i class="fa-solid fa-calculator"></i> Calculer</button>
            <span id="essai-resultat" class="el-muted"></span>
          </div>
        </div>
      </div>

    </div>
  </div>

  @include('organisation.enrolement.form_enrolement')
  @include('organisation.enrolement.js_script')
@endsection
