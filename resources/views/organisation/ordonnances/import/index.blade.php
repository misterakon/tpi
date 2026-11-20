@extends('layout.master')
@section('title', "Charger l'ordonnance")

@section('css')
  <link rel="stylesheet" href="{{ asset('assets/css/elouange-theme.css') }}?v={{ filemtime(public_path('assets/css/elouange-theme.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/affaires.css') }}?v={{ filemtime(public_path('assets/css/affaires.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/import-ordonnance.css') }}?v={{ filemtime(public_path('assets/css/import-ordonnance.css')) }}">
@endsection

@section('main-content')
  <div class="page-body io-page" id="io-racine">
    <div class="el-page tp-anime">

      <x-hero tag="Organisation" title="Charger l'ordonnance par fichier Excel" subtitle="Au lieu de remplir les formulaires un à un : téléchargez le fichier de l'organisation en cours, corrigez-le (ou complétez-le avec la nouvelle ordonnance), puis rechargez-le. Il est d'abord contrôlé, rien n'est modifié tant que vous n'avez pas vu ce qui va changer." icon="fa-solid fa-file-import">
        <x-slot:extra>
          <span><i class="fa-solid fa-shield-halved"></i> Rien n'est modifié avant votre accord</span>
          <span><i class="fa-solid fa-user-check"></i> Noms douteux : jamais devinés</span>
          <span><i class="fa-solid fa-rotate-left"></i> Import annulable</span>
        </x-slot:extra>
      </x-hero>

      @include('organisation.ordonnances._onglets', ['actif' => 'import'])

      <div class="el-card" style="margin-bottom:20px">
        <div class="el-card__body" style="padding:22px 24px">
          <form method="post" action="{{ route('organisation.ordonnances.import.store') }}" enctype="multipart/form-data" id="f-depot" data-io-depot>
            @csrf
            <label class="io-depot" for="fichier" id="zone-depot">
              <i class="fa-solid fa-cloud-arrow-up io-depot__icone"></i>
              <span class="io-depot__titre">Déposez le fichier Excel de l'ordonnance ici</span>
              <span class="io-depot__aide">ou cliquez pour le choisir (.xlsx, 10 Mo au plus)</span>
              <span class="io-depot__nom" id="nom-fichier"></span>
              <input type="file" name="fichier" id="fichier" accept=".xlsx" required>
            </label>
            @error('fichier')<div class="aff-erreur" style="margin-top:10px">{{ $message }}</div>@enderror

            <div class="io-modes">
              @foreach ($modes as $cle => $libelle)
                <label class="io-mode">
                  <input type="radio" name="mode" value="{{ $cle }}" @checked(old('mode', 'maj') === $cle)>
                  <span>
                    <span class="io-mode__titre">{{ $cle === 'maj' ? 'Mise à jour' : 'Ordonnance complète' }}</span>
                    <span class="io-mode__aide">{{ $cle === 'maj'
                        ? "Ajoute et modifie ce que contient le fichier. Le reste de l'organisation ne bouge pas (ordonnance modificative, correction)."
                        : "Le fichier est l'ordonnance entière : ce qui n'y figure plus est clôturé avec une date de fin, jamais supprimé (nouvelle ordonnance annuelle)." }}</span>
                  </span>
                </label>
              @endforeach
            </div>

            <div class="io-actions">
              <span class="el-muted"><i class="fa-solid fa-circle-info"></i> Le fichier est contrôlé en quelques secondes. Rien n'est enregistré dans l'organisation à cette étape.</span>
              <button class="el-btn el-btn--accent" type="submit"><i class="fa-solid fa-magnifying-glass-chart"></i> Charger et contrôler</button>
            </div>
          </form>
        </div>
      </div>

      <div class="el-card">
        <div class="el-toolbar"><span class="el-muted">Derniers chargements</span></div>
        @if ($imports->isEmpty())
          <div class="io-vide" style="margin:18px 24px">Aucun fichier n'a encore été chargé.</div>
        @else
          <div class="el-tblwrap">
            <table class="el-tbl">
              <thead><tr><th>Date</th><th>Fichier</th><th>Mode</th><th>Par</th><th>État</th><th style="width:70px"></th></tr></thead>
              <tbody>
                @foreach ($imports as $i)
                  @php $ton = ['brouillon' => 'warning', 'applique' => 'success', 'annule' => 'neutral', 'abandonne' => 'neutral'][$i->statut] ?? 'neutral'; @endphp
                  <tr>
                    <td>{{ $i->created_at?->format('d/m/Y H:i') }}</td>
                    <td><b>{{ $i->nom_fichier }}</b></td>
                    <td>{{ $i->mode === 'complet' ? 'Ordonnance complète' : 'Mise à jour' }}</td>
                    <td>{{ $i->auteur?->name ?? '—' }}</td>
                    <td><x-chip :tone="$ton" :label="\App\Models\ImportOrdonnance::STATUTS[$i->statut] ?? $i->statut" /></td>
                    <td><a class="el-iconbtn el-iconbtn--blue" data-io-nav href="{{ route('organisation.ordonnances.import.show', $i->id) }}" title="{{ $i->enCours() ? 'Reprendre' : 'Voir le bilan' }}" aria-label="Ouvrir"><i class="fa-solid {{ $i->enCours() ? 'fa-arrow-right' : 'fa-eye' }}"></i></a></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

    </div>
  </div>
  @include('organisation.ordonnances.import._chargeur')
@endsection

@section('script')
  <script src="{{ asset('assets/js/import-ordonnance.js') }}?v={{ filemtime(public_path('assets/js/import-ordonnance.js')) }}"></script>
@endsection
