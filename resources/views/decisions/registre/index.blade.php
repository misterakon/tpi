@extends('layout.master')
@section('title', 'Registre des décisions')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/audiences.css') }}?v={{ filemtime(public_path('assets/css/audiences.css')) }}">
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page au-page al-page">

      <x-hero tag="Décisions" title="Registre des décisions" subtitle="Toutes les décisions dont le texte est figé : numéro, date, chambre, signature et remises. Filtrez par année, chambre ou statut, retrouvez une décision par son numéro ou sa référence, puis exportez la liste en Excel ou en PDF." icon="fa-solid fa-book">
        <x-slot:actions>
          @if ($peutExporter)
            <a class="el-btn el-btn--ghost" id="export-xlsx" href="#"><i class="fa-solid fa-file-excel"></i> Excel</a>
            <a class="el-btn el-btn--accent" id="export-pdf" href="#"><i class="fa-solid fa-file-pdf"></i> PDF</a>
          @endif
        </x-slot:actions>
      </x-hero>

      <div class="au-kpis au-kpis--compact" id="kpis-registre" style="grid-template-columns: repeat(4, minmax(0, 1fr))"></div>

      <div class="au-cadre">
        <div class="au-compte-filtres" id="filtres-statut"></div>
        <div class="au-filtres-ligne">
          <div class="au-champ au-champ--date">
            <label for="f-annee">Année</label>
            <select class="form-select" id="f-annee" data-sans-recherche><option value="">Toutes</option>@foreach ($annees as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach</select>
          </div>
          <div class="au-champ">
            <label for="f-chambre">Chambre</label>
            <select class="form-select" id="f-chambre" data-sans-recherche><option value="">Toutes les chambres</option>@foreach ($chambres as $c)<option value="{{ $c->id }}">{{ $c->libelle }}</option>@endforeach</select>
          </div>
          <div class="au-recherche"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="f-q" placeholder="Numéro de décision, référence…" autocomplete="off" maxlength="80"></div>
          <button type="button" class="au-lien" id="f-reinit" hidden><i class="fa-solid fa-rotate-left"></i> Réinitialiser</button>
        </div>

        <div class="au-fond"><div class="au-tbl-carte">
          <table class="au-tbl">
            <colgroup><col style="width:128px"><col style="width:120px"><col><col style="width:20%"><col style="width:17%"><col style="width:128px"><col style="width:84px"><col style="width:58px"></colgroup>
            <thead><tr><th>N° de décision</th><th>Date</th><th>Référence et type</th><th>Chambre</th><th>Signature</th><th>Statut</th><th>Remises</th><th></th></tr></thead>
            <tbody id="registre-corps"></tbody>
          </table>
        </div></div>
        <div class="au-pagination" id="pagination-registre"></div>
      </div>

    </div>
  </div>
@endsection

@section('script')
  <script type="text/javascript">
  (function () {
    'use strict';
    const URL_DONNEES = @js(route('decisions.registre.donnees'));
    const URL_EXPORT = @js(route('decisions.registre.export'));
    const URL_DECISION = @js($urlDecision);
    const STATUTS = @js($statuts);
    const COULEURS = { validee: 'vert', signee: 'violet', delivree: 'orange' };
    const reduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const f = { statut: '', annee: '', chambre: '', q: '', page: 1 };
    let compteurs = {}, requete = 0, minuteur = null;

    const esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    const params = function (avecPage) { const p = new URLSearchParams(); ['statut', 'annee', 'chambre', 'q'].forEach(function (k) { if (f[k]) { p.set(k, f[k]); } }); if (avecPage) { p.set('page', f.page); } return p.toString(); };

    function kpis() {
      const total = Object.keys(STATUTS).reduce(function (a, k) { return a + (compteurs[k] || 0); }, 0);
      const cartes = [['ardoise', 'fa-book', 'Décisions au registre', total, 'fa-filter', 'avec les filtres en cours'], ['vert', 'fa-circle-check', 'Validées', compteurs.validee || 0, 'fa-hourglass-half', 'en attente de signature'], ['sarcelle', 'fa-signature', 'Signées', compteurs.signee || 0, 'fa-hashtag', 'numérotées, à délivrer'], ['orange', 'fa-paper-plane', 'Délivrées', compteurs.delivree || 0, 'fa-box-archive', 'remises aux parties']];
      $('#kpis-registre').html(cartes.map(function (k) { return '<div class="au-kpi au-kpi--' + k[0] + '"><div class="au-kpi__icone"><i class="fa-solid ' + k[1] + '"></i></div><span class="au-kpi__label">' + esc(k[2]) + '</span><div><div class="au-kpi__valeur"><span>' + k[3] + '</span></div><div class="au-chip"><i class="fa-solid ' + k[4] + '"></i><span>' + esc(k[5]) + '</span></div></div></div>'; }).join(''));
    }

    function chips() {
      const total = Object.keys(STATUTS).reduce(function (a, k) { return a + (compteurs[k] || 0); }, 0);
      $('#filtres-statut').html('<button type="button" class="au-filtre-etat ' + (f.statut === '' ? 'is-actif' : '') + '" data-s="">Toutes<b>' + total + '</b></button>' + Object.keys(STATUTS).map(function (k) { return '<button type="button" class="au-filtre-etat ' + (f.statut === k ? 'is-actif' : '') + '" data-s="' + k + '">' + esc(STATUTS[k]) + '<b>' + (compteurs[k] || 0) + '</b></button>'; }).join(''));
      $('#f-reinit').prop('hidden', !(f.statut || f.annee || f.chambre || f.q));
      const q = params(false);
      $('#export-xlsx').attr('href', URL_EXPORT + '?format=xlsx' + (q ? '&' + q : '')); $('#export-pdf').attr('href', URL_EXPORT + '?format=pdf' + (q ? '&' + q : ''));
    }

    function ligne(d) {
      const voir = URL_DECISION ? '<a class="au-iconbtn" href="' + esc(URL_DECISION.replace(/\/0$/, '/' + d.id)) + '" title="Ouvrir la décision" aria-label="Ouvrir la décision"><i class="fa-solid fa-eye"></i></a>' : '';
      return '<tr><td class="au-tbl__num">' + (d.numero ? esc(d.numero) : '<span class="au-tbl__vide">sans numéro</span>') + '</td>'
        + '<td>' + (d.date_decision ? '<span class="au-badge au-badge--date"><i class="fa-regular fa-calendar"></i>' + esc(d.date_decision) + '</span>' : '<span class="au-tbl__vide">—</span>') + '</td>'
        + '<td><div class="au-tbl__parties">' + esc(d.reference) + '</div><small>' + esc(d.type) + '</small></td><td>' + esc(d.chambre) + '</td>'
        + '<td>' + (d.date_signature ? esc(d.date_signature) : '<span class="au-tbl__vide">pas encore signée</span>') + '</td>'
        + '<td><span class="au-badge au-badge--' + COULEURS[d.statut] + '">' + esc(d.statut_libelle) + '</span></td><td>' + (d.remises ? '<b>' + d.remises + '</b>' : '<span class="au-tbl__vide">0</span>') + '</td><td class="au-tbl__act">' + voir + '</td></tr>';
    }

    function squelette() { let h = ''; for (let i = 0; i < 6; i++) { h += '<tr class="au-sq"><td><i style="width:70px"></i></td><td><i style="width:84px"></i></td><td><i style="width:80%"></i><i style="width:50%"></i></td><td><i style="width:70%"></i></td><td><i style="width:60%"></i></td><td><i style="width:80px;height:22px;border-radius:99px"></i></td><td></td><td></td></tr>'; } return h; }

    function charger() {
      const n = ++requete; $('.au-tbl-carte').addClass('is-charge'); if (!$('#registre-corps tr').length) { $('#registre-corps').html(squelette()); }
      fetch(URL_DONNEES + '?' + params(true), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { if (!r.ok) { throw new Error('Erreur'); } return r.json(); })
        .then(function (j) {
          if (n !== requete) { return; }
          compteurs = j.compteurs; kpis(); chips();
          $('#registre-corps').html(j.lignes.length ? j.lignes.map(ligne).join('') : '<tr><td colspan="8"><div class="au-vide"><i class="fa-solid fa-book"></i>Aucune décision ne correspond à ces filtres.</div></td></tr>');
          if (!j.total) { $('#pagination-registre').empty(); return; }
          const debut = (j.page - 1) * j.par_page + 1, fin = Math.min(j.total, j.page * j.par_page), pages = [];
          for (let i = 1; i <= j.pages; i++) { if (i === 1 || i === j.pages || Math.abs(i - j.page) <= 2) { pages.push(i); } }
          let nav = '<button type="button" data-p="' + (j.page - 1) + '"' + (j.page <= 1 ? ' disabled' : '') + '><i class="fa-solid fa-angle-left"></i></button>', prec = 0;
          pages.forEach(function (i) { if (i - prec > 1) { nav += '<span class="au-pagination__points">…</span>'; } nav += '<button type="button" data-p="' + i + '"' + (i === j.page ? ' class="is-actif"' : '') + '>' + i + '</button>'; prec = i; });
          nav += '<button type="button" data-p="' + (j.page + 1) + '"' + (j.page >= j.pages ? ' disabled' : '') + '><i class="fa-solid fa-angle-right"></i></button>';
          $('#pagination-registre').html('<span>' + debut + ' à ' + fin + ' sur ' + j.total + ' décision' + (j.total > 1 ? 's' : '') + '</span><nav>' + nav + '</nav>');
        })
        .catch(function () { $('#registre-corps').html('<tr><td colspan="8"><div class="au-vide"><i class="fa-solid fa-triangle-exclamation"></i>Impossible de charger le registre. Réessayez.</div></td></tr>'); })
        .then(function () { if (n === requete) { $('.au-tbl-carte').removeClass('is-charge'); } });
    }

    document.addEventListener('DOMContentLoaded', function () {
      charger();
      $('#filtres-statut').on('click', '.au-filtre-etat', function () { f.statut = this.getAttribute('data-s') || ''; f.page = 1; charger(); });
      $('#f-annee').on('change', function () { f.annee = this.value; f.page = 1; charger(); });
      $('#f-chambre').on('change', function () { f.chambre = this.value; f.page = 1; charger(); });
      $('#f-q').on('input', function () { const v = this.value; clearTimeout(minuteur); minuteur = setTimeout(function () { f.q = v.trim(); f.page = 1; charger(); }, 300); });
      $('#pagination-registre').on('click', 'button:not(:disabled)', function () { f.page = parseInt(this.getAttribute('data-p'), 10) || 1; charger(); });
      $('#f-reinit').on('click', function () { f.statut = f.annee = f.chambre = f.q = ''; f.page = 1; $('#f-annee, #f-chambre').val('').trigger('change.select2'); $('#f-q').val(''); charger(); });
    });
  })();
  </script>
@endsection
