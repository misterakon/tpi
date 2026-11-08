@extends('layout.master')
@section('title', 'Assistance judiciaire')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/statistiques.css') }}?v={{ filemtime(public_path('assets/css/statistiques.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Services annexes" title="Assistance judiciaire" subtitle="Les demandes examinées par le bureau local : demandeur, objet, audience, décision (accord ou rejet), recours et transmission à la DACP, comme dans le classeur du tribunal. Les chiffres se calculent tout seuls." icon="fa-solid fa-hand-holding-heart">
        <x-slot:extra>
          <span><i class="fa-solid fa-inbox"></i> {{ $compte['recues'] }} demande(s)</span>
          <span><i class="fa-solid fa-hourglass-half"></i> {{ $compte['a_traiter'] }} à traiter</span>
        </x-slot:extra>
        <x-slot:actions>
          @if ($peutCreer)<button class="el-btn el-btn--accent" type="button" onclick="ouvrir_aj(null)"><i class="fa-solid fa-plus"></i> Nouvelle demande</button>@endif
          @can('stats_registres.voir')<a class="el-btn st-btn-pdf" href="{{ route('statistiques.registres') }}"><i class="fa-solid fa-file-excel"></i> Registre Excel</a>@endcan
        </x-slot:actions>
      </x-hero>

      <div class="st-tuiles">
        <div class="st-tuile st-tuile--orange"><span class="st-tuile__icone"><i class="fa-solid fa-inbox"></i></span><div class="st-tuile__etiquette">Demandes reçues</div><div class="st-tuile__valeur">{{ $compte['recues'] }}</div><div class="st-tuile__sous">{{ $compte['a_traiter'] }} en attente de décision</div></div>
        <div class="st-tuile st-tuile--vert"><span class="st-tuile__icone"><i class="fa-solid fa-circle-check"></i></span><div class="st-tuile__etiquette">Accords</div><div class="st-tuile__valeur">{{ $compte['accords'] }}</div><div class="st-tuile__sous">{{ $compte['traitees'] ? round($compte['accords'] / $compte['traitees'] * 100) : 0 }} % des demandes traitées</div></div>
        <div class="st-tuile st-tuile--gris"><span class="st-tuile__icone"><i class="fa-solid fa-ban"></i></span><div class="st-tuile__etiquette">Rejets</div><div class="st-tuile__valeur">{{ $compte['rejets'] }}</div><div class="st-tuile__sous">{{ $compte['recours'] }} recours</div></div>
        <div class="st-tuile st-tuile--bleu"><span class="st-tuile__icone"><i class="fa-solid fa-clock"></i></span><div class="st-tuile__etiquette">Délai de décision</div><div class="st-tuile__valeur">{{ $compte['duree_moyenne'] !== null ? $compte['duree_moyenne'].' j' : '–' }}</div><div class="st-tuile__sous">en moyenne, de la réception à la décision</div></div>
      </div>

      <div class="el-card">
        <div class="el-toolbar">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-el-filter="5" data-value="">Toutes</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="a_traiter">À traiter</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="accord">Accords</button>
            <button type="button" class="el-pill" data-el-filter="5" data-value="rejet">Rejets</button>
          </div>
          <label class="el-search" style="max-width:320px"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="el-search" placeholder="N°, demandeur, objet…" autocomplete="off" aria-label="Rechercher"></label>
        </div>
        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-aj" data-serveur="{{ route('assistance.donnees') }}">
            <colgroup><col style="width:130px"><col><col style="width:200px"><col style="width:120px"><col style="width:150px"><col style="width:140px"><col style="width:100px"></colgroup>
            <thead><tr><th>N° et date du RG</th><th>Demandeur et objet</th><th>Nature</th><th>Audience</th><th>Décision</th><th>Recours</th><th>Actions</th></tr></thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade panel-modal" id="modal-aj" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false">
    <div class="modal-dialog aff-modal"><div class="modal-content">
      <div class="modal-header"><h4 class="modal-title" id="t-aj">Nouvelle demande</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
      <form method="post" action="{{ route('assistance.store') }}" id="f-aj" autocomplete="off">
        @csrf <input type="hidden" name="id_" id="aj_id">
        <div class="modal-body dark-modal"><div class="el-formgrid">
          <div class="el-c6"><label class="form-label"><b>N° et date du RG</b></label><input class="form-control" name="numero" id="aj_numero" maxlength="40" required placeholder="Ex. : 12/26 du 05/03/2026"></div>
          <div class="el-c6"><label class="form-label"><b>Date du RG</b></label><input class="form-control" type="date" name="date_rg" id="aj_date_rg" max="{{ date('Y-m-d') }}" required></div>
          <div class="el-c6"><label class="form-label"><b>Nom du demandeur</b></label><input class="form-control" name="demandeur" id="aj_demandeur" maxlength="200" required></div>
          <div class="el-c6"><label class="form-label"><b>Nature de l'affaire</b></label><select class="form-select" name="nature" id="aj_nature"><option value="">—</option>@foreach ($natures as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach</select></div>
          <div class="el-c12"><label class="form-label"><b>Objet de la demande</b></label><input class="form-control" name="objet" id="aj_objet" maxlength="300"></div>
          <div class="el-c4"><label class="form-label"><b>Date de la 1re audience</b></label><input class="form-control" type="date" name="date_audience" id="aj_audience"></div>
          <div class="el-c4"><label class="form-label"><b>Décision du bureau local</b></label><select class="form-select" name="decision" id="aj_decision" data-sans-recherche><option value="">Pas encore décidée</option>@foreach ($decisions as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></div>
          <div class="el-c4"><label class="form-label"><b>N° de la décision</b></label><input class="form-control" name="numero_decision" id="aj_numdec" maxlength="40"></div>
          <div class="el-c4"><label class="form-label"><b>Date de la décision</b></label><input class="form-control" type="date" name="date_decision" id="aj_date_decision" max="{{ date('Y-m-d') }}"></div>
          <div class="el-c4"><label class="form-label"><b>Date du recours</b></label><input class="form-control" type="date" name="date_recours" id="aj_recours" max="{{ date('Y-m-d') }}"></div>
          <div class="el-c4"><label class="form-label"><b>Transmission du recours à la DACP</b></label><input class="form-control" type="date" name="date_transmission_recours" id="aj_transm" max="{{ date('Y-m-d') }}"></div>
          <div class="el-c12"><label class="form-label"><b>Observation</b></label><input class="form-control" name="observation" id="aj_obs" maxlength="500"></div>
          @if ($errors->any())<div class="el-c12"><div class="aff-erreur">{{ $errors->first() }}</div></div>@endif
        </div></div>
        <div class="modal-footer"><button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button><button class="btn btn-primary text-white" type="submit">Enregistrer</button></div>
      </form>
    </div></div>
  </div>
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-aj', { order: [[0, 'desc']], noSort: [5, 6], search: '#el-search' });
    const U_AJ = { show: "{{ route('assistance.get', ':id') }}", del: "{{ route('assistance.delete', ':id') }}" };
    function ouvrir_aj(id) {
      $('#f-aj')[0].reset(); $('#aj_id').val(''); $('#aj_nature').val('').trigger('change.select2'); $('#aj_decision').val('').trigger('change.select2');
      const montrer = function (a) {
        $('#t-aj').text(a ? 'Modifier la demande' : 'Nouvelle demande');
        if (a) { $('#aj_id').val(a.id); $('#aj_numero').val(a.numero); $('#aj_date_rg').val(a.date_rg); $('#aj_demandeur').val(a.demandeur); $('#aj_objet').val(a.objet || ''); $('#aj_nature').val(a.nature || '').trigger('change.select2'); $('#aj_audience').val(a.date_audience || ''); $('#aj_decision').val(a.decision || '').trigger('change.select2'); $('#aj_numdec').val(a.numero_decision || ''); $('#aj_date_decision').val(a.date_decision || ''); $('#aj_recours').val(a.date_recours || ''); $('#aj_transm').val(a.date_transmission_recours || ''); $('#aj_obs').val(a.observation || ''); }
        $('#modal-aj').modal('show');
      };
      if (id === null) { montrer(null); return; }
      $.getJSON(U_AJ.show.replace(':id', id), montrer).fail(function () { Swal.fire({ icon: 'error', title: 'Erreur', text: 'Chargement impossible.' }); });
    }
    function supprimer_aj(id) {
      Swal.fire({ icon: 'warning', title: 'Supprimer cette demande ?', showCancelButton: true, confirmButtonText: 'Oui, supprimer', cancelButtonText: 'Retour' }).then(function (r) {
        if (!r.isConfirmed) { return; }
        $.ajax({ url: U_AJ.del.replace(':id', id), type: 'DELETE', data: { _token: '{{ csrf_token() }}' }, dataType: 'JSON', success: function (d) { Swal.fire({ icon: d.status ? 'success' : 'error', title: d.message }).then(function () { location.reload(); }); } });
      });
    }
    @if ($errors->any() && old('numero') !== null) document.addEventListener('DOMContentLoaded', function () { $('#modal-aj').modal('show'); }); @endif
  </script>
@endsection
