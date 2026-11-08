@extends('layout.master')
@section('title', 'Réglages')

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
@endsection

@section('main-content')
  @php $totalModifies = collect($rubriques)->sum('nbModifies'); @endphp

  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Paramètres" title="Réglages" subtitle="Réglez ici ce qui peut l'être sans risque. Un changement s'applique tout de suite, à tous les utilisateurs." icon="fa-solid fa-sliders">
        <x-slot:extra>
          <span><i class="fa-solid fa-layer-group"></i> {{ count($rubriques) }} rubriques</span>
          <span><i class="fa-solid fa-pen-ruler"></i> {{ $totalModifies }} réglage(s) personnalisé(s)</span>
        </x-slot:extra>
      </x-hero>

      <div class="el-grid">

        {{-- Rubriques --}}
        <div class="el-col" style="flex:0 0 290px; max-width:100%">
          <div class="el-card">
            <div class="el-card__head"><div><h2 class="el-card__title">Rubriques</h2><div class="el-card__sub">Choisissez ce que vous voulez régler.</div></div></div>
            <div class="el-card__body">
              <nav class="el-rubs" aria-label="Rubriques de réglages">
                @foreach ($rubriques as $cle => $rubrique)
                  <button type="button" class="el-rub {{ $cle === $actif ? 'is-active' : '' }}" data-rub="{{ $cle }}" aria-controls="rub-{{ $cle }}" aria-selected="{{ $cle === $actif ? 'true' : 'false' }}" onclick="afficher_rubrique('{{ $cle }}')">
                    <span class="el-rub__icone"><i class="{{ $rubrique['icone'] }}"></i></span>
                    <span class="el-rub__texte">{{ $rubrique['libelle'] }}</span>
                    @if ($rubrique['nbModifies'] > 0)
                      <span class="el-count" title="{{ $rubrique['nbModifies'] }} réglage(s) personnalisé(s)">{{ $rubrique['nbModifies'] }}</span>
                    @endif
                  </button>
                @endforeach
              </nav>
            </div>
          </div>

          <div class="el-note">
            <span class="el-note__icon"><i class="fa-solid fa-lock"></i></span>
            <div>
              <div class="el-note__title">Réglages protégés</div>
              <div class="el-note__text">L'accès à la base de données et les clés de chiffrement ne se règlent pas ici : ils restent dans le fichier de configuration du serveur. Le mot de passe de messagerie se règle dans « Courriels » ; il est chiffré et jamais affiché.</div>
            </div>
          </div>
        </div>

        {{-- Réglages de la rubrique choisie --}}
        <div class="el-col" style="flex:1 1 520px; min-width:0">
          @foreach ($rubriques as $cle => $rubrique)
            <section class="el-card el-panneau" id="rub-{{ $cle }}" @if ($cle !== $actif) hidden @endif>
              <form method="POST" action="{{ route('reglages.update', $cle) }}" class="el-reglages-form" autocomplete="off">
                @csrf
                <div class="el-card__head">
                  <div class="el-person">
                    <span class="el-droits__nom"><i class="{{ $rubrique['icone'] }}"></i></span>
                    <div>
                      <h2 class="el-card__title">{{ $rubrique['libelle'] }}</h2>
                      <div class="el-card__sub">{{ $rubrique['description'] }}</div>
                    </div>
                  </div>
                </div>

                <div class="el-reglages">
                  @foreach ($rubrique['champs'] as $champ)
                    @php $valeur = $champ['secret'] ? null : old($champ['nom'], $champ['valeur']); @endphp
                    <div class="el-reglage {{ $errors->has($champ['nom']) ? 'has-error' : '' }}">
                      <div class="el-reglage__info">
                        <label class="el-reglage__libelle" for="{{ $champ['nom'] }}">
                          {{ $champ['libelle'] }}
                          @if ($champ['modifie'])<x-chip tone="alert" label="Modifié" />@endif
                        </label>
                        <div class="el-reglage__aide">{{ $champ['aide'] }}</div>
                        @if ($champ['modifie'] && $champ['derniere'])
                          <div class="el-reglage__trace"><i class="fa-regular fa-clock"></i> {{ $champ['derniere']->auteur ? 'Par '.$champ['derniere']->auteur.', le ' : 'Le ' }}{{ \Illuminate\Support\Carbon::parse($champ['derniere']->updated_at)->format('d/m/Y à H:i') }}</div>
                        @endif
                      </div>

                      <div class="el-reglage__ctrl">
                        @if ($champ['secret'])
                          <input class="form-control" type="password" id="{{ $champ['nom'] }}" name="{{ $champ['nom'] }}" value="" maxlength="{{ $champ['max'] }}" autocomplete="new-password" placeholder="{{ $champ['definie'] ? '•••••••• (enregistré — laisser vide pour le conserver)' : 'Saisissez le mot de passe' }}" @cannot('reglages.modifier') disabled @endcannot>
                          <div class="el-reglage__defaut">
                            @if ($champ['definie'])
                              <i class="fa-solid fa-lock"></i> Un mot de passe est enregistré (chiffré, jamais affiché).
                            @else
                              <i class="fa-regular fa-circle"></i> Aucun mot de passe enregistré.
                            @endif
                          </div>
                          @if ($champ['definie'])
                            @can('reglages.modifier')
                            <label class="el-reglage__effacer"><input type="checkbox" name="{{ \App\Services\Reglages::nomEffacer($champ['cle']) }}" value="1"> Effacer le mot de passe enregistré</label>
                            @endcan
                          @endif
                        @elseif ($champ['type'] === 'entier')
                          <div class="el-saisie">
                            <input class="form-control" type="number" id="{{ $champ['nom'] }}" name="{{ $champ['nom'] }}" value="{{ $valeur }}" min="{{ $champ['min'] }}" max="{{ $champ['max'] }}" step="1" required data-initial="{{ $champ['valeur'] }}" @cannot('reglages.modifier') disabled @endcannot>
                            <span class="el-unite">{{ $champ['unite'] }}</span>
                          </div>
                          <div class="el-reglage__defaut">Par défaut : <b>{{ $champ['defaut'] }}</b> · entre {{ $champ['min'] }} et {{ $champ['max'] }}</div>
                        @elseif ($champ['type'] === 'liste')
                          <select class="form-select" id="{{ $champ['nom'] }}" name="{{ $champ['nom'] }}" data-initial="{{ $champ['valeur'] }}" @cannot('reglages.modifier') disabled @endcannot>
                            @foreach ($champ['options'] as $val => $texte)
                              <option value="{{ $val }}" @selected((string) $valeur === (string) $val)>{{ $texte }}</option>
                            @endforeach
                          </select>
                          <div class="el-reglage__defaut">Par défaut : <b>{{ $champ['options'][$champ['defaut']] ?? $champ['defaut'] }}</b></div>
                        @else
                          <input class="form-control" type="text" id="{{ $champ['nom'] }}" name="{{ $champ['nom'] }}" value="{{ $valeur }}" maxlength="{{ $champ['max'] }}" @required($champ['requis'] ?? true) data-initial="{{ $champ['valeur'] }}" @cannot('reglages.modifier') disabled @endcannot>
                          <div class="el-reglage__defaut">Par défaut : <b>{{ filled($champ['defaut']) ? $champ['defaut'] : 'vide' }}</b></div>
                        @endif
                        @error($champ['nom'])
                          <div class="el-reglage__erreur"><i class="fa-solid fa-circle-exclamation"></i> {{ $message }}</div>
                        @enderror
                      </div>
                    </div>
                  @endforeach
                </div>

                <div class="el-reglages__pied">
                  @can('reglages.modifier')
                    <button type="button" class="el-btn el-btn--ghost" onclick="retablir_defauts('{{ $cle }}', @js($rubrique['libelle']))"><i class="fa-solid fa-rotate-left"></i> Valeurs par défaut</button>
                    <button type="submit" class="el-btn el-btn--primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
                  @else
                    <span class="el-muted"><i class="fa-solid fa-eye"></i> Vous pouvez consulter ces réglages, pas les modifier.</span>
                  @endcan
                </div>
              </form>

              @can('reglages.modifier')
                <form id="defaut-{{ $cle }}" method="POST" action="{{ route('reglages.defaut', $cle) }}" hidden>@csrf</form>
              @endcan

              @if ($cle === 'courriels')
                @can('reglages.modifier')
                <div class="el-test">
                  <div class="el-test__titre"><i class="fa-solid fa-paper-plane"></i> Tester l'envoi</div>
                  <div class="el-reglage__aide">
                    Mode actuel : <b>{{ $modeCourriel === 'smtp' ? 'Envoi réel (SMTP)' : 'Test (les courriels ne partent pas)' }}</b>.
                    Enregistrez d'abord vos réglages, puis envoyez un courriel de test pour vérifier que le serveur répond.
                  </div>
                  <div class="el-test__ligne">
                    <input class="form-control" type="email" id="test_destinataire" value="{{ $emailUtilisateur }}" maxlength="255" autocomplete="off" aria-label="Adresse de destination du test">
                    <button type="button" class="el-btn el-btn--ghost" id="test_bouton" onclick="tester_courriel()"><i class="fa-solid fa-paper-plane"></i> Envoyer un test</button>
                  </div>
                  <div class="el-test__resultat" id="test_resultat" role="status" hidden></div>
                </div>
                @endcan
              @endif
            </section>
          @endforeach
        </div>
      </div>

    </div>
  </div>

  @include('reglage.js_script')
@endsection
