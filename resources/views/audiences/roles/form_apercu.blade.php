{{-- Aperçu du rôle en PDF dans une fenêtre, avant tout téléchargement (droit « roles.imprimer »). Variable : $role --}}
<div class="modal fade panel-modal" id="modal-apercu" tabindex="-1" role="dialog" aria-labelledby="titre-modal-apercu" aria-hidden="true">
  <div class="modal-dialog modal-xl rl-apercu">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-apercu">Aperçu du rôle</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body dark-modal rl-apercu__corps">
        <iframe id="cadre-apercu" class="rl-apercu__cadre" title="Aperçu du rôle en PDF" src="about:blank"></iframe>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
	/* L'aperçu est le même PDF que le téléchargement ; il se charge à l'ouverture et se vide à la fermeture. */
	function apercu_role() {
		$('#cadre-apercu').attr('src', @js(route('audiences.roles.export', [$role->id, 'pdf']).'?apercu=1'));
		$('#modal-apercu').modal('show');
	}
	document.addEventListener('DOMContentLoaded', function () {
		$('#modal-apercu').on('hidden.bs.modal', function () { $('#cadre-apercu').attr('src', 'about:blank'); });
	});
</script>
