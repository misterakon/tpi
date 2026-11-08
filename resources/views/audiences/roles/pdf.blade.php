<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>{{ $doc['titre'] }}</title>
<style>
  @page { margin: 8mm 12mm 14mm 12mm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 12pt; color: #000; }
  .filigrane { position: fixed; top: 30mm; left: 50mm; width: 180mm; z-index: -1; }
  .projet { position: fixed; top: 62mm; left: 40mm; width: 200mm; text-align: center; font: bold 92pt 'DejaVu Sans', sans-serif; color: #E4E4E4; z-index: -1; transform: rotate(-25deg); }
  table.tete { width: 100%; border-collapse: collapse; border: 0; table-layout: fixed; }
  table.tete td { vertical-align: top; padding: 0; border: 0; }
  td.bloc { text-align: center; font-size: 12pt; line-height: 1.12; }
  table.tete td.salle { text-align: center; vertical-align: middle; font-weight: bold; font-size: 18pt; text-decoration: underline; padding: 0 4mm; word-wrap: break-word; }
  table.equipe { width: 100%; margin-top: 5mm; border-collapse: collapse; font-size: 11pt; line-height: 1.5; }
  table.equipe td { border: 0; padding: 0; vertical-align: top; }
  table.equipe b { display: inline-block; width: 34mm; }
  h1 { margin: 6mm 0 0; text-align: center; font-size: 18pt; font-weight: bold; line-height: 1.4; }
  h1.suite { margin-top: 0; }
  table.equipe td.heure { width: 40mm; text-align: right; font-size: 14pt; font-weight: bold; }
  table.role { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 6mm; }
  table.role td { border: 0.7pt solid #000; padding: 5px 6px; vertical-align: top; word-wrap: break-word; line-height: 1.3; }
  td.rubrique { text-align: center; font-weight: bold; font-size: 15pt; padding: 7px; }
  tr.colonnes td { text-align: center; vertical-align: middle; font-weight: bold; font-size: 10pt; background: #F2F2F2; padding: 4px 3px; }
  tr.largeurs td { height: 0; padding: 0; border: 0; line-height: 0; font-size: 0; }
  tr { page-break-inside: avoid; }
  .avocat { font-style: italic; }
  .signature { margin: 18px 0 0 60%; font-size: 13pt; }
  .signature b { display: block; margin-top: 3px; }
  .pied { position: fixed; bottom: -9mm; right: 0; font-size: 10pt; color: #000; border: 0.6pt solid #000; padding: 2px 9px; }
  .pied:after { content: counter(page); }
  .version { position: fixed; bottom: -9mm; left: 0; font-size: 10pt; color: #000; padding: 2px 0; }
</style>
</head>
<body>
<img class="filigrane" src="{{ $logo }}" alt="">
@if (! empty($doc['brouillon']))<div class="projet">SPECIMEN</div>@endif
<table class="tete"><tr>
  <td class="bloc" style="width:28%">@foreach ($doc['entete_gauche'] ?? \App\Support\Roles\RoleDocument::enteteGauche() as $l){{ $l }}<br>@endforeach</td>
  <td class="salle" style="width:44%">{{ $doc['salle'] ?? '' }}</td>
  <td class="bloc" style="width:28%"><img src="{{ $armoiries }}" alt="" style="height:16mm; margin-bottom:1.5mm"><br>@foreach ($doc['entete_droite'] ?? \App\Support\Roles\RoleDocument::enteteDroite() as $l){{ $l }}<br>@endforeach</td>
</tr></table>
<h1>{!! nl2br(e($doc['titre_chambre'] ?? $doc['titre'])) !!}</h1>
@if (! empty($doc['titre_quand']))<h1 class="suite">{!! nl2br(e($doc['titre_quand'])) !!}</h1>@endif
@if (! empty($doc['equipe']))
  <table class="equipe"><tr>
    <td>@foreach ($doc['equipe'] as [$libelle, $valeur])<div><b>{{ $libelle }}</b>{{ $valeur }}</div>@endforeach</td>
    <td class="heure">{{ $doc['heure_droite'] ?? '' }}</td>
  </tr></table>
@endif

<table class="role">
  <tr class="largeurs">@foreach ($doc['colonnes'] as $c)<td style="width:{{ $c['pct'] }}%"></td>@endforeach</tr>
  @foreach ($doc['rubriques'] as $r)
    <tr><td class="rubrique" colspan="{{ count($doc['colonnes']) }}">{{ $r['titre'] }}</td></tr>
    <tr class="colonnes">@foreach ($doc['colonnes'] as $c)<td>{{ $c['titre'] }}</td>@endforeach</tr>
    @foreach ($r['lignes'] as $l)
      <tr>
        @foreach ($l['cellules'] as $cellule)
          <td>@foreach ($cellule as $ligne)@php [$texte, $italique, $gras] = \App\Support\Roles\RoleDocument::ligneDeCellule($ligne); @endphp @if ($gras || $italique)<span style="{{ $gras ? 'font-weight:bold;' : '' }}{{ $italique ? 'font-style:italic;' : '' }}">{{ $texte }}</span>@else{{ $texte }}@endif<br>@endforeach</td>
        @endforeach
      </tr>
    @endforeach
  @endforeach
</table>

<div class="signature">{{ $doc['signe_prefixe'] ?? 'Fait à ' }}{{ $doc['fait_a'] }}{{ $doc['date_signature'] ? ', le '.$doc['date_signature'] : '' }}<b>{{ $doc['signataire'] }}</b></div>
<div class="pied"></div>
<div class="version">Version {{ (int) ($doc['version'] ?? 1) }}</div>
</body>
</html>
