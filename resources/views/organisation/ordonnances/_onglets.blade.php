{{-- Navigation commune aux pages de l'ordonnance. Variable : $actif (archive, articles, import) --}}
@php $actif = $actif ?? 'archive'; @endphp
<nav class="io-onglets" aria-label="Pages de l'ordonnance">
  <a class="io-onglet {{ $actif === 'archive' ? 'is-actif' : '' }}" href="{{ route('organisation.ordonnances') }}"><i class="fa-solid fa-box-archive"></i> Archive des ordonnances</a>
  <a class="io-onglet {{ $actif === 'articles' ? 'is-actif' : '' }}" href="{{ route('organisation.ordonnances.articles') }}"><i class="fa-solid fa-scroll"></i> Articles</a>
  @can('ordonnances.importer')
    <a class="io-onglet {{ $actif === 'import' ? 'is-actif' : '' }}" href="{{ route('organisation.ordonnances.import') }}"><i class="fa-solid fa-file-import"></i> Charger un fichier Excel</a>
  @endcan
  <a class="io-onglet io-onglet--droite" href="{{ route('organisation.ordonnances.excel') }}" title="Télécharge l'organisation en cours au format du fichier d'ordonnance : on le corrige puis on le recharge"><i class="fa-solid fa-file-excel"></i> Télécharger le fichier Excel</a>
</nav>
