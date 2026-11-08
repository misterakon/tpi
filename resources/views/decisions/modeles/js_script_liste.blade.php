<script type="text/javascript">
  const APP_NOM = @js(config('app.name'));

  function dupliquer_modele(id) {
    Swal.fire({
      title: APP_NOM, text: "Créer une copie de ce modèle ? Vous pourrez la renommer et l'adapter (autre type de décision, autre cas).", icon: 'question', showCancelButton: true,
      confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, dupliquer'
    }).then((result) => {
      if (!result.isConfirmed) { return; }
      const f = document.getElementById('form-duplication');
      f.action = "{{ route('decisions.modeles.dupliquer', ':id') }}".replace(':id', id);
      f.submit();
    });
  }

  function delete_modele(id) {
    Swal.fire({
      title: APP_NOM, text: "Supprimer ce modèle et toutes ses versions ?", icon: 'warning', showCancelButton: true,
      confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Oui, supprimer'
    }).then((result) => {
      if (!result.isConfirmed) { return; }
      $.ajax({
        url: "{{ route('decisions.modeles.delete', ':id') }}".replace(':id', id), type: "DELETE",
        success: function (r) {
          if (r.status) { Swal.fire({ icon: 'success', title: APP_NOM, text: r.message }); $("#row-" + id).fadeOut(500, function () { $(this).remove(); }); }
          else { Swal.fire({ icon: 'error', title: 'Erreur', text: r.message }); }
        },
        error: function () { Swal.fire({ icon: 'error', title: 'Erreur', text: "Une erreur s'est produite lors de la suppression." }); }
      });
    });
  }
</script>
