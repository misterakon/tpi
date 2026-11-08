<!-- latest jquery-->
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/js/jquery-3.5.1.min.js') }}"></script>
<!-- feather icon js-->
<script src="{{ asset('assets/js/icons/feather-icon/feather.min.js') }}"></script>
<script src="{{ asset('assets/js/icons/feather-icon/feather-icon.js') }}"></script>
<!-- scrollbar js-->
<script src="{{ asset('assets/js/scrollbar/simplebar.min.js') }}"></script>
<script src="{{ asset('assets/js/scrollbar/custom.js') }}"></script>
<!-- Sidebar jquery-->
<script src="{{ asset('assets/js/config.js') }}"></script>
<script src="{{ asset('assets/js/modalpage/custom-modal.js') }}"> </script>
<!-- Plugins JS start-->
<script src="{{ asset('assets/js/sidebar-menu.js') }}"></script>
<script src="{{ asset('assets/js/sidebar-pin.js') }}"></script>
<script src="{{ asset('assets/js/slick/slick.min.js') }}"></script>
<script src="{{ asset('assets/js/slick/slick.js') }}"></script>
<script src="{{ asset('assets/js/header-slick.js') }}"></script>

<script src="{{ asset('assets/js/datatable/datatables/jquery.dataTables.min.js') }}"></script>

<script src="{{ asset('assets/js/prism/prism.min.js') }}"></script>
<script src="{{ asset('assets/js/clipboard/clipboard.min.js') }}"></script>
<script src="{{ asset('assets/js/custom-card/custom-card.js') }}"></script>
<script src="{{ asset('assets/js/sweet-alert/sweetalert.min.js') }}"></script>
<script src="{{ asset('assets/js/sweet-alert/app.js') }}"></script>
<script src="{{ asset('assets/js/height-equal.js') }}"></script>

<!-- tooltip JS Ends-->
<script src="{{ asset('assets/js/tooltip-init.js') }}"></script>
<!-- Plugins JS Ends-->
<!-- Theme js-->
<script src="{{ asset('assets/js/script1.js') }}"></script>
<script src="{{ asset('assets/js/theme-customizer/customizer.js') }}"></script>
<script src="{{ asset('assets/js/script.js') }}"></script>

{{-- Réglage « lignes par page » (page Réglages) : lu par les tableaux --}}
<script>window.TPI_LIGNES_PAR_PAGE = {{ (int) config('tpi.lignes_par_page') }};</script>

{{-- Chargements partout : barre de progression, boutons, liens --}}
<script src="{{ asset('assets/js/elouange-ui.js') }}"></script>
<script src="{{ asset('assets/js/select2/select2.full.min.js') }}"></script>
<script src="{{ asset('assets/js/select2-init.js') }}?v={{ filemtime(public_path('assets/js/select2-init.js')) }}"></script>

{{-- Jeton CSRF pour tous les appels AJAX --}}
<script>
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
</script>

{{-- Verrouillage de session : le navigateur verrouille l'écran dès que le délai d'inactivité est atteint
     (sans attendre un clic), signale régulièrement que l'utilisateur est actif, et réagit si le serveur
     répond « verrouillé » (code 423). --}}
