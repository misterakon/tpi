/* eLouange : tableaux (DataTables) en français, avec recherche et filtres du thème.
   Usage : ElTable.init('#mon-tableau', { order: [[5, 'desc']], noSort: [2, 6], search: '#recherche', pageLength: 10 })
   Filtres par boutons : <button data-el-filter="2" data-value="admin"> (colonne 2, valeur lue dans data-search de la cellule). */
(function () {
  'use strict';

  var FR = {
    emptyTable: 'Aucune donnée à afficher',
    info: 'Affichage de _START_ à _END_ sur _TOTAL_',
    infoEmpty: 'Aucun résultat',
    infoFiltered: '(sur _MAX_ au total)',
    zeroRecords: 'Aucun résultat ne correspond à la recherche',
    paginate: { first: '«', previous: '‹', next: '›', last: '»' }
  };

  /* Recherche sans tenir compte des accents ni des majuscules : « eglise » trouve « Église ». */
  function sansAccents(texte) {
    var t = String(texte == null ? '' : texte);
    return t.normalize ? t.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : t;
  }
  var jq = window.jQuery;
  if (jq && jq.fn && jq.fn.dataTable) {
    jq.fn.dataTable.ext.type.search.string = function (d) { return d ? sansAccents(d) : ''; };
    jq.fn.dataTable.ext.type.search.html = function (d) { return d ? sansAccents(String(d).replace(/<[^>]*>/g, ' ')) : ''; };
  }

  window.ElTable = {
    init: function (selector, opts) {
      opts = opts || {};
      var $t = window.jQuery(selector);
      if (!$t.length || !$t.DataTable) { return null; }

      var serveur = opts.serveur || $t.attr('data-serveur') || null;   // liste paginée côté serveur : seule la page affichée est chargée
      var filtres = {};
      var config = {
        dom: (serveur ? 'r' : '') + 't<"el-foot"ip>',
        autoWidth: false,
        pageLength: opts.pageLength || (serveur ? 20 : (window.TPI_LIGNES_PAR_PAGE || 10)),   // liste serveur : une vingtaine de lignes, la suite à la demande
        order: opts.order || [[0, 'desc']],
        pagingType: 'simple_numbers',
        language: serveur ? Object.assign({ processing: 'Chargement…' }, FR) : FR,
        columnDefs: [{ orderable: false, targets: opts.noSort || [] }]
      };
      if (serveur) {
        config.serverSide = true;
        config.processing = true;
        config.ajax = { url: serveur, type: 'GET', data: function (d) { d.filtre = filtres; } };
      }
      var table = $t.DataTable(config);

      if (opts.search) {
        var minuteur = null;
        window.jQuery(opts.search).on('input', function () {
          var saisie = this.value;
          if (!serveur) { table.search(sansAccents(saisie)).draw(); return; }
          clearTimeout(minuteur);
          minuteur = setTimeout(function () { table.search(saisie).draw(); }, 350);   // pas une requête par touche
        });
      }

      window.jQuery(document).on('click', '[data-el-filter]', function () {
        var $b = window.jQuery(this);
        var valeur = $b.attr('data-value') || '';
        var colonne = parseInt($b.attr('data-el-filter'), 10);
        if (serveur) {
          filtres[colonne] = valeur;
          table.draw();
        } else {
          table.column(colonne)
            .search(valeur ? '^' + valeur.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$' : '', true, false)
            .draw();
        }
        $b.closest('.el-pills').find('.el-pill').removeClass('is-active');
        $b.addClass('is-active');
      });

      return table;
    }
  };
})();
