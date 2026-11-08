@extends('layout.master')
@section('title', 'Mon compte')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <style>
    .compte-grille { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 22px; align-items: start; }
    @media (max-width: 960px) { .compte-grille { grid-template-columns: 1fr; } }
    .compte-section { margin: 18px 0 8px; font: 700 12px var(--police-app); letter-spacing: .6px; text-transform: uppercase; color: #00794C; }
    .compte-ligne { padding: 9px 0; border-top: 1px solid var(--el-line); }
    .compte-ligne:first-of-type { border-top: 0; }
    .compte-ligne__titre { font: 600 14.5px var(--police-app); }
    .compte-ligne__sous { margin-top: 2px; font: 400 13px var(--police-app); color: var(--el-mute); }
  </style>
@endsection

@section('main-content')
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Mon espace" title="Mon compte" :subtitle="$moi->name.' · '.$moi->email" icon="fa-solid fa-user-shield">
        <x-slot:extra>
          <span><i class="fa-solid fa-id-badge"></i> Profil : {{ $moi->profil->nom }}</span>
          @if ($agent)<span><i class="fa-solid fa-gavel"></i> {{ $agent->fonctionLibelle() }}</span>@endif
        </x-slot:extra>
      </x-hero>

      <div class="compte-grille">

        <div class="el-card">
          <div class="el-card__head">
            <div>
              <h2 class="el-card__title">Mon profil</h2>
              <div class="el-card__sub">Mes informations et mes fonctions au tribunal</div>
            </div>
          </div>
          <div class="el-card__body">
            <div class="el-info">
              <div class="el-info__row"><span class="el-info__icon"><i class="fa-regular fa-user"></i></span><div><div class="el-info__label">Nom</div><div class="el-info__value">{{ $moi->name }}</div></div></div>
              <div class="el-info__row"><span class="el-info__icon"><i class="fa-regular fa-envelope"></i></span><div><div class="el-info__label">E-mail</div><div class="el-info__value">{{ $moi->email }}</div></div></div>
              <div class="el-info__row"><span class="el-info__icon"><i class="fa-solid fa-id-badge"></i></span><div><div class="el-info__label">Profil</div><div class="el-info__value">{{ $moi->profil->nom }}</div></div></div>
              <div class="el-info__row"><span class="el-info__icon"><i class="fa-solid fa-key"></i></span><div><div class="el-info__label">Mot de passe modifié le</div><div class="el-info__value">{{ $moi->mot_de_passe_modifie_le?->format('d/m/Y') ?? 'jamais depuis la création' }}</div></div></div>
            </div>

            <div class="compte-section">Mes fonctions au tribunal</div>
            @if (! $agent)
              <p class="el-muted" style="margin:0">Votre compte n'est pas encore relié à un magistrat ou à un greffier. Demandez-le à l'administrateur.</p>
            @else
              <div class="compte-ligne" style="border-top:0">
                <div class="compte-ligne__titre">{{ $agent->nomComplet() }}</div>
                <div class="compte-ligne__sous">{{ $agent->fonctionLibelle() }}</div>
              </div>

              <div class="compte-section" style="margin-top:14px">Chambres et formations</div>
              @forelse ($affectations as $a)
                <div class="compte-ligne">
                  <div class="compte-ligne__titre">{{ $a->formation->chambre->libelle }}@if ($a->formation->nom) – {{ $a->formation->nom }}@endif</div>
                  <div class="compte-ligne__sous">{{ \App\Models\Affectation::ROLES[$a->role] ?? $a->role }}{{ $a->suppleant ? ' (suppléant)' : '' }} · {{ $a->formation->chambre->annee?->libelle }}</div>
                </div>
              @empty
                <p class="el-muted" style="margin:0">Aucune affectation en cours.</p>
              @endforelse

              <div class="compte-section" style="margin-top:14px">Services, comités et fonctions</div>
              @forelse ($services as $ligne)
                <div class="compte-ligne">
                  <div class="compte-ligne__titre">{{ $ligne->service->libelle }}</div>
                  @if ($ligne->fonction)<div class="compte-ligne__sous">{{ $ligne->fonction }}</div>@endif
                </div>
              @empty
                <p class="el-muted" style="margin:0">Aucun service en cours.</p>
              @endforelse
            @endif
          </div>
        </div>

        <div class="el-card">
          <div class="el-card__head">
            <div>
              <h2 class="el-card__title">Sécurité</h2>
              <div class="el-card__sub">Changer mon mot de passe (au moins {{ config('securite.mot_de_passe_min') }} caractères)</div>
            </div>
          </div>
          <div class="el-card__body">
            <form id="form-mdp" method="POST" action="{{ route('profile.password') }}" autocomplete="off">
              @csrf
              @method('PUT')
              <div class="el-formgrid">
                <div class="el-c12">
                  <label class="form-label" for="current_password"><b>Mot de passe actuel</b></label>
                  <input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                </div>
                <div class="el-c12">
                  <label class="form-label" for="password"><b>Nouveau mot de passe</b></label>
                  <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" minlength="{{ config('securite.mot_de_passe_min') }}" required>
                </div>
                <div class="el-c12">
                  <label class="form-label" for="password_confirmation"><b>Confirmer le nouveau mot de passe</b></label>
                  <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                </div>
                <div class="el-c12">
                  <button class="el-btn el-btn--accent" type="submit"><i class="fa-solid fa-lock"></i> Enregistrer</button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <div class="el-card" style="grid-column: 1 / -1">
          <div class="el-card__head">
            <div>
              <h2 class="el-card__title">Codes de secours</h2>
              <div class="el-card__sub">Pour vous connecter quand le code reçu par e-mail n'arrive pas : huit codes, chacun ne sert qu'une fois.</div>
            </div>
          </div>
          <div class="el-card__body">
            <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:14px">
              <div id="etat-codes" class="compte-ligne__sous" style="font-size:14px">
                @if ($codesSecours['total'] === 0)
                  <b style="color:#C95A00">Vous n'avez pas encore de codes de secours.</b> Générez-les maintenant et gardez-les en lieu sûr (imprimés ou notés), pas dans votre messagerie.
                @else
                  Il vous reste <b id="restants-codes" style="color:{{ $codesSecours['restants'] <= 2 ? '#B42318' : '#00794C' }}">{{ $codesSecours['restants'] }} code{{ $codesSecours['restants'] > 1 ? 's' : '' }}</b> sur {{ $codesSecours['total'] }}, générés le {{ $codesSecours['genere_le']?->format('d/m/Y') }}.
                @endif
              </div>
              <button class="el-btn el-btn--accent" type="button" id="btn-codes"><i class="fa-solid fa-key"></i> {{ $codesSecours['total'] === 0 ? 'Générer mes codes' : 'Générer de nouveaux codes' }}</button>
            </div>
            @if ($codesSecours['total'] > 0)<p class="compte-ligne__sous" style="margin:10px 0 0">Générer de nouveaux codes annule les anciens.</p>@endif
          </div>
        </div>

      </div>
    </div>
  </div>
@endsection

@section('script')
  <script>
    document.getElementById('btn-codes').addEventListener('click', async function () {
      var nom = @js(config('app.name'));
      var saisie = await Swal.fire({
        title: nom, icon: 'question', text: 'Saisissez votre mot de passe pour générer de nouveaux codes de secours. Les anciens codes seront annulés.',
        input: 'password', inputAttributes: { autocomplete: 'current-password', 'aria-label': 'Mot de passe' }, inputPlaceholder: 'Votre mot de passe',
        showCancelButton: true, confirmButtonColor: '#00794C', cancelButtonColor: '#d33', cancelButtonText: 'Annuler', confirmButtonText: 'Générer',
        inputValidator: function (v) { return v ? null : 'Saisissez votre mot de passe.'; }
      });
      if (!saisie.isConfirmed) { return; }
      var corps = new FormData(); corps.append('current_password', saisie.value); corps.append('_token', document.querySelector('meta[name="csrf-token"]').content);
      var r = await fetch(@js(route('profile.codes_secours')), { method: 'POST', body: corps, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      var j = await r.json().catch(function () { return {}; });
      if (!r.ok) {
        var m = (j.errors && Object.values(j.errors)[0][0]) || j.message || 'Une erreur est survenue.';
        Swal.fire({ icon: 'error', title: nom, text: m }); return;
      }
      var liste = j.codes.map(function (c) { return '<span style="display:block;padding:8px 6px;border-radius:10px;background:#F4FAF6;border:1px solid #CFE5D9;font:800 17px/1 monospace;letter-spacing:2px;color:#0B3D2A">' + c + '</span>'; }).join('');
      var texte = nom + ' : codes de secours de ' + @js($moi->name) + ' (générés le ' + j.genere_le + ')

' + j.codes.join('
') + '

Chaque code ne sert qu'une fois. À conserver en lieu sûr.';
      var res = await Swal.fire({
        title: 'Vos codes de secours', icon: 'success', width: 520, allowOutsideClick: false,
        html: '<p style="font-size:14px;margin:0 0 12px">Notez-les maintenant : <b>ils ne seront plus affichés</b>. Chaque code ne sert qu'une fois.</p><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">' + liste + '</div>',
        showDenyButton: true, showCancelButton: true, confirmButtonText: 'Copier', denyButtonText: 'Télécharger', cancelButtonText: 'J'ai noté mes codes',
        confirmButtonColor: '#00794C', denyButtonColor: '#E8710A', cancelButtonColor: '#334155', preConfirm: function () { if (navigator.clipboard) { navigator.clipboard.writeText(texte); } return false; },
        preDeny: function () { var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([texte], { type: 'text/plain;charset=utf-8' })); a.download = 'codes-de-secours.txt'; a.click(); return false; }
      });
      document.getElementById('etat-codes').innerHTML = 'Il vous reste <b style="color:#00794C">' + j.codes.length + ' codes</b> sur ' + j.codes.length + ', générés le ' + j.genere_le.split(' à ')[0] + '.';
      document.getElementById('btn-codes').lastChild.textContent = ' Générer de nouveaux codes';
    });
  </script>
  <script>
    document.getElementById('form-mdp').addEventListener('submit', async function (e) {
      e.preventDefault();
      var form = this;
      try {
        var r = await fetch(form.action, {
          method: 'POST', body: new FormData(form), credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        var j = await r.json().catch(function () { return {}; });
        if (r.ok) {
          form.reset();
          Swal.fire({ icon: 'success', title: @js(config('app.name')), text: j.message });
        } else if (r.status === 422 && j.errors) {
          var textes = Object.values(j.errors).map(function (m) { return m[0]; }).join('<br>');
          Swal.fire({ icon: 'error', title: @js(config('app.name')), html: textes });
        } else {
          Swal.fire({ icon: 'error', title: @js(config('app.name')), text: j.message || 'Une erreur est survenue.' });
        }
      } catch (err) {
        Swal.fire({ icon: 'error', title: @js(config('app.name')), text: 'Connexion impossible.' });
      }
    });
  </script>
@endsection
