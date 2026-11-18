{{-- Contenu de la grande fenêtre « Composer » d'une chambre (fragment HTML chargé en AJAX). Variable : $chambre (formations, affectations et audiences chargées).
     À gauche : la synthèse (effectifs, formations, attributions). À droite : pour la formation choisie, l'onglet « Qui siège » et l'onglet « Quand elle siège ». --}}
@php
  $aujourdhui = now()->startOfDay();
  $etat = function ($a) use ($aujourdhui) {
      if ($a->fin && $a->fin->lt($aujourdhui)) { return ['Terminée', 'neutral']; }
      if ($a->debut && $a->debut->gt($aujourdhui)) { return ['À venir', 'info']; }
      return ['En cours', 'success'];
  };
  $periode = function ($a) {
      if ($a->debut && $a->fin) { return 'Du '.$a->debut->format('d/m/Y').' au '.$a->fin->format('d/m/Y'); }
      if ($a->debut) { return 'Depuis le '.$a->debut->format('d/m/Y'); }
      if ($a->fin) { return "Jusqu'au ".$a->fin->format('d/m/Y'); }
      return 'Sans date';
  };
  $enCours = $chambre->formations->flatMap->affectations->filter(fn ($a) => $etat($a)[0] === 'En cours');
  $nbGreffiers = $enCours->where('role', 'greffier')->where('suppleant', false)->count();
  $nbSuppleants = $enCours->where('suppleant', true)->count();
  $nbTitulaires = $enCours->where('suppleant', false)->where('role', '!=', 'greffier')->count();
  $plusieurs = $chambre->formations->count() > 1;
  $nomFormation = fn ($formation, $rang) => $formation->nom ?: ($plusieurs ? 'Composition '.$rang : 'Composition de la chambre');
@endphp

