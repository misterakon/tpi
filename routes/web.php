<?php

use App\Http\Controllers\{
    AccueilController,
    AideController,
    AlertesController,
    CompteController,
    DelaisEtapeController,
    JournalController,
    NotificationsController,
    ProfilController,
    ProfileController,
    RechercheController,
    ReglageController,
    ReglesAlerteController,
};
use App\Http\Controllers\Affaires\{
    AffaireController,
    AvocatController,
    DetenuController,
    DossierNumeriqueController,
    EnrolementController,
    MiseEnEtatController,
    RepriseController,
    RequeteController,
    ScelleController,
};
use App\Http\Controllers\Annexes\{
    AssistanceJudiciaireController,
    JapController,
};
use App\Http\Controllers\Audiences\{
    FeuilleAudienceController,
    PilotageController,
    RenvoiController,
    RoleAudienceController,
    RoleDuJourController,
    SeanceHorsAudienceController,
    SuitesAudienceController,
    TableauAudiencesController,
};
use App\Http\Controllers\Auth\{
    LoginController,
    PasswordResetController,
    TwoFactorController,
    VerrouillageController,
};
use App\Http\Controllers\Decisions\{
    BlocTexteController,
    ModeleDecisionController,
    RecoursController,
    RedactionController,
    RegistreDecisionsController,
    RepertoirePenalController,
};
use App\Http\Controllers\Instruction\InstructionController;
use App\Http\Controllers\Organisation\{
    AffectationController,
    AgentController,
    AnneeJudiciaireController,
    ChambreController,
    CreneauController,
    DelaiEnrolementController,
    FermetureController,
    FormationController,
    OrdonnanceController,
    ReconductionController,
    SalleController,
    SectionController,
    ServiceController,
};
use App\Http\Controllers\Referentiels\{
    DelaiProcedureController,
    InfractionController,
    MatiereController,
    ModeSaisineController,
    NatureAffaireController,
    ObjetLitigeController,
    TypeAudienceController,
};
use App\Http\Controllers\Statistiques\{
    CivilController,
    InstructionController as StatistiquesInstructionController,
    PenalController,
    RegistresController,
};
use Illuminate\Support\Facades\Route;

// Redirections par route nommée (et non Route::redirect) : elles gardent le sous-dossier /tpi de l'adresse.
Route::get('/', fn () => redirect()->route('login'));

// Anciennes adresses (menu « Équipe », renommé « Utilisateurs ») : les favoris continuent de fonctionner.
Route::get('/equipe/comptes', fn () => redirect()->route('utilisateur.compte', status: 301));
Route::get('/equipe/profils', fn () => redirect()->route('profils.index', status: 301));

/* ---------- Visiteurs non connectés ---------- */
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:connexion');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:mot-de-passe')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'store'])->middleware('throttle:mot-de-passe')->name('password.store');
});

/* ---------- Code de double authentification (mot de passe déjà validé, pas encore connecté) ---------- */
Route::middleware('guest')->group(function () {
    Route::get('/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
    Route::post('/two-factor', [TwoFactorController::class, 'verify'])->middleware('throttle:double-auth')->name('two-factor.verify');
    Route::post('/two-factor/secours', [TwoFactorController::class, 'secours'])->middleware('throttle:double-auth')->name('two-factor.secours');
    Route::post('/two-factor/resend', [TwoFactorController::class, 'resend'])->middleware('throttle:double-auth')->name('two-factor.resend');
});

/* ---------- Utilisateur connecté : écran de verrouillage et déconnexion (accessibles même session verrouillée) ---------- */
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/verrouille', [VerrouillageController::class, 'show'])->name('verrouillage.show');
    Route::post('/verrouille', [VerrouillageController::class, 'unlock'])->middleware('throttle:deverrouillage')->name('verrouillage.unlock');
    Route::post('/verrouiller', [VerrouillageController::class, 'lock'])->name('verrouillage.lock');
});

