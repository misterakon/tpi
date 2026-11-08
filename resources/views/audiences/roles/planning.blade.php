@extends('layout.master')
@section('title', 'Planning des salles')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/roles.css') }}?v={{ filemtime(public_path('assets/css/roles.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Audiences" title="Planning des salles" subtitle="Les audiences de la semaine, salle par salle. Une même salle ne peut pas servir deux chambres à moins de trois heures d'écart : le conflit est signalé en rouge et le rôle ne peut pas être publié." icon="fa-solid fa-calendar-week">
        <x-slot:actions>
          <a class="el-btn el-btn--ghost" href="{{ route('audiences.roles') }}"><i class="fa-solid fa-list"></i> Les rôles</a>
        </x-slot:actions>
      </x-hero>

      <div class="el-card rl-planning" id="planning-zone" data-url="{{ route('audiences.roles.planning') }}">
        @include('audiences.roles.planning_semaine')
      </div>

    </div>
  </div>

  @can('roles.creer')
    @include('audiences.roles.form_role', ['chambres' => $chambres, 'salles' => $salles, 'peutPenal' => $peutPenal])
  @endcan
@endsection

@section('script')
  @can('roles.creer')
    @include('audiences.roles.js_script', ['creneaux' => $creneaux])
  @endcan
  @include('audiences.roles.js_script_planning')
@endsection
