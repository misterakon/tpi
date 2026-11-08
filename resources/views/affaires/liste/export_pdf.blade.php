<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>{{ $titre }}</title>
<style>
  @page { margin: 28px 26px 40px; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #10241B; }
  h1 { margin: 0 0 3px; font-size: 15px; color: #0B3D2A; }
  .sous { margin: 0 0 10px; color: #3F4D66; font-size: 8.5px; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  th { padding: 5px 6px; background: #00794C; color: #fff; text-align: left; font-size: 8.5px; }
  td { padding: 4px 6px; border-bottom: 1px solid #DDE3EC; vertical-align: top; word-wrap: break-word; }
  tr:nth-child(even) td { background: #F6FAF8; }
  .pied { position: fixed; bottom: -26px; left: 0; right: 0; text-align: right; color: #6B778C; font-size: 8px; }
</style>
</head>
<body>
  <h1>{{ $titre }}</h1>
  <p class="sous">{{ count($lignes) }} affaire(s) · {{ implode(' · ', $criteres) ?: 'Sans filtre' }} · Édité le {{ $date->format('d/m/Y à H:i') }}</p>
  <table>
    <colgroup><col style="width:9%"><col style="width:6%"><col style="width:10%"><col style="width:24%"><col style="width:22%"><col style="width:11%"><col style="width:12%"><col style="width:6%"></colgroup>
    <thead><tr>@foreach ($colonnes as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
    <tbody>
      @foreach ($lignes as $l)
        <tr>@foreach ($l as $v)<td>{{ $v }}</td>@endforeach</tr>
      @endforeach
    </tbody>
  </table>
  <div class="pied">Tribunal de première instance de Bingerville</div>
</body>
</html>
