{{-- Cloche de notifications de l'en-tête : le nombre de non lues, les plus récentes, et une mise à jour discrète toutes les 90 secondes. --}}
@php
    $nbNonLues = $moi->unreadNotifications()->count();
    $recentes = \App\Http\Controllers\NotificationsController::recentes($moi);
@endphp
<li class="onhover-dropdown cloche" id="cloche">
    <div class="notification-box" aria-label="Notifications" role="button" tabindex="0">
        <svg><use href="{{ asset('assets/svg/icon-sprite.svg#notification') }}"></use></svg>
        <span class="cloche__nb" id="cloche-nb" @if ($nbNonLues === 0) hidden @endif>{{ $nbNonLues > 99 ? '99+' : $nbNonLues }}</span>
    </div>
    <div class="onhover-show-div notification-dropdown cloche__liste">
        <div class="cloche__tete">
            <span>Notifications</span>
            <form method="post" action="{{ route('notifications.tout_lire') }}" id="cloche-tout-lire" @if ($nbNonLues === 0) hidden @endif>@csrf<button type="submit">Tout marquer comme lu</button></form>
        </div>
        <ul class="cloche__items" id="cloche-items">
            @forelse ($recentes as $r)
                <li><a href="{{ $r['ouvrir'] }}"><span class="cloche__pt cloche__pt--{{ $r['niveau'] }}"></span><span><span class="cloche__t">{{ $r['titre'] }}</span><span class="cloche__q">{{ $r['quand'] }}</span></span></a></li>
            @empty
                <li class="cloche__vide" id="cloche-vide">Aucune notification non lue.</li>
            @endforelse
        </ul>
        <a class="cloche__pied" href="{{ route('notifications.index') }}">Voir toutes les notifications</a>
    </div>
</li>

<script>
(function () {
    'use strict';
    var URL_RESUME = @js(route('notifications.resume'));
    var nb = document.getElementById('cloche-nb'), liste = document.getElementById('cloche-items'), toutLire = document.getElementById('cloche-tout-lire');
    var precedent = {{ (int) $nbNonLues }};
    function echapper(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function afficher(j) {
        if (!nb || !liste) { return; }
        nb.textContent = j.non_lues > 99 ? '99+' : j.non_lues; nb.hidden = j.non_lues === 0; if (toutLire) { toutLire.hidden = j.non_lues === 0; }
        liste.innerHTML = j.recentes.length ? j.recentes.map(function (r) { return '<li><a href="' + echapper(r.ouvrir) + '"><span class="cloche__pt cloche__pt--' + echapper(r.niveau) + '"></span><span><span class="cloche__t">' + echapper(r.titre) + '</span><span class="cloche__q">' + echapper(r.quand) + '</span></span></a></li>'; }).join('') : '<li class="cloche__vide">Aucune notification non lue.</li>';
        if (j.non_lues > precedent) {
            nb.classList.add('is-nouveau');
            if (window.Swal) { Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: (j.non_lues - precedent) + ' nouvelle' + (j.non_lues - precedent > 1 ? 's' : '') + ' alerte' + (j.non_lues - precedent > 1 ? 's' : ''), showConfirmButton: false, timer: 3500, timerProgressBar: true }); }
        }
        precedent = j.non_lues;
    }
    function actualiser() {
        if (document.hidden) { return; }
        fetch(URL_RESUME, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; }).then(function (j) { if (j) { afficher(j); } }).catch(function () {});
    }
    setInterval(actualiser, 90000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { actualiser(); } });
})();
</script>
