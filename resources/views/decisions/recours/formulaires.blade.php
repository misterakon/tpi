{{-- Fenêtres de la page Recours et certificats : signification, recours, transmission, certificat, délais. --}}
@php
  $fenetre = function (string $id, string $titre, string $corps, string $bouton) {
      return '<div class="modal fade panel-modal" id="'.$id.'" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false"><div class="modal-dialog aff-modal"><div class="modal-content">'
        .'<div class="modal-header"><h4 class="modal-title" id="t-'.$id.'">'.$titre.'</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>'
        .'<form id="f-'.$id.'" method="post" autocomplete="off">'.csrf_field().'<div class="modal-body dark-modal"><div class="el-formgrid">'.$corps.'</div></div>'
        .'<div class="modal-footer"><button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button><button class="btn btn-primary text-white" type="submit">'.$bouton.'</button></div></form></div></div></div>';
  };
@endphp

{!! $fenetre('modal-signif', 'Signification', '
  <div class="el-c6"><label class="form-label"><b>Date de signification</b></label><input class="form-control" id="g_sig_date" name="date_signification" type="date" max="'.date('Y-m-d').'" required></div>
  <div class="el-c6"><label class="form-label"><b>Voie de recours surveillée</b></label><select class="form-select" id="g_sig_nature" name="nature" data-sans-recherche><option value="appel">Appel</option><option value="opposition">Opposition (jugement par défaut)</option></select></div>
  <div class="el-c12"><label class="form-label"><b>Signifiée à</b></label><input class="form-control" name="signification_a" type="text" maxlength="200" placeholder="Partie destinataire (facultatif)"></div>
  <div class="el-c12"><div class="aff-info"><i class="fa-solid fa-circle-info"></i><span>Le délai de recours court à partir de cette date.</span></div></div>', 'Enregistrer') !!}

{!! $fenetre('modal-recours', 'Enregistrer un recours', '
  <div class="el-c6"><label class="form-label"><b>Nature</b></label><select class="form-select" name="nature" data-sans-recherche><option value="appel">Appel</option><option value="opposition">Opposition</option><option value="pourvoi">Pourvoi</option><option value="autre">Autre recours</option></select></div>
  <div class="el-c6"><label class="form-label"><b>Date du recours</b></label><input class="form-control" name="date_recours" type="date" max="'.date('Y-m-d').'" required></div>
  <div class="el-c12"><label class="form-label"><b>Auteur du recours</b></label><input class="form-control" name="auteur" type="text" maxlength="200" required></div>
  <div class="el-c12"><label class="form-label"><b>Observations</b></label><input class="form-control" name="observations" type="text" maxlength="500"></div>', 'Enregistrer') !!}

{!! $fenetre('modal-transmission', 'Transmettre à la Cour d\'appel', '
  <div class="el-c6"><label class="form-label"><b>Date de transmission</b></label><input class="form-control" name="date_transmission" type="date" max="'.date('Y-m-d').'" required></div>
  <div class="el-c6"><label class="form-label"><b>Référence à la Cour</b></label><input class="form-control" name="reference_cour" type="text" maxlength="100"></div>', 'Transmettre') !!}

{!! $fenetre('modal-certificat', 'Délivrer le certificat', '
  <div class="el-c8"><label class="form-label"><b>Délivré à</b></label><input class="form-control" name="delivre_a" type="text" maxlength="200" required></div>
  <div class="el-c4"><label class="form-label"><b>Qualité</b></label><input class="form-control" name="qualite" type="text" maxlength="100" placeholder="Ex. : avocat"></div>
  <div class="el-c12"><div class="aff-info"><i class="fa-solid fa-stamp"></i><span>Le numéro est attribué automatiquement. Le certificat est ensuite imprimé pour la signature du greffier en chef.</span></div></div>', 'Délivrer') !!}

@if ($peutDelais)
<div class="modal fade panel-modal" id="modal-delais" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog aff-modal" style="max-width:760px">
    <div class="modal-content">
      <div class="modal-header"><h4 class="modal-title">Délais de recours</h4><button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
      <form method="post" action="{{ route('decisions.recours.delais') }}" autocomplete="off">
        @csrf
        <div class="modal-body dark-modal">
          <div class="aff-info" style="margin-bottom:10px"><i class="fa-solid fa-circle-info"></i><span>Les délais installés suivent la norme usuelle (appel civil 1 mois, opposition 15 jours, référé 15 jours, appel correctionnel 10 jours). Modifiez-les si le tribunal applique une autre durée : un délai décoché ne permet pas de délivrer de certificat.</span></div>
          <table class="el-tbl">
            <thead><tr><th>Délai</th><th>Voie</th><th style="width:90px">Durée</th><th style="width:110px">Unité</th><th style="width:100px">Confirmé</th></tr></thead>
            <tbody>
              @foreach ($delais as $x)
                <tr>
                  <td>{{ $x->libelle }}<div class="el-person__sub">{{ $x->natureAffaire?->libelle ?? ($x->registre ? 'registre '.$x->registre : 'tous les registres') }}</div></td>
                  <td>{{ \App\Models\DelaiRecours::NATURES[$x->nature] }}</td>
                  <td><input class="form-control" type="number" min="1" max="120" name="delais[{{ $x->id }}][valeur]" value="{{ $x->valeur }}" required></td>
                  <td><select class="form-select" name="delais[{{ $x->id }}][unite]" data-sans-recherche><option value="jours" @selected($x->unite === 'jours')>jours</option><option value="mois" @selected($x->unite === 'mois')>mois</option></select></td>
                  <td><label><input type="checkbox" name="delais[{{ $x->id }}][valide]" value="1" @checked($x->valide)> Confirmé</label>@if ($x->valide_le)<div class="el-person__sub">{{ $x->valide_le->format('d/m/Y') }}</div>@endif</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Retour</button><button class="btn btn-primary text-white" type="submit">Enregistrer</button></div>
      </form>
    </div>
  </div>
</div>
@endif

<form id="f-abandon" method="post" hidden>@csrf</form>
<script type="text/javascript">
	const U = {
		signif: "{{ route('decisions.recours.signification', ':id') }}", recours: "{{ route('decisions.recours.enregistrer', ':id') }}", transm: "{{ route('decisions.recours.transmettre', ':id') }}",
		certif: "{{ route('decisions.recours.certifier', ':id') }}", abandon: "{{ route('decisions.recours.abandonner', ':id') }}"
	};
	function ouvrir_signif(id, num, date, nature) {
		const f = $('#f-modal-signif').attr('action', U.signif.replace(':id', id))[0]; f.reset();
		$('#t-modal-signif').text('Signification : ' + num); $('#g_sig_date').val(date || ''); $('#g_sig_nature').val(nature || 'appel').trigger('change.select2');
		$('#modal-signif').modal('show');
	}
	function ouvrir_recours(id, num) { $('#f-modal-recours').attr('action', U.recours.replace(':id', id))[0].reset(); $('#t-modal-recours').text('Recours : ' + num); $('#modal-recours').modal('show'); }
	function ouvrir_transmission(id) { $('#f-modal-transmission').attr('action', U.transm.replace(':id', id))[0].reset(); $('#modal-transmission').modal('show'); }
	function ouvrir_certificat(id, num) { $('#f-modal-certificat').attr('action', U.certif.replace(':id', id))[0].reset(); $('#t-modal-certificat').text('Certificat : ' + num); $('#modal-certificat').modal('show'); }
	function abandonner_recours(id) {
		Swal.fire({ icon: 'question', title: 'Abandonner ce recours ?', text: 'Le recours ne comptera plus : un certificat pourra être délivré à l\'expiration du délai.', showCancelButton: true, confirmButtonText: 'Oui, abandonner', cancelButtonText: 'Retour' })
			.then(function (r) { if (r.isConfirmed) { $('#f-abandon').attr('action', U.abandon.replace(':id', id)).trigger('submit'); } });
	}
</script>
