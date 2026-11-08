@extends('layout.master')
@section('title', 'Profils et droits')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  @php $totalPages = count(\App\Support\Droits::pages()); @endphp

  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Utilisateurs" title="Profils et droits" subtitle="Créez autant de profils que nécessaire et choisissez, pour chacun, ce qu'il a le droit de faire dans l'application." icon="fa-solid fa-user-lock">
        <x-slot:extra>
          <span><i class="fa-solid fa-id-badge"></i> {{ $profils->count() }} profil(s)</span>
          <span><i class="fa-solid fa-list-check"></i> {{ $totalPages }} sous-menus à régler</span>
        </x-slot:extra>
        @can('create', \App\Models\Profil::class)
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Nouveau profil</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-grid">

        <div class="el-col" style="flex:1 1 100%">
          <div class="el-card">
            <div class="el-card__head">
              <div><h2 class="el-card__title">Comment ça marche ?</h2><div class="el-card__sub">En trois idées.</div></div>
            </div>
            <div class="el-card__body">
              <div class="el-info el-info--ligne">
                <div class="el-info__row"><span class="el-info__icon"><i class="fa-solid fa-id-badge"></i></span><div><div class="el-info__label">Profil</div><div class="el-info__value">Un rôle dans le tribunal (greffier, juge…)</div></div></div>
                <div class="el-info__row"><span class="el-info__icon"><i class="fa-solid fa-key"></i></span><div><div class="el-info__label">Niveau d'accès</div><div class="el-info__value">Aucun, lecture, saisie ou gestion, par sous-menu</div></div></div>
                <div class="el-info__row"><span class="el-info__icon"><i class="fa-solid fa-user"></i></span><div><div class="el-info__label">Compte</div><div class="el-info__value">Une personne, rattachée à un profil</div></div></div>
              </div>
            </div>
          </div>

          @hasanyrole('Admin|Président')
            <div class="el-note el-note--compact">
              <span class="el-note__icon"><i class="fa-solid fa-shield-halved"></i></span>
              <div>
                <div class="el-note__title">Droits vérifiés côté serveur</div>
                <div class="el-note__text">Masquer un bouton ne suffit pas : chaque action est contrôlée à nouveau par le serveur. Les nouveaux modules ajouteront leurs droits à cette liste.</div>
              </div>
            </div>
          @endhasanyrole
        </div>

        <div class="el-card" style="flex:1 1 100%; min-width:0">
          <div class="el-toolbar">
            <div><h2 class="el-card__title">Liste des profils</h2><div class="el-card__sub">Un compte a toujours un profil. Un profil utilisé par des comptes ne peut pas être supprimé.</div></div>
            <label class="el-search" style="max-width:280px">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="search" id="el-search" placeholder="Rechercher un profil" autocomplete="off" aria-label="Rechercher un profil">
            </label>
          </div>

          <div class="el-tblwrap">
            <table class="el-tbl el-tbl--fixe" id="tbl-profils">
              <colgroup><col><col style="width:210px"><col style="width:100px"><col style="width:110px"></colgroup>
              <thead>
                <tr><th>Profil</th><th>Droits</th><th>Comptes</th><th>Actions</th></tr>
              </thead>
              <tbody>
                @foreach ($profils as $profil)
                  <tr id="row-{{ $profil->id }}">
                    <td>
                      <div class="el-person">
                        <div style="min-width:0">
                          <div class="el-person__name">{{ $profil->nom }}
                            @if ($profil->estSysteme())
                              <span class="el-lock" title="Profil système : il a tous les droits et ne peut être ni modifié ni supprimé"><i class="fa-solid fa-lock"></i> Système</span>
                            @endif
                          </div>
                          <div class="el-person__sub">{{ $profil->description ?: 'Aucune description' }}</div>
                        </div>
                      </div>
                    </td>
                    <td data-order="{{ $profil->nombrePages() }}">
                      @if ($profil->estSysteme())
                        <x-chip tone="alert" label="Tous les droits" />
                      @else
                        <span class="el-droits__nb">{{ $profil->nombrePages() }} / {{ $totalPages }} sous-menus</span>
                      @endif
                    </td>
                    <td class="el-num" data-order="{{ $profil->users_count }}">{{ $profil->users_count }}</td>
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('update', $profil)
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier le profil {{ $profil->nom }}" onclick="edit_profil({{ $profil->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('delete', $profil)
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer le profil {{ $profil->nom }}" onclick="deletes_profil({{ $profil->id }})"><i class="fa-regular fa-trash-can"></i></button>
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
  </div>

  @include('profils.form_profil')
  @include('profils.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-profils', { order: [[0, 'asc']], noSort: [3], search: '#el-search' });
  </script>
@endsection
