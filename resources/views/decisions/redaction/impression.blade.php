<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>{{ $decision->reference }} | {{ config('app.name') }}</title>
  <link rel="stylesheet" href="{{ asset('assets/css/vendors/fontawesome.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/police.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/feuille-acte.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/impression-decision.css') }}">
</head>
<body>
  <div class="imp-barre">
    <div>
      <b>{{ $decision->reference }}</b> · {{ $decision->modele->nom }} · version {{ $decision->numeroVersion() }}
      · {{ \App\Models\Decision::STATUTS[$decision->statut] ?? $decision->statut }}
    </div>
    <div class="imp-actions">
      <span id="imp-zoom-lib" class="imp-lib"></span>
      <button type="button" onclick="zoomer(-1)" aria-label="Réduire">&minus;</button>
      <button type="button" onclick="zoomer(1)" aria-label="Agrandir">+</button>
      <button type="button" onclick="zoomer(0)">Largeur</button>
      <button type="button" class="principal" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimer ou enregistrer en PDF</button>
      <button type="button" onclick="window.close()">Fermer</button>
    </div>
  </div>

  @if (! $decision->estValidee())
    <div class="imp-alerte">
      Brouillon : cette décision n'est pas validée.
      @if ($manquantes) Il manque encore : {{ implode(', ', $manquantes) }}. @endif
    </div>
  @endif
  <div class="imp-conseil">Dans la fenêtre d'impression, choisissez « Enregistrer au format PDF » pour obtenir un fichier ; désactivez les « en-têtes et pieds de page » et gardez les marges « Aucune ».</div>

  <div id="feuilles"></div>

  <script src="{{ asset('assets/js/feuille-acte.js') }}"></script>
  <script>
    const PARTIES = @js($parties);
    const MISE_EN_PAGE = @js($miseEnPage);
    const PALIERS = [0.5, 0.6, 0.75, 0.9, 1, 1.25, 1.5];
    let zoom = 'ajuste', echelle = 1;

    function dessiner() {
      const r = FeuilleActe.afficher(document.getElementById('feuilles'), PARTIES, MISE_EN_PAGE, { couleurs: false, zoom: zoom });
      if (r) { echelle = r.echelle; document.getElementById('imp-zoom-lib').textContent = Math.round(r.echelle * 100) + ' %'; }
    }

    function zoomer(pas) {
      if (pas === 0) { zoom = 'ajuste'; }
      else {
        const suivant = pas > 0 ? PALIERS.find(function (p) { return p > echelle + 0.01; }) : PALIERS.slice().reverse().find(function (p) { return p < echelle - 0.01; });
        zoom = suivant || echelle;
      }
      dessiner();
    }

    window.addEventListener('beforeprint', function () { FeuilleActe.afficher(document.getElementById('feuilles'), PARTIES, MISE_EN_PAGE, { couleurs: false, zoom: 1 }); });
    window.addEventListener('afterprint', dessiner);
    window.addEventListener('resize', function () { if (zoom === 'ajuste') { dessiner(); } });
    document.addEventListener('DOMContentLoaded', dessiner);
  </script>
</body>
</html>
