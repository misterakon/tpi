@extends('layout.master')
@section('title', 'Utilisateurs')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  @php
    $parProfil = $comptes->groupBy('profil_id')->map->count();
    $actifs = $comptes->where('actif', true)->count();
  @endphp

  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Utilisateurs" title="Liste des utilisateurs" subtitle="Gérez les personnes qui ont accès à l'application. Chaque connexion demande un code envoyé par e-mail." icon="fa-solid fa-user-shield">
        <x-slot:extra>
          <span><i class="fa-solid fa-users"></i> {{ $comptes->count() }} utilisateur(s)</span>
          <span><i class="fa-solid fa-circle-check"></i> {{ $actifs }} actif(s)</span>
        </x-slot:extra>
        @can('create', \App\Models\User::class)
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_form()"><i class="fa-solid fa-plus"></i> Ajouter un utilisateur</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <div class="el-grid">

        <div class="el-col" style="flex:1 1 100%">
          <div class="el-card">
            <div class="el-card__head">
              <div><h2 class="el-card__title">Profils et droits</h2><div class="el-card__sub">Ce que chaque profil peut faire.</div></div>
              @can('viewAny', \App\Models\Profil::class)
                <a class="el-btn el-btn--ghost el-btn--sm" href="{{ route('profils.index') }}"><i class="fa-solid fa-sliders"></i> Gérer</a>
              @endcan
            </div>
            <div class="el-card__body">
              <div class="el-rights el-rights--grille">
                @foreach ($profils as $profil)
                  <div>
                    <x-profil :profil="$profil" />
                    <p>{{ $profil->description ?: 'Aucune description.' }}</p>
                  </div>
                @endforeach
              </div>
            </div>
          </div>

          <div class="el-note el-note--compact">
            <span class="el-note__icon"><i class="fa-solid fa-shield-halved"></i></span>
            <div>
              <div class="el-note__title">Connexion en deux étapes</div>
              <div class="el-note__text">Un code à 6 chiffres est envoyé par e-mail à chaque connexion. Un compte « protégé » ne peut être ni modifié ni supprimé par un autre utilisateur.</div>
            </div>
          </div>
        </div>

        <div class="el-card" style="flex:1 1 100%; min-width:0">

          <div class="el-toolbar">
            <div class="el-pills">
              <button class="el-pill is-active" type="button" data-el-filter="1" data-value="">Tous <b>{{ $comptes->count() }}</b></button>
              @foreach ($profils as $profil)
                @if (($parProfil[$profil->id] ?? 0) > 0)
                  <button class="el-pill" type="button" data-el-filter="1" data-value="p{{ $profil->id }}">{{ $profil->nom }} <b>{{ $parProfil[$profil->id] }}</b></button>
                @endif
              @endforeach
            </div>
            <label class="el-search" style="max-width:300px">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="search" id="el-search" placeholder="Nom ou e-mail" autocomplete="off" aria-label="Rechercher un utilisateur">
            </label>
          </div>

          <div class="el-tblwrap">
            <table class="el-tbl el-tbl--fixe" id="tbl-comptes">
              <colgroup><col><col style="width:290px"><col style="width:100px"><col style="width:130px"><col style="width:194px"></colgroup>
              <thead>
                <tr><th>Utilisateur</th><th>Profil</th><th>Statut</th><th>Dernière connexion</th><th>Actions</th></tr>
              </thead>
              <tbody>
                @foreach ($comptes as $compte)
                  <tr id="row-{{ $compte->id }}">
                    <td>
                      <div class="el-person">
                        <x-avatar :name="$compte->name" />
                        <div style="min-width:0">
                          <div class="el-person__name">{{ $compte->name }}</div>
                          <div class="el-person__sub">{{ $compte->email }}</div>
                          <div class="el-person__sub" title="{{ $compte->agent ? 'Relié à '.$compte->agent->nomComplet() : 'Aucun agent relié : à faire dans « Magistrats et greffiers »' }}"><i class="fa-solid {{ $compte->agent ? 'fa-link' : 'fa-link-slash' }}"></i> {{ $compte->agent ? $compte->agent->nomComplet().' · '.$compte->agent->fonctionLibelle() : 'Aucun agent relié' }}</div>
                        </div>
                      </div>
                    </td>
                    <td data-search="p{{ $compte->profil_id }}">
                      <x-profil :profil="$compte->profil" />
                      @if ($compte->protege)
                        <span class="el-lock" title="Compte protégé : il ne peut être ni modifié ni supprimé par un autre utilisateur"><i class="fa-solid fa-lock"></i> Protégé</span>
                      @endif
                    </td>
                    <td data-order="{{ (int) $compte->actif }}"><x-chip :tone="$compte->actif ? 'success' : 'danger'" :label="$compte->actif ? 'Actif' : 'Inactif'" /></td>
                    <td class="el-num" data-order="{{ $compte->last_login_at?->timestamp ?? 0 }}">{{ $compte->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>
                      <div style="display:flex; gap:8px">
                        @can('update', $compte)
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier {{ $compte->name }}" onclick="edit_compte({{ $compte->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @endcan
                        @can('resetPassword', $compte)
                          <button class="el-iconbtn el-iconbtn--teal" type="button" title="Envoyer un lien pour choisir le mot de passe" aria-label="Envoyer un lien de mot de passe à {{ $compte->name }}" onclick="lien_mot_de_passe({{ $compte->id }}, @js($compte->email))"><i class="fa-regular fa-envelope"></i></button>
                        @endcan
                        @can('resetPassword', $compte)
                          <button class="el-iconbtn el-iconbtn--blue" type="button" title="Annuler les codes de secours" aria-label="Annuler les codes de secours de {{ $compte->name }}" onclick="revoquer_codes({{ $compte->id }}, @js($compte->name))"><i class="fa-solid fa-key"></i></button>
                        @endcan
                        @can('delete', $compte)
                          <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer {{ $compte->name }}" onclick="deletes_compte({{ $compte->id }})"><i class="fa-regular fa-trash-can"></i></button>
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

  @include('utilisateur.form_compte')
  @include('utilisateur.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/elouange-table.js') }}"></script>
  <script>
    ElTable.init('#tbl-comptes', { order: [[0, 'asc']], noSort: [4], search: '#el-search' });
  </script>
@endsection
