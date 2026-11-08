@php
    $moi = auth()->user();

    // Lien actif si la route existe, sinon « # ».
    $lien = fn (?string $nom) => $nom && \Illuminate\Support\Facades\Route::has($nom) ? route($nom) : '#';

    /*
     | Menu. Une entrée n'apparaît que si le profil a le droit (« droit », absent = tous) et si la route existe :
     | les pages des prochains lots (fidèles, paiements, contenu, rapports) apparaîtront d'elles-mêmes
     | dès que leur route portera le nom indiqué ici.
     | icone = nom du pictogramme dans assets/svg/icon-sprite.svg (stroke-<icone> / fill-<icone>).
     */
    $menu = [
        ['titre' => 'Tableau de bord', 'icone' => 'home', 'route' => 'dashboard'],
        ['titre' => 'Administration', 'icone' => 'administration', 'enfants' => [
            ['titre' => 'Utilisateurs', 'enfants' => [
                ['titre' => 'Liste des utilisateurs', 'route' => 'utilisateur.compte', 'droit' => 'comptes.voir'],
                ['titre' => 'Profils et droits', 'route' => 'profils.index', 'droit' => 'profils.voir'],
            ]],
            ['titre' => 'Organisation du tribunal', 'enfants' => [
                ['titre' => 'Année judiciaire', 'route' => 'organisation.annees', 'droit' => 'annees.voir'],
                ['titre' => 'Salles', 'route' => 'organisation.salles', 'droit' => 'salles.voir'],
                ['titre' => 'Magistrats et greffiers', 'route' => 'organisation.personnel', 'droit' => 'personnel.voir'],
                ['titre' => 'Sections', 'route' => 'organisation.sections', 'droit' => 'sections.voir'],
                ['titre' => 'Chambres', 'route' => 'organisation.chambres', 'droit' => 'chambres.voir'],
                ['titre' => 'Services et responsabilités', 'route' => 'organisation.services', 'droit' => 'services.voir'],
                ['titre' => 'Jours fériés et vacances', 'route' => 'organisation.fermetures', 'droit' => 'fermetures.voir'],
                ['titre' => "Dates limites d'enrôlement", 'route' => 'organisation.enrolement', 'droit' => 'delais.voir'],
                ['titre' => 'Reconduire une année', 'route' => 'organisation.reconduction', 'droit' => 'reconduction.voir'],
                ['titre' => 'Archive des ordonnances', 'route' => 'organisation.ordonnances', 'droit' => 'ordonnances.voir'],
            ]],
            ['titre' => 'Référentiels', 'enfants' => [
                ['titre' => 'Types d\'audience', 'route' => 'referentiels.types_audience', 'droit' => 'types_audience.voir'],
                ['titre' => 'Modes de saisine', 'route' => 'referentiels.modes_saisine', 'droit' => 'modes_saisine.voir'],
                ['titre' => 'Matières', 'route' => 'referentiels.matieres', 'droit' => 'matieres.voir'],
                ['titre' => 'Infractions', 'route' => 'referentiels.infractions', 'droit' => 'infractions.voir'],
                ['titre' => 'Natures d\'affaire', 'route' => 'referentiels.natures_affaire', 'droit' => 'natures_affaire.voir'],
                ['titre' => 'Objets du litige', 'route' => 'referentiels.objets_litige', 'droit' => 'objets_litige.voir'],
            ]],
            ['titre' => 'Paramètres et journaux', 'enfants' => [
                ['titre' => 'Réglages', 'route' => 'reglages.index', 'droit' => 'reglages.voir'],
                ['titre' => 'Journal de connexion', 'route' => 'journal.connexions', 'droit' => 'journaux.connexion'],
                ['titre' => "Journal d'audit", 'route' => 'journal.index', 'droit' => 'journaux.audit'],
            ]],
        ]],
        // Entrées de TEST (test => true : affichées même sans page, avec un lien « # »)
        ['titre' => 'Affaires', 'icone' => 'file', 'test' => true, 'enfants' => [
            ['titre' => 'Liste des affaires', 'route' => 'affaires.liste', 'droit' => 'affaires.voir'],
            ['titre' => 'Dossier numérique', 'route' => 'affaires.dossiers', 'droit' => 'dossiers.voir'],
            ['titre' => 'Mise en état', 'route' => 'affaires.mise_en_etat', 'droit' => 'mise_en_etat.voir'],
            ['titre' => 'Requêtes et hérédité', 'route' => 'affaires.requetes', 'droit' => 'requetes.voir'],
            ['titre' => 'Détenus', 'route' => 'affaires.detenus', 'droit' => 'detenus.voir'],
            ['titre' => 'Avocats', 'route' => 'affaires.avocats', 'droit' => 'avocats.voir'],
            ['titre' => "Importation affaire", 'route' => 'affaires.reprise', 'droit' => 'reprise.voir'],
        ]],
        ['titre' => 'Audiences', 'icone' => 'audience-tribunal', 'test' => true, 'enfants' => [
            ['titre' => 'Rôle du jour', 'route' => 'audiences.jour', 'droit' => 'roles.voir'],
            ['titre' => "Rôles d'audience", 'route' => 'audiences.roles', 'sauf' => ['audiences.roles.planning'], 'droit' => 'roles.voir'],
            ['titre' => 'Planning des salles', 'route' => 'audiences.roles.planning', 'droit' => 'roles.voir'],
            ['titre' => "Feuille d'audience", 'route' => 'audiences.feuille', 'droit' => 'feuille.voir'],
            ['titre' => 'Renvois et délibérés', 'route' => 'audiences.renvois', 'droit' => 'renvois.voir'],
            ['titre' => 'Séances hors audience', 'route' => 'audiences.seances', 'droit' => 'seances.voir'],
            ['titre' => "Suites d'audience", 'route' => 'audiences.suites', 'droit' => 'suites.voir'],
            ['titre' => 'Analyse et pilotage', 'route' => 'audiences.pilotage', 'droit' => 'tableau.voir'],
        ]],
        ['titre' => 'Décisions', 'icone' => 'decision', 'test' => true, 'enfants' => [
            ['titre' => 'Modèles de décision', 'route' => 'decisions.modeles', 'droit' => 'modeles.voir'],
            ['titre' => 'Blocs de texte', 'route' => 'decisions.blocs', 'droit' => 'blocs.voir'],
            ['titre' => 'Rédaction', 'route' => 'decisions.redaction', 'droit' => 'redaction.voir'],
            ['titre' => 'Registre des décisions', 'route' => 'decisions.registre', 'droit' => 'registre_decisions.voir'],
            ['titre' => 'Répertoire pénal', 'route' => 'decisions.repertoire_penal', 'droit' => 'repertoire_penal.voir'],
            ['titre' => 'Recours et certificats', 'route' => 'decisions.recours', 'droit' => 'recours.voir'],
        ]],
        ['titre' => 'Instruction et annexes', 'icone' => 'instruction', 'test' => true, 'enfants' => [
            ['titre' => "Dossiers d'instruction", 'route' => 'instruction', 'droit' => 'instruction.voir'],
            ['titre' => 'Assistance judiciaire', 'route' => 'assistance', 'droit' => 'assistance.voir'],
            ['titre' => 'Activités du JAP', 'route' => 'jap', 'droit' => 'jap.voir'],
        ]],
        ['titre' => 'Statistiques', 'icone' => 'statistique', 'test' => true, 'enfants' => [
            ['titre' => 'Statistiques civiles', 'route' => 'statistiques.civil', 'droit' => 'stats_civil.voir'],
            ['titre' => 'Statistiques pénales', 'route' => 'statistiques.penal', 'droit' => 'stats_penal.voir'],
            ['titre' => 'Statistiques de l’instruction', 'route' => 'statistiques.instruction', 'droit' => 'stats_instruction.voir'],
            ['titre' => 'Registres du tribunal', 'route' => 'statistiques.registres', 'droit' => 'stats_registres.voir'],
        ]],
        ['titre' => 'Alertes', 'icone' => 'alerte', 'enfants' => [
            ['titre' => 'Mes notifications', 'route' => 'notifications.index'],
            ['titre' => 'Suivi des alertes', 'route' => 'alertes.suivi', 'droit' => 'alertes.voir'],
            ['titre' => "Règles d'alerte", 'route' => 'alertes.regles', 'droit' => 'regles_alerte.voir'],
            ['titre' => 'Destinataires', 'route' => 'alertes.destinataires', 'droit' => 'regles_alerte.voir'],
            ['titre' => 'Délais', 'route' => 'alertes.delais', 'droit' => ['delais_etape.voir', 'delais_procedure.voir']],
        ]],
        ['titre' => 'Aide', 'icone' => 'aide', 'route' => 'aide.index'],
    ];

    // Entrée active = celle de la page affichée (le script du thème ne le fait que pour le menu vertical).
    $actif = fn (array $entree) => ! empty($entree['route']) && request()->routeIs($entree['route'], $entree['route'].'.*') && (empty($entree['sauf']) || ! request()->routeIs(...$entree['sauf']));

    // « droit » : un droit, ou une liste de droits dont un seul suffit.
    $permis = fn (array $entree) => empty($entree['droit']) || collect((array) $entree['droit'])->contains(fn ($d) => $moi->can($d));
    $existe = fn (array $entree) => ! empty($entree['test']) || (! empty($entree['route']) && \Illuminate\Support\Facades\Route::has($entree['route']));
    // Filtre une liste d'entrées (et ses sous-menus) selon les droits ; un groupe sans entrée visible disparaît.
    $filtrer = function (array $liste) use (&$filtrer, $moi, $permis, $existe) {
        return collect($liste)->map(function ($e) use (&$filtrer, $permis, $existe) {
            if (isset($e['enfants'])) {
                $e['enfants'] = $filtrer($e['enfants'])->all();

                return $e['enfants'] === [] ? null : $e;
            }

            return $permis($e) && $existe($e) ? $e : null;
        })->filter()->values();
    };
    $contientActif = function ($e) use (&$contientActif, $actif) {
        return isset($e['enfants']) ? collect($e['enfants'])->contains(fn ($x) => $contientActif($x)) : $actif($e);
    };
