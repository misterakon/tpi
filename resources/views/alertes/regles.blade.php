@extends('layout.master')
@section('title', "Règles d'alerte")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
@endsection

@section('main-content')
  @php
      $niveauxCouleur = ['info' => 'indigo', 'attention' => 'orange', 'critique' => 'rouge'];
  @endphp
  <div class="page-body">
    <div class="el-page au-page al-page">

      <x-hero tag="Alertes" title="Règles d'alerte" subtitle="À quel moment prévenir, qui prévenir et par quel canal. Une règle se déclenche à un nombre de jours précis avant ou après l'échéance (J-3, J+1…). Plus le retard grandit, plus on monte dans la hiérarchie." icon="fa-solid fa-sliders">
        <x-slot:actions>
          @can('delais_etape.voir')<a class="el-btn el-btn--ghost" href="{{ route('alertes.delais') }}"><i class="fa-solid fa-stopwatch"></i> Délais</a>@endcan
          <a class="el-btn el-btn--ghost" href="{{ route('alertes.destinataires') }}"><i class="fa-solid fa-users-viewfinder"></i> Destinataires</a>
          @if ($peutCreer)<button class="el-btn el-btn--accent" type="button" id="btn-nouvelle-regle"><i class="fa-solid fa-plus"></i> Nouvelle règle</button>@endif
        </x-slot:actions>
      </x-hero>

      <div class="au-cadre">
        <div class="au-fond">
          <div class="au-tbl-carte">
            <table class="au-tbl al-tbl">
              <colgroup><col style="width:92px"><col><col style="width:200px"><col style="width:26%"><col style="width:120px"><col style="width:110px"><col style="width:96px"><col style="width:112px"></colgroup>
              <thead><tr><th>Code</th><th>Règle</th><th>Quand</th><th>Qui est prévenu</th><th>Canaux</th><th>Gravité</th><th>Active</th><th>Actions</th></tr></thead>
              <tbody>
                @forelse ($regles as $r)
                  <tr id="regle-{{ $r->id }}" data-regle="{{ json_encode(['id' => $r->id, 'decalage_jours' => $r->decalage_jours, 'libelle' => $r->libelle, 'niveau' => $r->niveau, 'canaux' => $r->canaux, 'destinataires' => $r->destinataires, 'types' => $r->types ?? [], 'actif' => $r->actif]) }}">
                    <td><span class="au-badge au-badge--{{ $r->decalage_jours > 0 ? 'rouge' : ($r->decalage_jours === 0 ? 'orange' : 'sarcelle') }}">{{ $r->code }}</span></td>
                    <td><div class="au-tbl__num">{{ $r->libelle }}</div><small>{{ $r->types ? count($r->types).' type(s) d\'échéance' : 'Tous les types d\'échéance' }}</small></td>
                    <td>{{ $r->quand() }}</td>
                    <td>@foreach ($r->libellesDestinataires() as $d)<span class="al-puce">{{ $d }}</span>@endforeach</td>
                    <td>@foreach ($r->canaux as $c)<span class="al-canal" title="{{ $canaux[$c] ?? $c }}"><i class="fa-solid {{ $c === 'email' ? 'fa-envelope' : 'fa-bell' }}"></i></span>@endforeach</td>
                    <td><span class="au-badge au-badge--{{ $niveauxCouleur[$r->niveau] ?? 'gris' }}">{{ $niveaux[$r->niveau] ?? $r->niveau }}</span></td>
                    <td>
                      @if ($peutModifier)
                        <label class="al-interrupteur" title="Activer ou désactiver"><input type="checkbox" class="js-activer" data-id="{{ $r->id }}" @checked($r->actif)><span></span></label>
                      @else
                        <span class="au-badge au-badge--{{ $r->actif ? 'vert' : 'gris' }}">{{ $r->actif ? 'Oui' : 'Non' }}</span>
                      @endif
                    </td>
                    <td class="al-tbl__actions">
                      @if ($peutModifier)<button type="button" class="au-iconbtn js-modifier" data-id="{{ $r->id }}" title="Modifier" aria-label="Modifier"><i class="fa-solid fa-pen"></i></button>@endif
                      @if ($peutSupprimer)<button type="button" class="au-iconbtn au-iconbtn--rouge js-supprimer" data-id="{{ $r->id }}" title="Supprimer" aria-label="Supprimer"><i class="fa-regular fa-trash-can"></i></button>@endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="8"><div class="au-vide"><i class="fa-solid fa-sliders"></i>Aucune règle. Créez-en une pour que des alertes partent.</div></td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
  @if ($peutCreer || $peutModifier)
    @include('alertes.form_regle', ['destinataires' => $destinataires, 'canaux' => $canaux, 'niveaux' => $niveaux, 'types' => $types])
  @endif
@endsection

