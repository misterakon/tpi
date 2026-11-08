/*
 * Vues des tâches : Matrice, Calendrier (FullCalendar), Gantt
 * Les données arrivent dans la page en JSON (<script type="application/json" id="donnees-...">).
 * Chaque bloc ne s'active que si son conteneur existe : le fichier peut être chargé sur les trois pages.
 */
(function () {
  'use strict';

  var MOIS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
  var MOIS_COURTS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
  var JOURS = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

  /* ---------------------------------------------------------------- utilitaires */
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function lireJson(id) {
    var el = document.getElementById(id);
    if (!el) return null;
    try { return JSON.parse(el.textContent); } catch (e) { return null; }
  }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  // Jours entiers depuis 1970 (UTC) : évite tout décalage de fuseau horaire
  function jourDepuisTexte(t) { var p = t.split('-'); return Date.UTC(+p[0], +p[1] - 1, +p[2]) / 86400000; }
  function dateDepuisJour(n) { return new Date(n * 86400000); }

  /* ---------------------------------------------------------------- infobulle */
  var tipEl = null;
  function placerTip(evt) {
    if (!tipEl) return;
    var x = evt.clientX + 16, y = evt.clientY + 16;
    var w = tipEl.offsetWidth, h = tipEl.offsetHeight;
    if (x + w > window.innerWidth - 8) x = evt.clientX - w - 16;
    if (y + h > window.innerHeight - 8) y = evt.clientY - h - 16;
    tipEl.style.left = Math.max(8, x) + 'px';
    tipEl.style.top = Math.max(8, y) + 'px';
  }
  function montrerTip(html, evt) {
    if (!tipEl) {
      tipEl = document.createElement('div');
      tipEl.className = 'vt-tip';
      document.body.appendChild(tipEl);
    }
    tipEl.innerHTML = html;
    tipEl.style.display = 'block';
    placerTip(evt);
  }
  function cacherTip() { if (tipEl) tipEl.style.display = 'none'; }

  // p : { code, libelle, ton, statut, avancement, debut, fin, responsables }
  function contenuTache(p) {
    var h = '<span class="tl-badge tl-' + esc(p.ton) + '">' + esc(p.statut) + '</span>';
    h += '<strong>' + esc(p.code) + ' — ' + esc(p.libelle) + '</strong>';
    if (p.debut) h += '<div class="vt-tip__ligne"><span>Début</span><span>' + esc(p.debut) + '</span></div>';
    if (p.fin) h += '<div class="vt-tip__ligne"><span>Échéance</span><span>' + esc(p.fin) + '</span></div>';
    h += '<div class="vt-tip__ligne"><span>Avancement</span><span>' + esc(p.avancement) + ' %</span></div>';
    if (p.sousTaches) h += '<div class="vt-tip__ligne"><span>Sous-tâches faites</span><span>' + esc(p.sousTaches) + '</span></div>';
    if (p.responsables) h += '<div class="vt-tip__ligne"><span>Responsable(s)</span><span>' + esc(p.responsables) + '</span></div>';
    return h;
  }

  /* ================================================================ MATRICE */
  function initMatrice() {
    var racine = document.getElementById('matrice-taches');
    if (!racine) return;
    var axes = Array.prototype.slice.call(racine.querySelectorAll('.mx-axe'));

    racine.addEventListener('click', function (e) {
      var tete = e.target.closest('[data-mx-toggle]');
      if (!tete) return;
      e.preventDefault();
      tete.closest('.mx-axe').classList.toggle('mx-ferme');
    });

    document.querySelectorAll('[data-mx-tout]').forEach(function (b) {
      b.addEventListener('click', function () {
        var fermer = b.dataset.mxTout === 'fermer';
        axes.forEach(function (a) { a.classList.toggle('mx-ferme', fermer); });
      });
    });

    var champ = document.getElementById('mx-recherche');
    var aucun = document.getElementById('mx-aucun');
    if (!champ) return;

    champ.addEventListener('input', function () {
      var q = champ.value.trim().toLowerCase();
      var total = 0;

      axes.forEach(function (axe) {
        var lignes = Array.prototype.slice.call(axe.querySelectorAll('tbody tr'));
        var visiblesAxe = 0;
        var sousAm = false, sousAc = false;

        // Parcours à l'envers : un groupe reste affiché si une tâche visible le suit
        for (var i = lignes.length - 1; i >= 0; i--) {
          var tr = lignes[i];
          if (tr.classList.contains('mx-ligne-tache')) {
            var ok = !q || (tr.dataset.recherche || '').indexOf(q) !== -1;
            tr.style.display = ok ? '' : 'none';
            if (ok) { sousAm = true; sousAc = true; visiblesAxe++; }
          } else if (tr.classList.contains('mx-ligne-am')) {
            tr.style.display = (!q || sousAm) ? '' : 'none';
            sousAm = false;
          } else if (tr.classList.contains('mx-ligne-ac')) {
            tr.style.display = (!q || sousAc) ? '' : 'none';
            sousAc = false;
          }
        }

        axe.style.display = (!q || visiblesAxe > 0) ? '' : 'none';
        if (q && visiblesAxe > 0) axe.classList.remove('mx-ferme');
        total += visiblesAxe;
      });

      if (aucun) aucun.style.display = (q && total === 0) ? '' : 'none';
    });
  }

  /* ================================================================ CALENDRIER */
  function initCalendrier() {
    var el = document.getElementById('calendrier-taches');
    var evenements = lireJson('donnees-calendrier');
    if (!el || !evenements || !window.FullCalendar) return;

    var titre = document.getElementById('cal-titre');

    var cal = new FullCalendar.Calendar(el, {
      initialView: 'dayGridMonth',
      headerToolbar: false,          // barre d'outils dessinée dans la page (en français)
      height: 'auto',
      firstDay: 1,
      locale: 'fr',
      fixedWeekCount: false,
      dayMaxEvents: 3,
      displayEventTime: false,
      events: evenements,

      dayHeaderContent: function (a) {
        var j = JOURS[a.date.getDay()];
        return a.view.type === 'dayGridMonth' ? j : j + ' ' + a.date.getDate();
      },
      moreLinkContent: function (a) { return '+' + a.num + ' autre' + (a.num > 1 ? 's' : ''); },

      eventContent: function (arg) {
        var p = arg.event.extendedProps;
        var n = document.createElement('div');
        n.className = 'cal-ev';
        n.innerHTML = '<span class="cal-ev__code">' + esc(p.code) + '</span><span class="cal-ev__lib">' + esc(p.libelle) + '</span>';
        return { domNodes: [n] };
      },
      eventDidMount: function (info) {
        var p = info.event.extendedProps;
        info.el.addEventListener('mousemove', function (e) { montrerTip(contenuTache(p), e); });
        info.el.addEventListener('mouseleave', cacherTip);
      },
      eventClick: function (info) {
        info.jsEvent.preventDefault();
        cacherTip();
        window.location.href = info.event.extendedProps.url;
      },

      datesSet: function (info) {
        if (titre) {
          if (info.view.type === 'dayGridMonth') {
            var d = info.view.currentStart;
            titre.textContent = MOIS[d.getMonth()] + ' ' + d.getFullYear();
          } else {
            var a = info.start, b = new Date(info.end.getTime() - 86400000);
            titre.textContent = a.getDate() + ' ' + MOIS_COURTS[a.getMonth()] + ' – ' + b.getDate() + ' ' + MOIS_COURTS[b.getMonth()] + ' ' + b.getFullYear();
          }
        }
        document.querySelectorAll('[data-cal-vue]').forEach(function (b) {
          b.classList.toggle('actif', b.dataset.calVue === info.view.type);
        });
      }
    });
    cal.render();

    document.querySelectorAll('[data-cal]').forEach(function (b) {
      b.addEventListener('click', function () {
        var a = b.dataset.cal;
        if (a === 'prev') cal.prev(); else if (a === 'next') cal.next(); else cal.today();
      });
    });
    document.querySelectorAll('[data-cal-vue]').forEach(function (b) {
      b.addEventListener('click', function () { cal.changeView(b.dataset.calVue); });
    });

    // Filtres par statut
    document.querySelectorAll('[data-cal-ton]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var visibles = {};
        document.querySelectorAll('[data-cal-ton]').forEach(function (c) { visibles[c.dataset.calTon] = c.checked; });
        cal.getEvents().forEach(function (e) {
          e.setProp('display', visibles[e.extendedProps.ton] === false ? 'none' : 'auto');
        });
      });
    });
  }

  /* ================================================================ GANTT */
  function initGantt() {
    var racine = document.getElementById('gantt-taches');
    var data = lireJson('donnees-gantt');
    if (!racine || !data || !data.groupes || !data.groupes.length) return;

    var PX = { jour: 34, semaine: 12, mois: 4 };   // pixels par jour selon le zoom
    var etat = { zoom: 'semaine', replies: {}, repliesAc: {} };
    function cleAc(g, ac) { return g.id + '-' + ac.id; }

    // Préparation : jours entiers, plage totale
    var taches = {};
    var min = Infinity, max = -Infinity;
    data.groupes.forEach(function (g) {
      g.taches.forEach(function (t) {
        t._d = jourDepuisTexte(t.debut);
        t._f = jourDepuisTexte(t.fin);
        taches[t.id] = t;
        min = Math.min(min, t._d);
        max = Math.max(max, t._f);
      });
      // Bornes de l'axe et de chacune de ses actions clés (barres de synthèse)
      var bornes = function (liste, o) {
        o._d = Math.min.apply(null, liste.map(function (x) { return x._d; }));
        o._f = Math.max.apply(null, liste.map(function (x) { return x._f; }));
      };
      bornes(g.taches, g);
      (g.actionsCles || []).forEach(function (ac) {
        ac.taches = g.taches.filter(function (t) { return String(t.acId) === String(ac.id); });
        bornes(ac.taches, ac);
      });
    });
    var auj = jourDepuisTexte(data.aujourdhui);
    min = Math.min(min, auj);
    max = Math.max(max, auj);

    var dMin = dateDepuisJour(min), dMax = dateDepuisJour(max);
    var debut = Date.UTC(dMin.getUTCFullYear(), dMin.getUTCMonth(), 1) / 86400000;     // 1er du 1er mois
    var fin = Date.UTC(dMax.getUTCFullYear(), dMax.getUTCMonth() + 1, 0) / 86400000;   // dernier jour du dernier mois
    var nbJours = fin - debut + 1;

    function variableCss(nom, defaut) {
      return parseInt(getComputedStyle(racine).getPropertyValue(nom), 10) || defaut;
    }

    function render() {
      var px = PX[etat.zoom];
      var largeur = nbJours * px;
      var label = variableCss('--label', 330);
      var H = variableCss('--h', 46);
      var h = [];

      h.push('<div class="gt-canvas" style="width:' + (label + largeur) + 'px">');

      /* ---- en-tête : mois puis jours ou semaines ---- */
      h.push('<div class="gt-entete"><div class="gt-coin">Tâches</div><div class="gt-echelle" style="width:' + largeur + 'px">');
      h.push('<div class="gt-mois">');
      var cur = debut;
      while (cur <= fin) {
        var d = dateDepuisJour(cur);
        var y = d.getUTCFullYear(), m = d.getUTCMonth();
        var jm = new Date(Date.UTC(y, m + 1, 0)).getUTCDate();
        var w = jm * px;
        h.push('<div style="width:' + w + 'px">' + (w >= 120 ? MOIS[m] + ' ' + y : MOIS_COURTS[m] + ' ' + String(y).slice(2)) + '</div>');
        cur += jm;
      }
      h.push('</div>');

      if (etat.zoom === 'jour') {
        h.push('<div class="gt-jours">');
        for (var j = debut; j <= fin; j++) {
          var dj = dateDepuisJour(j);
          var wk = dj.getUTCDay() === 0 || dj.getUTCDay() === 6;
          h.push('<div class="' + (wk ? 'we ' : '') + (j === auj ? 'auj' : '') + '" style="width:' + px + 'px">' + dj.getUTCDate() + '</div>');
        }
        h.push('</div>');
      } else if (etat.zoom === 'semaine') {
        h.push('<div class="gt-jours">');
        var s = debut;
        while (s <= fin) {
          var ds = dateDepuisJour(s);
          var dow = (ds.getUTCDay() + 6) % 7;                   // lundi = 0
          var longueur = Math.min(7 - dow, fin - s + 1);
          var contientAuj = auj >= s && auj < s + longueur;
          h.push('<div class="' + (contientAuj ? 'auj' : '') + '" style="width:' + (longueur * px) + 'px">' + pad(ds.getUTCDate()) + '/' + pad(ds.getUTCMonth() + 1) + '</div>');
          s += longueur;
        }
        h.push('</div>');
      }
      h.push('</div></div>');

      /* ---- lignes visibles (groupes puis tâches) ---- */
      var lignes = [];
      data.groupes.forEach(function (g) {
        lignes.push({ g: g });
        if (etat.replies[g.id]) return;
        g.actionsCles.forEach(function (ac) {
          lignes.push({ ac: ac, g: g });
          if (!etat.repliesAc[cleAc(g, ac)]) ac.taches.forEach(function (t) { lignes.push({ t: t }); });
        });
      });
      var hauteurCorps = lignes.length * H;

      h.push('<div class="gt-corps">');

      /* grille : week-ends, débuts de mois, aujourd'hui */
      h.push('<div class="gt-grille" style="left:' + label + 'px;width:' + largeur + 'px">');
      if (px >= 10) {
        for (var k = debut; k <= fin; k++) {
          var wd = dateDepuisJour(k).getUTCDay();
          if (wd === 0 || wd === 6) h.push('<i class="we" style="left:' + ((k - debut) * px) + 'px;width:' + px + 'px"></i>');
        }
      }
      var c2 = debut;
      while (c2 <= fin) {
        var d2 = dateDepuisJour(c2);
        h.push('<i class="mois" style="left:' + ((c2 - debut) * px) + 'px"></i>');
        c2 += new Date(Date.UTC(d2.getUTCFullYear(), d2.getUTCMonth() + 1, 0)).getUTCDate();
      }
      h.push('<i class="auj" style="left:' + ((auj - debut) * px + px / 2) + 'px"></i>');
      h.push('</div>');

      /* lignes */
      // Pastilles : tâches en retard / en retard non justifiées
      function pastilles(s) {
        return (s.retards ? '<span class="gt-bdg gt-bdg--retard" title="' + s.retards + ' en retard">' + s.retards + '</span>' : '') +
               (s.nonJustifies ? '<span class="gt-bdg gt-bdg--nj" title="' + s.nonJustifies + ' en retard non justifié(s)">' + s.nonJustifies + '</span>' : '');
      }
      // Piste d'une ligne de synthèse (axe ou action clé) : barre min-max remplie selon l'avancement moyen
      function pisteResume(o) {
        var l0 = (o._d - debut) * px, w0 = Math.max((o._f - o._d + 1) * px, 6), p = o.stats.avancement;
        return '<div class="gt-piste" style="width:' + largeur + 'px"><div class="gt-resume-barre" style="left:' + l0 + 'px;width:' + w0 + 'px">' +
               '<span class="gt-resume-prog" style="width:' + p + '%"></span></div>' +
               '<span class="gt-pct" style="left:' + (l0 + w0 + 8) + 'px">' + p + '%</span></div>';
      }
      var CHEVRON = '<button type="button" class="gt-plier" aria-label="Plier / déplier"><i class="fa-solid fa-chevron-down"></i></button>';

      lignes.forEach(function (l) {
        if (l.t) {
          var t = l.t;
          var left = (t._d - debut) * px;
          var w2 = Math.max((t._f - t._d + 1) * px, 6);
          h.push('<div class="gt-ligne gt-ligne--tache tl-' + t.ton + '" data-id="' + t.id + '">');
          h.push('<div class="gt-label"><span class="gt-code">' + esc(t.code) + '</span><span class="gt-lib">' + esc(t.libelle) + '</span></div>');
          h.push('<div class="gt-piste" style="width:' + largeur + 'px">');
          if (t.jalon) {
            h.push('<div class="gt-jalon" style="left:' + (left + px / 2 - 9) + 'px"></div>');
            h.push('<span class="gt-pct" style="left:' + (left + px / 2 + 18) + 'px">' + t.avancement + '%' + (t.sousTaches ? ' · ' + t.sousTaches : '') + '</span>');
          } else {
            h.push('<div class="gt-barre" style="left:' + left + 'px;width:' + w2 + 'px"><span class="gt-barre__prog" style="width:' + t.avancement + '%"></span></div>');
            h.push('<span class="gt-pct" style="left:' + (left + w2 + 8) + 'px">' + t.avancement + '%' + (t.sousTaches ? ' · ' + t.sousTaches : '') + '</span>');
          }
          h.push('</div></div>');
        } else if (l.ac) {
          var ac = l.ac, kac = cleAc(l.g, ac);
          h.push('<div class="gt-ligne gt-ligne--ac ax-' + l.g.teinte + (etat.repliesAc[kac] ? ' gt-replie' : '') + '" data-ac="' + esc(kac) + '">');
          h.push('<div class="gt-label">' + CHEVRON +
                 '<span class="gt-ac-code">' + esc(ac.code) + '</span>' +
                 '<span class="gt-ac-titre" title="' + esc(ac.libelle) + '">' + esc(ac.libelle) + '</span>' +
                 pastilles(ac.stats) + '<span class="gt-axe-n">' + ac.stats.nb + '</span></div>');
          h.push(pisteResume(ac));
          h.push('</div>');
        } else {
          var g = l.g;
          h.push('<div class="gt-ligne gt-ligne--groupe ax-' + g.teinte + (etat.replies[g.id] ? ' gt-replie' : '') + '" data-groupe="' + g.id + '">');
          h.push('<div class="gt-label">' + CHEVRON +
                 '<span class="gt-axe-code">' + esc(g.code) + '</span>' +
                 (data.plusieursExercices && g.exercice ? '<span class="gt-axe-an">' + esc(g.exercice) + '</span>' : '') +
                 '<span class="gt-axe-titre" title="' + esc(g.libelle) + '">' + esc(g.libelle) + '</span>' +
                 pastilles(g.stats) + '<span class="gt-axe-n">' + g.stats.nb + '</span></div>');
          h.push(pisteResume(g));
          h.push('</div>');
        }
      });

      /* dépendances : flèches de la fin d'une tâche au début de la suivante */
      var index = {};
      lignes.forEach(function (l, i) { if (l.t) index[l.t.id] = i; });
      var svg = ['<svg class="gt-liens" style="left:' + label + 'px;width:' + largeur + 'px;height:' + hauteurCorps + 'px">',
        '<defs><marker id="gt-fleche" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="7" markerHeight="7" orient="auto"><path d="M0,0 L8,4 L0,8 z"/></marker></defs>'];
      lignes.forEach(function (l) {
        if (!l.t) return;
        (l.t.deps || []).forEach(function (pid) {
          var p = taches[pid];
          if (!p || index[pid] === undefined) return;
          var x1 = (p._f - debut + 1) * px, y1 = index[pid] * H + H / 2;
          var x2 = (l.t._d - debut) * px, y2 = index[l.t.id] * H + H / 2;
          var d3 = x2 >= x1 + 14
            ? 'M' + x1 + ' ' + y1 + ' H' + (x1 + 7) + ' V' + y2 + ' H' + (x2 - 2)
            : 'M' + x1 + ' ' + y1 + ' H' + (x1 + 7) + ' V' + (y1 + (y2 > y1 ? H / 2 : -H / 2)) + ' H' + (x2 - 9) + ' V' + y2 + ' H' + (x2 - 2);
          svg.push('<path d="' + d3 + '" marker-end="url(#gt-fleche)"/>');
        });
      });
      svg.push('</svg>');
      h.push(svg.join(''));

      h.push('</div></div>');
      racine.innerHTML = h.join('');
    }

    function versAujourdhui() {
      var px = PX[etat.zoom], label = variableCss('--label', 330);
      racine.scrollLeft = Math.max(0, (auj - debut) * px - (racine.clientWidth - label) / 2);
    }

    function changerZoom(zoom) {
      var label = variableCss('--label', 330);
      var centre = debut + (racine.scrollLeft + (racine.clientWidth - label) / 2) / PX[etat.zoom];   // jour au centre
      etat.zoom = zoom;
      render();
      racine.scrollLeft = Math.max(0, (centre - debut) * PX[zoom] - (racine.clientWidth - label) / 2);
      document.querySelectorAll('[data-gt-zoom]').forEach(function (b) { b.classList.toggle('actif', b.dataset.gtZoom === zoom); });
    }

    /* ---- interactions ---- */
    racine.addEventListener('click', function (e) {
      var groupe = e.target.closest('.gt-ligne--groupe');
      if (groupe && e.target.closest('.gt-label')) {
        var id = groupe.dataset.groupe;
        etat.replies[id] = !etat.replies[id];
        render();
        return;
      }
      var ligneAc = e.target.closest('.gt-ligne--ac');
      if (ligneAc && e.target.closest('.gt-label')) {
        var kac = ligneAc.dataset.ac;
        etat.repliesAc[kac] = !etat.repliesAc[kac];
        render();
        return;
      }
      var cible = e.target.closest('.gt-barre, .gt-jalon, .gt-label');
      var ligne = cible && cible.closest('.gt-ligne[data-id]');
      if (ligne && taches[ligne.dataset.id]) window.location.href = taches[ligne.dataset.id].url;
    });
    racine.addEventListener('mousemove', function (e) {
      var cible = e.target.closest('.gt-barre, .gt-jalon, .gt-label');
      var ligne = cible && cible.closest('.gt-ligne[data-id]');
      var t = ligne && taches[ligne.dataset.id];
      if (!t) { cacherTip(); return; }
      montrerTip(contenuTache({
        code: t.code, libelle: t.libelle, ton: t.ton, statut: t.statut, avancement: t.avancement, sousTaches: t.sousTaches,
        debut: t.debutTxt, fin: t.finTxt, responsables: t.responsables
      }), e);
    });
    racine.addEventListener('mouseleave', cacherTip);

    document.querySelectorAll('[data-gt-zoom]').forEach(function (b) {
      b.addEventListener('click', function () { changerZoom(b.dataset.gtZoom); });
    });
    var btnAuj = document.querySelector('[data-gt-aujourdhui]');
    if (btnAuj) btnAuj.addEventListener('click', versAujourdhui);
    // Niveau de détail : 1 = axes, 2 = + actions clés, 3 = + tâches
    document.querySelectorAll('[data-gt-niveau]').forEach(function (b) {
      b.addEventListener('click', function () {
        var n = parseInt(b.dataset.gtNiveau, 10);
        data.groupes.forEach(function (g) {
          etat.replies[g.id] = n === 1;
          g.actionsCles.forEach(function (ac) { etat.repliesAc[cleAc(g, ac)] = n === 2; });
        });
        render();
        document.querySelectorAll('[data-gt-niveau]').forEach(function (x) { x.classList.toggle('actif', x === b); });
      });
    });

    // Export PDF : impression du seul diagramme (échelle "mois", ajustée à la largeur d'une page A4 paysage)
    var btnExport = document.querySelector('[data-gt-imprimer]');
    if (btnExport) btnExport.addEventListener('click', function () {
      var zoomAvant = etat.zoom;
      var restaurer = function () {
        window.removeEventListener('afterprint', restaurer);
        document.body.classList.remove('gt-impression');
        racine.style.removeProperty('--gt-echelle');
        if (etat.zoom !== zoomAvant) changerZoom(zoomAvant);
      };
      changerZoom('mois');
      document.body.classList.add('gt-impression');
      racine.style.setProperty('--gt-echelle', Math.min(1, 1040 / racine.querySelector('.gt-canvas').offsetWidth));
      window.addEventListener('afterprint', restaurer);
      window.print();
    });

    render();
    versAujourdhui();
  }

  function init() {
    initMatrice();
    initCalendrier();
    initGantt();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
