<div class="modal fade panel-modal" id="modal-profil" tabindex="-1" role="dialog" aria-labelledby="titre-modal-profil" aria-hidden="true" data-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="titre-modal-profil">Profil</h4>
        <button class="btn-close py-0" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <form class="modal_form" action="{{ route('profils.store') }}" method="post">
        @csrf
        <input type="hidden" name="id_" value="">

        <div class="modal-body dark-modal">
          <div class="row">
            <div class="col-md-5 mb-2">
              <label class="form-label" for="nom"><b>Nom du profil</b></label>
              <input class="form-control" id="nom" name="nom" type="text" maxlength="80" required placeholder="Ex. : Greffier">
            </div>
            <div class="col-md-7 mb-2">
              <label class="form-label" for="description"><b>Description</b> <span class="text-muted">(facultatif)</span></label>
              <input class="form-control" id="description" name="description" type="text" maxlength="255" placeholder="À quoi sert ce profil ?">
            </div>
          </div>

          <div class="el-page" style="padding-bottom:0; gap:14px">

            {{-- Barre de contrôle : interrupteur général, compteur et rappel des niveaux --}}
            <div class="el-droits__barre">
              <label class="el-switch el-switch--lg" title="Donner le plus haut niveau partout">
                <input type="checkbox" id="droits-tout" onchange="basculer_tout(this.checked)">
                <span class="el-switch__piste"><i></i></span>
                <span class="el-switch__texte">Accès complet</span>
              </label>
              <div class="el-droits__legende">
                <span><b>Lecture</b> : voir</span>
                <span><b>Saisie</b> : voir, créer, modifier</span>
                <span><b>Gestion</b> : tout, y compris supprimer ou annuler</span>
              </div>
              <span class="el-droits__nb" id="compteur-droits">0 / 0</span>
            </div>

            {{-- Un bloc par menu ; dans chaque bloc, une ligne par sous-menu avec UN niveau à choisir --}}
            <div class="el-droits">
              @foreach ($catalogue as $cle => $menu)
                <section class="el-droits__mod" data-menu="{{ $cle }}">
                  <header class="el-droits__tete">
                    <span class="el-droits__nom"><i class="{{ $menu['icone'] }}"></i> {{ $menu['libelle'] }}</span>
                    <label class="el-switch el-switch--sm" title="Donner le plus haut niveau à tout le menu « {{ $menu['libelle'] }} »">
                      <input type="checkbox" class="droits-menu" data-menu="{{ $cle }}" onchange="basculer_menu('{{ $cle }}', this.checked)">
                      <span class="el-switch__piste"><i></i></span>
                    </label>
                  </header>

                  <div class="el-droits__pages">
                    @foreach ($menu['pages'] as $clePage => $page)
                      <div class="el-droits__ligne" data-page="{{ $clePage }}">
                        <div class="el-droits__page">
                          {{ $page['libelle'] }}
                          @if ($page['a_venir'])<span class="el-droits__bientot">à venir</span>@endif
                        </div>

                        {{-- Sélecteur de niveau : un seul choix --}}
                        <div class="el-niveaux" role="radiogroup" aria-label="Niveau d'accès : {{ $page['libelle'] }}">
                          <label class="el-niveau el-niveau--aucun"><input type="radio" name="niveaux[{{ $clePage }}]" value="aucun" checked onchange="synchroniser_droits()"><span>Aucun</span></label>
                          @foreach (array_keys($page['niveaux']) as $niveau)
                            <label class="el-niveau el-niveau--{{ $niveau }}"><input type="radio" name="niveaux[{{ $clePage }}]" value="{{ $niveau }}" onchange="synchroniser_droits()"><span>{{ \App\Support\Droits::NIVEAUX[$niveau] }}</span></label>
                          @endforeach
                        </div>

                        {{-- Droits spéciaux (actions sensibles), actifs seulement si la page est accessible --}}
                        @if ($page['speciales'])
                          <div class="el-droits__actions el-droits__speciales">
                            <span class="el-droits__plus">En plus :</span>
                            @foreach ($page['speciales'] as $droit => $libelle)
                              <label class="el-actionpill">
                                <input type="checkbox" name="speciales[]" value="{{ $droit }}" onchange="synchroniser_droits()">
                                <span><i class="fa-solid fa-check"></i>&nbsp;{{ $libelle }}</span>
                              </label>
                            @endforeach
                          </div>
                        @endif
                      </div>
                    @endforeach
                  </div>
                </section>
              @endforeach
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary text-white" type="button" data-bs-dismiss="modal">Annuler</button>
          <button class="btn btn-primary text-white submit" id="BtnEnregistrer" type="submit">Enregistrer</button>
        </div>
      </form>

    </div>
  </div>
</div>
