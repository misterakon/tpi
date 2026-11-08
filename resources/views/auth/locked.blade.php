@extends('layout.auth')
@section('title', 'Session verrouillée')

@section('content')

  {{-- Fond : copié dans la page (hors du cadre blanc) par le petit script ci-dessous, comme sur la page de connexion --}}
  <template id="ea-fond">
    <div class="ea-bg" aria-hidden="true"></div>
  </template>
  <script>
    (function () {
      if (document.querySelector('body > .ea-bg')) return;
      var modele = document.getElementById('ea-fond');
      if (modele && document.body) {
        document.body.insertBefore(modele.content.cloneNode(true), document.body.firstChild);
      }
    })();
  </script>

  <form class="theme-form ea-login ea-verrou" method="POST" action="{{ route('verrouillage.unlock') }}"
        data-ajax
        data-loading="Vérification de votre mot de passe…"
        data-success="Bon retour !"
        data-btn-loading="Déverrouillage…">
    @csrf
    <img class="ea-logo" src="{{ asset('assets/images/logo/logo_tpi.png') }}" alt="{{ config('app.name') }}">

    <div class="ea-verrou__etat"><i class="fa-solid fa-lock" aria-hidden="true"></i> Session verrouillée</div>
    <p class="text-center ea-verrou__info">Aucune activité depuis {{ $minutesInactivite }} minutes : par sécurité, votre session a été verrouillée.</p>

    {{-- Identité : affichée, jamais modifiable --}}
    <div class="ea-verrou__identite">
      <div class="ea-verrou__nom">{{ $nom }}</div>
      <div class="ea-verrou__email" title="Compte connecté">{{ $email }}</div>
    </div>
    {{-- Aide aux gestionnaires de mots de passe du navigateur (champ caché, sans nom : il n'est pas envoyé) --}}
    <input type="text" value="{{ $email }}" autocomplete="username" readonly hidden tabindex="-1" aria-hidden="true">

    <div class="form-group">
      <label class="col-form-label" for="password">Mot de passe</label>
      <div class="ea-champ ea-champ--mdp">
        <i class="fa-solid fa-lock ea-ico" aria-hidden="true"></i>
        <input
          class="form-control @error('password') is-invalid @enderror"
          id="password"
          type="password"
          name="password"
          required
          autofocus
          autocomplete="current-password"
          placeholder="*********">
        <button type="button" class="ea-oeil" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher / masquer"
          onclick="var i=document.getElementById('password'),v=i.type==='password';i.type=v?'text':'password';this.setAttribute('aria-pressed',v?'true':'false');this.setAttribute('aria-label',v?'Masquer le mot de passe':'Afficher le mot de passe');this.querySelector('i').className=v?'fa-solid fa-eye-slash':'fa-solid fa-eye';i.focus();">
          <i class="fa-solid fa-eye" aria-hidden="true"></i>
        </button>
      </div>
      @error('password')
        <span class="ea-error">{{ $message }}</span>
      @enderror
    </div>

    <div class="form-group mt-4 text-center">
      <button class="btn btn-primary btn-block w-auto px-6 py-2 text-white text-center" type="submit">DÉVERROUILLER</button>
      {{-- <p class="text-center mt-4 mb-0 ea-aide">
        Aucun code n'est nécessaire. Après {{ $minutesMax }} minutes de verrouillage, une nouvelle connexion complète sera demandée.
      </p> --}}
    </div>
  </form>

  <div class="text-center mt-4">
    <form method="POST" action="{{ route('logout') }}" class="d-inline">
      @csrf
      <button class="btn btn-link p-0 ea-verrou__sortie" type="submit">Ce n'est pas vous ? Se déconnecter</button>
    </form>
  </div>
@endsection
