{{-- Corps du formulaire d'une affaire pénale (RP) ou d'instruction (RI, RE). Variables : $def (famille), $pre (préfixe des identifiants), $defauts, $parties, $modification, $manuel, $modes, $natures, $matieres, $chambres, $nationalites --}}
@php
  $registre = $def['cle'];
  $instruction = $registre === 'instruction';
  $actif = old('registre') === $registre;          // les valeurs et erreurs d'une saisie refusée ne servent qu'à son propre formulaire
  $v = fn ($champ) => $actif ? old($champ, $defauts[$champ] ?? null) : ($defauts[$champ] ?? null);
  if (! $actif) { $errors = new \Illuminate\Support\ViewErrorBag; }
  $principal = $def['principal'];
  $titrePrincipal = $instruction ? 'Les inculpés' : 'Les prévenus';
  $motPrincipal = $instruction ? 'inculpé' : 'prévenu';
@endphp

<section class="aff-bloc">
  <header class="aff-bloc__tete">
    <span class="aff-bloc__icone"><i class="fa-solid {{ $def['icone'] }}"></i></span>
    <div><h3 class="aff-bloc__titre">{{ $instruction ? "L'information" : "L'affaire" }}</h3><p class="aff-bloc__sous">{{ $instruction ? "Réquisitoire ou information judiciaire ouverte au cabinet d'instruction." : 'Saisie du parquet : flagrant délit, citation directe, renvoi…' }}</p></div>
  </header>

  <div class="el-formgrid">
    @if ($manuel && ! $modification)
      <div class="el-c4">
        <label class="form-label" for="{{ $pre }}_numero"><b>Numéro {{ $instruction ? 'RI ou RE' : 'RP' }}</b></label>
        <input class="form-control @error('numero_rg') is-invalid @enderror" id="{{ $pre }}_numero" name="numero_rg" type="text" inputmode="numeric" maxlength="9" value="{{ $v('numero_rg') }}" placeholder="Ex. : 1334/26" required data-numero>
        @error('numero_rg') <div class="aff-erreur">{{ $message }}</div> @enderror
      </div>
      <div class="el-c4">
        <label class="form-label" for="{{ $pre }}_date_rg"><b>Date d'enregistrement</b></label>
        <input class="form-control @error('date_rg') is-invalid @enderror" id="{{ $pre }}_date_rg" name="date_rg" type="date" max="{{ date('Y-m-d') }}" value="{{ $v('date_rg') ?? date('Y-m-d') }}">
        @error('date_rg') <div class="aff-erreur">{{ $message }}</div> @enderror
      </div>
    @endif

    @if ($manuel && ! $modification)
      <div class="el-c12">
        <label class="aff-interrupteur" for="{{ $pre }}_jonction">
          <input type="checkbox" id="{{ $pre }}_jonction" name="jonction" value="1" data-jonction @checked($actif && old('jonction'))>
          <span class="aff-interrupteur__piste" aria-hidden="true"></span>
          <span class="aff-interrupteur__texte"><b>Jonction</b> : ce numéro existe déjà, cette affaire lui est jointe</span>
        </label>
        <div class="aff-alerte-numero" data-alerte-numero aria-live="polite"></div>
      </div>
    @endif

    <div class="el-c4">
      <label class="form-label" for="{{ $pre }}_parquet"><b>N° du parquet</b> <span class="el-muted">(facultatif)</span></label>
      <input class="form-control @error('numero_parquet') is-invalid @enderror" id="{{ $pre }}_parquet" name="numero_parquet" type="text" maxlength="40" value="{{ $v('numero_parquet') }}" placeholder="Numéro du dossier au parquet">
      @error('numero_parquet') <div class="aff-erreur">{{ $message }}</div> @enderror
    </div>
    @if (! $instruction)
      <div class="el-c8">
        <label class="form-label" for="{{ $pre }}_infraction"><b>Infraction</b> <span class="el-muted">(remplit la prévention et les articles)</span></label>
        <select class="form-select" id="{{ $pre }}_infraction" data-infraction="{{ $pre }}">
          <option value="">Autre : je saisis moi-même</option>
          @foreach (\App\Models\Infraction::where('actif', true)->orderBy('libelle')->get() as $inf)
            <option value="{{ $inf->libelle }}" data-articles="{{ $inf->articles }}">{{ $inf->libelle }}{{ $inf->articles ? ' ('.$inf->articles.')' : '' }}</option>
          @endforeach
        </select>
      </div>
    @endif

    <div class="el-c8">
      <label class="form-label" for="{{ $pre }}_objet"><b>{{ $def['objet'] }}</b></label>
      <textarea class="form-control @error('objet') is-invalid @enderror" id="{{ $pre }}_objet" name="objet" rows="2" maxlength="1000" placeholder="{{ $instruction ? 'Ex. : Meurtre, association de malfaiteurs' : 'Ex. : Coups et blessures volontaires sans ITT' }}" required>{{ $v('objet') }}</textarea>
      @error('objet') <div class="aff-erreur">{{ $message }}</div> @enderror
    </div>
    <div class="el-c4">
      <label class="form-label" for="{{ $pre }}_articles"><b>Articles visés</b> <span class="el-muted">(code pénal)</span></label>
      <textarea class="form-control" id="{{ $pre }}_articles" name="articles" rows="2" maxlength="300" placeholder="Ex. : Arts 381-4° et 387 du CP">{{ $v('articles') }}</textarea>
    </div>
    <div class="el-c12 aff-suggestions-ligne">
      <div class="aff-suggestions" data-suggestions aria-live="polite"></div>
    </div>

    <div class="el-c4">
      <label class="form-label" for="{{ $pre }}_mode"><b>Mode de saisine</b></label>
      <select class="form-select @error('mode_saisine_id') is-invalid @enderror" id="{{ $pre }}_mode" name="mode_saisine_id" required>
        <option value="">Choisir…</option>
        @foreach ($modes as $m)
          <option value="{{ $m->id }}" @selected((int) $v('mode_saisine_id') === $m->id)>{{ $m->libelle }}</option>
        @endforeach
      </select>
      @error('mode_saisine_id') <div class="aff-erreur">{{ $message }}</div> @enderror
    </div>

    <div class="el-c4">
      <label class="form-label" for="{{ $pre }}_nature"><b>{{ $instruction ? "Cabinet : adultes ou enfants" : "Nature de l'affaire" }}</b></label>
      <select class="form-select @error('nature_affaire_id') is-invalid @enderror" id="{{ $pre }}_nature" name="nature_affaire_id" required data-nature>
        <option value="">Choisir…</option>
        @foreach ($natures as $n)
          <option value="{{ $n->id }}" data-serie="{{ $n->registre ?? 'RP' }}" @selected((int) $v('nature_affaire_id') === $n->id)>{{ $n->libelle }}</option>
        @endforeach
      </select>
      @error('nature_affaire_id') <div class="aff-erreur">{{ $message }}</div> @enderror
    </div>
    <div class="el-c4">
      <label class="form-label" for="{{ $pre }}_matiere"><b>Matière</b></label>
      <select class="form-select @error('matiere_id') is-invalid @enderror" id="{{ $pre }}_matiere" name="matiere_id" required>
        <option value="">Choisir…</option>
        @foreach ($matieres as $m)
          <option value="{{ $m->id }}" @selected((int) $v('matiere_id') === $m->id)>{{ $m->libelle }}</option>
        @endforeach
      </select>
      @error('matiere_id') <div class="aff-erreur">{{ $message }}</div> @enderror
    </div>
    <div class="el-c4">
      <label class="form-label" for="{{ $pre }}_chambre"><b>{{ $instruction ? 'Cabinet' : 'Chambre' }}</b></label>
      @if ($modification)
        <input class="form-control" type="text" value="{{ $chambres->firstWhere('id', $defauts['chambre_id'] ?? 0)?->libelle }}" disabled>
        <small class="text-muted">Pour en changer, utilisez « Réattribuer » sur la fiche.</small>
      @else
        <select class="form-select @error('chambre_id') is-invalid @enderror" id="{{ $pre }}_chambre" name="chambre_id" required>
          <option value="">Choisir…</option>
          @foreach ($chambres as $c)
            <option value="{{ $c->id }}" @selected((int) $v('chambre_id') === $c->id)>{{ $c->libelle }}{{ $c->intitule ? ' – '.$c->intitule : '' }}</option>
          @endforeach
        </select>
        @error('chambre_id') <div class="aff-erreur">{{ $message }}</div> @enderror
      @endif
    </div>

    <div class="el-c3">
      <label class="form-label" for="{{ $pre }}_audience"><b>Première audience</b> <span class="el-muted">(facultatif)</span></label>
      <input class="form-control @error('date_premiere_audience') is-invalid @enderror" id="{{ $pre }}_audience" name="date_premiere_audience" type="date" value="{{ $v('date_premiere_audience') }}">
      @error('date_premiere_audience') <div class="aff-erreur">{{ $message }}</div> @enderror
    </div>
    <div class="el-c2">
      <label class="form-label" for="{{ $pre }}_heure"><b>Heure</b></label>
      <input class="form-control @error('heure_premiere_audience') is-invalid @enderror" id="{{ $pre }}_heure" name="heure_premiere_audience" type="time" value="{{ substr((string) $v('heure_premiere_audience'), 0, 5) }}">
      @error('heure_premiere_audience') <div class="aff-erreur">{{ $message }}</div> @enderror
    </div>
    @unless ($instruction || $modification)
      {{-- Pénal : les jours et heures d'audience de la chambre choisie, d'après l'ordonnance (pas de date limite d'enrôlement au pénal) --}}
      <div class="el-c12">
        <div class="aff-dates" data-dates-audience aria-live="polite"></div>
      </div>
    @endunless
  </div>
