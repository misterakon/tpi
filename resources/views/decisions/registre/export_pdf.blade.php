<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>{{ $titre }}</title>
<style>
  @page { margin: 8mm 12mm 14mm 12mm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #000; }
  .filigrane { position: fixed; top: 30mm; left: 50mm; width: 180mm; z-index: -1; }
  table.tete { width: 100%; border-collapse: collapse; border: 0; table-layout: fixed; }
  table.tete td { vertical-align: top; padding: 0; border: 0; }
  td.bloc { text-align: center; font-size: 12pt; line-height: 1.12; }
  table.tete td.milieu { text-align: center; vertical-align: middle; padding: 0 4mm; }
  .titre { font-weight: bold; font-size: 22pt; line-height: 1.25; text-decoration: underline; word-wrap: break-word; }
  .sous { margin: 3mm 0 0; text-align: center; font-size: 10pt; color: #26324A; }
  table.suites { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 5mm; }
  table.suites th { border: 0.7pt solid #000; padding: 4px 4px; background: #F2F2F2; text-align: center; font-size: 8.5pt; font-weight: bold; }
  table.suites td { border: 0.7pt solid #000; padding: 4px 5px; vertical-align: top; word-wrap: break-word; line-height: 1.3; }
  tr { page-break-inside: avoid; }
  .pied { position: fixed; bottom: -9mm; right: 0; font-size: 10pt; border: 0.6pt solid #000; padding: 2px 9px; }
  .pied:after { content: counter(page); }
  .edite { position: fixed; bottom: -9mm; left: 0; font-size: 9pt; padding: 2px 0; }
</style>
</head>
<body>
<img class="filigrane" src="{{ $logo }}" alt="">
<table class="tete"><tr>
  <td class="bloc" style="width:28%">@foreach ($gauche as $l){{ $l }}<br>@endforeach</td>
  <td class="milieu" style="width:44%"><div class="titre">{{ $titre }}</div></td>
  <td class="bloc" style="width:28%"><img src="{{ $armoiries }}" alt="" style="height:16mm; margin-bottom:1.5mm"><br>@foreach ($droite as $l){{ $l }}<br>@endforeach</td>
</tr></table>
<p class="sous">{{ count($lignes) }} décision(s) · {{ implode(' · ', $criteres) ?: 'Sans autre filtre' }}</p>
@php
  // Largeur de chaque colonne : numéro, date, signature, référence, type, chambre, statut, remises.
  $largeurs = [9, 11, 11, 13, 26, 17, 8, 5];
@endphp
<table class="suites">
  <colgroup>@foreach ($largeurs as $w)<col style="width:{{ $w }}%">@endforeach</colgroup>
  <thead><tr>@foreach ($colonnes as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
  <tbody>
    @foreach ($lignes as $l)
      <tr>@foreach ($l as $v)<td>{{ $v }}</td>@endforeach</tr>
    @endforeach
  </tbody>
</table>
<div class="pied"></div>
<div class="edite">Édité le {{ $date->format('d/m/Y à H:i') }}</div>
</body>
</html>
