/*
 * Page "Détails de la tâche" (public/assets/js/tache-details.js).
 *  - [data-radial]    : graphique circulaire du résumé  (JSON : series, labels, colors, centre, libelleCentre)
 *  - [data-evolution] : courbe d'évolution de l'avancement (JSON : [[timestamp_ms, %], ...])
 * Un graphique dans un onglet masqué ne peut pas être mesuré : il est tracé au premier affichage.
 */
(function () {
  'use strict';

  var faits = [];

  function lire(el, attribut) {
    try { return JSON.parse(el.getAttribute(attribut)); } catch (e) { return null; }
  }

  function message(el, texte) {
    el.innerHTML = '<p class="text-muted text-center" style="padding:24px 0;">' + texte + '</p>';
  }

  function radial(el) {
    var d = lire(el, 'data-radial');
    if (!d) { return; }

    new ApexCharts(el, {
      chart: { type: 'radialBar', height: 270, fontFamily: 'inherit' },
      series: d.series,
      labels: d.labels,
      colors: d.colors,
      plotOptions: {
        radialBar: {
          hollow: { size: '38%' },
          track: { background: '#F1F1F1' },
          dataLabels: {
            name: { show: true, fontSize: '13px', offsetY: 18 },
            value: { show: true, fontSize: '22px', fontWeight: 600, offsetY: -16, formatter: function (v) { return v + ' %'; } },
            total: {
              show: true,
              label: d.libelleCentre || '',
              formatter: function () { return d.centre; }
            }
          }
        }
      },
      stroke: { lineCap: 'round' },
      legend: { show: false }
    }).render();
  }

  function evolution(el) {
    var points = lire(el, 'data-evolution') || [];
    if (points.length < 2) { message(el, "Aucune mise à jour d'avancement enregistrée."); return; }

    new ApexCharts(el, {
      chart: { type: 'area', height: 270, fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false } },
      series: [{ name: 'Avancement', data: points }],
      colors: ['#7F73FF'],
      stroke: { curve: 'stepline', width: 3 },
      fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 100] } },
      dataLabels: { enabled: false },
      markers: { size: 4 },
      xaxis: { type: 'datetime', labels: { datetimeUTC: false, format: 'dd/MM' } },
      yaxis: { min: 0, max: 100, tickAmount: 5, labels: { formatter: function (v) { return Math.round(v) + ' %'; } } },
      tooltip: {
        x: { format: 'dd/MM/yyyy HH:mm' },
        y: { formatter: function (v) { return v + ' %'; } }
      },
      grid: { borderColor: '#e5e9f0' }
    }).render();
  }

  function tracer() {
    if (typeof ApexCharts === 'undefined') { return; }

    Array.prototype.forEach.call(document.querySelectorAll('[data-radial], [data-evolution]'), function (el) {
      if (faits.indexOf(el) !== -1 || el.offsetParent === null) { return; }
      faits.push(el);
      if (el.hasAttribute('data-radial')) { radial(el); } else { evolution(el); }
    });
  }

  function demarrer() {
    tracer();
    Array.prototype.forEach.call(document.querySelectorAll('.tab-link'), function (lien) {
      lien.addEventListener('click', function () {
        setTimeout(tracer, 150);
        setTimeout(tracer, 500);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', demarrer);
  } else {
    demarrer();
  }
})();
