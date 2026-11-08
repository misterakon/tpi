/*
 * Page "Charge de travail".
 *
 * 1) Graphiques ApexCharts : elements portant
 *      data-type="donut" | "barres"
 *      data-graphique="{...json...}"      (prepare par le controleur)
 *
 * 2) Tableaux pagines : <table class="table-charge" id="basic-6" data-nom="...">.
 *    Tous les tableaux gardent id="basic-6" (exigence du template) : ils sont donc
 *    identifies par leur attribut data-nom. La recherche est un champ
 *    <input data-recherche="[data-nom='...']"> place au-dessus du tableau.
 *    On n'utilise volontairement PAS la barre de controles de DataTables (dom: 'rtip').
 *    La pagination est stylee par styliserPagination(), rappelee a chaque
 *    reconstruction du DOM par DataTables.
 *
 * Les graphiques et tableaux places dans un onglet masque ne peuvent pas etre mesures :
 * ils sont initialises au premier affichage de leur onglet.
 */
(function () {
  'use strict';

  var graphiquesFaits = [];
  var tableaux = {};

  /* ---------- Graphiques ---------- */

  function lire(el) {
    try { return JSON.parse(el.getAttribute('data-graphique')); } catch (e) { return null; }
  }

  function message(el, texte) {
    el.innerHTML = '<p class="text-muted text-center" style="padding:24px 0;">' + texte + '</p>';
  }

  function somme(valeurs) {
    return (valeurs || []).reduce(function (a, b) { return a + b; }, 0);
  }

  // Sur un graphique en barres HORIZONTALES, ApexCharts applique yaxis.labels.formatter aux NOMS
  // des categories et xaxis.labels.formatter aux valeurs. Le formateur ne touche donc que les nombres
  // entiers et laisse passer tout le reste : pas de "NaN", pas de graduations decimales.
  function graduationEntiere(valeur) {
    var n = Number(valeur);
    if (isNaN(n)) { return valeur; }
    return Math.abs(n - Math.round(n)) < 1e-9 ? String(Math.round(n)) : '';
  }

  function donut(el, d) {
    return new ApexCharts(el, {
      chart: { type: 'donut', height: 290, fontFamily: 'inherit' },
      series: d.series,
      labels: d.labels,
      colors: d.colors,
      stroke: { width: 0 },
      legend: { position: 'bottom' },
      dataLabels: { enabled: false },
      plotOptions: {
        pie: {
          donut: {
            size: '68%',
            labels: {
              show: true,
              total: {
                show: true,
                label: 'Tâches',
                formatter: function (w) { return somme(w.globals.seriesTotals); }
              }
            }
          }
        }
      }
    });
  }

  function barres(el, d) {
    return new ApexCharts(el, {
      chart: {
        type: 'bar',
        stacked: true,
        height: Math.max(230, d.categories.length * 42 + 90),
        toolbar: { show: false },
        fontFamily: 'inherit'
      },
      plotOptions: { bar: { horizontal: true, barHeight: '58%' } },
      series: d.series,
      colors: d.colors,
      xaxis: { categories: d.categories, labels: { formatter: graduationEntiere } },
      dataLabels: { enabled: false },
      legend: { position: 'bottom' },
      grid: { strokeDashArray: 3 },
      tooltip: { shared: true, intersect: false }
    });
  }

  function tracerGraphiques() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-graphique]'), function (el) {
      if (graphiquesFaits.indexOf(el) !== -1 || el.offsetWidth === 0) { return; }
      graphiquesFaits.push(el);

      if (typeof ApexCharts === 'undefined') {
        message(el, 'Graphique indisponible : la librairie ApexCharts n\'est pas chargée.');
        return;
      }

      var donnees = lire(el);
      if (!donnees) { message(el, 'Graphique indisponible.'); return; }

      if (el.getAttribute('data-type') === 'donut') {
        if (somme(donnees.series) === 0) { message(el, 'Aucune tâche à afficher.'); return; }
        donut(el, donnees).render();
      } else {
        if (!donnees.categories || donnees.categories.length === 0) { message(el, 'Aucune donnée à afficher.'); return; }
        barres(el, donnees).render();
      }
    });
  }

  /* ---------- Tableaux ---------- */

  function initialiserTableaux() {
    var $ = window.jQuery;
    if (!$ || !$.fn || !$.fn.DataTable) { return; }

    $('table.table-charge').each(function () {
      var nom = this.getAttribute('data-nom');
      if (!nom || tableaux[nom] || this.offsetWidth === 0) { return; }

      tableaux[nom] = $(this).DataTable({
        dom: 'rtip',
        autoWidth: false,
        pageLength: 10,
        lengthChange: false,
        order: [],
        language: {
          emptyTable: 'Aucune donnée',
          zeroRecords: 'Aucun résultat',
          info: '_START_ à _END_ sur _TOTAL_',
          infoEmpty: '',
          infoFiltered: '(filtré sur _MAX_)',
          paginate: { first: '«', previous: '‹', next: '›', last: '»' }
        },
        // Pas de pagination quand tout tient sur une page
        drawCallback: function () {
          var pages = this.api().page.info().pages;
          $(this).closest('.dataTables_wrapper, .dt-container').find('.dataTables_paginate, .dt-paging').toggle(pages > 1);
        }
      });
    });
  }

  function brancherRecherche() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-recherche]'), function (champ) {
      champ.addEventListener('input', function () {
        var cible = document.querySelector(champ.getAttribute('data-recherche'));
        var api = cible && tableaux[cible.getAttribute('data-nom')];
        if (api) { api.search(champ.value).draw(); }
      });
    });
  }

  function ajusterColonnes() {
    Object.keys(tableaux).forEach(function (nom) {
      var el = document.querySelector('table[data-nom="' + nom + '"]');
      if (el && el.offsetWidth > 0) { tableaux[nom].columns.adjust(); }
    });
  }

  // Habille la pagination DataTables avec les classes Bootstrap du template.
  // Ne fait qu'ajouter des classes : sans risque de boucle avec l'observateur.
  function styliserPagination() {
    var $ = window.jQuery;
    if (!$) { return; }

    $('.dataTables_paginate, .dt-paging').each(function () {
      var $p = $(this).addClass('d-flex justify-content-end mt-3');
      $p.children('span').addClass('d-flex');
      $p.find('.paginate_button, .dt-paging-button').each(function () {
        var $b = $(this);
        $b.removeClass('btn-primary btn-outline-primary')
          .addClass('btn btn-sm mx-1')
          .addClass($b.hasClass('current') ? 'btn-primary' : 'btn-outline-primary');
      });
    });
    $('.dataTables_info, .dt-info').addClass('text-muted mt-3');
  }

  /* ---------- Demarrage ---------- */

  function toutInitialiser() {
    tracerGraphiques();
    initialiserTableaux();
    ajusterColonnes();
    styliserPagination();
  }

  function demarrer() {
    toutInitialiser();
    brancherRecherche();
    window.addEventListener('resize', ajusterColonnes);

    // DataTables reconstruit ses boutons a chaque page/onglet : on re-style apres chaque changement du DOM.
    var attente;
    new MutationObserver(function () {
      clearTimeout(attente);
      attente = setTimeout(styliserPagination, 30);
    }).observe(document.body, { childList: true, subtree: true });

    // Au clic sur un onglet : on attend la fin de l'affichage, puis on initialise ce qui est devenu visible.
    Array.prototype.forEach.call(document.querySelectorAll('.tab-link'), function (lien) {
      lien.addEventListener('click', function () {
        setTimeout(toutInitialiser, 120);
        setTimeout(toutInitialiser, 500);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', demarrer);
  } else {
    demarrer();
  }
})();
