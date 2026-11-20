@extends('layout.master')
@section('title', 'Ce qui va changer')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/import-ordonnance.css') }}?v={{ filemtime(public_path('assets/css/import-ordonnance.css')) }}">
@endsection

@section('main-content')
  @php
    $liste = collect($changements);
    $total = $liste->count();
    $nb = fn ($a) => $liste->where('action', $a)->count();
    $parBloc = $liste->groupBy('bloc');
    $etapes = [['Charger', 'Fichier déposé'], ['Contrôler', 'Erreurs et avertissements'], ['Rapprocher', 'Noms à confirmer'], ['Comparer', 'Ce qui va changer'], ['Appliquer', 'Enregistrer']];
    $icBloc = ['sections' => 'fa-layer-group', 'salles' => 'fa-door-open', 'agents' => 'fa-users', 'chambres' => 'fa-building-columns', 'formations' => 'fa-sitemap', 'composition' => 'fa-people-group', 'audiences' => 'fa-calendar-days', 'services' => 'fa-briefcase', 'responsables' => 'fa-user-tie', 'membres_exterieurs' => 'fa-handshake', 'membres' => 'fa-handshake', 'dates_limites' => 'fa-hourglass-end', 'articles' => 'fa-scroll', 'annee' => 'fa-calendar-check'];
    $icEtape = ['fa-cloud-arrow-up', 'fa-magnifying-glass-chart', 'fa-user-check', 'fa-code-compare', 'fa-circle-check'];
    $icone = ['creer' => ['fa-plus', 'Ajout', 'io-act--creer'], 'modifier' => ['fa-pen', 'Modification', 'io-act--modifier'], 'retirer' => ['fa-minus', 'Retrait', 'io-act--retirer']];
  @endphp
  <div class="page-body io-page" id="io-racine">
    <div class="el-page tp-anime">

      <x-hero tag="Organisation" title="Ce qui va changer" subtitle="{{ $import->nom_fichier }} comparé à l'organisation enregistrée. Décochez ce que vous ne voulez pas appliquer : rien n'est écrit tant que vous n'avez pas validé." icon="fa-solid fa-code-compare">
        <x-slot:extra>
          <span><i class="fa-solid fa-plus"></i> <b>{{ $nb('creer') }}</b> ajout(s)</span>
          <span><i class="fa-solid fa-pen"></i> <b>{{ $nb('modifier') }}</b> modification(s)</span>
          <span><i class="fa-solid fa-minus"></i> <b>{{ $nb('retirer') }}</b> retrait(s)</span>
        </x-slot:extra>
        <x-slot:actions>
          <a class="el-btn el-btn--ghost" data-io-nav href="{{ route('organisation.ordonnances.import.show', $import->id) }}"><i class="fa-solid fa-arrow-left"></i> Retour au contrôle</a>
        </x-slot:actions>
      </x-hero>

      @include('organisation.ordonnances._onglets', ['actif' => 'import'])

      <div class="el-card" style="margin-bottom:20px">
        <div class="el-card__body" style="padding:18px 24px">
          <ol class="io-etapes" aria-label="Étapes du chargement">
            @foreach ($etapes as $i => [$lib, $aide])
              @php $n = $i + 1; $classe = $n < 4 ? 'is-fait' : ($n === 4 ? 'is-actif' : ''); @endphp
              <li class="io-etape {{ $classe }}">
                <span class="io-etape__rond">@if ($classe === 'is-fait')<i class="fa-solid fa-check"></i>@else<i class="fa-solid {{ $icEtape[$i] }}"></i>@endif</span>
                <span class="io-etape__libelle">{{ $lib }}</span>
                <span class="io-etape__aide">{{ $aide }}</span>
              </li>
            @endforeach
          </ol>
        </div>
      </div>

      @if ($import->mode === 'complet')
        <div class="aff-info" style="margin-bottom:16px"><i class="fa-solid fa-circle-info"></i><span><b>Ordonnance complète :</b> ce qui ne figure plus dans le fichier est retiré. Rien n'est supprimé de l'historique : les affectations et les audiences reçoivent une date de fin, les chambres et formations sont désactivées, et l'import peut être annulé.</span></div>
      @endif

      @if ($total === 0)
        <div class="io-vide"><i class="fa-solid fa-circle-check"></i> Le fichier est identique à l'organisation enregistrée : il n'y a rien à appliquer.</div>
        <div class="io-actions"><a class="el-btn el-btn--ghost" data-io-nav href="{{ route('organisation.ordonnances.import.show', $import->id) }}">Retour</a></div>
      @else
        <form method="post" action="{{ route('organisation.ordonnances.import.appliquer', $import->id) }}" id="f-appliquer" data-io-action data-io-confirm="Appliquer {n} changement(s) à l'organisation ? Vous pourrez annuler cet import ensuite." data-io-titre="Application des changements" data-io-sous="Tout est écrit en une seule opération : rien n'est modifié si une erreur survient.">
          @csrf
          <div class="el-card" style="margin-bottom:16px">
            <div class="el-card__body io-actions" style="margin-top:0; padding:14px 24px">
              <label class="io-case-tout"><input type="checkbox" class="el-case" id="tout" checked> <b>Tout cocher</b></label>
              <div class="io-legende">
                @foreach ($icone as [$ic, $lib, $cl])<span><i class="fa-solid {{ $ic }} io-act {{ $cl }}"></i> {{ $lib }}</span>@endforeach
              </div>
            </div>
          </div>

          @foreach ($blocs as $cle => $titre)
            @continue(! isset($parBloc[$cle]))
            @php $items = $parBloc[$cle]; @endphp
            <details class="io-comp-bloc" @if ($items->count() <= 40) open @endif>
              <summary>
                <label class="io-comp-bloc__case" onclick="event.stopPropagation()"><input type="checkbox" class="el-case io-bloc-case" data-bloc="{{ $cle }}" checked aria-label="Tout cocher : {{ $titre }}"></label>
                <span class="io-comp-bloc__ic"><i class="fa-solid {{ $icBloc[$cle] ?? 'fa-table' }}"></i></span>
                <span class="io-comp-bloc__titre">{{ $titre }}</span>
                <span class="io-comp-bloc__nb">
                  @foreach (['creer' => 'ajout', 'modifier' => 'modification', 'retirer' => 'retrait'] as $a => $mot)
                    @if ($c = $items->where('action', $a)->count())<span class="io-puce io-puce--{{ $a }}">{{ $c }} {{ $mot }}{{ $c > 1 ? 's' : '' }}</span>@endif
                  @endforeach
                </span>
              </summary>
              <div class="io-comp-liste">
                @foreach ($items->take(400) as $c)
                  @php [$ic, $lib, $cl] = $icone[$c['action']]; @endphp
                  <label class="io-comp-ligne">
                    <input type="checkbox" class="el-case io-sel" name="sel[]" value="{{ $c['sig'] }}" data-bloc="{{ $cle }}" checked>
                    <span class="io-act {{ $cl }}" title="{{ $lib }}"><i class="fa-solid {{ $ic }}"></i></span>
                    <span class="io-comp-ligne__corps">
                      <span class="io-comp-ligne__titre">{{ $c['libelle'] }}</span>
                      @foreach (array_slice($c['details'], 0, 8) as [$champ, $avant, $apres])
                        <span class="io-comp-ligne__detail">
                          <b>{{ $champ }}</b> :
                          @if ($avant !== null && $avant !== '')<s>{{ $avant }}</s> <i class="fa-solid fa-arrow-right"></i>@endif
                          <span class="io-apres">{{ $apres }}</span>
                        </span>
                      @endforeach
                    </span>
                  </label>
                @endforeach
                @if ($items->count() > 400)<p class="el-muted" style="padding:10px 16px">{{ $items->count() - 400 }} autre(s) ligne(s) de ce bloc sont aussi proposées et appliquées avec la sélection « tout cocher ».</p>@endif
              </div>
            </details>
          @endforeach

          <div class="el-card" style="margin-top:18px">
            <div class="el-card__body io-actions" style="margin-top:0; padding:18px 24px">
              <span class="el-muted"><i class="fa-solid fa-rotate-left"></i> Vous pourrez annuler cet import ensuite, tant qu'aucun autre n'a été appliqué après lui.</span>
              <button class="el-btn el-btn--accent" type="submit" id="b-appliquer"><i class="fa-solid fa-check"></i> Appliquer <span id="n-sel">{{ $total }}</span> changement(s)</button>
            </div>
          </div>
        </form>
      @endif

    </div>
  </div>
  @include('organisation.ordonnances.import._chargeur')
@endsection

@section('script')
  <script src="{{ asset('assets/js/import-ordonnance.js') }}?v={{ filemtime(public_path('assets/js/import-ordonnance.js')) }}"></script>
@endsection
