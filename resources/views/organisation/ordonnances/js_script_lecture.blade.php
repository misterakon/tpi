<script>
  function imprimer_ordonnance() {
    var cadre = document.getElementById('lecteur-pdf');
    try { cadre.contentWindow.focus(); cadre.contentWindow.print(); } catch (e) { window.open(cadre.src, '_blank'); }
  }
</script>
