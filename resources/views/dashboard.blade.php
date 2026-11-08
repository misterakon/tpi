@extends('layout.master')
@section('title', 'Tableau de bord')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-dashboard.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-paiements.css') }}">
@endsection

{{-- Le titre et le fil d'Ariane sont ceux du modèle. L'essentiel seulement : 4 indicateurs, un graphique, les gains, les derniers paiements, ce qui reste à faire. --}}
@section('main-content')
        <div class="page-body">
          <div class="container">
            <div class="page-title">
              <div class="grid grid-cols-12 mx-2 items-center">
                <div class="col-span-6 sm:col-span-12">
                  <h3>Tableau de bord</h3>
                </div>
                <div class="col-span-6 sm:col-span-12">
                  <ol class="breadcrumb flex">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">
                        <svg class="stroke-icon">
                          <use href="{{ asset('assets/svg/icon-sprite.svg#stroke-home') }}"></use>
                        </svg></a></li>
                    <li class="breadcrumb-item active">Tableau de bord</li>
                  </ol>
                </div>
              </div>
            </div>
          </div>
          <!-- Container-fluid starts -->
          <div class="container default-dashboard ed-page">

            @php
              $f = fn ($n) => number_format((int) $n, 0, ',', ' ');
              $aFaire = $aTraiter['demandes_reset'] + $aTraiter['tentatives_en_attente'] + $aTraiter['doublons'] + $aTraiter['anomalies'];
            @endphp

            {{-- Les 4 indicateurs --}}
            <div class="ed-kpis">
              <div class="ed-kpi ed-kpi--ambre">
                <div class="ed-kpi__icone"><i class="fa-solid fa-coins"></i></div>
                <span class="ed-kpi__label">Recettes nettes (FCFA)</span>
                <div>
                  <div class="ed-kpi__valeur">{{ $f($indicateurs['net']) }}</div>
                  <div class="ed-chip"><i class="fa-regular fa-calendar"></i><span>Ce mois : {{ $f($indicateurs['net_mois']) }}</span></div>
                </div>
              </div>

              <div class="ed-kpi ed-kpi--vert">
                <div class="ed-kpi__icone"><i class="fa-solid fa-circle-check"></i></div>
                <span class="ed-kpi__label">Comptes actifs</span>
                <div>
                  <div class="ed-kpi__valeur"><span class="ed-compteur" data-valeur="{{ $indicateurs['actifs'] }}">{{ $f($indicateurs['actifs']) }}</span></div>
                  <div class="ed-chip"><i class="fa-solid fa-users"></i><span>sur {{ $f($indicateurs['inscrits']) }} inscrits ({{ str_replace('.', ',', (string) $indicateurs['taux_activation']) }} %)</span></div>
                </div>
              </div>

              <div class="ed-kpi ed-kpi--violet">
                <div class="ed-kpi__icone"><i class="fa-solid fa-receipt"></i></div>
                <span class="ed-kpi__label">Paiements</span>
                <div>
                  <div class="ed-kpi__valeur"><span class="ed-compteur" data-valeur="{{ $indicateurs['paiements'] }}">{{ $f($indicateurs['paiements']) }}</span></div>
                  <div class="ed-chip"><i class="fa-solid fa-arrow-trend-up"></i><span>{{ $f($indicateurs['net_7_jours']) }} FCFA sur 7 jours</span></div>
                </div>
              </div>

              <a class="ed-kpi {{ $aTraiter['demandes_reset'] > 0 ? 'ed-kpi--rouge' : 'ed-kpi--sarcelle' }}" href="{{ route('membres.demandes', ['statut' => 'en_attente']) }}">
                <div class="ed-kpi__icone"><i class="fa-solid {{ $aTraiter['demandes_reset'] > 0 ? 'fa-bell' : 'fa-thumbs-up' }}"></i></div>
                <span class="ed-kpi__label">À traiter</span>
                <div>
                  <div class="ed-kpi__valeur"><span class="ed-compteur" data-valeur="{{ $aTraiter['demandes_reset'] }}">{{ $f($aTraiter['demandes_reset']) }}</span></div>
                  <div class="ed-chip"><i class="fa-solid {{ $aTraiter['demandes_reset'] > 0 ? 'fa-arrow-right' : 'fa-check' }}"></i><span>{{ $aTraiter['demandes_reset'] > 0 ? 'demande(s) de remise à zéro' : 'Aucune demande en attente' }}</span></div>
                </div>
              </a>
            </div>

            {{-- Graphique et gains --}}
            <div class="ed-ligne">
              <div class="ed-s8">
                <div class="ed-panel">
                  <div class="ed-tete">
                    <div><h5>Évolution</h5><small id="libelle-periode">{{ $series['libelle'] }}</small></div>
                    <div class="ed-actions">
                      @if ($peutExporter)
                        <a class="btn btn-sm btn-outline-primary" id="export-pdf" href="{{ route('dashboard.export.pdf', ['periode' => $periode]) }}" download><i class="fa-regular fa-file-pdf"></i> PDF</a>
                        <a class="btn btn-sm btn-outline-success" id="export-excel" href="{{ route('dashboard.export.excel', ['periode' => $periode]) }}" download><i class="fa-regular fa-file-excel"></i> Excel</a>
                      @endif
                      <div class="dropdown custom-dropdown">
                        <button class="btn dropdown-toggle" id="periode-bouton" type="button" data-bs-toggle="dropdown" aria-expanded="false">{{ $periodes[$periode]['court'] }}</button>
                        <ul class="dropdown-menu">
                          @foreach ($periodes as $cle => $p)
                            <li><a class="dropdown-item" href="#!" data-periode="{{ $cle }}">{{ $p['court'] }} <span class="f-light">· {{ $p['libelle'] }}</span></a></li>
                          @endforeach
                        </ul>
                      </div>
                    </div>
                  </div>

                  <div class="ed-onglets" role="tablist">
                    <button type="button" class="is-actif" data-onglet="gains" role="tab">Gains par partie</button>
                    <button type="button" data-onglet="recettes" role="tab">Recettes</button>
                    <button type="button" data-onglet="fideles" role="tab">Fidèles</button>
                    <button type="button" data-onglet="tentatives" role="tab">Tentatives</button>
                  </div>

                  <div class="ed-resume" id="resume"></div>
                  <div id="graphique"></div>
                </div>
              </div>

              <div class="ed-s4">
                <div class="ed-sombre">
                  <h5>Gains par partie prenante</h5>
                  <small>Depuis le début, parts figées</small>
                  @if (count($gains) > 0)
                    @php $couleursGains = ['#7DD3FC', '#86EFAC', '#FDE68A', '#FDA4AF', '#C4B5FD', '#FDBA74']; @endphp
                    <div class="ed-gains">
                      <div id="chart-gains"></div>
                      <div class="ed-legende">
                        @foreach ($gains as $i => $g)
                          <div class="ed-legende__ligne">
                            <i class="ed-point" style="background: {{ $couleursGains[$i % count($couleursGains)] }}"></i>
                            <span class="ed-legende__nom">{{ $g['nom'] }}</span>
                            <b class="ed-legende__montant">{{ $f($g['total']) }}</b>
                            <em class="ed-legende__pct">{{ str_replace('.', ',', (string) $g['part']) }} %</em>
                          </div>
                        @endforeach
                      </div>
                      <div class="ed-total"><span>Total réparti</span><b>{{ $f(collect($gains)->sum('total')) }} FCFA</b></div>
                    </div>
                  @else
                    <div class="ed-vide" style="color:#fff;opacity:.85">Aucun paiement pour le moment : les gains apparaîtront ici.</div>
                  @endif
                </div>
              </div>
            </div>

            {{-- Derniers paiements et situation --}}
            <div class="ed-ligne">
              <div class="ed-s8">
                <div class="ed-panel">
                  <div class="ed-tete">
                    <div><h5>Derniers paiements</h5><small>Les {{ max(1, $derniers->count()) }} plus récents</small></div>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('paiements.index') }}">Tout voir</a>
                  </div>
                  <div class="overflow-x-auto custom-scrollbar">
                    <table class="ed-table" id="derniers-paiements">
                      <thead><tr><th>Fidèle</th><th>Téléphone</th><th>Réseau</th><th style="text-align:right">Net (FCFA)</th><th style="text-align:right">Date</th></tr></thead>
                      <tbody>
                        @forelse ($derniers as $p)
                          @php
                            $mots = preg_split('/\s+/', trim($p->member->nom_prenoms), -1, PREG_SPLIT_NO_EMPTY);
                            $initiales = mb_strtoupper(mb_substr($mots[0] ?? '?', 0, 1).mb_substr($mots[1] ?? '', 0, 1));
                          @endphp
                          <tr>
                            <td><div class="ed-personne"><div class="ed-av ed-av-{{ crc32($p->member->nom_prenoms) % 6 }}">{{ $initiales }}</div><div style="min-width:0"><a href="{{ route('membres.show', $p->member_id) }}">{{ $p->member->nom_prenoms }}</a><div class="ed-sous">{{ $p->member->eglise }}</div></div></div></td>
                            <td class="ed-temps">{{ $p->member->telephoneAffiche() }}</td>
                            <td>
                              @if ($p->canal === 'manuel')
                                {{ \App\Support\Reseau::pastilleManuel() }}
                              @else
                                {{ \App\Support\Reseau::pastille($p->attempt?->moyen_paiement) }}
                              @endif
                            </td>
                            <td style="text-align:right"><span class="ed-montant">{{ $f($p->montant_net) }}</span></td>
                            <td style="text-align:right"><span class="ed-date">{{ $p->paid_at->format('d/m/Y') }}</span><div class="ed-sous">{{ $p->paid_at->format('H:i') }} · {{ $p->paid_at->diffForHumans() }}</div></td>
                          </tr>
                        @empty
                          <tr><td colspan="5" class="ed-vide">Aucun paiement pour le moment.</td></tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="ed-s4">
                <div class="ed-panel">
                  <div class="ed-bloc">
                    <h6>Fidèles par statut</h6>
                    @php
                      $teintesStatut = ['en_attente' => '#F59E0B', 'actif' => '#10B981', 'bloque' => '#EF4444', 'bloque_appareils' => '#8B5CF6'];
                      $totalFideles = max(1, $indicateurs['inscrits']);
                    @endphp
                    <div class="ed-pile" role="img" aria-label="Répartition des fidèles par statut">
                      @foreach ($indicateurs['statuts'] as $s)
                        @if ($s['total'] > 0)<i style="flex: {{ $s['total'] }} 1 0; min-width: 6px; background: {{ $teintesStatut[$s['cle']] ?? '#64748B' }}" title="{{ $s['label'] }} : {{ $f($s['total']) }}"></i>@endif
                      @endforeach
                    </div>
                    <div class="ed-statuts-liste">
                      @foreach ($indicateurs['statuts'] as $s)
                        <div class="ed-statut-ligne">
                          <i class="ed-point" style="background: {{ $teintesStatut[$s['cle']] ?? '#64748B' }}"></i>
                          <span class="ed-statut-nom">{{ $s['label'] }}</span>
                          <b class="ed-statut-nombre">{{ $f($s['total']) }}</b>
                          <em class="ed-statut-pct">{{ round($s['total'] / $totalFideles * 100) }} %</em>
                        </div>
                      @endforeach
                    </div>
                  </div>

                  <div class="ed-bloc">
                    <h6>À traiter</h6>
                    @php
                      $taches = [
                          [$aTraiter['demandes_reset'], 'demande(s) de remise à zéro', route('membres.demandes')],
                          [$aTraiter['tentatives_en_attente'], 'paiement(s) en cours', route('paiements.tentatives', ['statut' => 'en_attente'])],
                          [$aTraiter['doublons'], 'paiement(s) en double à rembourser', route('paiements.tentatives', ['statut' => 'acceptee'])],
                          [$aTraiter['anomalies'], 'anomalie(s) de montant', auth()->user()->isAdminLevel() ? route('journal.index') : '#!'],
                      ];
                    @endphp
                    @foreach ($taches as [$n, $texte, $lien])
                      @if ($n > 0)
                        <a class="ed-tache" href="{{ $lien }}"><span><b>{{ $n }}</b>{{ $texte }}</span><i class="fa-solid fa-chevron-right"></i></a>
                      @endif
                    @endforeach
                    @if ($aTraiter['paiement_ferme'])
                      <div class="ed-tache" style="color:#B42318"><span><i class="fa-solid fa-lock" style="margin-right:10px"></i>Paiement fermé : {{ $aTraiter['paiement_ferme'] }}</span></div>
                    @endif
                    @if ($aFaire === 0 && ! $aTraiter['paiement_ferme'])
                      <div class="ed-ok"><i class="fa-regular fa-circle-check"></i> Rien à traiter pour le moment.</div>
                    @endif
                  </div>
                </div>
              </div>
            </div>

          </div>
          <!-- Container-fluid Ends -->
        </div>
