@extends('layout.master')
@section('title', 'Composition de la chambre')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/vendors/quill.snow.css') }}">
@endsection

@section('main-content')
  @php
    $aujourdhui = now()->startOfDay();
    $etat = function ($a) use ($aujourdhui) {
        if ($a->fin && $a->fin->lt($aujourdhui)) { return ['Terminée', 'neutral']; }
        if ($a->debut && $a->debut->gt($aujourdhui)) { return ['À venir', 'info']; }
        return ['En cours', 'success'];
    };
    $total = $chambre->formations->sum(fn ($f) => $f->affectations->count());
  @endphp

  <div class="page-body">
    <div class="el-page">

      <x-hero :tag="$chambre->section->libelle" :title="$chambre->libelleComplet()" subtitle="Les attributions de la chambre, ses formations (facultatives) et, pour chacune, les personnes affectées avec leur rôle et leurs dates de validité. Pour un changement en cours d'année, utilisez « Remplacer » : l'ancienne affectation est terminée et l'historique est conservé." icon="fa-solid fa-users">
        <x-slot:extra>
          <span><i class="fa-solid fa-calendar-days"></i> {{ $chambre->annee->libelle }}</span>
          <span><i class="fa-solid fa-layer-group"></i> {{ $chambre->formations->count() }} formation(s)</span>
          <span><i class="fa-solid fa-user-check"></i> {{ $total }} affectation(s)</span>
        </x-slot:extra>
        @can('formations.creer')
          <x-slot:actions>
            <button class="el-btn el-btn--accent" type="button" onclick="add_formation()"><i class="fa-solid fa-plus"></i> Ajouter une formation</button>
          </x-slot:actions>
        @endcan
      </x-hero>

      <a class="el-retour" href="{{ route('organisation.chambres', ['annee' => $chambre->annee_judiciaire_id]) }}"><i class="fa-solid fa-arrow-left"></i> Retour aux chambres</a>

      @if ($chambre->groupe || $chambre->attributions || $chambre->remarque)
        <div class="el-note" style="margin:0">
          <span class="el-note__icon"><i class="fa-solid fa-gavel"></i></span>
          <div>
            <div class="el-note__title">Attributions de la chambre{{ $chambre->groupe ? ' — '.$chambre->groupe : '' }}</div>
            @if ($chambre->attributions)<div class="el-note__text el-riche">{!! \App\Support\TexteRiche::html($chambre->attributions) !!}</div>@endif
            @if ($chambre->remarque)<div class="el-note__text el-riche" style="margin-top:6px"><b>À noter :</b>{!! \App\Support\TexteRiche::html($chambre->remarque) !!}</div>@endif
          </div>
        </div>
      @endif

      @forelse ($chambre->formations as $formation)
        <div class="el-card" id="formation-{{ $formation->id }}">
          <div class="el-card__head">
            <div>
              <h2 class="el-card__title">{{ $formation->libelle() }}
                @unless ($formation->actif)<x-chip tone="neutral" label="Inactive" />@endunless
              </h2>
              <div class="el-card__sub">{{ $formation->affectations->count() }} affectation(s) · {{ $formation->creneaux->count() }} audience(s)</div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap">
              @can('affectations.creer')
                <button class="el-btn el-btn--primary el-btn--sm" type="button" onclick="add_form({{ $formation->id }})"><i class="fa-solid fa-user-plus"></i> Ajouter une personne</button>
              @endcan
              @can('formations.modifier')
                <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier la formation" aria-label="Modifier la formation" onclick="edit_formation({{ $formation->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
              @endcan
              @can('formations.supprimer')
                <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer la formation" aria-label="Supprimer la formation" onclick="delete_formation({{ $formation->id }})"><i class="fa-regular fa-trash-can"></i></button>
              @endcan
            </div>
          </div>

          @if ($formation->attributions)
            <div class="el-card__body" style="padding-bottom:0"><div class="el-riche" style="font-size:13px; color:var(--el-mute)">{!! \App\Support\TexteRiche::html($formation->attributions) !!}</div></div>
          @endif

          <div class="el-tblwrap">
            <table class="el-tbl el-tbl--fixe">
              <colgroup><col style="width:20%"><col><col style="width:11%"><col style="width:11%"><col style="width:11%"><col style="width:150px"></colgroup>
              <thead>
                <tr><th>Rôle</th><th>Personne</th><th>Du</th><th>Au</th><th>État</th>@canany(['affectations.modifier', 'affectations.supprimer'])<th>Actions</th>@endcanany</tr>
              </thead>
              <tbody>
                @forelse ($formation->affectations as $a)
                  @php [$libelleEtat, $tonEtat] = $etat($a); @endphp
                  <tr id="row-{{ $a->id }}">
                    <td>
                      <div class="el-person__name">{{ \App\Models\Affectation::ROLES[$a->role] ?? $a->role }}</div>
                      <div class="el-person__sub">{{ $a->suppleant ? 'Suppléant' : 'Titulaire' }}@if ($a->chef_section) · Chef de section @endif</div>
                    </td>
                    <td>
                      <div class="el-person__name">{{ $a->agent?->nomComplet() }}</div>
                      <div class="el-person__sub">{{ $a->agent?->fonctionLibelle() }}@if ($a->remarque) · {{ $a->remarque }}@endif</div>
                    </td>
                    <td class="el-num">{{ $a->debut?->format('d/m/Y') ?? '—' }}</td>
                    <td class="el-num">{{ $a->fin?->format('d/m/Y') ?? '—' }}</td>
                    <td><x-chip :tone="$tonEtat" :label="$libelleEtat" /></td>
                    @canany(['affectations.modifier', 'affectations.supprimer'])
                      <td>
                        <div style="display:flex; gap:8px">
                          @can('affectations.modifier')
                            <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_affectation({{ $a->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                            @can('affectations.creer')
                              @if ($libelleEtat !== 'Terminée')
                                <button class="el-iconbtn el-iconbtn--teal" type="button" title="Remplacer" aria-label="Remplacer {{ $a->agent?->nomComplet() }}" onclick="remplacer_affectation({{ $a->id }}, @js($a->agent?->nomComplet()))"><i class="fa-solid fa-right-left"></i></button>
                              @endif
                            @endcan
                          @endcan
                          @can('affectations.supprimer')
                            <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_affectation({{ $a->id }})"><i class="fa-regular fa-trash-can"></i></button>
                          @endcan
                        </div>
                      </td>
                    @endcanany
                  </tr>
                @empty
                  <tr><td colspan="6" class="el-vide"><i class="fa-regular fa-folder-open"></i> Aucune personne affectée pour le moment.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="el-card__body" style="border-top:1px solid var(--el-line)">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px">
              <div class="el-info__label">Jours et heures d'audience</div>
              @can('creneaux.creer')
                <button class="el-btn el-btn--ghost el-btn--sm" type="button" onclick="add_creneau({{ $formation->id }})"><i class="fa-solid fa-plus"></i> Ajouter une audience</button>
              @endcan
            </div>
            @forelse ($formation->creneaux->sortBy([fn ($a, $b) => ($a->jour ?? 9) <=> ($b->jour ?? 9), fn ($a, $b) => strcmp((string) $a->heure, (string) $b->heure)]) as $c)
              @php
                $termine = $c->fin && $c->fin->lt($aujourdhui);
                $aVenir = $c->debut && $c->debut->gt($aujourdhui);
              @endphp
              <div class="el-info__row" id="creneau-{{ $c->id }}" style="margin-bottom:8px; align-items:center; @if ($termine) opacity:.6 @endif">
                <span class="el-info__icon"><i class="fa-regular fa-clock"></i></span>
                <div style="flex:1; min-width:0">
                  <div class="el-info__label">{{ $c->type }}</div>
                  <div class="el-info__value">
                    {{ $c->quand() }}@if ($c->salle) · {{ $c->salle->nom }}@endif
                    @if ($c->precision)<br><span class="el-muted">{{ $c->precision }}</span>@endif
                    @if ($c->frequence !== 'ponctuelle' && ($c->debut || $c->fin))<br><span class="el-muted">@if ($c->debut)Du {{ $c->debut->format('d/m/Y') }} @endif @if ($c->fin)jusqu'au {{ $c->fin->format('d/m/Y') }}@endif</span>@endif
                  </div>
                </div>
                @if ($termine)<x-chip tone="neutral" label="Terminée" />@elseif ($aVenir)<x-chip tone="info" label="À venir" />@endif
                @canany(['creneaux.modifier', 'creneaux.supprimer'])
                  <div style="display:flex; gap:6px">
                    @can('creneaux.modifier')
                      <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier l'audience" onclick="edit_creneau({{ $c->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                    @endcan
                    @can('creneaux.supprimer')
                      <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer l'audience" onclick="delete_creneau({{ $c->id }})"><i class="fa-regular fa-trash-can"></i></button>
                    @endcan
                  </div>
                @endcanany
              </div>
            @empty
              <span class="el-muted">Aucune audience enregistrée.</span>
            @endforelse
          </div>
        </div>
      @empty
        <div class="el-card">
          <div class="el-card__body">
            <span class="el-muted">Cette chambre n'a pas encore de formation : ajoutez la composition de la chambre (sans nom de formation) ou ses formations.</span>
          </div>
        </div>
      @endforelse

    </div>
  </div>

  @include('organisation.affectations.form_affectation')
  @include('organisation.affectations.form_remplacement')
  @include('organisation.affectations.js_script')
  @include('organisation.formations.form_formation')
  @include('organisation.formations.js_script')
  @include('organisation.creneaux.form_creneau')
  @include('organisation.creneaux.js_script')
@endsection

@section('script')
  <script src="{{ asset('assets/js/editors/quill.js') }}"></script>
  <script src="{{ asset('assets/js/editeur-riche.js') }}"></script>
@endsection
