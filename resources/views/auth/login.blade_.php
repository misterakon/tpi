@extends('layout.auth')
@section('title', 'Connexion')

@section('content')
  <form class="theme-form" method="POST" action="{{ route('login') }}"
        data-ajax
        data-loading="Vérification de vos accès…"
        data-loading2="Envoi du code par e-mail…"
        data-success="Code envoyé"
        data-btn-loading="Connexion…">
    @csrf
    <div class="ea-badge"><i class="fa-solid fa-lock"></i></div>
    <h4 class="text-center font-bold ea-title">{{ config('app.name') }}</h4>
    <p class="text-center">Veuillez entrer votre e-mail et votre mot de passe</p>

    <div class="form-group">
      <label class="col-form-label" for="email">E-mail</label>
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
      @error('email')
        <span class="ea-error">{{ $message }}</span>
      @enderror
    </div>

    <div class="form-group">
      <label class="col-form-label" for="password">Mot de passe</label>
      <div class="form-input relative">
        <input
          class="form-control @error('password') is-invalid @enderror"
          id="password"
          type="password"
          name="password"
          required
          autocomplete="current-password"
          placeholder="*********">
        <div class="show-hide"><span class="show"> </span></div>
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
      <p class="text-center text-muted mt-4 mb-0" style="font-size:.8rem; color:rgb(0, 88, 27)">Un code de vérification vous sera envoyé par e-mail.</p>
    </div>
  </form>
@endsection
