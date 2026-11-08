<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>{{ \App\Models\CertificatRecours::TYPES[$c->type] }} n° {{ $c->numeroAffichage() }}</title>
<style>
  @page { margin: 10mm 18mm 16mm 18mm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #000; line-height: 1.5; }
  .filigrane { position: fixed; top: 60mm; left: 35mm; width: 140mm; z-index: -1; }
  table.tete { width: 100%; border-collapse: collapse; table-layout: fixed; }
  table.tete td { vertical-align: top; padding: 0; text-align: center; font-size: 11pt; line-height: 1.15; }
  h1 { text-align: center; font-size: 17pt; text-decoration: underline; margin: 12mm 0 2mm; }
  .num { text-align: center; font-weight: bold; margin-bottom: 8mm; }
  p { text-align: justify; margin: 0 0 4mm; }
  .sign { margin-top: 16mm; text-align: right; }
</style>
</head>
<body>
<img class="filigrane" src="{{ $logo }}" alt="">
<table class="tete"><tr>
  <td style="width:45%">@foreach ($gauche as $l){{ $l }}<br>@endforeach</td>
  <td style="width:10%"></td>
  <td style="width:45%"><img src="{{ $armoiries }}" alt="" style="height:16mm"><br>@foreach ($droite as $l){{ $l }}<br>@endforeach</td>
</tr></table>

<h1>{{ mb_strtoupper(\App\Models\CertificatRecours::TYPES[$c->type]) }}</h1>
<div class="num">N° {{ $c->numeroAffichage() }}</div>

@php $voie = $c->type === 'non_opposition' ? 'opposition' : 'appel'; @endphp
<p>Le greffier en chef du {{ $juridiction }} certifie que {{ $d->numeroAffichage() ? 'la décision n° '.$d->numeroAffichage() : 'la décision' }}@if ($d->date_signature), rendue le {{ $d->date_signature->format('d/m/Y') }},@endif
  dans l'affaire {{ $a?->numeroAffichage() }}@if ($a?->objet) ({{ $a->objet }})@endif,
  opposant {{ $a ? \App\Support\Audiences\SuiviAffaires::parties($a) : '' }}, a été signifiée le {{ $c->date_signification->format('d/m/Y') }}.</p>
<p>Le délai {{ $voie === 'appel' ? 'd\'appel' : 'd\'opposition' }} a expiré le {{ $c->echeance->format('d/m/Y') }}. Il n'a été formé, à la date de ce jour, ni {{ $voie === 'appel' ? 'appel' : 'opposition' }} ni pourvoi contre cette décision, d'après les registres du greffe.</p>
<p>En conséquence, la décision est passée en force de chose jugée en ce qui concerne la voie de l'{{ $voie }}. Le présent certificat est délivré à {{ $c->delivre_a }}@if ($c->qualite) ({{ $c->qualite }})@endif pour servir et valoir ce que de droit.</p>

<div class="sign">Fait à Bingerville, le {{ $c->date_delivrance->format('d/m/Y') }}<br><br><b>Le Greffier en chef</b></div>
</body>
</html>
