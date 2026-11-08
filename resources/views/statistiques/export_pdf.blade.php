<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>{{ $titre }}</title>
<style>
  @page { margin: 8mm 12mm 14mm 12mm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #111; }
  .filigrane { position: fixed; top: 40mm; left: 60mm; width: 160mm; z-index: -1; opacity: .12; }
  table.tete { width: 100%; border-collapse: collapse; table-layout: fixed; }
  table.tete td { vertical-align: top; padding: 0; border: 0; text-align: center; font-size: 11pt; line-height: 1.12; }
  .titre { font-weight: bold; font-size: 20pt; text-decoration: underline; line-height: 1.2; }
  .sous { margin: 2mm 0 3mm; text-align: center; font-size: 9.5pt; color: #26324A; }
  h2 { font-size: 11pt; margin: 4mm 0 1mm; color: #00794C; }
  table.bloc { width: 100%; border-collapse: collapse; margin-bottom: 4mm; page-break-inside: avoid; }
  table.bloc { table-layout: fixed; }
  table.bloc th.btitre { text-align: left; font-weight: bold; background: #00794C; color: #fff; padding: 4px 8px; font-size: 9.5pt; border: 0.5pt solid #00794C; }
  .bloc-garde { page-break-inside: avoid; }
  table.bloc th { background: #FCE4CC; border: 0.5pt solid #999; padding: 3px 4px; text-align: center; font-size: 8pt; }
  table.bloc td { border: 0.5pt solid #bbb; padding: 2.5px 5px; text-align: right; }
  table.bloc td:first-child { text-align: left; }
  table.bloc tr.total td { background: #E7F3EC; font-weight: bold; }
  .pied { position: fixed; bottom: -9mm; right: 0; font-size: 9pt; }
  .pied:after { content: counter(page); }
  .edite { position: fixed; bottom: -9mm; left: 0; font-size: 8pt; color: #555; }
</style>
</head>
<body>
<img class="filigrane" src="{{ $logo }}" alt="">
<table class="tete"><tr>
  <td style="width:28%">@foreach ($gauche as $l){{ $l }}<br>@endforeach</td>
  <td style="width:44%; vertical-align:middle"><div class="titre">{{ mb_strtoupper($titre) }}</div></td>
  <td style="width:28%"><img src="{{ $armoiries }}" alt="" style="height:16mm; margin-bottom:1.5mm"><br>@foreach ($droite as $l){{ $l }}<br>@endforeach</td>
</tr></table>
<p class="sous">{{ $periode }}</p>
@foreach ($onglets as $nom => $blocs)
  @if (! $loop->first)<div style="page-break-before: always"></div>@endif
  <h2>{{ $nom }}</h2>
  @foreach ($blocs as $b)
    <div class="bloc-garde"><table class="bloc">
      <colgroup><col style="width:30%">@for ($k = 1; $k < count($b['entete']); $k++)<col>@endfor</colgroup>
      <thead>
        <tr><th class="btitre" colspan="{{ count($b['entete']) }}">{{ $b['titre'] }}</th></tr>
        <tr>@foreach ($b['entete'] as $e)<th>{{ $e }}</th>@endforeach</tr>
      </thead>
      <tbody>
        @foreach ($b['lignes'] as $i => $l)
          <tr @class(['total' => ($b['total'] ?? true) && $i === count($b['lignes']) - 1])>@foreach ($l as $c)<td>{{ $c }}</td>@endforeach</tr>
        @endforeach
      </tbody>
    </table></div>
  @endforeach
@endforeach
<div class="edite">Édité le {{ $date->format('d/m/Y à H:i') }}</div>
<div class="pied"></div>
</body>
</html>
