@extends('layout.master')
@section('title', 'Reconduire une année')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Organisation" title="Reconduire une année" subtitle="Au début d'une année judiciaire, repartez de l'organisation de l'année précédente plutôt que de tout ressaisir : les chambres et leurs formations sont recopiées, puis vous choisissez ce qu'il faut reprendre en plus. Ajustez ensuite selon la nouvelle ordonnance." icon="fa-solid fa-clone" />

      <div class="el-card">
        <div class="el-card__body" style="padding-top:22px">
          @if ($annees->count() < 2)
            <span class="el-muted">Il faut au moins deux années judiciaires : créez d'abord la nouvelle année dans « Année judiciaire ».</span>
          @else
            <form id="form-reconduction" method="post" action="{{ route('organisation.reconduction.executer') }}" autocomplete="off">
              @csrf
              <div class="el-formgrid">
                <div class="el-c6">
                  <label class="form-label" for="source_id"><b>Reconduire l'année</b></label>
                  <select class="form-select" id="source_id" name="source_id" required data-sans-recherche>
                    <option value="">Choisir…</option>
                    @foreach ($annees as $a)
                      <option value="{{ $a->id }}" @selected((int) old('source_id') === $a->id)>{{ $a->libelle }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="el-c6">
                  <label class="form-label" for="cible_id"><b>Vers l'année</b></label>
                  <select class="form-select" id="cible_id" name="cible_id" required data-sans-recherche>
                    <option value="">Choisir…</option>
                    @foreach ($annees as $a)
                      <option value="{{ $a->id }}" @selected((int) old('cible_id') === $a->id)>{{ $a->libelle }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="el-c12"><b>À reprendre en plus des chambres et formations</b></div>
                <div class="el-c12">
                  <label class="el-switch el-switch--lg" for="o_composition">
                    <input type="checkbox" id="o_composition" name="composition" value="1" checked>
                    <span class="el-switch__piste"><i></i></span>
                    <span class="el-switch__texte">La composition (personnes encore en poste, avec leur rôle) : la nouvelle période commence à la date de début de la nouvelle année</span>
                  </label>
                </div>
                <div class="el-c12">
                  <label class="el-switch el-switch--lg" for="o_audiences">
                    <input type="checkbox" id="o_audiences" name="audiences" value="1" checked>
                    <span class="el-switch__piste"><i></i></span>
                    <span class="el-switch__texte">Les jours, heures et salles d'audience en vigueur (sauf audiences supplémentaires)</span>
                  </label>
                </div>
                <div class="el-c12">
                  <label class="el-switch el-switch--lg" for="o_services">
                    <input type="checkbox" id="o_services" name="services" value="1" checked>
                    <span class="el-switch__piste"><i></i></span>
                    <span class="el-switch__texte">Les services, comités et fonctions, avec leurs personnes</span>
                  </label>
                </div>
                <div class="el-c12">
                  <small class="text-muted">Rien n'est écrasé : une chambre ou un service déjà présent dans la nouvelle année reste tel quel. Les jours fériés et vacances, propres à chaque année, ne sont pas copiés.</small>
                </div>
                <div class="el-c12">
                  @can('reconduction.executer')
                    <button class="el-btn el-btn--accent" type="submit"><i class="fa-solid fa-clone"></i> Reconduire</button>
                  @endcan
                </div>
              </div>
            </form>
          @endif
        </div>
      </div>

    </div>
  </div>
@endsection

@section('script')
  <script>
    $('#form-reconduction').on('submit', function (e) {
      if (this.dataset.ok) { return; }
      e.preventDefault();
      const f = this;
      const de = $('#source_id option:selected').text(), vers = $('#cible_id option:selected').text();
      Swal.fire({
        title: @js(config('app.name')), icon: 'question', showCancelButton: true,
        text: 'Reconduire ' + de + ' vers ' + vers + ' ?',
        confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, reconduire'
      }).then(function (r) { if (r.isConfirmed) { f.dataset.ok = '1'; f.submit(); } });
    });
  </script>
@endsection
