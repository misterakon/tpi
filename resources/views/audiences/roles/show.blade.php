@extends('layout.master')
@section('title', "Rôle d'audience")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/roles.css') }}?v={{ filemtime(public_path('assets/css/roles.css')) }}">
@endsection

@section('main-content')
  @php
    $publie = $role->estPublie();
    $peutModifier = auth()->user()->can('roles.modifier');
    $editable = $peutModifier && ! $publie;
    $jour = ucfirst($role->date_audience->copy()->locale('fr')->translatedFormat('l d F Y'));
  @endphp
  <div class="page-body">
    <div class="el-page">

      <x-hero tag="Rôle d'audience" :title="$role->chambre->libelle.' · '.$jour" :subtitle="($role->heureCourte() ? 'À '.$role->heureCourte() : 'Heure à fixer').' — '.($role->salle?->nom ?? 'salle à fixer').'. '.($role->chambre->intitule ?? '')" icon="fa-solid fa-gavel">
        <x-slot:extra>
          <span><i class="fa-solid {{ $publie ? 'fa-circle-check' : 'fa-pen-ruler' }}"></i> {{ $publie ? 'Publié' : 'En préparation' }} · version {{ $role->version }}</span>
          <span><i class="fa-solid fa-list-ol"></i> {{ $doc['total'] }} affaire{{ $doc['total'] > 1 ? 's' : '' }}</span>
        </x-slot:extra>
        <x-slot:actions>
          <a class="el-btn el-btn--ghost" href="{{ route('audiences.roles') }}"><i class="fa-solid fa-arrow-left"></i> Les rôles</a>
          @if ($publie)
            @can('feuille.voir')<a class="el-btn el-btn--accent" href="{{ route('audiences.feuille.show', $role->id) }}"><i class="fa-solid fa-clipboard-list"></i> Feuille d'audience</a>@endcan
          @endif
          @can('roles.imprimer')
            <a class="el-btn el-btn--ghost" href="{{ route('audiences.roles.export', [$role->id, 'docx']) }}"><i class="fa-solid fa-file-word"></i> Word</a>
            <button class="el-btn el-btn--ghost" type="button" onclick="apercu_role()" title="Aperçu du PDF"><i class="fa-solid fa-file-pdf"></i> PDF</button>
          @endcan
        </x-slot:actions>
      </x-hero>

      <div class="rl-mise-en-page">

        {{-- ============================ Le rôle, tel qu'il s'imprime ============================ --}}
        <div class="rl-feuille" id="feuille">
          <div class="rl-feuille__entete">
            <div class="rl-feuille__salle">{{ $doc['salle'] ?? '' }}</div>
            <img class="rl-feuille__logo" src="{{ asset('assets/images/role-balance.png') }}" alt="" width="84" height="49">
            <div class="rl-feuille__vide"></div>
          </div>
          <h2 class="rl-feuille__titre">{!! nl2br(e($doc['titre_chambre'] ?? $doc['titre'])) !!}@if (! empty($doc['titre_quand']))<br>{!! nl2br(e($doc['titre_quand'])) !!}@endif</h2>
          @if (! empty($doc['equipe']))
            <div class="rl-equipe"><div>@foreach ($doc['equipe'] as [$libelle, $valeur])<div><b>{{ $libelle }}</b> {{ $valeur ?: '…' }}</div>@endforeach</div>@if (! empty($doc['heure_droite']))<div class="rl-equipe__heure">{{ $doc['heure_droite'] }}</div>@endif</div>
          @endif

          @php $n = 0 @endphp
          @foreach (\App\Models\RoleLigne::TITRES as $code => $titre)
            @php $lignes = $role->lignes->where('rubrique', $code) @endphp
            @continue($lignes->isEmpty() && ! $editable)
            <div class="rl-rubrique">
              <div class="rl-rubrique__bandeau">
                <span class="rl-rubrique__titre"><i class="fa-solid {{ ['deliberes' => 'fa-hourglass-half', 'anciennes' => 'fa-clock-rotate-left', 'nouvelles' => 'fa-file-circle-plus'][$code] }}"></i> {{ $titre }}</span>
                <span class="rl-rubrique__compte">{{ $lignes->count() }}</span>
                @if ($editable)
                  <button type="button" class="rl-mini" onclick="ajouter_ligne('{{ $code }}')" title="Ajouter une affaire à cette rubrique"><i class="fa-solid fa-plus"></i> Ajouter</button>
                @endif
              </div>
              @if ($lignes->isEmpty())
                <div class="rl-rubrique__vide">Aucune affaire dans cette rubrique.</div>
              @else
                <table class="rl-table">
                  <colgroup>@foreach ($doc['colonnes'] as $c)<col style="width:{{ $c['pct'] }}%">@endforeach<col class="c-act"></colgroup>
                  <thead><tr class="rl-colonnes">@foreach ($doc['colonnes'] as $c)<th>{{ $c['titre'] }}</th>@endforeach<th class="rl-colonnes__act"></th></tr></thead>
                  <tbody>
                    @foreach ($lignes as $l)
                      @php
                        $n++;
                        $cellules = \App\Support\Roles\RoleDocument::cellules($l, $role->type, $n, (bool) $role->avec_avocats, count($doc['colonnes']) === 5);
                      @endphp
                      <tr id="ligne-{{ $l->id }}" class="{{ $l->origine === 'libre' && ! $l->numero ? 'is-libre' : '' }}">
                        @foreach ($cellules as $i => $cellule)
                          <td class="{{ $i === 0 ? 'c-n' : '' }}">
                            @if ($i === 0)<span class="rl-num">{{ \App\Support\Roles\RoleDocument::ligneDeCellule($cellule[0])[0] }}</span>@foreach (array_slice($cellule, 1) as $x)<div class="rl-rg">{{ \App\Support\Roles\RoleDocument::ligneDeCellule($x)[0] }}</div>@endforeach
                            @else
                              @forelse ($cellule as $ligne)
                                @php [$texte, $italique, $gras] = \App\Support\Roles\RoleDocument::ligneDeCellule($ligne); @endphp
                                <div class="{{ $gras ? 'rl-gras' : '' }} {{ $italique ? 'rl-ital' : '' }} {{ ($i === 1 && $role->type !== 'penal') ? ($loop->first ? 'rl-rg' : 'rl-rg rl-rg--et') : '' }}">{{ $texte }}</div>
                              @empty
                                @if ($i === 1 && $role->type !== 'penal')<span class="rl-vide">—</span>@endif
                              @endforelse
                            @endif
                            @if ($i === count($cellules) - 1 && $l->issue)
                              <span class="rl-suite rl-suite--{{ $l->issue }}" title="Suite donnée à l'audience (non imprimée)"><i class="fa-solid fa-flag-checkered"></i> {{ \App\Models\RoleLigne::ISSUES[$l->issue] }}{{ $l->renvoi_au ? ' au '.$l->renvoi_au->format('d/m/Y') : '' }}</span>
                            @endif
                          </td>
                        @endforeach
                        <td class="c-act">
                          @if ($peutModifier)
                            <div class="rl-actions rl-actions--ligne">
                              <button type="button" class="rl-iconbtn rl-iconbtn--bleu" title="{{ $publie ? 'Noter la suite donnée' : 'Modifier la ligne' }}" aria-label="Modifier la ligne" onclick="modifier_ligne({{ $l->id }})"><i class="fa-solid {{ $publie ? 'fa-flag' : 'fa-pen' }}"></i></button>
                              @if ($editable)
                                <form method="post" action="{{ route('audiences.roles.ligne.deplacer', [$role->id, $l->id]) }}">@csrf<input type="hidden" name="sens" value="haut"><button class="rl-iconbtn" title="Monter" aria-label="Monter"><i class="fa-solid fa-arrow-up"></i></button></form>
                                <form method="post" action="{{ route('audiences.roles.ligne.deplacer', [$role->id, $l->id]) }}">@csrf<input type="hidden" name="sens" value="bas"><button class="rl-iconbtn" title="Descendre" aria-label="Descendre"><i class="fa-solid fa-arrow-down"></i></button></form>
                                <button type="button" class="rl-iconbtn rl-iconbtn--rouge" title="Retirer du rôle" aria-label="Retirer du rôle" onclick="retirer_ligne({{ $l->id }}, @js($l->numero ?: 'cette ligne'))"><i class="fa-solid fa-trash"></i></button>
                              @endif
                            </div>
                          @endif
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              @endif
            </div>
          @endforeach

          @if ($doc['total'] === 0)
            <div class="rl-feuille__vide-tout"><i class="fa-regular fa-folder-open"></i><b>Ce rôle est encore vide</b><span>Aucune affaire de cette chambre n'est à porter à cette audience pour le moment. Vous pouvez en ajouter une à la main.</span></div>
          @endif

          <div class="rl-signature">
            <div>{{ $doc['signe_prefixe'] ?? 'Fait à ' }}{{ $role->fait_a }}{{ $role->date_signature ? ', le '.$role->date_signature->format('d/m/Y') : '' }}</div>
            <b>{{ $role->signataire }}</b>
          </div>
        </div>

        {{-- ============================ Colonne de droite ============================ --}}
        <aside class="rl-aside">

          <section class="rl-bloc">
            <header class="rl-bloc__tete"><span class="rl-bloc__icone"><i class="fa-solid fa-calendar-day"></i></span><h3>Audience</h3>
              @if ($editable)<button type="button" class="rl-iconbtn rl-iconbtn--vert" onclick="$('#modal-entete').modal('show')" title="Modifier l'en-tête" aria-label="Modifier l'en-tête"><i class="fa-solid fa-pen"></i></button>@endif
            </header>
            <dl class="rl-liste">
              <div><dt>Date</dt><dd>{{ $jour }}</dd></div>
              <div><dt>Heure</dt><dd>{{ $role->heureCourte() ?? 'À fixer' }}</dd></div>
              <div><dt>Salle</dt><dd>{{ $role->salle?->nom ?? 'À fixer' }}</dd></div>
              <div><dt>Avocats</dt><dd>{{ $role->avec_avocats ? 'Affichés sous les parties' : 'Non affichés' }}</dd></div>
              <div><dt>Signé le</dt><dd>{{ $role->date_signature?->format('d/m/Y') ?? '—' }} · {{ $role->signataire }}</dd></div>
            </dl>
            @foreach ($conflits as $c)
              <div class="rl-note rl-note--rouge"><i class="fa-solid fa-triangle-exclamation"></i><span>Conflit de salle : la {{ $c->chambre->libelle }} siège dans la même salle{{ $c->heureCourte() ? ' à '.$c->heureCourte() : '' }}.</span></div>
            @endforeach
            @foreach ($avertissements->groupBy(fn ($x) => $x['autre']->id) as $groupe)
              <div class="rl-note rl-note--orange"><i class="fa-solid fa-user-clock"></i><span><b>Avertissement :</b> {{ $groupe->map(fn ($x) => $x['agent']->nomComplet().' ('.$x['role'].')')->implode(', ') }} {{ $groupe->count() > 1 ? 'figurent aussi' : 'figure aussi' }} dans la composition de la {{ $groupe[0]['autre']->chambre->libelle }}{{ $groupe[0]['autre']->heureCourte() ? ', qui siège à '.$groupe[0]['autre']->heureCourte() : '' }} le même jour. Vérifiez la composition ; la publication n'est pas bloquée.</span></div>
            @endforeach
            @if ($fermeture)
              <div class="rl-note rl-note--orange"><i class="fa-solid fa-calendar-xmark"></i><span>Ce jour tombe pendant « {{ $fermeture->libelle }} » : le tribunal est fermé.</span></div>
            @elseif (! $jourHabituel)
              <div class="rl-note rl-note--bleu"><i class="fa-solid fa-circle-info"></i><span>Ce n'est pas un jour d'audience habituel de la chambre (audience supplémentaire ?).</span></div>
            @endif
          </section>

          @if ($editable)
            <section class="rl-bloc">
              <header class="rl-bloc__tete"><span class="rl-bloc__icone rl-bloc__icone--orange"><i class="fa-solid fa-wand-magic-sparkles"></i></span><h3>Affaires proposées</h3></header>
              @if ($avecPropositions)
                <ul class="rl-propositions">
                  @foreach (\App\Models\RoleLigne::RUBRIQUES as $code => $lib)
                    @if ($propositions[$code])<li><span>{{ $lib }}</span><b>{{ $propositions[$code] }}</b></li>@endif
                  @endforeach
                </ul>
                <form method="post" action="{{ route('audiences.roles.completer', $role->id) }}">@csrf
                  <button class="el-btn el-btn--accent rl-large" type="submit"><i class="fa-solid fa-plus"></i> Ajouter au rôle</button>
                </form>
              @else
                <div class="rl-note rl-note--vert"><i class="fa-solid fa-circle-check"></i><span>Le rôle est complet : toutes les affaires de la chambre à porter à cette audience y figurent.</span></div>
              @endif
            </section>
          @endif

          <section class="rl-bloc">
            <header class="rl-bloc__tete"><span class="rl-bloc__icone rl-bloc__icone--bleu"><i class="fa-solid fa-stamp"></i></span><h3>Publication</h3></header>
            @if ($publie)
              <div class="rl-note rl-note--vert"><i class="fa-solid fa-circle-check"></i><span>Publié le {{ $role->publie_le?->format('d/m/Y à H:i') }}. Le texte est figé ; vous pouvez noter la suite donnée à chaque affaire après l'audience.</span></div>
              @can('roles.publier')
                @can('roles.modifier')
                  <button class="el-btn el-btn--ghost rl-large" type="button" onclick="$('#modal-rouvrir').modal('show')"><i class="fa-solid fa-lock-open"></i> Rouvrir pour corriger</button>
                @endcan
              @endcan
            @else
              <p class="rl-aide">Publiez le rôle quand il est prêt : sa version est conservée, et il peut s'exporter et s'afficher.</p>
              @can('roles.publier')
                <form id="form-publier" method="post" action="{{ route('audiences.roles.publier', $role->id) }}">@csrf<input type="hidden" name="motif" value="{{ $role->version > 1 ? 'Nouvelle version' : '' }}"></form>
                <button class="el-btn el-btn--primary rl-large" type="button" onclick="publier_role()" {{ $doc['total'] === 0 || $conflits->isNotEmpty() ? 'disabled' : '' }}><i class="fa-solid fa-paper-plane"></i> Publier le rôle</button>
                @if ($doc['total'] === 0)<small class="rl-aide">Ajoutez au moins une affaire.</small>@elseif ($conflits->isNotEmpty())<small class="rl-aide">Réglez d'abord le conflit de salle.</small>@endif
              @endcan
              @can('roles.supprimer')
                @if ($role->versions->isEmpty())
                  <form id="form-supprimer" method="post" action="{{ route('audiences.roles.supprimer', $role->id) }}">@csrf</form>
                  <button class="rl-lien rl-lien--rouge" type="button" onclick="supprimer_role()"><i class="fa-solid fa-trash"></i> Supprimer ce rôle</button>
                @endif
              @endcan
            @endif

            @if ($role->versions->isNotEmpty())
              <h4 class="rl-sous-titre">Versions publiées</h4>
              <ul class="rl-versions">
                @foreach ($role->versions as $v)
                  <li>
                    <span class="rl-versions__v">v{{ $v->version }}</span>
                    <span class="rl-versions__t"><b>{{ $v->created_at->format('d/m/Y à H:i') }}</b><small>{{ $v->auteur?->name ?? '—' }}{{ $v->motif ? ' · '.$v->motif : '' }}</small></span>
                    @can('roles.imprimer')
                      <a class="rl-iconbtn rl-iconbtn--orange" href="{{ route('audiences.roles.version', [$role->id, $v->version, 'docx']) }}" title="Word, version {{ $v->version }}" aria-label="Word, version {{ $v->version }}"><i class="fa-solid fa-file-word"></i></a>
                      <a class="rl-iconbtn rl-iconbtn--rouge" href="{{ route('audiences.roles.version', [$role->id, $v->version, 'pdf']) }}" title="PDF, version {{ $v->version }}" aria-label="PDF, version {{ $v->version }}"><i class="fa-solid fa-file-pdf"></i></a>
                    @endcan
                  </li>
                @endforeach
              </ul>
            @endif
          </section>

        </aside>
      </div>
    </div>
  </div>

  @can('roles.imprimer')
    @include('audiences.roles.form_apercu', ['role' => $role])
  @endcan
  @if ($peutModifier)
    @include('audiences.roles.form_entete', ['role' => $role, 'salles' => $salles])
    @include('audiences.roles.form_ligne', ['role' => $role])
    @if ($editable)
      @include('audiences.roles.form_ajout', ['role' => $role])
    @else
      @include('audiences.roles.form_rouvrir', ['role' => $role])
    @endif
  @endif
@endsection

@section('script')
  @if ($peutModifier)
    @include('audiences.roles.js_script_role', ['role' => $role, 'publie' => $publie])
  @endif
  <script>
    document.addEventListener('DOMContentLoaded', function () { if (location.hash) { const e = document.querySelector(location.hash); if (e) { e.classList.add('is-visee'); } } });
  </script>
@endsection