<div class="comp-detail" data-chambre="{{ $chambre->id }}">

  {{-- ========== Synthèse ========== --}}
  <aside class="comp-lateral">
    <div class="comp-stats">
      <div class="comp-stat"><b>{{ $nbTitulaires }}</b><span>Titulaires</span></div>
      <div class="comp-stat"><b>{{ $nbSuppleants }}</b><span>Suppléants</span></div>
      <div class="comp-stat"><b>{{ $nbGreffiers }}</b><span>Greffiers</span></div>
    </div>

    @if ($plusieurs)
      <div>
        <div class="comp-lateral__rub" style="margin-bottom:8px">Formations ({{ $chambre->formations->count() }})</div>
        <div class="comp-formations">
          @foreach ($chambre->formations as $formation)
            <button type="button" class="el-pill {{ $loop->first ? 'is-active' : '' }}" data-formation="{{ $formation->id }}">{{ $nomFormation($formation, $loop->iteration) }}</button>
          @endforeach
        </div>
      </div>
    @endif

    @if ($chambre->groupe || $chambre->attributions || $chambre->remarque)
      {{-- Ouvertes par défaut (zone défilante si le texte est long) --}}
      <details class="comp-attrib" open>
        <summary><i class="fa-solid fa-gavel"></i> Attributions de la chambre</summary>
        @if ($chambre->groupe)<div class="comp-aide" style="margin-bottom:6px"><b>{{ $chambre->groupe }}</b></div>@endif
        @if ($chambre->attributions)<div class="el-riche">{!! \App\Support\TexteRiche::html($chambre->attributions) !!}</div>@endif
        @if ($chambre->remarque)<div class="el-riche" style="margin-top:8px"><b>À noter :</b>{!! \App\Support\TexteRiche::html($chambre->remarque) !!}</div>@endif
      </details>
    @endif

    <p class="comp-aide">Pour un départ ou un changement en cours d'année, utilisez « Remplacer » : l'ancienne affectation est terminée et l'historique est conservé.</p>
  </aside>

  {{-- ========== Composition ========== --}}
  <div class="comp-principal">
    @forelse ($chambre->formations as $formation)
      <div class="el-card comp-formation" data-formation="{{ $formation->id }}" @if (! $loop->first) hidden @endif>
        @if ($plusieurs)
          <div class="comp-formation__tete">
            <h3>{{ $nomFormation($formation, $loop->iteration) }} @unless ($formation->actif)<x-chip tone="neutral" label="Inactive" />@endunless</h3>
          </div>
        @endif
        @if ($formation->attributions)
          <details class="comp-attrib" style="margin-top:{{ $plusieurs ? '0' : '16px' }}">
            <summary><i class="fa-solid fa-gavel"></i> Attributions de cette formation</summary>
            <div class="el-riche">{!! \App\Support\TexteRiche::html($formation->attributions) !!}</div>
          </details>
        @endif

        <div class="comp-onglets" role="tablist">
          <button type="button" class="comp-onglet is-actif" role="tab" data-cible="personnes"><i class="fa-solid fa-users"></i> Qui siège <span class="comp-onglet__nb">{{ $formation->affectations->count() }}</span></button>
          <button type="button" class="comp-onglet" role="tab" data-cible="audiences"><i class="fa-regular fa-clock"></i> Quand elle siège <span class="comp-onglet__nb">{{ $formation->creneaux->count() }}</span></button>
        </div>

        {{-- ========== Qui siège ========== --}}
        <div class="comp-pane" data-pane="personnes">
          @can('affectations.creer')
            <div class="comp-pane__barre">
              <button class="el-btn el-btn--primary el-btn--sm" type="button" onclick="add_form({{ $formation->id }})"><i class="fa-solid fa-user-plus"></i> Ajouter une personne</button>
            </div>
          @endcan

          @forelse ($formation->affectations->groupBy('role') as $role => $groupe)
            <div class="comp-groupe">
              <div class="comp-groupe__titre">{{ \App\Models\Affectation::ROLES[$role] ?? $role }} <span>{{ $groupe->count() }}</span></div>
              @foreach ($groupe as $a)
                @php [$libelleEtat, $tonEtat] = $etat($a); @endphp
                <div class="comp-pers" id="row-{{ $a->id }}">
                  @if ($a->agent)<x-avatar :name="$a->agent->nom.' '.$a->agent->prenoms" :size="38" />@endif
                  <div class="comp-pers__id">
                    @if ($a->agent)
                      <button type="button" class="comp-lien comp-pers__nom" title="Voir la fiche" onclick="voir_agent({{ $a->agent_id }})">{{ $a->agent->nomComplet() }}</button>
                    @endif
                    <div class="comp-pers__sub">{{ $a->agent?->fonctionLibelle() }}@if ($a->remarque) · {{ $a->remarque }}@endif</div>
                  </div>
                  <div class="comp-pers__statut">
                    <x-chip :tone="$a->suppleant ? 'warning' : 'success'" :label="$a->suppleant ? 'Suppléant' : 'Titulaire'" />
                    @if ($a->chef_section)<x-chip tone="purple" label="Chef de section" />@endif
                    @if ($libelleEtat !== 'En cours')<x-chip :tone="$tonEtat" :label="$libelleEtat" />@endif
                  </div>
                  <div class="comp-pers__periode">{{ $periode($a) }}</div>
                  @canany(['affectations.modifier', 'affectations.supprimer'])
                    <div class="comp-pers__actions">
                      @can('affectations.modifier')
                        <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier" onclick="edit_affectation({{ $a->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                        @can('affectations.creer')
                          @if ($libelleEtat !== 'Terminée')
                            <button class="el-iconbtn el-iconbtn--teal" type="button" title="Remplacer" aria-label="Remplacer {{ $a->agent?->nomComplet() }}" onclick="remplacer_affectation({{ $a->id }}, @js($a->agent?->nomComplet()))"><i class="fa-solid fa-right-left"></i></button>
                          @endif
                        @endcan
                      @endcan
                      @can('affectations.supprimer')
                        <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer" onclick="delete_affectation({{ $a->id }})"><i class="fa-regular fa-trash-can"></i></button>
                      @endcan
                    </div>
                  @endcanany
                </div>
              @endforeach
            </div>
          @empty
            <div class="comp-vide"><i class="fa-regular fa-folder-open"></i> Aucune personne affectée pour le moment.</div>
          @endforelse
        </div>

        {{-- ========== Quand elle siège ========== --}}
        <div class="comp-pane" data-pane="audiences" hidden>
          @can('creneaux.creer')
            <div class="comp-pane__barre">
              <button class="el-btn el-btn--primary el-btn--sm" type="button" onclick="add_creneau({{ $formation->id }})"><i class="fa-solid fa-plus"></i> Ajouter une audience</button>
            </div>
          @endcan

          @forelse ($formation->creneaux->sortBy([fn ($a, $b) => ($a->jour ?? 9) <=> ($b->jour ?? 9), fn ($a, $b) => strcmp((string) $a->heure, (string) $b->heure)]) as $c)
            @php
              $termine = $c->fin && $c->fin->lt($aujourdhui);
              $aVenir = $c->debut && $c->debut->gt($aujourdhui);
            @endphp
            <div class="comp-aud {{ $termine ? 'is-termine' : '' }}" id="creneau-{{ $c->id }}">
              <span class="comp-aud__icone"><i class="fa-regular fa-clock"></i></span>
              <div class="comp-aud__corps">
                <div class="comp-aud__type">{{ $c->type }}</div>
                <div class="comp-aud__quand">{{ $c->quand() }}@if ($c->salle) · {{ $c->salle->nom }}@endif</div>
                @if ($c->precision)<div class="comp-aud__sub">{{ $c->precision }}</div>@endif
                @if ($c->frequence !== 'ponctuelle' && ($c->debut || $c->fin))<div class="comp-aud__sub">@if ($c->debut)Du {{ $c->debut->format('d/m/Y') }} @endif @if ($c->fin)jusqu'au {{ $c->fin->format('d/m/Y') }}@endif</div>@endif
              </div>
              @if ($termine)<x-chip tone="neutral" label="Terminée" />@elseif ($aVenir)<x-chip tone="info" label="À venir" />@endif
              @canany(['creneaux.modifier', 'creneaux.supprimer'])
                <div class="comp-pers__actions">
                  @can('creneaux.modifier')
                    <button class="el-iconbtn el-iconbtn--blue" type="button" title="Modifier" aria-label="Modifier l'audience" onclick="edit_creneau({{ $c->id }})"><i class="fa-regular fa-pen-to-square"></i></button>
                  @endcan
                  @can('creneaux.supprimer')
                    <button class="el-iconbtn el-iconbtn--red" type="button" title="Supprimer" aria-label="Supprimer l'audience" onclick="delete_creneau({{ $c->id }})"><i class="fa-regular fa-trash-can"></i></button>
                  @endcan
                </div>
              @endcanany
            </div>
          @empty
            <div class="comp-vide"><i class="fa-regular fa-calendar-xmark"></i> Aucune audience enregistrée.</div>
          @endforelse
        </div>
      </div>
    @empty
      <div class="el-card">
        <div class="el-card__body">
          <span class="el-muted">Cette chambre n'a pas encore de formation : créez-la depuis la page « Chambres » (colonne Formations), puis revenez composer.</span>
        </div>
      </div>
    @endforelse
  </div>

</div>
