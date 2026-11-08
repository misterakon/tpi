{{-- Corps du formulaire d'une affaire (saisie dans la fenêtre de la liste, et correction). Variables : $defauts (valeurs par défaut), $parties, $modification (bool), $manuel (bool), $modes, $natures, $matieres, $chambres, $nationalites --}}
@php
  $actif = old('registre', 'civil') === 'civil';   // les valeurs et erreurs d'une saisie refusée ne servent qu'à son propre formulaire
  $v = fn ($champ) => $actif ? old($champ, $defauts[$champ] ?? null) : ($defauts[$champ] ?? null);
  if (! $actif) { $errors = new \Illuminate\Support\ViewErrorBag; }
  $peutDeroger = \Illuminate\Support\Facades\Gate::allows('enrolement.deroger');
@endphp

<section class="aff-bloc">
  <header class="aff-bloc__tete">
    <span class="aff-bloc__icone"><i class="fa-solid fa-folder-open"></i></span>
    <div><h3 class="aff-bloc__titre">L'affaire</h3><p class="aff-bloc__sous">L'objet se saisit librement : l'application propose la nature, la matière et la chambre.</p></div>
  </header>

  <div class="el-formgrid">
    @if ($manuel && ! $modification)
      {{-- Numéro du greffe, reporté à la main : un doublon est bloqué, sauf jonction --}}
      <div class="el-c4">
        <label class="form-label" for="f_numero_rg"><b>Numéro RG</b></label>
        <input class="form-control @error('numero_rg') is-invalid @enderror" id="f_numero_rg" name="numero_rg" type="text" inputmode="numeric" maxlength="9" value="{{ $v('numero_rg') }}" placeholder="Ex. : 1391/25" required>
        @error('numero_rg') <div class="aff-erreur">{{ $message }}</div> @enderror
      </div>
      <div class="el-c4">
        <label class="form-label" for="f_date_rg"><b>Date du RG</b></label>
        <input class="form-control @error('date_rg') is-invalid @enderror" id="f_date_rg" name="date_rg" type="date" max="{{ date('Y-m-d') }}" value="{{ $v('date_rg') ?? date('Y-m-d') }}">
        @error('date_rg') <div class="aff-erreur">{{ $message }}</div> @enderror
      </div>
    @endif

    <div class="el-c4">
      <label class="form-label" for="f_mode"><b>Mode de saisine</b></label>
      <select class="form-select @error('mode_saisine_id') is-invalid @enderror" id="f_mode" name="mode_saisine_id" required>
        <option value="">Choisir…</option>
        @foreach ($modes as $m)
          <option value="{{ $m->id }}" @selected((int) $v('mode_saisine_id') === $m->id)>{{ $m->libelle }}</option>
        @endforeach
      </select>
      @error('mode_saisine_id') <div class="aff-erreur">{{ $message }}</div> @enderror
    </div>

    @if ($manuel && ! $modification)
      <div class="el-c12">
        <label class="aff-interrupteur" for="f_jonction">
          <input type="checkbox" id="f_jonction" name="jonction" value="1" @checked($actif && old('jonction'))>
          <span class="aff-interrupteur__piste" aria-hidden="true"></span>
          <span class="aff-interrupteur__texte"><b>Jonction</b> : ce numéro existe déjà, cette affaire lui est jointe</span>
        </label>
        <div id="alerte-numero" class="aff-alerte-numero" aria-live="polite"></div>
      </div>
    @endif

    <div class="el-c12">
      <label class="form-label" for="f_objet"><b>Objet du litige</b></label>
      <textarea class="form-control @error('objet') is-invalid @enderror" id="f_objet" name="objet" rows="2" maxlength="1000" placeholder="Ex. : Demande en paiement de loyers impayés et en expulsion" required>{{ $v('objet') }}</textarea>
      @error('objet') <div class="aff-erreur">{{ $message }}</div> @enderror
      <div id="suggestions" class="aff-suggestions" aria-live="polite"></div>
    </div>

    <div class="el-c4">
      <label class="form-label" for="f_nature"><b>Nature de l'affaire</b></label>
      <select class="form-select @error('nature_affaire_id') is-invalid @enderror" id="f_nature" name="nature_affaire_id" required>
        <option value="">Choisir…</option>
        @foreach ($natures as $n)
          <option value="{{ $n->id }}" @selected((int) $v('nature_affaire_id') === $n->id)>{{ $n->libelle }}</option>
        @endforeach
      </select>
      @error('nature_affaire_id') <div class="aff-erreur">{{ $message }}</div> @enderror
    </div>
    <div class="el-c4">
      <label class="form-label" for="f_matiere"><b>Matière</b></label>
      <select class="form-select @error('matiere_id') is-invalid @enderror" id="f_matiere" name="matiere_id" required>
        <option value="">Choisir…</option>
        @foreach ($matieres as $m)
          <option value="{{ $m->id }}" @selected((int) $v('matiere_id') === $m->id)>{{ $m->libelle }}</option>
        @endforeach
      </select>
      @error('matiere_id') <div class="aff-erreur">{{ $message }}</div> @enderror
    </div>
    <div class="el-c4">
      <label class="form-label" for="f_chambre"><b>Chambre</b></label>
      @if ($modification)
        <input class="form-control" type="text" value="{{ $chambres->firstWhere('id', $defauts['chambre_id'] ?? 0)?->libelle }}" disabled>
        <small class="text-muted">Pour changer de chambre, utilisez « Réattribuer » sur la fiche.</small>
        <input type="hidden" id="f_chambre" value="{{ $defauts['chambre_id'] ?? '' }}">
      @else
        <select class="form-select @error('chambre_id') is-invalid @enderror" id="f_chambre" name="chambre_id" required>
          <option value="">Choisir…</option>
          @foreach ($chambres as $c)
            <option value="{{ $c->id }}" @selected((int) $v('chambre_id') === $c->id)>{{ $c->libelle }}{{ $c->intitule ? ' – '.$c->intitule : '' }}</option>
          @endforeach
        </select>
        @error('chambre_id') <div class="aff-erreur">{{ $message }}</div> @enderror
      @endif
    </div>

    <div class="el-c3">
      <label class="form-label" for="f_date_audience"><b>Première audience</b> @if ($manuel)<span class="el-muted">(facultatif)</span>@endif</label>
      <input class="form-control @error('date_premiere_audience') is-invalid @enderror" id="f_date_audience" name="date_premiere_audience" type="date" @unless ($manuel) min="{{ date('Y-m-d') }}" @endunless value="{{ $v('date_premiere_audience') }}">
      @error('date_premiere_audience') <div class="aff-erreur">{{ $message }}</div> @enderror
    </div>
    <div class="el-c2">
      <label class="form-label" for="f_heure_audience"><b>Heure</b></label>
      <input class="form-control @error('heure_premiere_audience') is-invalid @enderror" id="f_heure_audience" name="heure_premiere_audience" type="time" value="{{ substr((string) $v('heure_premiere_audience'), 0, 5) }}">
      @error('heure_premiere_audience') <div class="aff-erreur">{{ $message }}</div> @enderror
    </div>
    <div class="el-c12">
      <div class="aff-dates" id="dates-audience" data-manuel="{{ $manuel ? 1 : 0 }}" aria-live="polite"></div>
    </div>

    @if (! $manuel && $errors->has('date_premiere_audience') && (session('derogation') || old('derogation_motif')))
      @if ($peutDeroger)
        <div class="el-c12 aff-derogation">
          <label class="form-label" for="f_derogation"><b>Dérogation de la Présidente</b> — motif</label>
          <textarea class="form-control" id="f_derogation" name="derogation_motif" rows="2" maxlength="300" placeholder="Ex. : affaire urgente, autorisée par la Présidente">{{ old('derogation_motif') }}</textarea>
          <small class="text-muted">La date limite d'enrôlement est dépassée : en indiquant un motif et en enregistrant à nouveau, l'affaire est enrôlée à cette date. La dérogation est tracée.</small>
        </div>
      @endif
    @endif
  </div>
</section>

{{-- Demandeurs et défendeurs : deux blocs séparés --}}
@foreach (['demandeur' => ['Les demandeurs', 'Au moins un demandeur.', 'fa-gavel', 'demandeur'], 'defendeur' => ['Les défendeurs', 'Facultatif : une requête n\'a pas de défendeur.', 'fa-shield-halved', 'défendeur']] as $role => [$titre, $aide, $icone, $mot])
  <section class="aff-bloc aff-bloc--{{ $role }}">
    <header class="aff-bloc__tete">
      <span class="aff-bloc__icone"><i class="fa-solid {{ $icone }}"></i></span>
      <div><h3 class="aff-bloc__titre">{{ $titre }}</h3><p class="aff-bloc__sous">{{ $aide }}</p></div>
      <button type="button" class="aff-bloc__ajout" onclick="ajouter_partie('{{ $role }}')"><i class="fa-solid fa-plus"></i> Ajouter un {{ $mot }}</button>
    </header>
    @if ($role === 'demandeur') @error('parties') <div class="aff-erreur">{{ $message }}</div> @enderror @endif
    <div class="aff-liste" id="parties-{{ $role }}" data-defaut="1">
      @foreach ($parties as $i => $p)
        @if (($p['role'] ?? '') === $role)
          @include('affaires.enrolement.partie', ['i' => $i, 'role' => $role, 'p' => $p, 'nationalites' => $nationalites, 'erreurs' => $errors])
        @endif
      @endforeach
    </div>
  </section>
@endforeach

<template id="modele-partie">
  @include('affaires.enrolement.partie', ['i' => '__I__', 'role' => '__ROLE__', 'p' => [], 'nationalites' => $nationalites, 'erreurs' => new \Illuminate\Support\ViewErrorBag])
</template>