@endphp

<!-- Page Sidebar Start-->
<div class="sidebar-wrapper" data-sidebar-layout="stroke-svg">
    <div>
    <div class="logo-wrapper"><a href="{{ route('dashboard') }}"><img class="max-w-full h-auto for-light" src="{{ asset(config('elouange.logo')) }}" alt="{{ config('app.name') }}"><img class="max-w-full h-auto for-dark" src="{{ asset(config('elouange.logo_sombre')) }}" alt="{{ config('app.name') }}"></a>
        <div class="back-btn hidden lg:block"><i class="fa-solid fa-angle-left"></i></div>
        <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="grid"> </i></div>
    </div>
    <div class="logo-icon-wrapper"><a href="{{ route('dashboard') }}"><img class="max-w-full h-auto" src="{{ asset(config('elouange.logo_icone')) }}" alt="{{ config('app.name') }}"></a></div>
    <nav class="sidebar-main">
        <div id="sidebar-menu">
            <ul class="sidebar-links" id="simple-bar">
                <li class="back-btn"> <a href="{{ route('dashboard') }}"><img class="max-w-full h-auto" src="{{ asset(config('elouange.logo_icone')) }}" alt=""></a>
                    <div class="mobile-back text-end"><span>Retour</span><i class="fa-solid fa-angle-right ps-2" aria-hidden="true"></i></div>
                </li>
                <li class="pin-title sidebar-main-title">
                    <div>
                        <h6>Menu</h6>
                    </div>
                </li>

                @foreach ($menu as $entree)
                    @continue(! $permis($entree))

                    @if (isset($entree['enfants']))
                        @php $enfants = $filtrer($entree['enfants']); @endphp
                        @continue($enfants->isEmpty())

                        <li class="sidebar-list"><i class="fa-solid fa-thumbtack"></i>
                            <a class="sidebar-link sidebar-title {{ $enfants->contains(fn ($e) => $contientActif($e)) ? 'active' : '' }}" href="#">
                                <svg class="stroke-icon"><use href="{{ asset('assets/svg/icon-sprite.svg#stroke-'.$entree['icone']) }}"></use></svg>
                                <svg class="fill-icon"><use href="{{ asset('assets/svg/icon-sprite.svg#fill-'.$entree['icone']) }}"></use></svg>
                                <span>{{ $entree['titre'] }}</span>
                            </a>
                            @include('layout.sidebar_sous_menu', ['enfants' => $enfants])
                        </li>
                    @elseif ($existe($entree))
                        <li class="sidebar-list"><i class="fa-solid fa-thumbtack"></i>
                            <a class="sidebar-link sidebar-title link-nav {{ $actif($entree) ? 'active' : '' }}" href="{{ $lien($entree['route'] ?? null) }}">
                                <svg class="stroke-icon"><use href="{{ asset('assets/svg/icon-sprite.svg#stroke-'.$entree['icone']) }}"></use></svg>
                                <svg class="fill-icon"><use href="{{ asset('assets/svg/icon-sprite.svg#fill-'.$entree['icone']) }}"></use></svg>
                                <span>{{ $entree['titre'] }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach

            </ul>
        </div>
    </nav>
    </div>
</div>
<!-- Page Sidebar Ends-->