@auth
<script>
(function () {
    var LIMITE = {{ (int) config('securite.inactivite_minutes') }} * 60 * 1000;   // inactivité avant verrouillage
    var AVERT = Math.min({{ (int) config('securite.avertissement_secondes') }} * 1000, Math.max(0, LIMITE - 10000));   // compte à rebours avant verrouillage (0 = sans avertissement)
    var PING_TOUS = 5 * 60 * 1000;                                                  // signal d'activité au serveur
    var URL_VERROU = @js(route('verrouillage.lock'));
    var URL_PING = @js(route('session.ping'));
    var JETON = $('meta[name="csrf-token"]').attr('content');
    var CLE = 'tpi_derniere_activite';   // partagée entre les onglets ouverts
    var dernierePing = Date.now(), verrouille = false, local = Date.now(), avertissement = null;

    function lire() { try { return parseInt(localStorage.getItem(CLE), 10) || local; } catch (e) { return local; } }
    function noter() { local = Date.now(); try { localStorage.setItem(CLE, String(local)); } catch (e) {} }

    function limiter(f, ms) { var t = 0; return function () { var n = Date.now(); if (n - t > ms) { t = n; f(); } }; }
    // Pendant l'avertissement, seul le bouton « Rester connecté » compte comme une activité.
    var noterLimite = limiter(function () { if (!avertissement) { noter(); } }, 3000);
    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (ev) {
        window.addEventListener(ev, noterLimite, { passive: true });
    });
    noter();

    // Fenêtre d'avertissement avec compte à rebours (créée à la demande).
    var CIRC = 2 * Math.PI * 52;
    function afficherAvertissement(reste) {
        if (!avertissement) {
            var fond = document.createElement('div');
            fond.className = 'tp-inactif';
            fond.innerHTML =
                '<div class="tp-inactif__carte" role="alertdialog" aria-modal="true" aria-labelledby="tp-inactif-titre" aria-describedby="tp-inactif-texte">' +
                '<div class="tp-inactif__cercle"><svg viewBox="0 0 120 120" aria-hidden="true"><circle class="tp-inactif__piste" cx="60" cy="60" r="52"/><circle class="tp-inactif__arc" cx="60" cy="60" r="52" stroke-dasharray="' + CIRC + '"/></svg>' +
                '<div class="tp-inactif__valeur"><strong data-tp="reste"></strong><span data-tp="unite"></span></div></div>' +
                '<h2 id="tp-inactif-titre">Êtes-vous toujours là ?</h2>' +
                '<p id="tp-inactif-texte">Faute d\'activité, votre session va être verrouillée dans quelques secondes. Cliquez pour continuer à travailler.</p>' +
                '<button type="button" class="tp-inactif__rester" data-tp="rester"><i class="fa-solid fa-hand-pointer"></i> Rester connecté</button>' +
                '<button type="button" class="tp-inactif__verrou" data-tp="verrou"><i class="fa-solid fa-lock"></i> Verrouiller maintenant</button></div>';
            document.body.appendChild(fond);
            fond.querySelector('[data-tp="rester"]').addEventListener('click', resterConnecte);
            fond.querySelector('[data-tp="verrou"]').addEventListener('click', verrouiller);
            avertissement = fond;
            fond.querySelector('[data-tp="rester"]').focus();
        }
        avertissement.querySelector('[data-tp="reste"]').textContent = reste;
        avertissement.querySelector('[data-tp="unite"]').textContent = reste > 1 ? 'secondes' : 'seconde';
        avertissement.querySelector('.tp-inactif__arc').style.strokeDashoffset = CIRC * (1 - Math.min(1, reste * 1000 / AVERT));
    }
    function masquerAvertissement() {
        if (avertissement) { avertissement.remove(); avertissement = null; }
    }
    function resterConnecte() {
        masquerAvertissement();
        noter();
        dernierePing = Date.now();
        fetch(URL_PING, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': JETON, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    }

    function aller(url) { verrouille = true; window.location.href = url; }

    function verrouiller() {
        if (verrouille) { return; }
        verrouille = true;
        var donnees = new FormData();
        donnees.append('_token', JETON); donnees.append('auto', '1'); donnees.append('retour', window.location.href);
        fetch(URL_VERROU, { method: 'POST', body: donnees, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (j) { window.location.href = j.redirect; })
            .catch(function () { window.location.reload(); });
    }

    function signaler() {
        var n = Date.now();
        if (n - lire() > PING_TOUS || n - dernierePing < PING_TOUS) { return; }   // inactif, ou signal déjà envoyé récemment
        dernierePing = n;
        fetch(URL_PING, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': JETON, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    }

    setInterval(function () {
        if (verrouille) { return; }
        var inactif = Date.now() - lire();
        if (inactif > LIMITE) { masquerAvertissement(); verrouiller(); }
        else if (AVERT > 0 && inactif > LIMITE - AVERT) { afficherAvertissement(Math.ceil((LIMITE - inactif) / 1000)); }
        else { masquerAvertissement(); signaler(); }
    }, 1000);

    // Le serveur répond « verrouillé » (423) à un appel Ajax ou fetch : on affiche l'écran de verrouillage.
    $(document).ajaxError(function (e, xhr) {
        if (xhr.status === 423 && xhr.responseJSON && xhr.responseJSON.redirect) { aller(xhr.responseJSON.redirect); }
        else if (xhr.status === 401) { aller(@js(route('login'))); }
    });
    var fetchOrigine = window.fetch;
    window.fetch = function () {
        return fetchOrigine.apply(this, arguments).then(function (r) {
            if (r.status === 423) { r.clone().json().then(function (j) { aller(j.redirect); }).catch(function () { aller(URL_VERROU.replace('verrouiller', 'verrouille')); }); }
            return r;
        });
    };
})();
</script>
@endauth

{{-- Pour la gestion des modal --}}
<script>
    $.fn.modal = function (action) {
        return this.each(function () {
            const $el = $(this);

            if (action === 'hide') {
                document.activeElement?.blur();
                $el.removeClass('show').css('display', 'none');
                $('body').removeClass('modal-open');
                $('.modal-backdrop').remove();
            } else {
                $el.addClass('show').css('display', 'block');
                $('body').addClass('modal-open');
                if (!$('.modal-backdrop').length) {
                    $('body').append('<div class="modal-backdrop fade show" style="opacity: 0.35;"></div>');
                }
            }
        });
    };

    $(document).on('click', '[data-bs-dismiss="modal"]', function () {
        $(this).closest('.modal').modal('hide');
    });
</script>
