@extends('layout.master')
@section('title', 'Séances hors audience')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
@endsection

@section('main-content')
  @php $tons = ['prevue' => 'info', 'tenue' => 'success', 'annulee' => 'neutral']; @endphp
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Audiences" title="Séances hors audience publique" subtitle="Réception des requêtes au cabinet du juge, conciliation, tutelle : on planifie la séance, on y inscrit les dossiers examinés et leur issue, puis on la déclare tenue." icon="fa-solid fa-door-open">
        <x-slot:extra>
          <span><i class="fa-solid fa-calendar-day"></i> {{ $prochaines }} séance(s) à venir</span>
          <span><i class="fa-solid fa-check"></i> {{ $tenues }} tenue(s)</span>
        </x-slot:extra>
        @can('seances.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="ouvrir_seance(null)"><i class="fa-solid fa-plus"></i> Planifier une séance</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-card">
        <div class="el-toolbar">
          <div class="el-pills">
            <button type="button" class="el-pill is-active" data-el-filter="4" data-value="">Toutes</button>
            @foreach (\App\Models\SeanceHorsAudience::STATUTS as $code => $lib)<button type="button" class="el-pill" data-el-filter="4" data-value="{{ $code }}">{{ $lib }}</button>@endforeach
          </div>
          <label class="el-search" style="max-width:320px"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="el-search" placeholder="Type, chambre, juge, dossier…" autocomplete="off" aria-label="Rechercher"></label>
        </div>
        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-seances" data-serveur="{{ route('audiences.seances.donnees') }}">
            <colgroup><col style="width:130px"><col style="width:190px"><col style="width:190px"><col><col style="width:120px"><col style="width:170px"></colgroup>
            <thead><tr><th>Date</th><th>Séance</th><th>Magistrat et greffier</th><th>Dossiers examinés</th><th>État</th><th>Actions</th></tr></thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade panel-modal" id="modal-seance" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false">
    <div class="modal-dialog aff-modal" style="width:60%"><div class="modal-content">
      <div class="modal-header"><h4 class="modal-title" id="t-seance">Planifier une séance</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
      <form method="post" action="{{ route('audiences.seances.store') }}" id="f-seance" autocomplete="off">
        @csrf <input type="hidden" name="id_" id="s_id">
        <div class="modal-body dark-modal"><div class="el-formgrid">
          <div class="el-c6"><label class="form-label"><b>Type de séance</b></label><select class="form-select" name="type" id="s_type" data-sans-recherche>@foreach (\App\Models\SeanceHorsAudience::TYPES as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach</select></div>
          <div class="el-c6"><label class="form-label"><b>Chambre</b></label><select class="form-select" name="chambre_id" id="s_chambre"><option value="">Toutes chambres</option>@foreach ($chambres as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach</select></div>
          <div class="el-c4"><label class="form-label"><b>Date</b></label><input class="form-control" type="date" name="date_seance" id="s_date" required></div>
          <div class="el-c3"><label class="form-label"><b>Heure</b></label><input class="form-control" type="time" name="heure" id="s_heure"></div>
          <div class="el-c5"><label class="form-label"><b>Lieu</b></label><input class="form-control" type="text" name="lieu" id="s_lieu" maxlength="120" placeholder="Ex. : cabinet du juge"></div>
          <div class="el-c6"><label class="form-label"><b>Magistrat</b></label><select class="form-select" name="juge_id" id="s_juge"><option value="">À désigner</option>@foreach ($magistrats as $a)<option value="{{ $a->id }}">{{ $a->nomComplet() }}</option>@endforeach</select></div>
          <div class="el-c6"><label class="form-label"><b>Greffier</b></label><select class="form-select" name="greffier_id" id="s_greffier"><option value="">À désigner</option>@foreach ($greffiers as $a)<option value="{{ $a->id }}">{{ $a->nomComplet() }}</option>@endforeach</select></div>
          <div class="el-c12"><label class="form-label"><b>Observations</b></label><input class="form-control" type="text" name="observations" id="s_obs" maxlength="1000"></div>
        </div></div>
        <div class="modal-footer"><button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button><button class="btn btn-primary text-white" type="submit">Enregistrer</button></div>
      </form>
    </div></div>
  </div>

  <div class="modal fade panel-modal" id="modal-dossier" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false">
    <div class="modal-dialog aff-modal"><div class="modal-content">
      <div class="modal-header"><h4 class="modal-title">Ajouter un dossier à la séance</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
      <form method="post" id="f-dossier" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal"><div class="el-formgrid">
          <div class="el-c12"><label class="form-label"><b>Affaire (facultatif)</b></label><select class="form-select" name="affaire_id"><option value="">Aucune affaire enrôlée</option>@foreach ($affaires as $a)<option value="{{ $a->id }}">{{ $a->numero_complet }} : {{ \Illuminate\Support\Str::limit($a->objet, 60) }}</option>@endforeach</select></div>
          <div class="el-c12"><label class="form-label"><b>Objet</b></label><input class="form-control" type="text" name="objet" maxlength="250" required></div>
          <div class="el-c12"><label class="form-label"><b>Issue</b></label><input class="form-control" type="text" name="issue" maxlength="250" placeholder="Ex. : ordonnance rendue, tentative de conciliation échouée"></div>
        </div></div>
        <div class="modal-footer"><button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button><button class="btn btn-primary text-white" type="submit">Ajouter</button></div>
      </form>
    </div></div>
  </div>

  <form id="f-action" method="post" hidden>@csrf <input type="hidden" name="_method" id="a_methode" value="POST"> <input type="hidden" name="statut" id="a_statut"></form>
  <script type="text/javascript">
    function ouvrir_seance(s) {
      $('#f-seance')[0].reset(); $('#s_id').val(s ? s.id : ''); $('#t-seance').text(s ? 'Corriger la séance' : 'Planifier une séance');
      $('#s_type').val(s ? s.type : 'reception_requetes').trigger('change.select2'); $('#s_chambre').val(s && s.chambre_id ? s.chambre_id : '').trigger('change.select2');
      $('#s_date').val(s ? s.date : ''); $('#s_heure').val(s ? (s.heure || '') : ''); $('#s_lieu').val(s ? (s.lieu || '') : '');
      $('#s_juge').val(s && s.juge_id ? s.juge_id : '').trigger('change.select2'); $('#s_greffier').val(s && s.greffier_id ? s.greffier_id : '').trigger('change.select2'); $('#s_obs').val(s ? (s.observations || '') : '');
      $('#modal-seance').modal('show');
    }
    function ouvrir_dossier(id) { $('#f-dossier').attr('action', "{{ route('audiences.seances.dossier', ':id') }}".replace(':id', id))[0].reset(); $('#modal-dossier').modal('show'); }
    function retirer_dossier(id) {
      Swal.fire({ icon: 'question', title: 'Retirer ce dossier de la séance ?', showCancelButton: true, confirmButtonText: 'Oui, retirer', cancelButtonText: 'Retour' }).then(function (r) {
        if (r.isConfirmed) { $('#a_methode').val('DELETE'); $('#f-action').attr('action', "{{ route('audiences.seances.dossier.retirer', ':id') }}".replace(':id', id)).trigger('submit'); }
      });
    }
    function changer_statut(id, statut) {
      Swal.fire({ icon: 'question', title: statut === 'tenue' ? 'Déclarer la séance tenue ?' : 'Annuler cette séance ?', showCancelButton: true, confirmButtonText: 'Oui', cancelButtonText: 'Retour' }).then(function (r) {
        if (r.isConfirmed) { $('#a_methode').val('POST'); $('#a_statut').val(statut); $('#f-action').attr('action', "{{ route('audiences.seances.statut', ':id') }}".replace(':id', id)).trigger('submit'); }
      });
    }
  </script>
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>ElTable.init('#tbl-seances', { order: [[0, 'desc']], noSort: [1, 2, 3, 5], search: '#el-search' });</script>
@endsection