</section>

{{-- Personnes poursuivies, puis victimes et parties civiles --}}
@foreach ([$principal => [$titrePrincipal, $instruction ? 'Au moins un inculpé.' : 'Au moins un prévenu.', 'fa-user-lock', $motPrincipal], 'victime' => ['Victimes et parties civiles', 'Facultatif.', 'fa-user-injured', 'victime']] as $role => [$titre, $aide, $icone, $mot])
  <section class="aff-bloc aff-bloc--{{ $role === 'victime' ? 'demandeur' : 'defendeur' }}">
    <header class="aff-bloc__tete">
      <span class="aff-bloc__icone"><i class="fa-solid {{ $icone }}"></i></span>
      <div><h3 class="aff-bloc__titre">{{ $titre }}</h3><p class="aff-bloc__sous">{{ $aide }}</p></div>
      <button type="button" class="aff-bloc__ajout" onclick="ajouter_partie_poursuite(this, '{{ $role }}')"><i class="fa-solid fa-plus"></i> Ajouter {{ $role === 'victime' ? 'une' : 'un' }} {{ $mot }}</button>
    </header>
    @if ($role === $principal) @error('parties') <div class="aff-erreur">{{ $message }}</div> @enderror @endif
    <div class="aff-liste" data-liste="{{ $role }}" data-defaut="{{ $role === $principal ? 1 : 0 }}">
      @foreach ($parties as $i => $p)
        @if (($p['role'] ?? '') === $role)
          @include('affaires.enrolement.partie_poursuivie', ['i' => $i, 'role' => $role, 'p' => $p, 'nationalites' => $nationalites, 'erreurs' => $errors])
        @endif
      @endforeach
    </div>
  </section>
@endforeach

@foreach ([$principal, 'victime'] as $roleModele)
  <template data-modele-partie="{{ $roleModele }}">
    @include('affaires.enrolement.partie_poursuivie', ['i' => '__I__', 'role' => $roleModele, 'p' => [], 'nationalites' => $nationalites, 'erreurs' => new \Illuminate\Support\ViewErrorBag])
  </template>
@endforeach

@once
  <script>
    /* Choisir une infraction recopie sa nature et ses articles dans les champs de l'affaire (modifiables ensuite). */
    document.addEventListener('change', function (e) {
      var sel = e.target.closest('select[data-infraction]');
      if (!sel || !sel.value) { return; }
      var pre = sel.getAttribute('data-infraction'), opt = sel.options[sel.selectedIndex];
      var objet = document.getElementById(pre + '_objet'), articles = document.getElementById(pre + '_articles');
      if (objet) { objet.value = sel.value; objet.dispatchEvent(new Event('input', { bubbles: true })); }
      if (articles && opt.getAttribute('data-articles')) { articles.value = opt.getAttribute('data-articles'); }
    });
  </script>
@endonce
