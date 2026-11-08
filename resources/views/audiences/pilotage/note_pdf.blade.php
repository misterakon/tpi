{{-- Note d'analyse et de pilotage (Dompdf). Variables : $d (chiffres), $a (analyse automatique), $note, $ia, $editeur, $date, $courbe, $jauge, $logo, $armoiries, $gauche, $droite --}}
@php
  $k = $d['kpis'];
  $nb = fn ($v, $suf = '') => $v === null ? '—' : number_format($v, 0, ',', ' ').$suf;
  $couleurs = ['bon' => '#00794C', 'vigilance' => '#E8710A', 'alerte' => '#B42318'];
  $cs = $couleurs[$a['sante']['niveau']] ?? '#00794C';
  $cartes = [
    ['Affaires en cours', $nb($k['stock']), 'stock du tribunal', '#00794C'],
    ['Affaires enrôlées', $nb($k['entrees']), 'sur la période', '#0A7C86'],
    ['Affaires terminées', $nb($k['sorties']), 'jugées, radiées, jointes, désistées', '#334155'],
    ['Taux de traitement', $nb($k['taux_sortie'], ' %'), 'terminées pour 100 enrôlées', ($k['taux_sortie'] !== null && $k['taux_sortie'] < 80) ? '#B42318' : '#00794C'],
    ['Délai moyen', $nb($k['delai_moyen'], ' j'), "de l'enrôlement à la fin", '#0A7C86'],
    ['Affaires en retard', $nb($k['retards']), 'échéance dépassée', $k['retards'] > 0 ? '#B42318' : '#00794C'],
    ['Décisions à rédiger', $nb($k['a_rediger']), $k['a_rediger'] ? 'la plus ancienne : '.$k['plus_ancienne_decision'].' j' : 'rien en attente', $k['a_rediger'] > 0 ? '#C95A00' : '#00794C'],
    ['Alertes critiques', $nb($k['alertes_critiques']), $k['alertes_ouvertes'].' alerte(s) ouverte(s)', $k['alertes_critiques'] > 0 ? '#B42318' : '#00794C'],
  ];
  $totalSuites = max(1, collect($d['suites'])->sum('n'));
  $maxStock = max(1, collect($d['par_chambre'])->max('stock'));
  $chambres = collect($d['par_chambre'])->sortByDesc('stock')->values();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Note d'analyse et de pilotage</title>
<style>
  @page { margin: 8mm 12mm 14mm 12mm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.8pt; color: #111827; }
  .filigrane { position: fixed; top: 55mm; left: 25mm; width: 160mm; z-index: -1; }
  table { border-collapse: collapse; }
  table.tete { width: 100%; table-layout: fixed; }
  table.tete td { vertical-align: top; padding: 0; border: 0; }
  td.bloc { text-align: center; font-size: 8.8pt; line-height: 1.2; }
  td.milieu { text-align: center; vertical-align: middle; padding: 0 3mm; }
  .titre { margin-top: 4mm; padding: 2mm 0; text-align: center; font-weight: bold; font-size: 15pt; letter-spacing: 0.4pt; color: #0B3D2A; border-top: 1.2pt solid #00794C; border-bottom: 1.2pt solid #E8710A; }
  .sous { margin: 2.5mm 0 1mm; text-align: center; font-size: 9.5pt; color: #26324A; }
  h2 { margin: 5mm 0 1.5mm; padding-bottom: 1mm; font-size: 11pt; color: #0B3D2A; border-bottom: 1.2pt solid #00794C; }
  table.cartes { width: 100%; table-layout: fixed; margin-top: 2mm; }
  table.cartes td { padding: 0 1.2mm 2.4mm 0; vertical-align: top; }
  .carte { border: 0.6pt solid #CFE5D9; border-radius: 2mm; padding: 2mm 2.5mm; background: #F7FBF8; }
  .carte .lib { font-size: 7.6pt; color: #26324A; font-weight: bold; }
  .carte .val { font-size: 17pt; font-weight: bold; margin: 0.5mm 0; }
  .carte .pet { font-size: 6.8pt; color: #4A5670; }
  .sante td { vertical-align: middle; }
  .verdict { font-size: 14pt; font-weight: bold; }
  .facteur { padding: 1.2mm 2mm; margin-bottom: 1mm; background: #FDECEA; color: #8E1B10; font-size: 8.2pt; }
  .facteur b { float: right; }
  .situation { padding: 3mm 4mm; background: #F1F8F4; border-left: 3pt solid #00794C; font-size: 9.2pt; line-height: 1.5; }
  table.analyse { width: 100%; table-layout: fixed; margin-top: 3mm; }
  table.analyse td { vertical-align: top; width: 33.3%; padding-right: 2.5mm; }
  table.analyse td.dernier { padding-right: 0; }
  .bl { margin-top: 3mm; page-break-inside: avoid; border-top: 2.5pt solid #00794C; padding: 2mm 3mm; border-left: 0.5pt solid #E3E9F0; border-right: 0.5pt solid #E3E9F0; border-bottom: 0.5pt solid #E3E9F0; }
  .bl h4 { margin: 0 0 1.5mm; font-size: 9pt; }
  .bl ul, .bl ol { margin: 0; padding-left: 4mm; } .bl li { margin-bottom: 1.4mm; font-size: 8.8pt; line-height: 1.4; }
  img.g { width: 100%; }
  table.t { width: 100%; }
  table.t th { background: #E4F3EB; color: #0B3D2A; font-size: 7.4pt; padding: 1.6mm 1mm; border: 0.5pt solid #CFE5D9; text-align: center; }
  table.t td { padding: 1.5mm 1.2mm; border: 0.5pt solid #E3E9F0; font-size: 8pt; text-align: center; }
  table.t td.l, table.t th.l { text-align: left; }
  tr.alt td { background: #F7FBF8; }
  .barre { height: 2mm; background: #E5EDE8; margin-top: 0.8mm; } .barre i { display: block; height: 2mm; background: #00794C; }
  table.suites { width: 100%; }
  table.suites td { padding: 1mm 1.5mm; font-size: 8.3pt; border: 0; } table.suites td.b { width: 55%; }
  .pastille { display: inline-block; width: 2.6mm; height: 2.6mm; margin-right: 1.5mm; }
  .al { padding: 1.4mm 2.5mm; margin-bottom: 1mm; border-left: 2.5pt solid #E8710A; background: #FFF7EE; font-size: 8.2pt; }
  .al.critique { border-left-color: #B42318; background: #FDF1EF; }
  .regles { font-size: 7.6pt; color: #4A5670; margin-top: 1.5mm; }
  .bloc { page-break-inside: avoid; }
  .pied { position: fixed; bottom: -9mm; right: 0; font-size: 9pt; border: 0.6pt solid #000; padding: 2px 9px; } .pied:after { content: counter(page); }
  .edite { position: fixed; bottom: -9mm; left: 0; font-size: 8pt; padding: 2px 0; }
</style>
</head>
<body>
<img class="filigrane" src="{{ $logo }}" alt="">
<table class="tete"><tr>
  <td class="bloc" style="width:40%">@foreach ($gauche as $l){{ $l }}<br>@endforeach</td>
  <td style="width:20%"></td>
  <td class="bloc" style="width:40%"><img src="{{ $armoiries }}" alt="" style="height:13mm; margin-bottom:1mm"><br>@foreach ($droite as $l){{ $l }}<br>@endforeach</td>
</tr></table>
<div class="titre">NOTE D'ANALYSE ET DE PILOTAGE</div>
<p class="sous">{{ $d['perimetre'] }} · {{ $d['periode']['libelle'] }} (du {{ \Carbon\Carbon::parse($d['periode']['debut'])->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($d['periode']['fin'])->format('d/m/Y') }})</p>

<h2>Indicateurs clés</h2>
<table class="cartes">
  @foreach (array_chunk($cartes, 4) as $rang)
    <tr>@foreach ($rang as $c)
      <td style="width:25%"><div class="carte" style="border-top: 2.5pt solid {{ $c[3] }}"><div class="lib">{{ $c[0] }}</div><div class="val" style="color:{{ $c[3] }}">{{ $c[1] }}</div><div class="pet">{{ $c[2] }}</div></div></td>
    @endforeach</tr>
  @endforeach
</table>

<div class="bloc">
<h2>Indice de santé du tribunal</h2>
<table class="sante" style="width:100%"><tr>
  <td style="width:34%; text-align:center"><img src="{{ $jauge }}" alt="" style="width:46mm"><div class="verdict" style="color:{{ $cs }}">{{ $a['sante']['libelle'] }}</div></td>
  <td style="width:66%">
    <div style="font-weight:bold; margin-bottom:1.5mm">Ce qui le fait baisser aujourd'hui</div>
    @forelse ($a['sante']['facteurs'] as $f)<div class="facteur">{{ $f['libelle'] }}<b>{{ $f['points'] }}</b></div>@empty<div>Rien ne fait baisser l'indice : il est à 100.</div>@endforelse
    <div class="regles">L'indice part de 100 ; on retire des points pour chaque difficulté (au plus : @foreach ($a['sante']['regles'] as $r){{ $r['critere'] }} {{ preg_match('/jusqu.à (-\d+)/u', $r['retrait'], $mm) ? $mm[1] : '' }}{{ $loop->last ? '' : ' · ' }}@endforeach). 80 et plus : maîtrisée · 60 à 79 : vigilance · moins de 60 : préoccupante.</div>
  </td>
</tr></table>
</div>

<div class="bloc">
<h2>Affaires enrôlées et terminées, mois par mois</h2>
<img class="g" src="{{ $courbe }}" alt="">
</div>

<div class="bloc">
<h2>Suites notées aux audiences de la période</h2>
<table class="suites">
  @foreach ($d['suites'] as $s)
    <tr><td class="b"><span class="pastille" style="background: {{ \App\Support\Pilotage\NotePdf::COULEURS_SUITES[$s['cle']] ?? '#94A3B8' }}"></span>{{ $s['libelle'] }}</td><td style="width:10%; text-align:right"><b>{{ $s['n'] }}</b></td>
      <td><div class="barre"><i style="width: {{ round($s['n'] / $totalSuites * 100) }}%; background: {{ \App\Support\Pilotage\NotePdf::COULEURS_SUITES[$s['cle']] ?? '#94A3B8' }}"></i></div></td></tr>
  @endforeach
</table>
</div>


<h2>Analyse et recommandations <span style="font-size:8pt; font-weight:normal; color:#4A5670">— {{ $ia ? 'rédigée par l\'IA (' . $ia->created_at->format('d/m/Y à H:i') . ') à partir des seuls totaux' : 'analyse automatique (règles et modèles de phrases)' }}</span></h2>
<div class="situation">{{ $note['situation'] }}</div>
<div class="bl" style="border-top-color:#00794C"><h4 style="color:#00794C">Points forts</h4><ul>@foreach ($note['points_forts'] as $t)<li>{{ $t }}</li>@endforeach</ul></div>
<div class="bl" style="border-top-color:#E8710A"><h4 style="color:#C95A00">Points d'attention</h4><ul>@forelse ($note['points_attention'] as $t)<li>{{ is_array($t) ? $t['texte'] : $t }}</li>@empty<li>Aucun point d'attention sur la période.</li>@endforelse</ul></div>
<div class="bl" style="border-top-color:#3B76B8"><h4 style="color:#1F4E85">Recommandations</h4><ol>@foreach ($note['recommandations'] as $t)<li>{{ $t }}</li>@endforeach</ol></div>

<div class="bloc">
<h2>Situation par chambre</h2>
<table class="t">
  <thead><tr><th class="l" style="width:25%">Chambre</th><th>En cours</th><th>Enrôlées</th><th>Terminées</th><th>Taux</th><th>Délai</th><th>En retard</th><th>À rédiger</th><th>Renvois</th><th>Alertes</th></tr></thead>
  <tbody>
    @foreach ($chambres as $n => $c)
      <tr class="{{ $n % 2 ? 'alt' : '' }}">
        <td class="l">{{ $c['libelle'] }}<div class="barre"><i style="width: {{ round($c['stock'] / $maxStock * 100) }}%"></i></div></td>
        <td><b>{{ $c['stock'] }}</b></td><td>{{ $c['entrees'] }}</td><td>{{ $c['sorties'] }}</td>
        <td>{{ $c['taux_sortie'] === null ? '—' : $c['taux_sortie'].' %' }}</td><td>{{ $c['delai_moyen'] === null ? '—' : $c['delai_moyen'].' j' }}</td>
        <td style="{{ $c['retards'] > 0 ? 'color:#8E1B10; font-weight:bold' : '' }}">{{ $c['retards'] }}</td><td>{{ $c['a_rediger'] }}</td>
        <td>{{ $c['renvois_pct'] === null ? '—' : $c['renvois_pct'].' %' }}</td><td>{{ $c['alertes'] }}</td>
      </tr>
    @endforeach
  </tbody>
</table>
</div>

@if (! empty($d['alertes_recentes']))
  <div class="bloc">
    <h2>Alertes à traiter en priorité</h2>
    @foreach ($d['alertes_recentes'] as $al)
      <div class="al {{ $al['niveau'] === 'critique' ? 'critique' : '' }}"><b>{{ $al['type_libelle'] }}</b>@if ($al['chambre']) · {{ $al['chambre'] }}@endif — {{ $al['titre'] }} (échéance {{ $al['echeance'] }}@if ($al['retard'] > 0), {{ $al['retard'] }} j de retard @endif)</div>
    @endforeach
    <div class="regles">{{ $k['alertes_ouvertes'] }} alerte(s) ouverte(s) au total, dont {{ $k['alertes_critiques'] }} critique(s).</div>
  </div>
@endif

<div class="pied"></div>
<div class="edite">Édité le {{ $date->format('d/m/Y à H:i') }} par {{ $editeur }}</div>
</body>
</html>
