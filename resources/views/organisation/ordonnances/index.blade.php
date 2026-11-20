@extends('layout.master')
@section('title', 'Archive des ordonnances')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/vendors/quill.snow.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/import-ordonnance.css') }}?v={{ filemtime(public_path('assets/css/import-ordonnance.css')) }}">
@endsection

@section('main-content')
  <div class="page-body io-page">
    <div class="el-page">

      <x-hero tag="Organisation" title="Archive des ordonnances" subtitle="Les ordonnances signées par la Présidente (ordonnance de l'année et ordonnances modificatives) sont conservées ici en PDF : on les lit directement dans l'application ou on les télécharge. Un fichier archivé ne peut pas être modifié." icon="fa-solid fa-file-signature">
        <x-slot:extra>
          <span><i class="fa-solid fa-box-archive"></i> {{ $lignes->count() }} ordonnance(s) archivée(s)</span>
        </x-slot:extra>
        <x-slot:actions>
          @can('ordonnances.importer')
            <a class="el-btn el-btn--accent" href="{{ route('organisation.ordonnances.import') }}"><i class="fa-solid fa-file-import"></i> Charger un fichier Excel</a>
          @endcan
          @can('ordonnances.creer')
            <button class="el-btn el-btn--ghost" type="button" onclick="add_form()"><i class="fa-solid fa-file-pdf"></i> Archiver une ordonnance (PDF)</button>
          @endcan
        </x-slot:actions>
      </x-hero>

      @include('organisation.ordonnances._onglets', ['actif' => 'archive'])

      <div class="io-blocs io-blocs--compact" style="margin-bottom:20px">
        <div class="io-bloc">
          <span class="io-bloc__icone"><i class="fa-solid fa-calendar-check"></i></span>
          <span class="io-bloc__valeur" style="font-size:22px">{{ $annee?->libelle ?? '—' }}</span>
          <span class="io-bloc__libelle">Année en vigueur</span>
          <span class="io-bloc__etat" style="color:#3F4D66">{{ $annee?->ordonnance ?? 'ordonnance non renseignée' }}</span>
        </div>
        <div class="io-bloc">
          <span class="io-bloc__icone"><i class="fa-solid fa-box-archive"></i></span>
          <span class="io-bloc__valeur">{{ $lignes->count() }}</span>
          <span class="io-bloc__libelle">Ordonnances archivées</span>
          <span class="io-bloc__etat" style="color:#3F4D66">{{ $lignes->where('type', 'modificative')->count() }} modificative(s)</span>
        </div>
        <div class="io-bloc">
          <span class="io-bloc__icone"><i class="fa-solid fa-scroll"></i></span>
          <span class="io-bloc__valeur">{{ $nbArticles }}</span>
          <span class="io-bloc__libelle">Articles enregistrés</span>
          <a class="io-bloc__etat" href="{{ route('organisation.ordonnances.articles') }}">Lire les articles <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        @can('ordonnances.importer')
          <div class="io-bloc {{ $dernierImport?->statut === 'brouillon' ? 'io-bloc--orange' : '' }}">
            <span class="io-bloc__icone"><i class="fa-solid fa-file-import"></i></span>
            <span class="io-bloc__valeur" style="font-size:22px">{{ $dernierImport ? ($dernierImport->applique_le ?? $dernierImport->created_at)?->format('d/m/Y') : '—' }}</span>
            <span class="io-bloc__libelle">Dernier chargement Excel</span>
            <span class="io-bloc__etat" style="color:#3F4D66">{{ $dernierImport ? (\App\Models\ImportOrdonnance::STATUTS[$dernierImport->statut] ?? $dernierImport->statut) : 'aucun fichier chargé' }}</span>
          </div>
        @endcan
      </div>

      <div class="el-card">
        <div class="el-toolbar">
          <span class="el-muted">Ordonnances, de la plus récente à la plus ancienne</span>
          <label class="el-search" style="max-width:300px">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="el-search" placeholder="Rechercher" autocomplete="off" aria-label="Rechercher">
          </label>
        </div>

        <div class="el-tblwrap">
          <table class="el-tbl el-tbl--fixe" id="tbl-ordonnances">
            <colgroup><col><col style="width:190px"><col style="width:110px"><col style="width:120px"><col style="width:96px"><col style="width:200px"></colgroup>
            <thead>
              <tr><th>Ordonnance</th><th>Type</th><th>Année</th><th>Signée le</th><th>Fichier</th><th>Actions</th></tr>
            </thead>
            <tbody>
              @foreach ($lignes as $l)
                <tr id="row-{{ $l->id }}">
                  <td>
                    <div class="el-person__name" title="{{ $l->libelle() }}">{{ $l->libelle() }}</div>
                    @if ($l->objet)<div class="el-person__sub" title="{{ $l->objet }}">{{ $l->objet }}</div>@endif
                  </td>
                  <td><x-chip :tone="$l->type === 'annuelle' ? 'success' : 'alert'" :label="\App\Models\Ordonnance::TYPES[$l->type] ?? $l->type" class="el-chip--libre" /></td>
                  <td>{{ $l->annee?->libelle }}</td>
                  <td data-order="{{ $l->date_signature->format('Y-m-d') }}">{{ $l->date_signature->format('d/m/Y') }}</td>
                  <td class="el-num" data-order="{{ $l->taille }}">{{ $l->tailleLisible() }}</td>
                  <td>
                    <div style="display:flex; gap:8px">
                      <a class="el-iconbtn el-iconbtn--blue" href="{{ route('organisation.ordonnances.consulter', $l->id) }}" title="Lire dans l'application" aria-label="Lire"><i class="fa-regular fa-eye"></i></a>
                      <a class="el-iconbtn el-iconbtn--blue" href="{{ route('organisation.ordonnances.telecharger', $l->id) }}" title="Télécharger le PDF" aria-label="Télécharger"><i class="fa-solid fa-download"></i></a>
                      @can('ordonnances.modifier')
                        <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier les informations" aria-label="Modifier" onclick="edit_ordonnance({{ $l->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                      @endcan
                      @can('ordonnances.supprimer')
                        <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_ordonnance({{ $l->id }})"><i class="fa-regular fa-trash-can"></i></button>
                      @endcan
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  @can('ordonnances.creer')
    @include('organisation.ordonnances.form_ordonnance')
  @endcan
  @include('organisation.ordonnances.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/editors/quill.js') }}"></script>
  <script src="{{ asset('assets/js/editeur-riche.js') }}"></script>
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-ordonnances', { order: [[3, 'desc']], noSort: [5], search: '#el-search' });
  </script>
@endsection
