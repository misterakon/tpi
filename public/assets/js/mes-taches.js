/*
 * Page "Mes taches" (public/assets/js/mes-taches.js).
 *
 * 1) Onglets du menu de gauche : [data-mt-tab="x"] affiche [data-mt-pane="x"].
 * 2) Modal de mise a jour : boutons .btn-maj-tache (data-url, data-titre, data-priorite,
 *    data-statut, data-avancement) -> #modalMajTache, envoi AJAX vers data-url.
 * 3) Tableau "Toutes mes taches" : recalcul des colonnes quand l'onglet devient visible
 *    (DataTables ne sait pas mesurer une table cachee).
 */
(function ($) {
  'use strict';

  var $modal, $form, $statut, $avancement, $valeur;

  /* ---------- Onglets ---------- */

  function afficherOnglet(cle) {
    $('[data-mt-pane]').hide();
    $('[data-mt-pane="' + cle + '"]').show();

    $('[data-mt-tab]').removeClass('active');
    $('[data-mt-tab="' + cle + '"]').addClass('active');

    if (window.jQuery.fn.dataTable) {
      $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    }
  }

  /* ---------- Modal de mise a jour ---------- */

  function statutEstTermine() {
    var code = $statut.find('option:selected').data('code');
    return code === $form.data('code-terminee');
  }

  // Une tache "terminee" est toujours a 100 % : on fige le curseur
  function synchroniserAvancement() {
    if (statutEstTermine()) {
      $avancement.val(100).prop('disabled', true);
    } else {
      $avancement.prop('disabled', false);
    }
    $valeur.text($avancement.val());
  }

  function ouvrirModal($bouton) {
    $form.attr('action', $bouton.data('url'));
    $('#majTitre').text($bouton.data('titre'));
    $('#maj_priorite').val($bouton.data('priorite') || '');
    $statut.val($bouton.data('statut'));
    $avancement.prop('disabled', false).val($bouton.data('avancement') || 0);
    synchroniserAvancement();
    $modal.modal('show');
  }

  function enregistrer(e) {
    e.preventDefault();

    // Un champ desactive n'est pas envoye : on le reactive juste avant la serialisation
    $avancement.prop('disabled', false);
    var donnees = $form.serialize();
    synchroniserAvancement();

    $('#btnEnregistrerMaj').prop('disabled', true);

    $.ajax({
      url: $form.attr('action'),
      type: 'POST',
      data: donnees,
      success: function (reponse) {
        $modal.modal('hide');
        Swal.fire('e-Tache', reponse.message, 'success').then(function () { window.location.reload(); });
      },
      error: function (jqXHR) {
        var erreurs = jqXHR.responseJSON && jqXHR.responseJSON.errors;
        var message = (jqXHR.responseJSON && jqXHR.responseJSON.message) || "Une erreur s'est produite.";
        if (erreurs) { message = Object.keys(erreurs).map(function (k) { return erreurs[k][0]; }).join('\n'); }
        Swal.fire('Erreur', message, 'error');
      },
      complete: function () { $('#btnEnregistrerMaj').prop('disabled', false); }
    });
  }

  /* ---------- Demarrage ---------- */

  $(function () {
    $modal = $('#modalMajTache');
    $form = $('#formMajTache');
    $statut = $('#maj_statut');
    $avancement = $('#maj_avancement');
    $valeur = $('#majAvancementValeur');

    $('[data-mt-tab]').on('click', function (e) {
      e.preventDefault();
      afficherOnglet($(this).data('mt-tab'));
    });

    $(document).on('click', '.btn-maj-tache', function () { ouvrirModal($(this)); });
    $statut.on('change', synchroniserAvancement);
    $avancement.on('input', function () { $valeur.text(this.value); });
    $form.on('submit', enregistrer);

    afficherOnglet('jour');
  });
})(window.jQuery);
