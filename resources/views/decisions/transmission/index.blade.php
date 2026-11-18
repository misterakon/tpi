@extends('layout.master')
@section('title', 'Transmission au greffe')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <style>
    .tr-filtres { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 16px; padding: 20px 24px; }
    .tr-champ { display: flex; flex-direction: column; gap: 6px; }
    .tr-champ label { font-weight: 700; font-size: 13px; margin: 0; }
    .tr-champ .form-control, .tr-champ .form-select { height: 42px; min-width: 170px; }
    .tr-champ--large { flex: 1 1 240px; max-width: 360px; }
    .tr-champ--large .form-select { width: 100%; }
    .tr-champ--btn { flex-direction: row; gap: 10px; margin-left: auto; }
    .tr-carte { padding: 4px 24px 24px; }
    .tr-carte .el-tblwrap { margin: 0 -24px; }
    .tr-pied { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 20px 28px; margin-top: 22px; padding-top: 20px; border-top: 1px solid var(--el-line, #e3ebe6); }
    .tr-copie { flex: 1 1 320px; display: flex; flex-direction: column; gap: 6px; padding-bottom: 8px; }
    .tr-aide { font-size: 12.5px; color: #6b7280; }
    .tr-pied .el-btn { margin-left: auto; white-space: nowrap; }
    .tr-vide { padding: 22px 24px; }
  </style>
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page tp-anime">

      <x-hero tag="Décisions" title="Transmission au greffe" subtitle="Les décisions signées qui n'ont pas encore été transmises. Cochez celles à transmettre (ou toutes celles d'une période) : la date de transmission vaut date de délivrance au greffe." icon="fa-solid fa-share">
        <x-slot:extra>
          <span><i class="fa-solid fa-file-signature"></i> <b>{{ $total }}</b> décision(s) à transmettre</span>
        </x-slot:extra>
      </x-hero>

      <div class="el-card" style="margin-bottom:16px">
        <form method="get" class="tr-filtres">
          <div class="tr-champ"><label for="f-du">Signées du</label><input class="form-control" type="date" id="f-du" name="du" value="{{ $f['du'] ?? '' }}"></div>
          <div class="tr-champ"><label for="f-au">au</label><input class="form-control" type="date" id="f-au" name="au" value="{{ $f['au'] ?? '' }}"></div>
          <div class="tr-champ tr-champ--large"><label for="f-ch">Chambre</label>
            <select class="form-select" id="f-ch" name="chambre" data-sans-recherche><option value="">Toutes les chambres</option>@foreach ($chambres as $c)<option value="{{ $c->id }}" @selected((int) ($f['chambre'] ?? 0) === $c->id)>{{ $c->libelle }}</option>@endforeach</select></div>
          <div class="tr-champ tr-champ--btn">
            <button class="el-btn el-btn--ghost" type="submit"><i class="fa-solid fa-filter"></i> Filtrer</button>
            @if (! empty($f['du']) || ! empty($f['au']) || ! empty($f['chambre']))<a class="el-btn el-btn--ghost" href="{{ route('decisions.transmission') }}"><i class="fa-solid fa-rotate-left"></i> Réinitialiser</a>@endif
          </div>
        </form>
      </div>

      <div class="el-card tr-carte">
        @if ($decisions->isEmpty())
          <div class="aff-info tr-vide" style="margin-top:20px"><i class="fa-solid fa-circle-check"></i><span>Aucune décision signée en attente de transmission{{ ! empty($f['du']) || ! empty($f['au']) || ! empty($f['chambre']) ? ' avec ces filtres' : '' }}.</span></div>
        @else
          @if ($total > $limite)<div class="aff-info" style="margin:16px 0 10px"><i class="fa-solid fa-triangle-exclamation"></i><span>{{ $total }} décisions correspondent ; les {{ $limite }} plus anciennes sont affichées. Resserrez la période pour voir les autres.</span></div>@endif
          <form method="post" action="{{ route('decisions.transmission.store') }}" id="f-lot" autocomplete="off">
            @csrf
            <div class="el-tblwrap">
              <table class="el-tbl">
                <thead><tr><th style="width:56px;padding-left:24px"><input type="checkbox" class="el-case" id="tout" aria-label="Tout cocher"></th><th style="width:120px">Décision</th><th style="width:140px">Affaire</th><th>Chambre et objet</th><th style="width:130px">Signée le</th><th style="width:120px">Attend depuis</th></tr></thead>
                <tbody>
                  @foreach ($decisions as $d)
                    <tr>
                      <td style="padding-left:24px"><input type="checkbox" class="el-case ligne" name="ids[]" value="{{ $d->id }}" aria-label="Choisir {{ $d->numeroAffichage() ?? $d->reference }}"></td>
                      <td><b>{{ $d->numeroAffichage() ?? '—' }}</b></td>
                      <td>{{ $d->affaire?->numeroAffichage() ?? '—' }}</td>
                      <td><div class="el-person__name">{{ $d->chambre?->libelle }}</div><div class="el-person__sub">{{ $d->affaire?->objet }}</div></td>
                      <td>{{ $d->date_signature?->format('d/m/Y') }}</td>
                      <td>{{ $d->date_signature ? (int) $d->date_signature->diffInDays(today()).' j' : '—' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            <div class="tr-pied">
              <div class="tr-champ"><label for="g-date">Date de transmission</label><input class="form-control" type="date" id="g-date" name="date_transmission" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required></div>
              <div class="tr-copie">
                <label class="aff-interrupteur" for="g-copie"><input type="checkbox" id="g-copie" name="envoyer_copie" value="1"><span class="aff-interrupteur__piste" aria-hidden="true"></span><span class="aff-interrupteur__texte"><b>Envoyer une copie au greffier en chef</b> (Word, par e-mail)</span></label>
                @if (! $adresse)<div class="tr-aide">L'adresse du greffier en chef n'est pas encore réglée (Réglages &gt; Décisions) : la copie ne partirait pas.</div>@endif
              </div>
              <button class="el-btn el-btn--accent" type="submit" id="b-lot" disabled><i class="fa-solid fa-share"></i> Transmettre <span id="n-lot">0</span> décision(s)</button>
            </div>
          </form>
        @endif
      </div>

    </div>
  </div>
@endsection

@section('script')
  <script>
    (function () {
      var lignes = document.querySelectorAll('.ligne'), tout = document.getElementById('tout'), btn = document.getElementById('b-lot'), n = document.getElementById('n-lot');
      if (!btn) { return; }
      function maj() {
        var c = document.querySelectorAll('.ligne:checked').length;
        n.textContent = c; btn.disabled = c === 0; tout.checked = c > 0 && c === lignes.length;
      }
      tout.addEventListener('change', function () { lignes.forEach(function (l) { l.checked = tout.checked; }); maj(); });
      lignes.forEach(function (l) { l.addEventListener('change', maj); });
      document.getElementById('f-lot').addEventListener('submit', function (e) {
        if (!document.getElementById('g-copie').checked) { return; }
        @if (! $adresse)
          if (!confirm("L'adresse du greffier en chef n'est pas réglée : les décisions seront transmises mais la copie ne partira pas. Continuer ?")) { e.preventDefault(); }
        @endif
      });
      maj();
    })();
  </script>
@endsection