@endsection

@section('script')
  <script src="{{ asset('assets/js/chart/apex-chart/apex-chart.js') }}"></script>
  <script>
    /* Les chiffres des cartes sont déjà écrits dans la page. Ce script ne fait que les « compter » jusqu'à leur valeur. */
    (function () {
      var reduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var fmt = function (n) { return new Intl.NumberFormat('fr-FR').format(n).replace(/[\u202f\u00a0]/g, ' '); };

      document.querySelectorAll('.ed-compteur').forEach(function (el) {
        var cible = parseInt(el.getAttribute('data-valeur'), 10) || 0;
        if (reduit || cible <= 0 || !window.requestAnimationFrame) { return; }

        var debut = null;
        el.textContent = '0';
        window.requestAnimationFrame(function pas(t) {
          if (debut === null) { debut = t; }
          var p = Math.min(1, (t - debut) / 900);
          el.textContent = fmt(Math.round(cible * (1 - Math.pow(1 - p, 3))));
          if (p < 1) { window.requestAnimationFrame(pas); }
        });
      });
    })();

    (function () {
      'use strict';

      var DONNEES = @js($series);
      var GAINS = @js($gains);
      var URL_DONNEES = @js(route('dashboard.donnees'));
      var URL_PDF = @js(route('dashboard.export.pdf'));
      var URL_EXCEL = @js(route('dashboard.export.excel'));
      var onglet = 'gains';
      var graphique = null;

      var conteneur = document.querySelector('#graphique');
      if (typeof ApexCharts === 'undefined') {
        conteneur.innerHTML = '<div class="ed-vide">Bibliothèque de graphiques introuvable (assets/js/chart/apex-chart/apex-chart.js).</div>';
        return;
      }

      var fmt = function (n) { return new Intl.NumberFormat('fr-FR').format(Math.round(n)).replace(/[\u202f\u00a0]/g, ' '); };
      var somme = function (t) { return t.reduce(function (a, b) { return a + b; }, 0); };
      var base = { fontFamily: 'Rubik, sans-serif', toolbar: { show: false }, animations: { speed: 600 } };
      var PALETTE = ['#2F6BE8', '#10B981', '#F59E0B', '#F43F5E', '#8B5CF6', '#06B6D4'];
      var grille = { borderColor: 'rgba(120,130,150,.16)', strokeDashArray: 4, padding: { left: 6, right: 6 } };
      var vide = { text: 'Aucune donnée sur cette période', style: { fontSize: '14px' } };

      /* Des repères d'axe lisibles, même quand tout vaut zéro (plus de « 2 2 2 1 1 1 0 0 ») */
      function axeEntier(plancher) {
        return { min: 0, tickAmount: 4, forceNiceScale: false, max: function (m) { return Math.max(plancher, Math.ceil(m * 1.12)); }, decimalsInFloat: 0, labels: { formatter: function (v) { return Math.round(v); } } };
      }
      function axeMontant() {
        return { min: 0, tickAmount: 4, max: function (m) { return m <= 0 ? 4000 : Math.ceil(m * 1.12); }, labels: { formatter: function (v) { return v >= 1000 ? fmt(v / 1000) + ' k' : fmt(v); } } };
      }

      var vues = {
        recettes: {
          resume: function (d) { return [[fmt(d.totaux.paiements), 'paiements', ''], [fmt(d.totaux.net), 'recettes nettes', 'FCFA'], [fmt(d.totaux.brut), 'recettes brutes', 'FCFA']]; },
          options: function (d) { return {
            chart: Object.assign({ type: 'line', height: 330 }, base),
            series: [{ name: 'Recettes nettes (FCFA)', type: 'area', data: d.paiements.net }, { name: 'Paiements', type: 'column', data: d.paiements.n }],
            colors: ['#2F6BE8', '#F97316'], stroke: { width: [3, 0], curve: 'smooth' },
            fill: { type: ['gradient', 'solid'], opacity: [0.4, 0.9], gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.04, stops: [0, 90, 100] } },
            plotOptions: { bar: { columnWidth: '36%', borderRadius: 6 } },
            yaxis: [Object.assign({ seriesName: 'Recettes nettes (FCFA)' }, axeMontant()), Object.assign({ seriesName: 'Paiements', opposite: true }, axeEntier(4))],
            tooltip: { shared: true, intersect: false, y: [{ formatter: function (v) { return fmt(v) + ' FCFA'; } }, { formatter: function (v) { return Math.round(v) + ' paiement(s)'; } }] } }; }
        },
        fideles: {
          resume: function (d) { return [[fmt(d.totaux.inscriptions), 'inscriptions', ''], [fmt(d.totaux.paiements), 'comptes activés', ''], [d.totaux.inscriptions > 0 ? Math.round(d.totaux.paiements / d.totaux.inscriptions * 100) + ' %' : '—', 'activés / inscrits', '']]; },
          options: function (d) { return {
            chart: Object.assign({ type: 'area', height: 330 }, base),
            series: [{ name: 'Inscriptions', data: d.inscriptions }, { name: 'Comptes activés (paiements)', data: d.paiements.n }],
            colors: ['#2F6BE8', '#10B981'], stroke: { width: 3, curve: 'smooth' }, fill: { type: 'gradient', gradient: { opacityFrom: 0.42, opacityTo: 0.04 } },
            yaxis: axeEntier(4) }; }
        },
        gains: {
          resume: function (d) { return d.gains.length ? d.gains.map(function (g) { return [fmt(somme(g.data)), g.nom, 'FCFA']; }).slice(0, 4) : [['0', 'aucun gain', 'FCFA']]; },
          options: function (d) { return {
            chart: Object.assign({ type: 'bar', height: 330, stacked: true }, base),
            series: d.gains.map(function (g) { return { name: g.nom, data: g.data }; }), colors: PALETTE, plotOptions: { bar: { columnWidth: '42%', borderRadius: 5 } },
            yaxis: axeMontant(), tooltip: { y: { formatter: function (v) { return fmt(v) + ' FCFA'; } } } }; }
        },
        tentatives: {
          resume: function (d) { return [[fmt(somme(d.tentatives.acceptee)), 'acceptées', ''], [fmt(somme(d.tentatives.refusee)), 'refusées', ''], [fmt(somme(d.tentatives.expiree)), 'expirées', '']]; },
          options: function (d) { return {
            chart: Object.assign({ type: 'bar', height: 330, stacked: true }, base),
            series: [{ name: 'Acceptées', data: d.tentatives.acceptee }, { name: 'Refusées', data: d.tentatives.refusee }, { name: 'Expirées', data: d.tentatives.expiree }, { name: 'En attente', data: d.tentatives.en_attente }],
            colors: ['#10B981', '#EF4444', '#9AA4B2', '#F59E0B'], plotOptions: { bar: { columnWidth: '42%', borderRadius: 5 } }, yaxis: axeEntier(4) }; }
        }
      };

      /* Abscisses lisibles : les mois sur une ligne avec l'année dessous (pour janvier et le premier), les jours un sur trois, les semaines toutes. */
      function libelles(d) {
        return d.labels.map(function (l, i) {
          if (d.periode === 'mois') { var p = l.split(' '); return (i === 0 || p[0].indexOf('janv') === 0) ? [p[0], '20' + p[1]] : [p[0]]; }
          if (d.periode === 'jour') { return (i % 3 === 0 || i === d.labels.length - 1) ? l : ''; }
          return l;
        });
      }

      function communes(d) {
        return { xaxis: { categories: libelles(d), tickPlacement: 'between', labels: { rotate: 0, rotateAlways: false, hideOverlappingLabels: false, trim: false, style: { fontSize: '12.5px', fontWeight: 500 } }, axisBorder: { show: false }, axisTicks: { show: false } },
                 dataLabels: { enabled: false }, legend: { position: 'top', horizontalAlign: 'left', markers: { radius: 12 } }, grid: grille, noData: vide };
      }

      function afficher() {
        var v = vues[onglet];
        document.getElementById('resume').innerHTML = v.resume(DONNEES).map(function (r) {
          return '<div><b>' + r[0] + (r[2] ? ' <small>' + r[2] + '</small>' : '') + '</b><span>' + r[1] + '</span></div>';
        }).join('');
        if (graphique) { graphique.destroy(); }
        var opts = Object.assign({}, communes(DONNEES), v.options(DONNEES));
        var labelsComplets = DONNEES.labels;
        opts.tooltip = Object.assign({}, opts.tooltip, { x: { formatter: function (val, o) { return (o && o.dataPointIndex != null && labelsComplets[o.dataPointIndex]) ? labelsComplets[o.dataPointIndex] : val; } } });
        graphique = new ApexCharts(conteneur, opts);
        graphique.render();
      }

      document.querySelectorAll('.ed-onglets button').forEach(function (b) {
        b.addEventListener('click', function () {
          onglet = b.getAttribute('data-onglet');
          document.querySelectorAll('.ed-onglets button').forEach(function (x) { x.classList.toggle('is-actif', x === b); });
          afficher();
        });
      });
      afficher();

      /* Anneau des gains (carte sombre) */
      var zoneGains = document.querySelector('#chart-gains');
      if (zoneGains && GAINS.length) {
        new ApexCharts(zoneGains, {
          chart: Object.assign({ type: 'donut', height: 200 }, base),
          series: GAINS.map(function (g) { return g.total; }), labels: GAINS.map(function (g) { return g.nom; }),
          colors: ['#7DD3FC', '#86EFAC', '#FDE68A', '#FDA4AF', '#C4B5FD', '#FDBA74'],
          stroke: { width: 0 }, dataLabels: { enabled: false }, legend: { show: false },
          tooltip: { theme: 'light', fillSeriesColor: false, y: { formatter: function (v) { return fmt(v) + ' FCFA'; } } },
          plotOptions: { pie: { donut: { size: '72%', labels: { show: true, name: { show: false }, value: { show: true, color: '#fff', fontSize: '20px', fontWeight: 700, formatter: function (v) { return fmt(v); } },
            total: { show: true, label: 'FCFA', color: '#fff', formatter: function (w) { return fmt(somme(w.globals.seriesTotals)); } } } } } }
        }).render();
      }

      /* Changement de période, sans recharger la page */
      document.querySelectorAll('[data-periode]').forEach(function (a) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          var periode = a.getAttribute('data-periode');
          if (window.EA && EA.progress) { EA.progress.start(); }
          fetch(URL_DONNEES + '?periode=' + encodeURIComponent(periode), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) { throw new Error('http'); } return r.json(); })
            .then(function (d) {
              DONNEES = d;
              afficher();
              document.getElementById('libelle-periode').textContent = d.libelle;
              document.getElementById('periode-bouton').textContent = a.textContent.split('·')[0].trim();
              var pdf = document.getElementById('export-pdf'), xls = document.getElementById('export-excel');
              if (pdf) { pdf.href = URL_PDF + '?periode=' + d.periode; }
              if (xls) { xls.href = URL_EXCEL + '?periode=' + d.periode; }
            })
            .catch(function () { if (window.Swal) { Swal.fire({ icon: 'error', title: 'Erreur', text: 'Impossible de charger les statistiques. Réessayez.' }); } })
            .then(function () { if (window.EA && EA.progress) { EA.progress.done(); } });
        });
      });
    })();
  </script>
@endsection
