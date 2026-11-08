@extends('layout.master')
@section('title', 'Délais')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page au-page al-page">

      <x-hero tag="Alertes" title="Délais" subtitle="Le temps accordé à chaque étape du circuit d'un dossier et à la procédure dans son ensemble. Ces délais fixent les échéances ; les règles d'alerte prévoient ensuite qui prévenir avant et après chaque échéance." icon="fa-solid fa-stopwatch">
        <x-slot:actions>
          @can('regles_alerte.voir')<a class="el-btn el-btn--ghost" href="{{ route('alertes.regles') }}"><i class="fa-solid fa-sliders"></i> Règles d'alerte</a>@endcan
          @can('delais_procedure.creer')<button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter un délai de procédure</button>@endcan
        </x-slot:actions>
      </x-hero>

      {{-- 1. Délais par étape : ils fixent les échéances des alertes. --}}
      @if ($peutVoirEtapes)
      <div class="au-cadre">
        <div class="al-controle__tete"><div><b>Délais par étape</b><small>Le temps accordé à chaque action du circuit : mise au rôle, publication du rôle, saisie des suites, rédaction, validation et signature d'une décision.</small></div></div>
        <div class="au-fond">
          <div class="au-tbl-carte">
            <table class="au-tbl al-tbl">
              <colgroup><col style="width:30%"><col style="width:190px"><col style="width:20%"><col><col style="width:80px"></colgroup>
              <thead><tr><th>Étape</th><th>Délai</th><th>Qui doit agir</th><th>Comment il est compté</th><th>Action</th></tr></thead>
              <tbody>
                @foreach ($delais as $d)
                  <tr id="delai-{{ $d->id }}" data-delai="{{ json_encode(['id' => $d->id, 'libelle' => $d->libelle, 'valeur' => $d->valeur, 'unite' => $d->unite]) }}">
                    <td class="au-tbl__num">{{ $d->libelle }}</td>
                    <td><span class="au-badge au-badge--date js-valeur"><i class="fa-regular fa-clock"></i>{{ $d->libelleDelai() }}</span></td>
                    <td>{{ $d->acteur }}</td>
                    <td>{{ $d->aide }}</td>
                    <td class="al-tbl__actions">@if ($peutModifier)<button type="button" class="au-iconbtn js-modifier" data-id="{{ $d->id }}" title="Modifier le délai" aria-label="Modifier"><i class="fa-solid fa-pen"></i></button>@endif</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
        <p class="al-note"><i class="fa-solid fa-circle-info"></i> Les <b>jours ouvrables</b> excluent les samedis, les dimanches et les jours de fermeture du tribunal (jours fériés et vacances judiciaires déclarés dans Organisation).</p>
      </div>
      @endif

      {{-- 2. Délais de procédure : le délai global (civil, correctionnel, référé-expulsion) et celui de chaque étape, avec la personne qui doit agir. --}}
      @if ($peutVoirProcedure)
        <div class="au-cadre" id="delais-procedure">
          <div class="al-controle__tete"><div><b>Délais de procédure</b><small>Le délai global de la procédure (civil 6 mois, correctionnel 3 mois, référé-expulsion 15 jours avec un seul renvoi) se règle sur l'étape « Procédure complète ». Une règle sur une nature d'affaire précise l'emporte sur celle d'un registre.</small></div></div>
          @include('referentiels.delais_procedure.tableau')
        </div>
      @endif

    </div>
  </div>

  @if ($peutVoirEtapes && $peutModifier)
    <div class="modal fade panel-modal" id="modal-delai" tabindex="-1" role="dialog" aria-labelledby="titre-modal-delai" aria-hidden="true" data-keyboard="false">
      <div class="modal-dialog" style="max-width:480px">
        <div class="modal-content">
          <div class="modal-header"><h4 class="modal-title" id="titre-modal-delai">Modifier le délai</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
          <form id="form-delai" autocomplete="off">
            <div class="modal-body dark-modal">
              <div class="el-formgrid">
                <div class="el-c5"><label class="form-label" for="f_delai_valeur"><b>Délai</b></label><input class="form-control" id="f_delai_valeur" name="valeur" type="number" min="0" max="365" step="1" required></div>
                <div class="el-c7"><label class="form-label" for="f_delai_unite"><b>Unité</b></label>
                  <select class="form-select" id="f_delai_unite" name="unite" data-sans-recherche>@foreach ($unites as $cle => $libelle)<option value="{{ $cle }}">{{ $libelle }}</option>@endforeach</select></div>
                <div class="el-c12" id="erreur-delai"></div>
              </div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary text-white" id="valider-delai" type="submit">Enregistrer</button></div>
          </form>
        </div>
      </div>
    </div>
  @endif

  @if ($peutVoirProcedure)
    @include('referentiels.delais_procedure.form_delais_procedure')
    @include('referentiels.delais_procedure.js_script')
  @endif
@endsection

@section('script')
  @if ($peutVoirProcedure)
    <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
    <script>ElTable.init('#tbl-delais_procedure', { order: [[0, 'asc']], noSort: [4], search: '#el-search' });</script>
  @endif
  <script type="text/javascript">
  (function () {
    'use strict';
    const URL_MAJ = @js(route('alertes.delais.update', 0));
    const CSRF = @js(csrf_token());
    let id = null;
    const esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    document.addEventListener('DOMContentLoaded', function () {
      $(document).on('click', '.js-modifier', function () {
        const d = JSON.parse($('#delai-' + this.getAttribute('data-id')).attr('data-delai')); id = d.id;
        $('#titre-modal-delai').text(d.libelle); $('#f_delai_valeur').val(d.valeur); $('#f_delai_unite').val(d.unite).trigger('change.select2'); $('#erreur-delai').empty(); $('#modal-delai').modal('show');
      });
      $('#form-delai').on('submit', function (e) {
        e.preventDefault(); $('#erreur-delai').empty(); $('#valider-delai').prop('disabled', true);
        fetch(URL_MAJ.replace(/\/0$/, '/' + id), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ valeur: $('#f_delai_valeur').val(), unite: $('#f_delai_unite').val() }) })
          .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { throw new Error((j.errors ? Object.values(j.errors)[0][0] : j.message) || 'Erreur'); } return j; }); })
          .then(function (j) {
            const $l = $('#delai-' + id), d = JSON.parse($l.attr('data-delai')); d.valeur = parseInt($('#f_delai_valeur').val(), 10); d.unite = $('#f_delai_unite').val(); $l.attr('data-delai', JSON.stringify(d));
            $l.find('.js-valeur').html('<i class="fa-regular fa-clock"></i>' + esc(j.libelle_delai)); $('#modal-delai').modal('hide');
            if (window.Swal) { Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: j.message, showConfirmButton: false, timer: 3000, timerProgressBar: true }); }
          })
          .catch(function (er) { $('#erreur-delai').html('<div class="au-erreur"><i class="fa-solid fa-circle-exclamation"></i> ' + esc(er.message) + '</div>'); }).then(function () { $('#valider-delai').prop('disabled', false); });
      });
    });
  })();
  </script>
@endsection