/* ---------- Utilisateurs connectés (session non verrouillée) ---------- */
Route::middleware(['auth', 'auth.session', 'inactivite'])->group(function () {
    Route::post('/session/actif', [VerrouillageController::class, 'ping'])->name('session.ping');

    Route::get('/accueil', [AccueilController::class, 'index'])->name('dashboard');
    Route::get('/accueil/donnees', [AccueilController::class, 'donnees'])->middleware('throttle:60,1')->name('dashboard.donnees');

    /* Notifications de l'utilisateur (cloche de l'en-tête) : chacun ne voit que les siennes. */
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/resume', [NotificationsController::class, 'resume'])->middleware('throttle:120,1')->name('notifications.resume');
    Route::post('/notifications/tout-lire', [NotificationsController::class, 'toutLire'])->name('notifications.tout_lire');
    Route::get('/notifications/{id}/ouvrir', [NotificationsController::class, 'ouvrir'])->whereUuid('id')->name('notifications.ouvrir');
    Route::post('/notifications/{id}/lire', [NotificationsController::class, 'lire'])->whereUuid('id')->name('notifications.lire');

    /* Suivi des alertes (par chambre, par gravité) et justification d'un retard. */
    /* ---------- Manuel d'utilisation (tout compte connecté ; les chapitres suivent les droits) ---------- */
    Route::prefix('aide')->group(function () {
        Route::get('/', [AideController::class, 'index'])->name('aide.index');
        Route::get('/recherche', [AideController::class, 'chercher'])->middleware('throttle:120,1')->name('aide.recherche');
        Route::get('/contexte', [AideController::class, 'contexte'])->middleware('throttle:120,1')->name('aide.contexte');
        Route::get('/manuel', [AideController::class, 'manuel'])->name('aide.manuel');
        Route::get('/{slug}', [AideController::class, 'chapitre'])->where('slug', '[a-z0-9-]+')->name('aide.chapitre');
    });

    Route::prefix('alertes')->group(function () {
        Route::get('/suivi', [AlertesController::class, 'index'])->middleware('can:alertes.voir')->name('alertes.suivi');
        Route::get('/suivi/donnees', [AlertesController::class, 'donnees'])->middleware('can:alertes.voir')->name('alertes.suivi.donnees');
        Route::post('/{id}/justifier', [AlertesController::class, 'justifier'])->whereNumber('id')->middleware('throttle:60,1')->name('alertes.justifier');

        // Paramètres : règles de déclenchement et délais par étape.
        Route::get('/regles', [ReglesAlerteController::class, 'index'])->middleware('can:regles_alerte.voir')->name('alertes.regles');
        Route::get('/destinataires', [ReglesAlerteController::class, 'destinataires'])->middleware('can:regles_alerte.voir')->name('alertes.destinataires');
        Route::post('/regles', [ReglesAlerteController::class, 'enregistrer'])->name('alertes.regles.store');
        Route::post('/regles/{id}', [ReglesAlerteController::class, 'enregistrer'])->whereNumber('id')->name('alertes.regles.update');
        Route::post('/regles/{id}/activer', [ReglesAlerteController::class, 'activer'])->whereNumber('id')->name('alertes.regles.activer');
        Route::delete('/regles/{id}', [ReglesAlerteController::class, 'supprimer'])->whereNumber('id')->name('alertes.regles.delete');
        Route::get('/delais', [DelaisEtapeController::class, 'index'])->name('alertes.delais');
        Route::post('/delais/{id}', [DelaisEtapeController::class, 'modifier'])->whereNumber('id')->name('alertes.delais.update');
    });

    /* Utilisateurs : liste des comptes et profils (chaque action vérifie en plus le droit du profil, voir les politiques) */
    Route::prefix('utilisateurs')->group(function () {
        Route::get('/liste', [CompteController::class, 'index'])->middleware('can:viewAny,App\Models\User')->name('utilisateur.compte');
        Route::post('/liste', [CompteController::class, 'store'])->name('utilisateur.compte.store');
        Route::get('/liste/{user}', [CompteController::class, 'show'])->name('utilisateur.compte.get');
        Route::delete('/liste/{user}', [CompteController::class, 'destroy'])->name('utilisateur.compte.delete');
        Route::post('/liste/{user}/codes-secours/revoquer', [CompteController::class, 'revoquerCodes'])->middleware('throttle:mot-de-passe')->name('utilisateur.compte.codes_secours.revoquer');
        Route::post('/liste/{user}/lien', [CompteController::class, 'lien'])->middleware('throttle:mot-de-passe')->name('utilisateur.compte.lien');

        Route::get('/profils', [ProfilController::class, 'index'])->middleware('can:viewAny,App\Models\Profil')->name('profils.index');
        Route::post('/profils', [ProfilController::class, 'store'])->name('profils.store');
        Route::get('/profils/{profil}', [ProfilController::class, 'show'])->name('profils.get');
        Route::delete('/profils/{profil}', [ProfilController::class, 'destroy'])->name('profils.delete');
    });

    /* Organisation du tribunal (lot D) : écrans de liste + fenêtre de saisie, droits {cle}.voir / creer / modifier / supprimer */
    // Lot E : référentiels judiciaires. Chaque page a son propre contrôleur ; seules les routes sont déclarées ici.

    // Lot H (début) : enrôlement des affaires civiles (série RG) et liste des affaires.
    Route::prefix('affaires')->group(function () {
        Route::post('/enrolement', [EnrolementController::class, 'store'])->middleware('throttle:60,1')->name('affaires.enrolement.store');
        Route::post('/enrolement/suggestions', [EnrolementController::class, 'suggestions'])->middleware('throttle:120,1')->name('affaires.enrolement.suggestions');

        Route::post('/enrolement/numero', [EnrolementController::class, 'numero'])->middleware('throttle:120,1')->name('affaires.enrolement.numero');
        Route::post('/enrolement/dates', [EnrolementController::class, 'dates'])->middleware('throttle:120,1')->name('affaires.enrolement.dates');

        // Dossier numérique : pièces consultables dans l'application ; les fichiers ne sortent que par ces routes (droits et chambre contrôlés).
        Route::get('/dossiers', [DossierNumeriqueController::class, 'index'])->middleware('can:dossiers.voir')->name('affaires.dossiers');
        Route::get('/dossiers/donnees', [DossierNumeriqueController::class, 'donnees'])->middleware(['can:dossiers.voir', 'throttle:240,1'])->name('affaires.dossiers.donnees');
        Route::post('/liste/{id}/pieces', [DossierNumeriqueController::class, 'ajouter'])->whereNumber('id')->middleware('throttle:60,1')->name('affaires.dossier.ajouter');
        Route::post('/liste/{id}/localisation', [DossierNumeriqueController::class, 'localisation'])->whereNumber('id')->name('affaires.dossier.localisation');
        Route::get('/pieces/{id}/fichier', [DossierNumeriqueController::class, 'fichier'])->whereNumber('id')->middleware('throttle:240,1')->name('affaires.dossier.fichier');
        Route::get('/pieces/{id}/telecharger', [DossierNumeriqueController::class, 'telecharger'])->whereNumber('id')->middleware('throttle:120,1')->name('affaires.dossier.telecharger');
        Route::get('/pieces/{id}/consultations', [DossierNumeriqueController::class, 'consultations'])->whereNumber('id')->name('affaires.dossier.consultations');
        Route::post('/pieces/{id}/modifier', [DossierNumeriqueController::class, 'modifier'])->whereNumber('id')->name('affaires.dossier.modifier');
        Route::post('/pieces/{id}/retirer', [DossierNumeriqueController::class, 'retirer'])->whereNumber('id')->name('affaires.dossier.retirer');

        Route::get('/liste', [AffaireController::class, 'index'])->middleware('can:affaires.voir')->name('affaires.liste');
        Route::get('/liste/donnees', [AffaireController::class, 'donnees'])->middleware(['can:affaires.voir', 'throttle:240,1'])->name('affaires.liste.donnees');
        Route::get('/liste/export', [AffaireController::class, 'exporter'])->middleware('can:affaires.exporter')->name('affaires.liste.export');
        Route::get('/liste/{id}', [AffaireController::class, 'fiche'])->whereNumber('id')->middleware('can:affaires.voir')->name('affaires.liste.fiche');
        Route::post('/liste/{id}/modifier', [AffaireController::class, 'mettreAJour'])->whereNumber('id')->name('affaires.liste.modifier.enregistrer');
        Route::post('/liste/{id}/joindre', [AffaireController::class, 'joindre'])->whereNumber('id')->name('affaires.liste.joindre');
        Route::post('/liste/{id}/disjoindre', [AffaireController::class, 'disjoindre'])->whereNumber('id')->name('affaires.liste.disjoindre');
        Route::post('/liste/{id}/rediger', [AffaireController::class, 'rediger'])->whereNumber('id')->name('affaires.liste.rediger');
        Route::post('/liste/{id}/numero', [AffaireController::class, 'corrigerNumero'])->whereNumber('id')->name('affaires.liste.numero');
        Route::post('/liste/{id}/annuler', [AffaireController::class, 'annuler'])->whereNumber('id')->name('affaires.liste.annuler');
        Route::post('/liste/{id}/reattribuer', [AffaireController::class, 'reattribuer'])->whereNumber('id')->name('affaires.liste.reattribuer');
    });

    // Lot M (début) : modèles de décision et blocs de texte réutilisables.
    Route::prefix('decisions')->group(function () {
        Route::get('/modeles', [ModeleDecisionController::class, 'index'])->middleware('can:modeles.voir')->name('decisions.modeles');
        Route::get('/modeles/nouveau', [ModeleDecisionController::class, 'edition'])->middleware('can:modeles.creer')->name('decisions.modeles.nouveau');
        Route::post('/modeles/apercu', [ModeleDecisionController::class, 'apercu'])->middleware('throttle:60,1')->name('decisions.modeles.apercu');
        Route::get('/modeles/{id}/edition', [ModeleDecisionController::class, 'edition'])->whereNumber('id')->middleware('can:modeles.voir')->name('decisions.modeles.edition');
        Route::post('/modeles', [ModeleDecisionController::class, 'store'])->name('decisions.modeles.store');
        Route::post('/modeles/{id}/dupliquer', [ModeleDecisionController::class, 'dupliquer'])->whereNumber('id')->name('decisions.modeles.dupliquer');
        Route::post('/modeles/{id}/versions/{numero}/restaurer', [ModeleDecisionController::class, 'restaurer'])->whereNumber(['id', 'numero'])->name('decisions.modeles.restaurer');
        Route::delete('/modeles/{id}', [ModeleDecisionController::class, 'destroy'])->whereNumber('id')->name('decisions.modeles.delete');

        Route::get('/redaction', [RedactionController::class, 'index'])->middleware('can:redaction.voir')->name('decisions.redaction');
        Route::get('/redaction/donnees', [RedactionController::class, 'donnees'])->middleware(['can:redaction.voir', 'throttle:240,1'])->name('decisions.redaction.donnees');
        Route::get('/redaction/affaires', [RedactionController::class, 'affaires'])->middleware('throttle:120,1')->name('decisions.redaction.affaires');
        Route::post('/redaction', [RedactionController::class, 'creer'])->name('decisions.redaction.creer');
        Route::get('/redaction/{id}', [RedactionController::class, 'edition'])->whereNumber('id')->name('decisions.redaction.edition');
        Route::post('/redaction/{id}', [RedactionController::class, 'enregistrer'])->whereNumber('id')->name('decisions.redaction.store');
        Route::post('/redaction/{id}/apercu', [RedactionController::class, 'apercu'])->whereNumber('id')->middleware('throttle:120,1')->name('decisions.redaction.apercu');
        Route::post('/redaction/{id}/composition', [RedactionController::class, 'recomposer'])->whereNumber('id')->name('decisions.redaction.composition');
        Route::post('/redaction/{id}/retouches', [RedactionController::class, 'retoucher'])->whereNumber('id')->name('decisions.redaction.retoucher');
        Route::post('/redaction/{id}/redigee', [RedactionController::class, 'redigee'])->whereNumber('id')->name('decisions.redaction.redigee');
        Route::post('/redaction/{id}/renvoyer', [RedactionController::class, 'renvoyer'])->whereNumber('id')->name('decisions.redaction.renvoyer');
        Route::post('/redaction/{id}/signer', [RedactionController::class, 'signer'])->whereNumber('id')->name('decisions.redaction.signer');
        Route::post('/redaction/{id}/delivrer', [RedactionController::class, 'delivrer'])->whereNumber('id')->name('decisions.redaction.delivrer');
        Route::post('/redaction/{id}/numeroter', [RedactionController::class, 'numeroter'])->whereNumber('id')->name('decisions.redaction.numeroter');
        Route::post('/redaction/{id}/valider', [RedactionController::class, 'valider'])->whereNumber('id')->name('decisions.redaction.valider');
        Route::post('/redaction/{id}/corriger', [RedactionController::class, 'corriger'])->whereNumber('id')->name('decisions.redaction.corriger');
        Route::get('/redaction/{id}/imprimer', [RedactionController::class, 'imprimer'])->whereNumber('id')->name('decisions.redaction.imprimer');
        Route::delete('/redaction/{id}', [RedactionController::class, 'destroy'])->whereNumber('id')->name('decisions.redaction.delete');

        Route::get('/registre', [RegistreDecisionsController::class, 'index'])->middleware('can:registre_decisions.voir')->name('decisions.registre');
        Route::get('/registre/donnees', [RegistreDecisionsController::class, 'donnees'])->middleware('can:registre_decisions.voir')->name('decisions.registre.donnees');
        Route::get('/registre/export', [RegistreDecisionsController::class, 'exporter'])->middleware(['can:registre_decisions.exporter', 'throttle:20,1'])->name('decisions.registre.export');

        Route::get('/repertoire-penal', [RepertoirePenalController::class, 'index'])->middleware('can:repertoire_penal.voir')->name('decisions.repertoire_penal');
        Route::get('/repertoire-penal/donnees', [RepertoirePenalController::class, 'donnees'])->middleware(['can:repertoire_penal.voir', 'throttle:240,1'])->name('decisions.repertoire_penal.donnees');
        Route::get('/repertoire-penal/{id}', [RepertoirePenalController::class, 'show'])->whereNumber('id')->name('decisions.repertoire_penal.get');
        Route::post('/repertoire-penal', [RepertoirePenalController::class, 'store'])->name('decisions.repertoire_penal.store');
        Route::post('/repertoire-penal/{id}/minute', [RepertoirePenalController::class, 'minute'])->whereNumber('id')->name('decisions.repertoire_penal.minute');
        Route::delete('/repertoire-penal/{id}', [RepertoirePenalController::class, 'destroy'])->whereNumber('id')->name('decisions.repertoire_penal.delete');
        Route::get('/recours', [RecoursController::class, 'index'])->middleware('can:recours.voir')->name('decisions.recours');
        Route::get('/recours/donnees', [RecoursController::class, 'donnees'])->middleware(['can:recours.voir', 'throttle:240,1'])->name('decisions.recours.donnees');
        Route::get('/recours/compteurs', [RecoursController::class, 'compteurs'])->middleware(['can:recours.voir', 'throttle:60,1'])->name('decisions.recours.compteurs');
        Route::post('/recours/{id}/signification', [RecoursController::class, 'signifier'])->whereNumber('id')->name('decisions.recours.signification');
        Route::post('/recours/{id}/recours', [RecoursController::class, 'enregistrer'])->whereNumber('id')->name('decisions.recours.enregistrer');
        Route::post('/recours/recours/{id}/transmettre', [RecoursController::class, 'transmettre'])->whereNumber('id')->name('decisions.recours.transmettre');
        Route::post('/recours/recours/{id}/abandonner', [RecoursController::class, 'abandonner'])->whereNumber('id')->name('decisions.recours.abandonner');
        Route::post('/recours/{id}/certificat', [RecoursController::class, 'certifier'])->whereNumber('id')->name('decisions.recours.certifier');
        Route::get('/recours/certificat/{id}/imprimer', [RecoursController::class, 'imprimer'])->whereNumber('id')->middleware('can:recours.voir')->name('decisions.recours.imprimer');
        Route::post('/recours/delais', [RecoursController::class, 'delais'])->name('decisions.recours.delais');
        Route::get('/blocs',[BlocTexteController::class, 'index'])->middleware('can:blocs.voir')->name('decisions.blocs');
        Route::get('/blocs/donnees', [BlocTexteController::class, 'donnees'])->middleware(['can:blocs.voir', 'throttle:240,1'])->name('decisions.blocs.donnees');
        Route::get('/blocs/{id}', [BlocTexteController::class, 'show'])->whereNumber('id')->name('decisions.blocs.get');
        Route::post('/blocs', [BlocTexteController::class, 'store'])->name('decisions.blocs.store');
        Route::delete('/blocs/{id}', [BlocTexteController::class, 'destroy'])->whereNumber('id')->name('decisions.blocs.delete');
    });

    Route::prefix('affaires')->group(function () {
        Route::get('/reprise', [RepriseController::class, 'index'])->middleware('can:reprise.voir')->name('affaires.reprise');
        Route::get('/reprise/modele/{serie}', [RepriseController::class, 'modele'])->where('serie', 'RG|RP|RI|RE')->middleware('can:reprise.voir')->name('affaires.reprise.modele');
        Route::post('/reprise/televerser', [RepriseController::class, 'televerser'])->middleware('throttle:20,1')->name('affaires.reprise.televerser');
        Route::post('/reprise/{reference}/analyser', [RepriseController::class, 'analyser'])->where('reference', '[0-9A-Za-z]{26}')->name('affaires.reprise.analyser');
        Route::post('/reprise/{reference}/controler', [RepriseController::class, 'controler'])->where('reference', '[0-9A-Za-z]{26}')->name('affaires.reprise.controler');
        Route::post('/reprise/{reference}/importer', [RepriseController::class, 'importer'])->where('reference', '[0-9A-Za-z]{26}')->name('affaires.reprise.importer');
        Route::get('/reprise/{reference}/etat', [RepriseController::class, 'etat'])->where('reference', '[0-9A-Za-z]{26}')->name('affaires.reprise.etat');
        Route::get('/reprise/{reference}/rapport', [RepriseController::class, 'rapport'])->where('reference', '[0-9A-Za-z]{26}')->name('affaires.reprise.rapport');
        Route::post('/reprise/{reference}/annuler', [RepriseController::class, 'annuler'])->where('reference', '[0-9A-Za-z]{26}')->name('affaires.reprise.annuler');
        Route::get('/mise-en-etat', [MiseEnEtatController::class, 'index'])->middleware('can:mise_en_etat.voir')->name('affaires.mise_en_etat');
        Route::get('/mise-en-etat/donnees', [MiseEnEtatController::class, 'donnees'])->middleware(['can:mise_en_etat.voir', 'throttle:240,1'])->name('affaires.mise_en_etat.donnees');
        Route::get('/mise-en-etat/{id}', [MiseEnEtatController::class, 'show'])->whereNumber('id')->name('affaires.mise_en_etat.get');
        Route::post('/mise-en-etat', [MiseEnEtatController::class, 'store'])->name('affaires.mise_en_etat.store');
        Route::post('/mise-en-etat/{id}/cloturer', [MiseEnEtatController::class, 'cloturer'])->whereNumber('id')->name('affaires.mise_en_etat.cloturer');
        Route::post('/mise-en-etat/{id}/retirer', [MiseEnEtatController::class, 'retirer'])->whereNumber('id')->name('affaires.mise_en_etat.retirer');
        Route::get('/requetes', [RequeteController::class, 'index'])->middleware('can:requetes.voir')->name('affaires.requetes');
        Route::get('/requetes/donnees', [RequeteController::class, 'donnees'])->middleware(['can:requetes.voir', 'throttle:240,1'])->name('affaires.requetes.donnees');
        Route::get('/requetes/{id}', [RequeteController::class, 'show'])->whereNumber('id')->name('affaires.requetes.get');
        Route::post('/requetes', [RequeteController::class, 'store'])->name('affaires.requetes.store');
        Route::post('/requetes/{id}/repartir', [RequeteController::class, 'repartir'])->whereNumber('id')->name('affaires.requetes.repartir');
        Route::post('/requetes/{id}/cloturer', [RequeteController::class, 'cloturer'])->whereNumber('id')->name('affaires.requetes.cloturer');
        Route::get('/detenus', [DetenuController::class, 'index'])->middleware('can:detenus.voir')->name('affaires.detenus');
        Route::get('/detenus/donnees', [DetenuController::class, 'donnees'])->middleware(['can:detenus.voir', 'throttle:240,1'])->name('affaires.detenus.donnees');
        Route::post('/liste/{id}/scelles', [ScelleController::class, 'store'])->whereNumber('id')->name('affaires.scelles.store');
        Route::delete('/scelles/{id}', [ScelleController::class, 'destroy'])->whereNumber('id')->name('affaires.scelles.delete');
        Route::get('/avocats', [AvocatController::class, 'index'])->middleware('can:avocats.voir')->name('affaires.avocats');
        Route::get('/avocats/donnees', [AvocatController::class, 'donnees'])->middleware(['can:avocats.voir', 'throttle:240,1'])->name('affaires.avocats.donnees');
        Route::get('/avocats/{id}', [AvocatController::class, 'show'])->whereNumber('id')->name('affaires.avocats.get');
        Route::post('/avocats', [AvocatController::class, 'store'])->name('affaires.avocats.store');
        Route::delete('/avocats/{id}', [AvocatController::class, 'destroy'])->whereNumber('id')->name('affaires.avocats.delete');
    });

    Route::prefix('audiences')->group(function () {
        Route::get('/jour', [RoleDuJourController::class, 'index'])->middleware('can:roles.voir')->name('audiences.jour');
        Route::get('/jour/donnees', [RoleDuJourController::class, 'donnees'])->middleware(['can:roles.voir', 'throttle:120,1'])->name('audiences.jour.donnees');
        Route::get('/seances', [SeanceHorsAudienceController::class, 'index'])->middleware('can:seances.voir')->name('audiences.seances');
        Route::get('/seances/donnees', [SeanceHorsAudienceController::class, 'donnees'])->middleware(['can:seances.voir', 'throttle:240,1'])->name('audiences.seances.donnees');
        Route::post('/seances', [SeanceHorsAudienceController::class, 'store'])->name('audiences.seances.store');
        Route::post('/seances/{id}/dossiers', [SeanceHorsAudienceController::class, 'dossier'])->whereNumber('id')->name('audiences.seances.dossier');
        Route::delete('/seances/dossiers/{id}', [SeanceHorsAudienceController::class, 'retirerDossier'])->whereNumber('id')->name('audiences.seances.dossier.retirer');
        Route::post('/seances/{id}/statut', [SeanceHorsAudienceController::class, 'statut'])->whereNumber('id')->name('audiences.seances.statut');
        Route::get('/roles', [RoleAudienceController::class, 'index'])->middleware('can:roles.voir')->name('audiences.roles');
        Route::get('/roles/donnees', [RoleAudienceController::class, 'donnees'])->middleware(['can:roles.voir', 'throttle:240,1'])->name('audiences.roles.donnees');
        Route::get('/roles/planning', [RoleAudienceController::class, 'planning'])->middleware('can:roles.voir')->name('audiences.roles.planning');
        Route::post('/roles', [RoleAudienceController::class, 'store'])->name('audiences.roles.store');
        Route::get('/roles/{id}', [RoleAudienceController::class, 'show'])->whereNumber('id')->middleware('can:roles.voir')->name('audiences.roles.show');
        Route::post('/roles/{id}/entete', [RoleAudienceController::class, 'entete'])->whereNumber('id')->name('audiences.roles.entete');
        Route::get('/roles/{id}/renvoi', [RoleAudienceController::class, 'renvoi'])->whereNumber('id')->middleware('can:roles.voir')->name('audiences.roles.renvoi');
        Route::post('/roles/{id}/completer', [RoleAudienceController::class, 'completer'])->whereNumber('id')->name('audiences.roles.completer');
        Route::post('/roles/{id}/lignes', [RoleAudienceController::class, 'ligneAjouter'])->whereNumber('id')->name('audiences.roles.ligne.ajouter');
        Route::post('/roles/{id}/lignes/{ligne}', [RoleAudienceController::class, 'ligneModifier'])->whereNumber(['id', 'ligne'])->name('audiences.roles.ligne.modifier');
        Route::post('/roles/{id}/lignes/{ligne}/deplacer', [RoleAudienceController::class, 'ligneDeplacer'])->whereNumber(['id', 'ligne'])->name('audiences.roles.ligne.deplacer');
        Route::post('/roles/{id}/lignes/{ligne}/retirer', [RoleAudienceController::class, 'ligneRetirer'])->whereNumber(['id', 'ligne'])->name('audiences.roles.ligne.retirer');
        Route::post('/roles/{id}/publier', [RoleAudienceController::class, 'publier'])->whereNumber('id')->name('audiences.roles.publier');
        Route::post('/roles/{id}/rouvrir', [RoleAudienceController::class, 'rouvrir'])->whereNumber('id')->name('audiences.roles.rouvrir');
        Route::post('/roles/{id}/supprimer', [RoleAudienceController::class, 'supprimer'])->whereNumber('id')->name('audiences.roles.supprimer');
        Route::get('/roles/{id}/export/{format}', [RoleAudienceController::class, 'exporter'])->whereNumber('id')->where('format', 'pdf|docx')->name('audiences.roles.export');
        Route::get('/roles/{id}/versions/{version}/{format}', [RoleAudienceController::class, 'exporter'])->whereNumber(['id', 'version'])->where('format', 'pdf|docx')->name('audiences.roles.version');
    });

    // Lot K : tableau de bord, feuille d'audience, renvois et délibérés.
    Route::prefix('audiences')->group(function () {
        Route::get('/tableau', [TableauAudiencesController::class, 'index'])->middleware('can:tableau.voir')->name('audiences.tableau');
        Route::get('/tableau/donnees', [TableauAudiencesController::class, 'donnees'])->middleware('can:tableau.voir')->name('audiences.tableau.donnees');

        // Analyse et pilotage : la vue d'ensemble du tribunal, avec analyse et recommandations (automatiques, ou par l'IA sur demande).
        Route::get('/pilotage', [PilotageController::class, 'index'])->middleware('can:tableau.voir')->name('audiences.pilotage');
        Route::get('/pilotage/donnees', [PilotageController::class, 'donnees'])->middleware(['can:tableau.voir', 'throttle:60,1'])->name('audiences.pilotage.donnees');
        Route::get('/pilotage/note.pdf', [PilotageController::class, 'pdf'])->middleware(['can:tableau.voir', 'throttle:20,1'])->name('audiences.pilotage.pdf');
        Route::post('/pilotage/analyser', [PilotageController::class, 'analyser'])->middleware('throttle:6,1')->name('audiences.pilotage.analyser');
        Route::get('/pilotage/syntheses/{id}', [PilotageController::class, 'synthese'])->whereNumber('id')->middleware('can:tableau.voir')->name('audiences.pilotage.synthese');

        Route::get('/feuille', [FeuilleAudienceController::class, 'index'])->middleware('can:feuille.voir')->name('audiences.feuille');
        Route::get('/feuille/{id}', [FeuilleAudienceController::class, 'show'])->whereNumber('id')->middleware('can:feuille.voir')->name('audiences.feuille.show');
        Route::get('/feuille/{id}/dates', [FeuilleAudienceController::class, 'dates'])->whereNumber('id')->middleware('can:feuille.voir')->name('audiences.feuille.dates');
        Route::post('/feuille/{id}/ouvrir', [FeuilleAudienceController::class, 'ouvrir'])->whereNumber('id')->name('audiences.feuille.ouvrir');
        Route::post('/feuille/{id}/composition', [FeuilleAudienceController::class, 'composition'])->whereNumber('id')->name('audiences.feuille.composition');
        Route::post('/feuille/{id}/lignes/{ligne}', [FeuilleAudienceController::class, 'suite'])->whereNumber(['id', 'ligne'])->name('audiences.feuille.suite');
        Route::post('/feuille/{id}/terminer', [FeuilleAudienceController::class, 'terminer'])->whereNumber('id')->name('audiences.feuille.terminer');
        Route::post('/feuille/{id}/rouvrir', [FeuilleAudienceController::class, 'rouvrir'])->whereNumber('id')->name('audiences.feuille.rouvrir');

        Route::get('/renvois', [RenvoiController::class, 'index'])->middleware('can:renvois.voir')->name('audiences.renvois');
        Route::get('/renvois/{ligne}/dates', [RenvoiController::class, 'dates'])->whereNumber('ligne')->middleware('can:renvois.voir')->name('audiences.renvois.dates');
        Route::post('/renvois/{ligne}/date', [RenvoiController::class, 'changerDate'])->whereNumber('ligne')->name('audiences.renvois.date');
        Route::post('/renvois/{ligne}/proroger', [RenvoiController::class, 'proroger'])->whereNumber('ligne')->name('audiences.renvois.proroger');

        Route::get('/suites', [SuitesAudienceController::class, 'index'])->middleware('can:suites.voir')->name('audiences.suites');
        Route::get('/suites/donnees', [SuitesAudienceController::class, 'donnees'])->middleware('can:suites.voir')->name('audiences.suites.donnees');
        Route::get('/suites/export', [SuitesAudienceController::class, 'exporter'])->middleware(['can:suites.exporter', 'throttle:20,1'])->name('audiences.suites.export');
    });

    Route::get('/recherche', [RechercheController::class, 'index'])->middleware('throttle:60,1')->name('recherche');

    Route::prefix('annexes')->group(function () {
        Route::get('/assistance-judiciaire', [AssistanceJudiciaireController::class, 'index'])->middleware('can:assistance.voir')->name('assistance');
        Route::get('/assistance-judiciaire/donnees', [AssistanceJudiciaireController::class, 'donnees'])->middleware(['can:assistance.voir', 'throttle:240,1'])->name('assistance.donnees');
        Route::get('/assistance-judiciaire/{id}', [AssistanceJudiciaireController::class, 'show'])->whereNumber('id')->name('assistance.get');
        Route::post('/assistance-judiciaire', [AssistanceJudiciaireController::class, 'store'])->name('assistance.store');
        Route::delete('/assistance-judiciaire/{id}', [AssistanceJudiciaireController::class, 'destroy'])->whereNumber('id')->name('assistance.delete');
        Route::get('/jap', [JapController::class, 'index'])->middleware('can:jap.voir')->name('jap');
        Route::get('/jap/donnees', [JapController::class, 'donnees'])->middleware('can:jap.voir')->name('jap.donnees');
        Route::post('/jap', [JapController::class, 'enregistrer'])->name('jap.enregistrer');
    });

    Route::prefix('instruction')->group(function () {
        Route::get('/', [InstructionController::class, 'index'])->middleware('can:instruction.voir')->name('instruction');
        Route::get('/donnees', [InstructionController::class, 'donnees'])->middleware(['can:instruction.voir', 'throttle:240,1'])->name('instruction.donnees');
        Route::get('/commissions', [InstructionController::class, 'commissions'])->middleware('can:instruction.voir')->name('instruction.commissions');
        Route::post('/commissions', [InstructionController::class, 'enregistrerCommissions'])->name('instruction.commissions.store');
        Route::get('/dossiers/{id}', [InstructionController::class, 'show'])->whereNumber('id')->middleware('can:instruction.voir')->name('instruction.show');
        Route::post('/dossiers/{id}', [InstructionController::class, 'enregistrerDossier'])->whereNumber('id')->name('instruction.dossier');
        Route::post('/inculpes/{id}', [InstructionController::class, 'enregistrerInculpe'])->whereNumber('id')->name('instruction.inculpe');
        Route::post('/victimes/{id}', [InstructionController::class, 'enregistrerVictime'])->whereNumber('id')->name('instruction.victime');
    });

    Route::prefix('statistiques')->group(function () {
        Route::get('/civil', [CivilController::class, 'index'])->middleware('can:stats_civil.voir')->name('statistiques.civil');
        Route::get('/civil/donnees', [CivilController::class, 'donnees'])->middleware(['can:stats_civil.voir', 'throttle:60,1'])->name('statistiques.civil.donnees');
        Route::get('/civil/detail', [CivilController::class, 'detail'])->middleware(['can:stats_civil.detail', 'throttle:60,1'])->name('statistiques.civil.detail');
        Route::post('/civil/qualifier', [CivilController::class, 'qualifier'])->name('statistiques.civil.qualifier');
        Route::get('/instruction', [StatistiquesInstructionController::class, 'index'])->middleware('can:stats_instruction.voir')->name('statistiques.instruction');
        Route::get('/instruction/donnees', [StatistiquesInstructionController::class, 'donnees'])->middleware(['can:stats_instruction.voir', 'throttle:60,1'])->name('statistiques.instruction.donnees');
        Route::get('/registres', [RegistresController::class, 'index'])->middleware('can:stats_registres.voir')->name('statistiques.registres');
        Route::get('/registres/effectifs', [RegistresController::class, 'effectifs'])->middleware(['can:stats_registres.voir', 'throttle:60,1'])->name('statistiques.registres.effectifs');
        Route::get('/registres/{code}/export', [RegistresController::class, 'exporter'])->where('code', '[a-z_]+')->middleware(['can:stats_registres.exporter', 'throttle:20,1'])->name('statistiques.registres.export');
        Route::get('/penal', [PenalController::class, 'index'])->middleware('can:stats_penal.voir')->name('statistiques.penal');
        Route::get('/penal/donnees', [PenalController::class, 'donnees'])->middleware(['can:stats_penal.voir', 'throttle:60,1'])->name('statistiques.penal.donnees');
        Route::get('/penal/export', [PenalController::class, 'exporter'])->middleware(['can:stats_penal.exporter', 'throttle:20,1'])->name('statistiques.penal.export');
        Route::get('/civil/export', [CivilController::class, 'exporter'])->middleware(['can:stats_civil.exporter', 'throttle:20,1'])->name('statistiques.civil.export');
    });

    Route::prefix('referentiels')->group(function () {
        $pages = [
            'types_audience' => [TypeAudienceController::class, 'types-audience'],
            'modes_saisine' => [ModeSaisineController::class, 'modes-saisine'],
            'matieres' => [MatiereController::class, 'matieres'],
            'natures_affaire' => [NatureAffaireController::class, 'natures-affaire'],
            'delais_procedure' => [DelaiProcedureController::class, 'delais-procedure'],
            'objets_litige' => [ObjetLitigeController::class, 'objets-litige'],
            'infractions' => [InfractionController::class, 'infractions'],
        ];
        foreach ($pages as $cle => [$controleur, $slug]) {
            Route::get("/$slug", [$controleur, 'index'])->middleware("can:$cle.voir")->name("referentiels.$cle");
            Route::get("/$slug/{id}", [$controleur, 'show'])->whereNumber('id')->name("referentiels.$cle.get");
            Route::post("/$slug", [$controleur, 'store'])->name("referentiels.$cle.store");
            Route::delete("/$slug/{id}", [$controleur, 'destroy'])->whereNumber('id')->name("referentiels.$cle.delete");
        }
        Route::get('/objets-litige/donnees', [ObjetLitigeController::class, 'donnees'])->middleware(['can:objets_litige.voir', 'throttle:240,1'])->name('referentiels.objets_litige.donnees');
        Route::get('/infractions/donnees', [InfractionController::class, 'donnees'])->middleware(['can:infractions.voir', 'throttle:240,1'])->name('referentiels.infractions.donnees');
    });

    Route::prefix('organisation')->group(function () {
        foreach ([
            'annees' => AnneeJudiciaireController::class,
            'salles' => SalleController::class,
            'personnel' => AgentController::class,
        ] as $cle => $controleur) {
            Route::get("/$cle", [$controleur, 'index'])->middleware("can:$cle.voir")->name("organisation.$cle");
            Route::get("/$cle/{id}", [$controleur, 'show'])->whereNumber('id')->name("organisation.$cle.get");
            Route::post("/$cle", [$controleur, 'store'])->name("organisation.$cle.store");
            Route::delete("/$cle/{id}", [$controleur, 'destroy'])->whereNumber('id')->name("organisation.$cle.delete");
        }

        // Sections, chambres et formations, composition (affectations) et services : une page, un contrôleur chacun
        Route::get('/sections', [SectionController::class, 'index'])->middleware('can:sections.voir')->name('organisation.sections');
        Route::get('/sections/{id}', [SectionController::class, 'show'])->whereNumber('id')->name('organisation.sections.get');
        Route::post('/sections', [SectionController::class, 'store'])->name('organisation.sections.store');
        Route::delete('/sections/{id}', [SectionController::class, 'destroy'])->whereNumber('id')->name('organisation.sections.delete');

        Route::get('/chambres', [ChambreController::class, 'index'])->middleware('can:chambres.voir')->name('organisation.chambres');
        Route::get('/chambres/{id}', [ChambreController::class, 'show'])->whereNumber('id')->name('organisation.chambres.get');
        Route::post('/chambres', [ChambreController::class, 'store'])->name('organisation.chambres.store');
        Route::delete('/chambres/{id}', [ChambreController::class, 'destroy'])->whereNumber('id')->name('organisation.chambres.delete');
        Route::get('/chambres/{chambre}/composition', [AffectationController::class, 'index'])->whereNumber('chambre')->middleware('can:affectations.voir')->name('organisation.affectations');

        Route::get('/formations/{id}', [FormationController::class, 'show'])->whereNumber('id')->name('organisation.formations.get');
        Route::post('/formations', [FormationController::class, 'store'])->name('organisation.formations.store');
        Route::delete('/formations/{id}', [FormationController::class, 'destroy'])->whereNumber('id')->name('organisation.formations.delete');

        Route::get('/affectations/{id}', [AffectationController::class, 'show'])->whereNumber('id')->name('organisation.affectations.get');
        Route::post('/affectations', [AffectationController::class, 'store'])->name('organisation.affectations.store');
        Route::post('/affectations/{id}/remplacer', [AffectationController::class, 'remplacer'])->whereNumber('id')->name('organisation.affectations.remplacer');
        Route::delete('/affectations/{id}', [AffectationController::class, 'destroy'])->whereNumber('id')->name('organisation.affectations.delete');

        Route::get('/services', [ServiceController::class, 'index'])->middleware('can:services.voir')->name('organisation.services');
        Route::get('/services/{id}', [ServiceController::class, 'show'])->whereNumber('id')->name('organisation.services.get');
        Route::post('/services', [ServiceController::class, 'store'])->name('organisation.services.store');
        Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->whereNumber('id')->name('organisation.services.delete');

        Route::get('/creneaux/{id}', [CreneauController::class, 'show'])->whereNumber('id')->name('organisation.creneaux.get');
        Route::post('/creneaux', [CreneauController::class, 'store'])->name('organisation.creneaux.store');
        Route::delete('/creneaux/{id}', [CreneauController::class, 'destroy'])->whereNumber('id')->name('organisation.creneaux.delete');

        Route::get('/fermetures', [FermetureController::class, 'index'])->middleware('can:fermetures.voir')->name('organisation.fermetures');
        Route::get('/fermetures/{id}', [FermetureController::class, 'show'])->whereNumber('id')->name('organisation.fermetures.get');
        Route::post('/fermetures', [FermetureController::class, 'store'])->name('organisation.fermetures.store');
        Route::post('/fermetures/feries-fixes', [FermetureController::class, 'feriesFixes'])->name('organisation.fermetures.feries');
        Route::delete('/fermetures/{id}', [FermetureController::class, 'destroy'])->whereNumber('id')->name('organisation.fermetures.delete');

        Route::get('/enrolement', [DelaiEnrolementController::class, 'index'])->middleware('can:delais.voir')->name('organisation.enrolement');
        Route::get('/enrolement/calcul', [DelaiEnrolementController::class, 'calculer'])->name('organisation.enrolement.calcul');
        Route::get('/enrolement/{id}', [DelaiEnrolementController::class, 'show'])->whereNumber('id')->name('organisation.enrolement.get');
        Route::post('/enrolement', [DelaiEnrolementController::class, 'store'])->name('organisation.enrolement.store');
        Route::delete('/enrolement/{id}', [DelaiEnrolementController::class, 'destroy'])->whereNumber('id')->name('organisation.enrolement.delete');

        Route::get('/reconduction', [ReconductionController::class, 'index'])->middleware('can:reconduction.voir')->name('organisation.reconduction');
        Route::post('/reconduction', [ReconductionController::class, 'executer'])->name('organisation.reconduction.executer');

        Route::get('/ordonnances', [OrdonnanceController::class, 'index'])->middleware('can:ordonnances.voir')->name('organisation.ordonnances');
        Route::get('/ordonnances/{id}', [OrdonnanceController::class, 'show'])->whereNumber('id')->name('organisation.ordonnances.get');
        Route::get('/ordonnances/{id}/consulter', [OrdonnanceController::class, 'consulter'])->whereNumber('id')->name('organisation.ordonnances.consulter');
        Route::get('/ordonnances/{id}/lire', [OrdonnanceController::class, 'lire'])->whereNumber('id')->name('organisation.ordonnances.lire');
        Route::get('/ordonnances/{id}/telecharger', [OrdonnanceController::class, 'telecharger'])->whereNumber('id')->name('organisation.ordonnances.telecharger');
        Route::post('/ordonnances', [OrdonnanceController::class, 'store'])->name('organisation.ordonnances.store');
        Route::delete('/ordonnances/{id}', [OrdonnanceController::class, 'destroy'])->whereNumber('id')->name('organisation.ordonnances.delete');
    });

    /* Paramètres : réglages modifiables depuis l'application (droits reglages.voir / reglages.modifier) */
    Route::prefix('parametres')->middleware('can:reglages.voir')->group(function () {
        Route::get('/reglages', [ReglageController::class, 'index'])->name('reglages.index');
        Route::post('/reglages/courriels/test', [ReglageController::class, 'tester'])->middleware('throttle:6,1')->name('reglages.tester');
        Route::post('/reglages/{rubrique}', [ReglageController::class, 'update'])->name('reglages.update');
        Route::post('/reglages/{rubrique}/defaut', [ReglageController::class, 'defaut'])->name('reglages.defaut');
    });

    /* Journaux (lecture seule) : consultation et export, droits journaux.* */
    Route::prefix('journaux')->group(function () {
        Route::get('/connexions/donnees', [JournalController::class, 'donneesConnexions'])->middleware(['can:journaux.connexion', 'throttle:240,1'])->name('journal.connexions.donnees');
        Route::get('/audit/donnees', [JournalController::class, 'donneesAudit'])->middleware(['can:journaux.audit', 'throttle:240,1'])->name('journal.audit.donnees');
        Route::get('/connexions', [JournalController::class, 'connexions'])->middleware('can:journaux.connexion')->name('journal.connexions');
        Route::get('/connexions/export', [JournalController::class, 'exporterConnexions'])->middleware(['can:journaux.connexion.exporter', 'throttle:10,1'])->name('journal.connexions.export');
        Route::get('/audit', [JournalController::class, 'audit'])->middleware('can:journaux.audit')->name('journal.index');
        Route::get('/audit/export', [JournalController::class, 'exporterAudit'])->middleware(['can:journaux.audit.exporter', 'throttle:10,1'])->name('journal.audit.export');
    });

    Route::get('/mon-compte', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/mon-compte/codes-secours', [ProfileController::class, 'genererCodes'])->middleware('throttle:mot-de-passe')->name('profile.codes_secours');
    Route::put('/mon-compte/mot-de-passe', [ProfileController::class, 'updatePassword'])->middleware('throttle:mot-de-passe')->name('profile.password');
});
