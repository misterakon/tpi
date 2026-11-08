@extends('layout.auth')
@section('title', 'Vérification')

@section('content')
{{-- Fond et décor : copiés dans la page (hors du cadre blanc) par le petit script ci-dessous --}}
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
  
  <form class="theme-form ea-login" method="POST" action="{{ route('two-factor.verify') }}"
        data-ajax data-autosubmit
        data-loading="Vérification du code…"
        data-success="Bienvenue !"
        data-btn-loading="Vérification…">
    @csrf
    <img class="ea-logo" src="{{ asset('assets/images/logo/logo_tpi.png') }}" alt="{{ config('app.name') }}">
    <h4 class="text-center font-bold ea-title mt-4">Vérification en deux étapes</h4>
    <p class="text-center">
      Un code à 6 chiffres vient d'être envoyé à <b>{{ $email }}</b>.<br>
      <span style="color:rgb(230, 74, 35)">Il est valable {{ $validite }} minute(s).</span>
    </p>

    <div class="form-group">
      <label class="col-form-label d-block text-center" for="otp-0">Code reçu par e-mail</label>

      {{-- 6 cases pour l'œil, un seul champ « code » pour le serveur : le remplissage automatique
           du téléphone et le collage d'un code complet fonctionnent. --}}
      <div class="ea-otp @error('code') invalid @enderror" data-otp>
        @for ($i = 0; $i < 6; $i++)
          <input
            id="otp-{{ $i }}"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
            aria-label="Chiffre {{ $i + 1 }} sur 6"
            data-otp-digit
            @if ($i === 0) autofocus @endif>
        @endfor
        <input type="hidden" name="code" value="">
      </div>
      @error('code')
        <span class="ea-error ea-error--center">{{ $message }}</span>
      @enderror
    </div>

    <div class="form-group mt-4 text-center">
      <button class="btn btn-primary btn-block w-auto px-6 py-2 text-white text-center" type="submit">VALIDER</button>
    </div>
  </form>

  {{-- Le code par e-mail n'arrive pas : un code de secours (usage unique) le remplace. --}}
  <div class="text-center mt-3">
    <a href="#" id="lien-secours" style="font-weight:bold; color:#C95A00" aria-expanded="false" aria-controls="bloc-secours">Je ne reçois pas le code : utiliser un code de secours</a>
  </div>
  <form class="theme-form ea-login" id="bloc-secours" method="POST" action="{{ route('two-factor.secours') }}" hidden
        data-ajax data-loading="Vérification du code de secours…" data-success="Bienvenue !" data-btn-loading="Vérification…" style="margin-top:12px">
    @csrf
    <div class="form-group">
      <label class="col-form-label d-block text-center" for="code-secours">Code de secours</label>
      <input class="form-control text-center" id="code-secours" name="code_secours" type="text" maxlength="12" inputmode="numeric" pattern="[0-9 -]*" autocomplete="off" spellcheck="false" placeholder="00000000" style="letter-spacing:5px; font-weight:700">
      <small class="d-block text-center text-muted mt-2">Chaque code ne sert qu'une fois. Vous les trouvez dans « Mon compte », section Sécurité.</small>
    </div>
    <div class="form-group mt-3 text-center">
      <button class="btn btn-primary btn-block w-auto px-6 py-2 text-white text-center" type="submit">SE CONNECTER AVEC CE CODE</button>
    </div>
  </form>

  <div class="text-center mt-4">
    <form method="POST" action="{{ route('two-factor.resend') }}" class="d-inline"
          data-ajax data-veil="off" data-btn-loading="Envoi…">
      @csrf
      <button class="btn btn-link p-0" style="color: #129e00; font-weight:bold" type="submit" data-countdown data-attente="{{ (int) $attente }}" @if ($attente > 0) disabled @endif>
        Renvoyer le code<span data-countdown-label>@if ($attente > 0) ({{ $attente }} s)@endif</span>
      </button>
    </form>
    <span class="text-muted"> <b>&larr;</b> </span>
    <a href="{{ route('login') }}" data-swap>Retour à la connexion</a>
  </div>
@endsection