@section('script')
  <script type="text/javascript">
  (function () {
    'use strict';
    const URL_CREER = @js(route('alertes.regles.store'));
    const URL_MAJ = @js(route('alertes.regles.update', 0));
    const URL_ACTIVER = @js(route('alertes.regles.activer', 0));
    const URL_SUPPR = @js(route('alertes.regles.delete', 0));
    const CSRF = @js(csrf_token());
    let editee = null;

    const esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    const toast = function (icone, titre) { if (window.Swal) { Swal.fire({ toast: true, position: 'top-end', icon: icone, title: titre, showConfirmButton: false, timer: 3000, timerProgressBar: true }); } };
    function appel(url, methode, corps) {
      return fetch(url, { method: methode, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }, body: corps ? JSON.stringify(corps) : undefined })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { throw new Error((j.errors ? Object.values(j.errors)[0][0] : j.message) || 'Erreur'); } return j; }); });
    }
    const codeDe = function (n) { n = parseInt(n, 10); return isNaN(n) ? '' : (n === 0 ? 'J0' : (n < 0 ? 'J' + n : 'J+' + n)); };

    function ouvrir(regle) {
      editee = regle ? regle.id : null; const $f = $('#form-regle');
      $f[0].reset(); $('#erreur-regle').empty();
      $('#titre-modal-regle').text(regle ? 'Modifier la règle ' + codeDe(regle.decalage_jours) : "Nouvelle règle d'alerte");
      $('#f_regle_decalage').val(regle ? regle.decalage_jours : ''); $('#f_regle_libelle').val(regle ? regle.libelle : ''); $('#f_regle_niveau').val(regle ? regle.niveau : 'attention').trigger('change.select2');
      $f.find('input[type=checkbox][name^="canaux"], input[type=checkbox][name^="destinataires"], input[type=checkbox][name^="types"]').prop('checked', false);
      if (regle) {
        (regle.canaux || []).forEach(function (c) { $f.find('input[name="canaux[]"][value="' + c + '"]').prop('checked', true); });
        (regle.destinataires || []).forEach(function (c) { $f.find('input[name="destinataires[]"][value="' + c + '"]').prop('checked', true); });
        (regle.types || []).forEach(function (c) { $f.find('input[name="types[]"][value="' + c + '"]').prop('checked', true); });
      } else { $f.find('input[name="canaux[]"][value="application"]').prop('checked', true); }
      $('#f_regle_actif').prop('checked', regle ? !!regle.actif : true); apercu();
      $('#modal-regle').modal('show');
    }
    function apercu() { const c = codeDe($('#f_regle_decalage').val()); $('#apercu-code').text(c ? 'Code de la règle : ' + c : 'Le code se déduit du décalage (J-3, J0, J+7).'); }

    document.addEventListener('DOMContentLoaded', function () {
      $('#btn-nouvelle-regle').on('click', function () { ouvrir(null); });
      $('#f_regle_decalage').on('input', apercu);
      $(document).on('click', '.js-modifier', function () { ouvrir(JSON.parse($('#regle-' + this.getAttribute('data-id')).attr('data-regle'))); });
      $(document).on('change', '.js-activer', function () {
        const $c = $(this), id = this.getAttribute('data-id');
        appel(URL_ACTIVER.replace(/\/0\/activer$/, '/' + id + '/activer'), 'POST').then(function (j) { toast('success', j.message); }).catch(function (e) { $c.prop('checked', !$c.prop('checked')); toast('error', e.message); });
      });
      $(document).on('click', '.js-supprimer', function () {
        const id = this.getAttribute('data-id'), r = JSON.parse($('#regle-' + id).attr('data-regle'));
        Swal.fire({ icon: 'warning', title: 'Supprimer la règle ' + codeDe(r.decalage_jours) + ' ?', text: 'Elle n\'enverra plus aucune alerte. Vous pouvez aussi la désactiver pour la garder.', showCancelButton: true, confirmButtonText: 'Supprimer', cancelButtonText: 'Annuler', confirmButtonColor: '#B42318' }).then(function (x) {
          if (!x.isConfirmed) { return; }
          appel(URL_SUPPR.replace(/\/0$/, '/' + id), 'DELETE').then(function (j) { toast('success', j.message); $('#regle-' + id).fadeOut(250, function () { $(this).remove(); }); }).catch(function (e) { toast('error', e.message); });
        });
      });
      $('#form-regle').on('submit', function (e) {
        e.preventDefault(); const $f = $(this), checked = function (n) { return $f.find('input[name="' + n + '[]"]:checked').map(function () { return this.value; }).get(); };
        const corps = { decalage_jours: $('#f_regle_decalage').val(), libelle: $('#f_regle_libelle').val(), niveau: $('#f_regle_niveau').val(), canaux: checked('canaux'), destinataires: checked('destinataires'), types: checked('types'), actif: $('#f_regle_actif').prop('checked') ? 1 : 0 };
        $('#erreur-regle').empty(); $('#valider-regle').prop('disabled', true);
        appel(editee ? URL_MAJ.replace(/\/0$/, '/' + editee) : URL_CREER, 'POST', corps).then(function (j) { $('#modal-regle').modal('hide'); toast('success', j.message); setTimeout(function () { location.reload(); }, 600); })
          .catch(function (er) { $('#erreur-regle').html('<div class="au-erreur"><i class="fa-solid fa-circle-exclamation"></i> ' + esc(er.message) + '</div>'); }).then(function () { $('#valider-regle').prop('disabled', false); });
      });
    });
  })();
  </script>
@endsection
