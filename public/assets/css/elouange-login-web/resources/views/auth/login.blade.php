@extends('layout.auth')
@section('title', 'Connexion')

@section('content')
  <style>
    /* ───────────── Page de connexion eLouange ───────────── */
    :root {
      --ea-orange: #F28A1E;
      --ea-vert: #2E9E5B;
      --ea-vert-fonce: #0B6B45;
      /* Image de fond facultative : remplacer none par url('/images/login/mon-fond.jpg') */
      --ea-fond-image: none;
    }

    /* Grand fond : dégradé orange → blanc → vert (remplace l'ancien fond) */
    .ea-bg {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background:
        var(--ea-fond-image),
        radial-gradient(900px 620px at 0% 0%, rgba(242, 138, 30, .55), transparent 62%),
        radial-gradient(900px 660px at 100% 100%, rgba(46, 158, 91, .55), transparent 62%),
        radial-gradient(760px 520px at 100% 0%, rgba(46, 158, 91, .16), transparent 62%),
        radial-gradient(760px 520px at 0% 100%, rgba(242, 138, 30, .16), transparent 62%),
        linear-gradient(135deg, #fff6e9 0%, #ffffff 50%, #eefaf2 100%);
      background-size: cover;
    }

    /* Décor musical : une clé à gauche, la même en miroir à droite, derrière le cadre */
    .ea-deco {
      position: fixed;
      top: 50%;
      z-index: 1;
      width: clamp(190px, 22vw, 340px);
      height: auto;
      pointer-events: none;
      user-select: none;
      filter: drop-shadow(0 18px 26px rgba(30, 40, 30, .22));
      translate: 0 -50%;
      animation: ea-flotte 9s ease-in-out infinite;
    }
    .ea-deco--gauche { right: calc(50% + 150px); rotate: -6deg; }
    .ea-deco--droite { left: calc(50% + 150px); scale: -1 1; rotate: -6deg; animation-delay: -4.5s; }

    @keyframes ea-flotte {
      0%, 100% { translate: 0 -50%; }
      50%      { translate: 0 calc(-50% - 14px); }
    }

    /* Le cadre blanc (parent direct du formulaire) : bords orange et vert + grosse ombre */
    :has(> .ea-login) {
      position: relative;
      z-index: 2;
      background: #fff !important;
      border: 0 !important;
      border-radius: 22px !important;
      box-shadow:
        -7px 0 0 0 var(--ea-orange),
         7px 0 0 0 var(--ea-vert),
        -24px 0 36px -16px rgba(242, 138, 30, .60),
         24px 0 36px -16px rgba(46, 158, 91, .60),
        0 38px 70px -20px rgba(15, 35, 25, .50),
        0 14px 26px -10px rgba(15, 35, 25, .22) !important;
      animation: ea-entree .6s cubic-bezier(.2, .8, .2, 1) both;
    }
    @keyframes ea-entree {
      from { opacity: 0; translate: 0 18px; }
      to   { opacity: 1; translate: 0 0; }
    }

    /* L'ancien logo placé au-dessus du cadre est remplacé par celui du cadre */
    body:has(.ea-login) .login-card a.logo,
    body:has(.ea-login) .login-card .logo-wrapper { display: none !important; }

    /* Logo à la place de l'icône cadenas */
    .ea-login .ea-logo {
      display: block;
      width: min(210px, 70%);
      height: auto;
      margin: 2px auto 6px;
    }

    /* Champs avec icône */
    .ea-login .ea-champ { position: relative; }
    .ea-login .ea-champ > .ea-ico {
      position: absolute;
      left: 14px;
      top: 50%;
      translate: 0 -50%;
      color: #8a96a3;
      font-size: .95rem;
      pointer-events: none;
      transition: color .2s;
    }
    .ea-login .ea-champ .form-control { padding-left: 42px; transition: border-color .2s, box-shadow .2s; }
    .ea-login .ea-champ .form-control:focus {
      border-color: var(--ea-vert);
      box-shadow: 0 0 0 4px rgba(46, 158, 91, .16);
    }
    .ea-login .ea-champ:focus-within > .ea-ico { color: var(--ea-vert); }

    /* Bouton afficher / masquer le mot de passe */
    .ea-login .ea-champ--mdp .form-control { padding-right: 48px; }
    .ea-login .ea-oeil {
      position: absolute;
      right: 6px;
      top: 50%;
      translate: 0 -50%;
      width: 38px;
      height: 38px;
      border: 0;
      border-radius: 10px;
      background: transparent;
      color: #6b7785;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background .2s, color .2s;
    }
    .ea-login .ea-oeil:hover { background: rgba(46, 158, 91, .12); color: var(--ea-vert-fonce); }
    .ea-login .ea-oeil:focus-visible { outline: 2px solid var(--ea-vert); outline-offset: 1px; }
    .ea-login .ea-oeil[aria-pressed="true"] { color: var(--ea-vert-fonce); }

    .ea-login .ea-aide { font-size: .8rem; color: rgb(0, 88, 27); }

    /* Écrans moyens : le décor se rapproche et s'estompe ; petits écrans : il disparaît */
    @media (max-width: 1100px) {
      .ea-deco { opacity: .55; }
      .ea-deco--gauche { right: calc(50% + 120px); }
      .ea-deco--droite { left: calc(50% + 120px); }
    }
    @media (max-width: 760px) {
      .ea-deco { display: none; }
    }
    @media (prefers-reduced-motion: reduce) {
      .ea-deco, :has(> .ea-login) { animation: none !important; }
    }
  </style>

  <div class="ea-bg" aria-hidden="true"></div>
  <img class="ea-deco ea-deco--gauche" src="{{ asset('images/login/fond_melodie3.png') }}" alt="" aria-hidden="true">
  <img class="ea-deco ea-deco--droite" src="{{ asset('images/login/fond_melodie3.png') }}" alt="" aria-hidden="true">

  <form class="theme-form ea-login" method="POST" action="{{ route('login') }}"
        data-ajax
        data-loading="Vérification de vos accès…"
        data-loading2="Envoi du code par e-mail…"
        data-success="Code envoyé"
        data-btn-loading="Connexion…">
    @csrf
    <img class="ea-logo" src="{{ asset('images/login/logo_elouange.png') }}" alt="{{ config('app.name') }}">
    <h4 class="text-center font-bold ea-title">{{ config('app.name') }}</h4>
    <p class="text-center">Veuillez entrer votre e-mail et votre mot de passe</p>

    <div class="form-group">
      <label class="col-form-label" for="email">E-mail</label>
      <div class="ea-champ">
        <i class="fa-solid fa-envelope ea-ico" aria-hidden="true"></i>
        <input
          class="form-control @error('email') is-invalid @enderror"
          id="email"
          type="email"
          name="email"
          value="{{ old('email') }}"
          required
          autofocus
          autocomplete="username"
          placeholder="nom@exemple.com">
      </div>
      @error('email')
        <span class="ea-error">{{ $message }}</span>
      @enderror
    </div>

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
          autocomplete="current-password"
          placeholder="*********">
        {{-- Gestionnaire écrit directement dans le bouton : il fonctionne même quand le formulaire est affiché sans rechargement de la page --}}
        <button type="button" class="ea-oeil" aria-label="Afficher le mot de passe" aria-pressed="false" title="Afficher / masquer"
          onclick="var i=document.getElementById('password'),v=i.type==='password';i.type=v?'text':'password';this.setAttribute('aria-pressed',v?'true':'false');this.setAttribute('aria-label',v?'Masquer le mot de passe':'Afficher le mot de passe');this.querySelector('i').className=v?'fa-solid fa-eye-slash':'fa-solid fa-eye';i.focus();">
          <i class="fa-solid fa-eye" aria-hidden="true"></i>
        </button>
      </div>
      @error('password')
        <span class="ea-error">{{ $message }}</span>
      @enderror
    </div>

    <div class="form-group mt-4">
      <a class="link" href="{{ route('password.request') }}" data-swap>Mot de passe oublié ?</a><br>

      <div class="text-center">
        <button class="btn btn-primary btn-block w-auto px-6 py-2 text-white mt-[24px] text-center" type="submit">
          SE CONNECTER
        </button>
      </div>
      <p class="text-center text-muted mt-4 mb-0 ea-aide">
        <i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i>
        Un code de vérification vous sera envoyé par e-mail.
      </p>
    </div>
  </form>
@endsection
