<?php

namespace App\Console\Commands;

use App\Models\Affaire;
use App\Models\AnneeJudiciaire;
use App\Models\Chambre;
use Illuminate\Support\Carbon;
use App\Models\Matiere;
use App\Models\ModeSaisine;
use App\Models\NatureAffaire;
use App\Models\Partie;
use App\Models\PieceDossier;
use App\Services\NumerotationRegistre;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 | Affaires FICTIVES pour essayer l'application sans tout saisir : civil, pénal, instruction (adultes et enfants), une jonction,
 | une affaire annulée, un groupe « et x autres », le ministère public comme partie. Les noms sont inventés.
 |
 |   php artisan affaires:demo            crée les affaires d'essai (une seule fois)
 |   php artisan affaires:demo --retirer  les retire (et seulement elles)
 |
 | Elles sont reconnaissables à l'ancien numéro « DEMO » ; les vraies affaires ne sont jamais touchées.
 */
class AffairesDemo extends Command
{
    protected $signature = 'affaires:demo {--retirer : Retire les affaires d\'essai}';

    protected $description = "Crée (ou retire) des affaires fictives pour essayer le menu Affaires";

    public function handle(): int
    {
        if ($this->option('retirer')) {
            $ids = DB::table('affaires')->where('ancien_numero', 'DEMO')->pluck('id');
            // Affaires jointes d'abord (la principale est référencée par les autres).
            DB::table('affaires')->whereIn('id', $ids)->update(['jointe_a_id' => null]);
            foreach ($ids as $id) {
                Storage::disk('local')->deleteDirectory(PieceDossier::DOSSIER.'/'.$id);
            }
            DB::table('dossier_pieces')->whereIn('affaire_id', $ids)->delete();
            DB::table('scelles')->whereIn('affaire_id', $ids)->delete();
            // Rôles d'essai : ceux qui portent une affaire d'essai.
            $roles = DB::table('role_lignes')->whereIn('affaire_id', $ids)->pluck('role_id')->unique();
            DB::table('roles_audience')->whereIn('id', $roles)->delete();
            DB::table('fermetures')->where('libelle', 'like', '%(essai)')->delete();
            DB::table('requetes')->where('objet', 'like', '%(essai)')->delete();
            DB::table('parties')->whereIn('affaire_id', $ids)->delete();
            \App\Models\Avocat::where('a_completer', true)->whereDoesntHave('parties', fn ($q) => $q->whereNotIn('affaire_id', $ids))->delete();
            DB::table('affaire_attributions')->whereIn('affaire_id', $ids)->delete();
            DB::table('affaires')->whereIn('id', $ids)->delete();
            $this->info($ids->count().' affaire(s) d\'essai retirée(s).');

            return self::SUCCESS;
        }

        if (Affaire::where('ancien_numero', 'DEMO')->exists()) {
            $this->warn("Les affaires d'essai existent déjà (php artisan affaires:demo --retirer pour les retirer).");
            $this->rolesDemo();
            $this->rolesDemoTypes();
            $this->rolesDemoScenarios();
            $this->rolesDemoSuites();

            return self::SUCCESS;
        }

        // Chambres de chaque famille : civile (la présidentielle), 2e chambre civile, correctionnelle, cabinets d'instruction.
        $chambre = fn (string $filtre, string $section) => Chambre::where('libelle', 'like', $filtre)->orderBy('ordre')->first()
            ?? Chambre::whereHas('section', fn ($q) => $q->where('code', $section))->orderBy('ordre')->firstOrFail();
        $civile = $chambre('1re chambre civile', 'civile');
        $autre = $chambre('2e chambre civile', 'civile');
        $correctionnelle = $chambre('1re chambre correctionnelle', 'penale');
        $cabinet = $chambre("1er cabinet d'instruction", 'instruction');
        $juge = $chambre('Cabinet du juge des enfants', 'instruction');
        $yy = now()->year;

        $mode = fn (string $libelle, string $registre) => ModeSaisine::where('libelle', $libelle)->first()?->id ?? ModeSaisine::where('registre', $registre)->value('id');
        $nature = fn (string $libelle, string $registre) => NatureAffaire::where('libelle', $libelle)->first()?->id ?? NatureAffaire::where('registre', $registre)->value('id');
        $matiere = fn (string $libelle) => Matiere::where('libelle', $libelle)->first()?->id ?? Matiere::value('id');

        $creer = function (string $serie, int $numero, array $affaire, array $parties, ?Affaire $principale = null) use ($yy) {
            $a = Affaire::create($affaire + [
                'serie' => $serie, 'annee' => $yy, 'numero' => $numero, 'numero_complet' => NumerotationRegistre::formater($serie, $numero, $yy),
                'numero_saisi' => true, 'ancien_numero' => 'DEMO', 'date_rg' => now()->subDays(random_int(2, 20))->toDateString(),
                'statut' => 'enrolee', 'jointe_a_id' => $principale?->id, 'meme_numero' => $principale !== null,
            ]);
            foreach ($parties as $i => $p) {
                $physique = in_array($p['nature'], ['homme', 'femme'], true);
                $a->parties()->create($p + ['ordre' => $i, 'cle' => Partie::cle($physique ? ($p['nom'] ?? '').' '.($p['prenoms'] ?? '') : (string) ($p['denomination'] ?? ''))]);
            }

            return $a;
        };
        $personne = fn (string $role, string $nature, string $nom, string $prenoms, array $plus = []) => ['role' => $role, 'nature' => $nature, 'nom' => $nom, 'prenoms' => $prenoms] + $plus;
        $societe = fn (string $role, string $nom, array $plus = []) => ['role' => $role, 'nature' => 'morale', 'denomination' => $nom] + $plus;

        // ---------------- civil (RG) ----------------
        $societeBatimat = $societe('defendeur', 'Société BATIMAT SARL', ['domicile' => 'Abidjan, Plateau']);
        $principale = $creer('RG', 1391, [
            'mode_saisine_id' => $mode('Assignation', 'civil'), 'objet' => 'Paiement de loyers impayés et expulsion', 'nature_affaire_id' => $nature('Civile ordinaire', 'RG'),
            'matiere_id' => $matiere('Civile'), 'chambre_id' => $civile->id, 'date_premiere_audience' => now()->addDays(10)->toDateString(),
        ], [
            $personne('demandeur', 'femme', 'KOFFI', 'Adjoua', ['naissance_mode' => 'date', 'naissance_date' => '1978-04-12', 'lieu_naissance' => 'Bouaké', 'nationalite' => 'ivoirienne', 'profession' => 'Commerçante', 'domicile' => 'Bingerville', 'avocat' => 'Maître YAO Aminata']),
            $societeBatimat,
        ]);
        $creer('RG', 1391, [
            'mode_saisine_id' => $mode('Assignation', 'civil'), 'objet' => 'Paiement de dommages-intérêts (jonction avec 1391/'.sprintf('%02d', $yy % 100).')', 'nature_affaire_id' => $nature('Civile ordinaire', 'RG'),
            'matiere_id' => $matiere('Civile'), 'chambre_id' => $civile->id,
        ], [$personne('demandeur', 'homme', 'TRAORE', 'Salif', ['nationalite' => 'malienne', 'profession' => 'Maçon']), $societeBatimat], $principale);
        $creer('RG', 1402, [
            'mode_saisine_id' => $mode('Assignation', 'civil'), 'objet' => 'Déguerpissement, démolition et paiement de dommages-intérêts', 'nature_affaire_id' => $nature('Civile ordinaire', 'RG'),
            'matiere_id' => $matiere('Civile'), 'chambre_id' => $civile->id,
        ], [
            $personne('demandeur', 'homme', 'DIABY', 'Karamoko', ['avocat' => 'Cabinet ABI & Associés']),
            $personne('defendeur', 'homme', 'GNAGNE', 'Brice', ['autres' => 12, 'avocat' => 'Maître N\'GUESSAN Paul']),
        ]);
        $creer('RG', 1410, [
            'mode_saisine_id' => $mode('Requête', 'civil'), 'objet' => "Exequatur d'un jugement de divorce", 'nature_affaire_id' => $nature('État des personnes', 'RG'),
            'matiere_id' => $matiere('Civile'), 'chambre_id' => $autre->id,
        ], [$personne('demandeur', 'femme', 'BAMBA', 'Fatoumata'), ['role' => 'defendeur', 'nature' => 'mp', 'denomination' => 'MP']]);
        $annulee = $creer('RG', 1415, [
            'mode_saisine_id' => $mode('Assignation', 'civil'), 'objet' => 'Saisie en double (annulée)', 'nature_affaire_id' => $nature('Civile ordinaire', 'RG'),
            'matiere_id' => $matiere('Civile'), 'chambre_id' => $civile->id,
        ], [$personne('demandeur', 'homme', 'OUATTARA', 'Siaka')]);
        $annulee->forceFill(['statut' => 'annulee', 'motif_annulation' => "Saisie en double de l'affaire 1391", 'annulee_le' => now()])->save();

        // ---------------- pénal (RP) ----------------
        $creer('RP', 1334, [
            'mode_saisine_id' => $mode('Flagrant délit', 'penal'), 'objet' => 'Destruction volontaire de biens', 'articles' => 'Art. 485 du CP', 'nature_affaire_id' => $nature('Flagrants délits', 'RP'),
            'matiere_id' => $matiere('Correctionnelle'), 'chambre_id' => $correctionnelle->id,
        ], [
            $personne('prevenu', 'homme', 'JOHNSON', 'Wedha', ['naissance_mode' => 'annee', 'naissance_annee' => 2003, 'nationalite' => 'béninoise', 'profession' => 'Vigile', 'mandat_depot_date' => now()->subDays(12)->toDateString(), 'lieu_detention' => 'Maison d\'arrêt et de correction d\'Abidjan']),
            $personne('prevenu', 'homme', 'KOUADIO', 'Kouakou', ['naissance_mode' => 'annee', 'naissance_annee' => 1990, 'nationalite' => 'ivoirienne', 'profession' => 'Électricien']),
            $societe('victime', 'Entreprise BTP DELTA'),
        ]);
        $creer('RP', 1340, [
            'mode_saisine_id' => $mode('Citation directe', 'penal'), 'objet' => 'Abus de confiance', 'articles' => 'Art. 421 du CP', 'nature_affaire_id' => $nature('Délits divers', 'RP'),
            'matiere_id' => $matiere('Correctionnelle'), 'chambre_id' => $correctionnelle->id,
        ], [$societe('prevenu', 'Société NOVA SERVICES SA'), $personne('victime', 'femme', 'SERY', 'Dakoury')]);

        // ---------------- instruction (RI adultes, RE enfants) ----------------
        $creer('RI', 45, [
            'mode_saisine_id' => $mode('Réquisitoire', 'instruction'), 'objet' => 'Association de malfaiteurs et vol en réunion', 'articles' => 'Arts 205 et 462 du CP', 'nature_affaire_id' => $nature('Instruction (adultes)', 'RI'),
            'matiere_id' => $matiere('Instruction'), 'chambre_id' => $cabinet->id,
        ], [$personne('inculpe', 'homme', 'DOSSO', 'Ibrahim', ['nationalite' => 'burkinabè', 'profession' => 'Chauffeur', 'mandat_depot_date' => now()->subDays(40)->toDateString(), 'lieu_detention' => 'Maison d\'arrêt et de correction d\'Abidjan', 'echeance_detention' => now()->addDays(9)->toDateString()])]);
        $creer('RE', 45, [
            'mode_saisine_id' => $mode('Réquisitoire', 'instruction'), 'objet' => 'Vol avec effraction', 'articles' => 'Art. 457 du CP', 'nature_affaire_id' => $nature('Instruction (enfants)', 'RE'),
            'matiere_id' => $matiere('Enfance délinquante'), 'chambre_id' => $juge->id,
        ], [$personne('inculpe', 'homme', 'MINEUR', 'A.', ['naissance_mode' => 'annee', 'naissance_annee' => 2010])]);

        // ---------------- dossier numérique : quelques pièces fictives (vrais PDF et vraies images) ----------------
        $pieces = [
            [$principale, 'assignation', 'Assignation en paiement de loyers', 'pdf', ['ASSIGNATION', 'L\'an deux mil vingt-six, à la requête de Madame KOFFI Adjoua,', 'j\'ai donné assignation à la Société BATIMAT SARL', 'd\'avoir à comparaître devant le Tribunal de Première Instance', 'de Bingerville, 1re chambre civile, aux fins de paiement', 'des loyers impayés et d\'expulsion.']],
            [$principale, 'conclusions', 'Conclusions en défense de BATIMAT SARL', 'pdf', ['CONCLUSIONS EN DÉFENSE', 'Plaise au Tribunal de constater que les loyers réclamés', 'ont été réglés pour partie. (document fictif d\'essai)']],
            [$principale, 'pieces_versees', 'Quittance de loyer du mois de mars', 'png', ['Quittance de loyer', 'Mois de mars 2026', 'Montant : 250 000 FCFA']],
            [$principale, 'procuration', 'Procuration de Madame KOFFI', 'pdf', ['PROCURATION', 'Je soussignée KOFFI Adjoua donne pouvoir à Maître YAO Aminata', 'de me représenter devant le Tribunal. (fictif)']],
        ];
        foreach (Affaire::where('ancien_numero', 'DEMO')->where('serie', 'RP')->orderBy('id')->limit(1)->get() as $penale) {
            $pieces[] = [$penale, 'requisitoire', 'Procès-verbal de flagrant délit', 'pdf', ['PROCÈS-VERBAL DE FLAGRANT DÉLIT', 'Destruction volontaire de biens (art. 485 du CP)', 'Interpellation sur les lieux. (document fictif d\'essai)']];
            $pieces[] = [$penale, 'mandat', 'Mandat de dépôt', 'png', ['Mandat de dépôt', 'JOHNSON Wedha', 'Maison d\'arrêt de Bingerville']];
        }
        // Toutes les autres affaires d'essai reçoivent aussi quelques pièces, selon leur série.
        $deja = collect($pieces)->map(fn ($p) => $p[0]->id)->unique();
        foreach (Affaire::where('ancien_numero', 'DEMO')->orderBy('id')->get()->reject(fn ($x) => $deja->contains($x->id)) as $x) {
            $n = $x->numeroAffichage();
            $objet = (string) $x->objet;
            $note = '(document fictif d\'essai)';
            if (in_array($x->serie, ['RP', 'RI', 'RE'], true)) {
                $pieces[] = [$x, 'requisitoire', 'Procès-verbal d\'enquête - '.$n, 'pdf', ['PROCÈS-VERBAL D\'ENQUÊTE', 'Affaire '.$n, $objet, $note]];
                $pieces[] = [$x, 'ordonnance', 'Ordonnance du juge - '.$n, 'pdf', ['ORDONNANCE', 'Affaire '.$n, 'Le juge ordonne la poursuite de la procédure.', $note]];
                $pieces[] = [$x, 'pieces_versees', 'Photographie des lieux - '.$n, 'png', ['Photographie des lieux', $n, 'Pièce à conviction n° 1']];
            } else {
                $pieces[] = [$x, 'assignation', 'Assignation - '.$n, 'pdf', ['ASSIGNATION', 'Affaire '.$n, $objet, 'Le défendeur est cité à comparaître devant le Tribunal.', $note]];
                $pieces[] = [$x, 'signification', 'Acte de signification - '.$n, 'pdf', ['ACTE DE SIGNIFICATION', 'Affaire '.$n, 'Remis à personne par l\'huissier de justice.', $note]];
                $pieces[] = [$x, 'pieces_versees', 'Pièce versée aux débats - '.$n, 'png', ['Pièce versée aux débats', $n, 'Copie du titre produit par le demandeur']];
            }
        }
        foreach ($pieces as [$a, $type, $titre, $format, $lignes]) {
            $octets = $format === 'pdf' ? $this->pdf($titre, $lignes) : $this->image($titre, $lignes);
            $nom = Str::lower((string) Str::ulid()).'.'.$format;
            Storage::disk('local')->put(PieceDossier::DOSSIER.'/'.$a->id.'/'.$nom, $octets);
            PieceDossier::create([
                'affaire_id' => $a->id, 'type' => $type, 'titre' => $titre, 'date_piece' => now()->subDays(random_int(3, 25))->toDateString(), 'chemin' => $nom,
                'nom_original' => Str::slug($titre).'.'.$format, 'mime' => $format === 'pdf' ? 'application/pdf' : 'image/png', 'taille' => strlen($octets), 'empreinte' => hash('sha256', $octets),
            ]);
        }
        // Pénal : deux scellés d'essai sur la première affaire pénale ; requêtes : une d'hérédité à répartir, une déjà répartie.
        if ($penale = Affaire::where('ancien_numero', 'DEMO')->where('serie', 'RP')->orderBy('id')->first()) {
            $penale->scelles()->create(['numero' => 'S1', 'designation' => 'Une machette et un téléphone portable', 'nature' => 'arme', 'date_saisie' => now()->subDays(14)->toDateString(), 'lieu_conservation' => 'Greffe, armoire 2', 'etat' => 'conserve']);
            $penale->scelles()->create(['numero' => 'S2', 'designation' => 'Un sac contenant des effets personnels', 'nature' => 'autre', 'date_saisie' => now()->subDays(14)->toDateString(), 'lieu_conservation' => 'Greffe, armoire 2', 'etat' => 'restitue', 'date_etat' => now()->subDays(3)->toDateString(), 'observations' => 'Restitué au prévenu']);
        }
        if (! \App\Models\Requete::where('objet', 'like', '%(essai)')->exists()) {
            $annee = (int) date('Y');
            foreach ([
                ['heredite', now()->subDays(4), "Établissement de la qualité d'héritiers de feu KONAN Jean (essai)", "KONAN Marie, épouse\nKONAN Paul, fils", 'KONAN Jean', null],
                ['ordonnance_requete', now()->subDays(1), 'Autorisation de saisie conservatoire de créances (essai)', 'Société EXEMPLE IMMOBILIER SARL', null, '2e chambre civile'],
            ] as [$type, $date, $objet, $requerants, $defunt, $chambre]) {
                $n = \App\Services\NumerotationRegistre::attribuer('RQ', $annee);
                $chambreId = $chambre ? \App\Models\Chambre::where('libelle', $chambre)->value('id') : null;
                \App\Models\Requete::create(['annee' => $n['annee'], 'numero' => $n['numero'], 'numero_complet' => $n['libelle'], 'type' => $type, 'date_depot' => $date->toDateString(), 'objet' => $objet, 'requerants' => $requerants,
                    'defunt_nom' => $defunt, 'defunt_date_deces' => $defunt ? now()->subMonths(5)->toDateString() : null, 'defunt_lieu_deces' => $defunt ? 'Bingerville' : null,
                    'statut' => $chambreId ? 'repartie' : 'recue', 'chambre_id' => $chambreId, 'reparti_le' => $chambreId ? now() : null]);
            }
        }

        // Les avocats cités dans les affaires d'essai entrent dans le référentiel.
        foreach (Partie::whereIn('affaire_id', Affaire::where('ancien_numero', 'DEMO')->pluck('id'))->whereNotNull('avocat')->get() as $partie) {
            [$id, $texte] = \App\Models\Avocat::rattacher($partie->avocat, true);
            $partie->forceFill(['avocat_id' => $id, 'avocat' => $texte])->save();
        }
        $principale->forceFill(['localisation_dossier' => 'Greffe, armoire B, rayon 3, carton 12'])->save();

        $this->rolesDemo();
        $this->rolesDemoTypes();
        $this->rolesDemoScenarios();
        $this->rolesDemoSuites();
        $this->info(Affaire::where('ancien_numero', 'DEMO')->count().' affaires d\'essai créées : 5 civiles (dont une jonction, une annulée, un groupe « et 12 autres », le MP), 2 pénales, 1 RI, 1 RE.');
        $this->line("Pour les retirer : php artisan affaires:demo --retirer");

        return self::SUCCESS;
    }

    /** Un petit PDF valide (une page, du texte) : de quoi essayer la visionneuse sans fichier réel. */
    /** Deux rôles d'essai pour la chambre civile : l'audience passée (publiée, une affaire renvoyée) et la prochaine (composée automatiquement). */
    private function rolesDemo(): void
    {
        $demo = Affaire::where('ancien_numero', 'DEMO');
        if (\App\Models\RoleLigne::whereIn('affaire_id', (clone $demo)->pluck('id'))->exists()) {
            return;
        }
        $principale = (clone $demo)->where('serie', 'RG')->where('numero', 1391)->whereNull('jointe_a_id')->first();
        $ancienne = (clone $demo)->where('serie', 'RG')->where('numero', 1402)->first();
        if (! $principale || ! $ancienne || ! $principale->date_premiere_audience) {
            return;
        }
        $passee = now()->subDays(4)->startOfDay();
        $ancienne->forceFill(['date_premiere_audience' => $passee->toDateString()])->save();
        $salle = \App\Models\Salle::where('nom', 'like', '%n°4%')->first() ?? \App\Models\Salle::where('type', 'audience')->first();
        $base = ['chambre_id' => $principale->chambre_id, 'heure' => '09:00', 'salle_id' => $salle?->id, 'fait_a' => 'Bingerville'];

        $premier = \App\Models\RoleAudience::create($base + ['date_audience' => $passee->toDateString(), 'date_signature' => $passee->copy()->subDays(2)->toDateString()]);
        \App\Support\Roles\GenerateurRole::completer($premier);
        $premier->lignes()->update(['issue' => 'renvoi', 'renvoi_au' => $principale->date_premiere_audience->toDateString()]);
        $premier->forceFill(['statut' => 'publie', 'publie_le' => now()->subDays(3)])->save();
        \App\Models\RoleVersion::create(['role_id' => $premier->id, 'version' => 1, 'contenu' => json_encode(\App\Support\Roles\RoleDocument::depuis($premier->fresh()), JSON_UNESCAPED_UNICODE)]);

        $second = \App\Models\RoleAudience::create($base + ['date_audience' => $principale->date_premiere_audience->toDateString(), 'date_signature' => now()->toDateString()]);
        \App\Support\Roles\GenerateurRole::completer($second);
        $this->line("Rôles d'essai : ".$premier->lignes()->count().' affaire passée, '.$second->lignes()->count().' affaires au prochain rôle.');
    }

    /** Un rôle pénal du parquet (flagrants délits, avec mandats de dépôt) et un rôle de référé (expulsion et ordinaire), pour essayer les autres modèles. */
    private function rolesDemoTypes(): void
    {
        $demo = Affaire::where('ancien_numero', 'DEMO');
        $liees = \App\Models\RoleLigne::whereIn('affaire_id', (clone $demo)->pluck('id'));
        if ((clone $liees)->whereHas('role', fn ($q) => $q->whereIn('type', ['penal', 'refere']))->exists()) {
            return;
        }
        $penales = (clone $demo)->where('serie', 'RP')->get();
        $expulsion = (clone $demo)->where('serie', 'RG')->where('numero', 1402)->first();
        $ordinaire = (clone $demo)->where('serie', 'RG')->where('numero', 1391)->whereNull('jointe_a_id')->first();
        $ville = ucfirst(mb_strtolower((string) config('tpi.ville', 'Bingerville')));

        if ($penales->isNotEmpty()) {
            $date = now()->addDays(3)->toDateString();
            foreach ($penales as $a) {
                $a->forceFill(['date_premiere_audience' => $date])->save();
            }
            $role = \App\Models\RoleAudience::create([
                'chambre_id' => $penales->first()->chambre_id, 'type' => 'penal', 'date_audience' => $date, 'heure' => '10:00', 'salle_id' => \App\Models\Salle::where('nom', 'like', '%n°4%')->value('id'),
                'intitule' => 'AUDIENCE PUBLIQUE ORDINAIRE DES FLAGRANTS DELITS', 'fait_a' => 'Parquet de '.$ville, 'signataire' => 'LE PROCUREUR DE LA REPUBLIQUE', 'date_signature' => now()->toDateString(),
            ]);
            \App\Support\Roles\GenerateurRole::completer($role);
        }
        if ($expulsion && $ordinaire) {
            $role = \App\Models\RoleAudience::create([
                'chambre_id' => $expulsion->chambre_id, 'type' => 'refere', 'date_audience' => now()->addDays(7)->toDateString(), 'heure' => '09:00', 'salle_id' => \App\Models\Salle::where('nom', 'like', '%n°3%')->value('id'),
                'intitule' => 'REFERE EXPULSION – ORDINAIRE – HEURE A HEURE', 'fait_a' => $ville, 'date_signature' => now()->toDateString(),
            ]);
            foreach ([[$expulsion, 'nouvelles'], [$ordinaire, 'nouvelles']] as $i => [$a, $rubrique]) {
                $role->lignes()->create(\App\Support\Roles\GenerateurRole::attributs($a, $rubrique, $i + 1, 'ajout', 'refere'));
            }
        }
        $this->line("Rôles d'essai : un rôle pénal (flagrants délits) et un rôle de référé.");
    }

    /**
     * Scénarios pour essayer tout le menu des rôles : propositions à ajouter, suites à noter, conflit de salle (inséré tel quel pour le voir
     * en rouge), magistrat ou greffier déjà pris, jour fermé, vacances. Tout est fictif et retiré par « affaires:demo --retirer ».
     */
    private function rolesDemoScenarios(): void
    {
        if (Affaire::where('ancien_numero', 'DEMO')->where('serie', 'RG')->where('numero', 1501)->exists()) {
            return;
        }
        $principale = Affaire::where('ancien_numero', 'DEMO')->where('serie', 'RG')->where('numero', 1391)->whereNull('jointe_a_id')->first();
        $annee = now()->year;
        $mode = ModeSaisine::where('registre', 'civil')->value('id');
        $nature = NatureAffaire::where('registre', 'RG')->value('id');
        $matiere = Matiere::value('id');
        $salle3 = \App\Models\Salle::where('nom', 'like', '%n°3%')->value('id');
        $salle4 = \App\Models\Salle::where('nom', 'like', '%n°4%')->value('id');
        $chambre = fn (string $libelle) => Chambre::where('libelle', $libelle)->first();
        $ville = ucfirst(mb_strtolower((string) config('tpi.ville', 'Bingerville')));

        $noms = [['DEMO ABOUA', 'Serge'], ['DEMO TANOH', 'Esther'], ['DEMO YAPI', 'Roland'], ['DEMO GOHOU', 'Clarisse'], ['DEMO SIDIBE', 'Moussa'], ['DEMO KRAGBE', 'Nadège'], ['DEMO ZAHUI', 'Patrice'], ['DEMO BAKAYOKO', 'Fanta']];
        $objets = ['Paiement de loyers impayés', 'Résolution de bail et expulsion', 'Revendication de parcelle de terrain', 'Paiement de dommages-intérêts', 'Déguerpissement et démolition', 'Exécution forcée de convention', 'Responsabilité en paiement', 'Nullité de contrat de vente'];
        $i = 0;
        $creer = function (int $numero, ?Chambre $c, string $date) use (&$i, $annee, $mode, $nature, $matiere, $noms, $objets) {
            [$nom, $prenoms] = $noms[$i % count($noms)];
            $objet = $objets[$i % count($objets)];
            $i++;
            $a = Affaire::create([
                'serie' => 'RG', 'annee' => $annee, 'numero' => $numero, 'numero_complet' => NumerotationRegistre::formater('RG', $numero, $annee), 'numero_saisi' => true, 'ancien_numero' => 'DEMO',
                'date_rg' => now()->subDays(random_int(10, 40))->toDateString(), 'statut' => 'enrolee', 'objet' => $objet.' (essai)', 'mode_saisine_id' => $mode, 'nature_affaire_id' => $nature, 'matiere_id' => $matiere,
                'chambre_id' => $c->id, 'date_premiere_audience' => $date,
            ]);
            $a->parties()->create(['role' => 'demandeur', 'nature' => 'homme', 'nom' => $nom, 'prenoms' => $prenoms, 'ordre' => 0, 'cle' => Partie::cle($nom.' '.$prenoms)]);
            $a->parties()->create(['role' => 'defendeur', 'nature' => 'morale', 'denomination' => 'SOCIETE EXEMPLE '.$numero.' SARL', 'ordre' => 1, 'cle' => Partie::cle('SOCIETE EXEMPLE '.$numero.' SARL')]);

            return $a;
        };
        $role = fn (Chambre $c, string $date, string $heure, ?int $salle, string $statut = 'brouillon') => \App\Models\RoleAudience::create([
            'chambre_id' => $c->id, 'date_audience' => $date, 'heure' => $heure, 'salle_id' => $salle, 'fait_a' => $ville, 'date_signature' => now()->toDateString(), 'statut' => $statut,
        ]);
        $creneauJour = fn (Carbon $depart, int $jourIso) => ($depart->isoWeekday() === $jourIso ? $depart->copy() : $depart->copy()->next($jourIso))->startOfDay();

        // 1. Propositions : trois affaires nouvelles dont la première audience est celle du rôle civil en préparation.
        if ($principale && $principale->date_premiere_audience) {
            foreach ([1501, 1502, 1503] as $n) {
                $creer($n, $principale->chambre, $principale->date_premiere_audience->toDateString());
            }
        }

        // 2. Suites à noter : rôle publié de la 2e chambre civile (lundi dernier), quatre affaires, aucune suite notée. Le lundi suivant est à créer.
        $dernierLundi = now()->startOfWeek()->startOfDay();
        $dernierLundi = $dernierLundi->lt(today()) ? $dernierLundi : $dernierLundi->subWeek();
        $deuxieme = $chambre('2e chambre civile');
        if ($deuxieme) {
            foreach ([1511, 1512, 1513, 1514] as $n) {
                $creer($n, $deuxieme, $dernierLundi->toDateString());
            }
            $publie = $role($deuxieme, $dernierLundi->toDateString(), '09:00', $salle4);
            \App\Support\Roles\GenerateurRole::completer($publie);
            $publie->forceFill(['statut' => 'publie', 'publie_le' => $dernierLundi->copy()->subDay()])->save();
            \App\Models\RoleVersion::create(['role_id' => $publie->id, 'version' => 1, 'contenu' => json_encode(\App\Support\Roles\RoleDocument::depuis($publie->fresh()), JSON_UNESCAPED_UNICODE)]);
        }

        // 3. Conflit de salle, inséré directement pour le voir en rouge : 5e chambre civile à 09h00 et 4e à 10h00, même salle, mercredi dans deux semaines.
        $mercredi = $creneauJour(now()->addWeeks(2), 3);
        $cinq = $chambre('5e chambre civile');
        $quatre = $chambre('4e chambre civile');
        if ($cinq && $quatre) {
            foreach ([[1521, $cinq, '09:00'], [1522, $quatre, '10:00']] as [$n, $c, $h]) {
                $creer($n, $c, $mercredi->toDateString());
                \App\Support\Roles\GenerateurRole::completer($role($c, $mercredi->toDateString(), $h, $salle3));
            }
        }

        // 4. Magistrat ou greffier déjà pris : 3e chambre civile (salle 4, 09h00) et 5e (salle 3, 10h30) le vendredi suivant ; les mêmes agents figurent dans les deux compositions.
        $vendredi = $creneauJour($mercredi->copy()->addDay(), 5);
        $trois = $chambre('3e chambre civile');
        if ($trois && $cinq) {
            foreach ([[1531, $trois, '09:00', $salle4], [1532, $cinq, '10:30', $salle3]] as [$n, $c, $h, $sa]) {
                $creer($n, $c, $vendredi->toDateString());
                \App\Support\Roles\GenerateurRole::completer($role($c, $vendredi->toDateString(), $h, $sa));
            }
        }

        // 5. Jour fermé : fermeture exceptionnelle le jeudi de cette semaine-là, avec un rôle de la 1re chambre civile ce jour-là ; et des vacances en décembre.
        $courante = \App\Models\AnneeJudiciaire::where('courante', true)->value('id');
        $jeudi = $creneauJour($mercredi->copy()->addDay(), 4);
        $une = $chambre('1re chambre civile');
        if ($courante && $une) {
            \App\Models\Fermeture::create(['annee_judiciaire_id' => $courante, 'type' => 'fermeture', 'libelle' => 'Fermeture exceptionnelle (essai)', 'debut' => $jeudi->toDateString(), 'fin' => $jeudi->toDateString()]);
            \App\Models\Fermeture::create(['annee_judiciaire_id' => $courante, 'type' => 'vacances', 'libelle' => 'Vacances judiciaires (essai)', 'debut' => $annee.'-12-14', 'fin' => $annee.'-12-18']);
            $creer(1541, $une, $jeudi->toDateString());
            \App\Support\Roles\GenerateurRole::completer($role($une, $jeudi->toDateString(), '09:00', $salle4));
        }

        $this->line('Scénarios de rôles : dernier lundi '.$dernierLundi->format('d/m').' (2e civile, publié), mercredi '.$mercredi->format('d/m').' (conflit), jeudi '.$jeudi->format('d/m').' (fermé), vendredi '.$vendredi->format('d/m').' (avertissement), vacances du 14 au 18/12.');
    }

    /**
     * Données d'essai du lot K (feuille d'audience, renvois et délibérés, tableau de bord) : un rôle publié aujourd'hui à tenir (5e chambre civile)
     * avec des affaires renvoyées deux fois, un délibéré à rendre et six nouvelles ; deux audiences passées terminées ; et, pour la 3e chambre civile,
     * un renvoi dépassé, un délibéré échu, un délibéré prorogé, une affaire jugée à rédiger, des renvois proches et une affaire sans suite.
     */
    private function rolesDemoSuites(): void
    {
        if (Affaire::where('ancien_numero', 'DEMO')->where('serie', 'RG')->where('numero', 1601)->exists()) {
            return;
        }
        $cinq = Chambre::where('libelle', '5e chambre civile')->first();
        $trois = Chambre::where('libelle', '3e chambre civile')->first();
        if (! $cinq || ! $trois || \App\Models\RoleAudience::where('chambre_id', $cinq->id)->whereDate('date_audience', today())->exists()) {
            return;
        }
        $annee = now()->year;
        $mode = ModeSaisine::where('registre', 'civil')->value('id');
        $nature = NatureAffaire::where('registre', 'RG')->value('id');
        $matiere = Matiere::value('id');
        $salle = \App\Models\Salle::where('nom', 'like', '%n°3%')->value('id');
        $ville = ucfirst(mb_strtolower((string) config('tpi.ville', 'Bingerville')));
        $auj = today();

        $noms = [['DEMO ABOUA', 'Serge'], ['DEMO TANOH', 'Esther'], ['DEMO YAPI', 'Roland'], ['DEMO GOHOU', 'Clarisse'], ['DEMO SIDIBE', 'Moussa'], ['DEMO KRAGBE', 'Nadège'], ['DEMO ZAHUI', 'Patrice'], ['DEMO BAKAYOKO', 'Fanta']];
        $objets = ['Paiement de loyers impayés', 'Résolution de bail et expulsion', 'Revendication de parcelle de terrain', 'Paiement de dommages-intérêts', 'Déguerpissement et démolition', 'Exécution forcée de convention', 'Responsabilité en paiement', 'Nullité de contrat de vente'];
        $i = 0;
        $creer = function (int $numero, Chambre $c, Carbon $premiere) use (&$i, $annee, $mode, $nature, $matiere, $noms, $objets) {
            [$nom, $prenoms] = $noms[$i % count($noms)];
            $objet = $objets[$i % count($objets)];
            $i++;
            $a = Affaire::create([
                'serie' => 'RG', 'annee' => $annee, 'numero' => $numero, 'numero_complet' => NumerotationRegistre::formater('RG', $numero, $annee), 'numero_saisi' => true, 'ancien_numero' => 'DEMO',
                'date_rg' => $premiere->copy()->subDays(12)->toDateString(), 'statut' => 'enrolee', 'objet' => $objet.' (essai)', 'mode_saisine_id' => $mode, 'nature_affaire_id' => $nature, 'matiere_id' => $matiere,
                'chambre_id' => $c->id, 'date_premiere_audience' => $premiere->toDateString(),
            ]);
            $a->parties()->create(['role' => 'demandeur', 'nature' => 'homme', 'nom' => $nom, 'prenoms' => $prenoms, 'ordre' => 0, 'cle' => Partie::cle($nom.' '.$prenoms)]);
            $a->parties()->create(['role' => 'defendeur', 'nature' => 'morale', 'denomination' => 'SOCIETE EXEMPLE '.$numero.' SARL', 'ordre' => 1, 'cle' => Partie::cle('SOCIETE EXEMPLE '.$numero.' SARL')]);

            return $a;
        };
        // Un rôle publié dont les lignes portent déjà leur suite ; $termine : l'audience est close.
        $publie = function (Chambre $c, Carbon $date, array $lignes, bool $termine) use ($salle, $ville) {
            $role = \App\Models\RoleAudience::create(['chambre_id' => $c->id, 'date_audience' => $date->toDateString(), 'heure' => '09:00', 'salle_id' => $salle, 'fait_a' => $ville, 'date_signature' => $date->toDateString()]);
            $ordres = [];
            foreach ($lignes as [$affaire, $rubrique, $suite]) {
                $ordres[$rubrique] = ($ordres[$rubrique] ?? 0) + 1;
                $role->lignes()->create(\App\Support\Roles\GenerateurRole::attributs($affaire, $rubrique, $ordres[$rubrique], 'auto') + $suite + ($suite ? ['suite_le' => $date] : []));
            }
            $role->forceFill(['statut' => 'publie', 'publie_le' => $date->copy()->subDay()])->save();
            \App\Models\RoleVersion::create(['role_id' => $role->id, 'version' => 1, 'contenu' => json_encode(\App\Support\Roles\RoleDocument::depuis($role->fresh()), JSON_UNESCAPED_UNICODE)]);
            if ($termine) {
                $membres = $role->compositionTenue();
                if ($membres) {
                    $membres[0]['present'] = false;
                    $membres[0]['remplacant'] = 'TRAORE Salif (essai)';
                }
                $role->forceFill(['audience_ouverte_le' => $date, 'audience_terminee_le' => $date->copy()->setTime(13, 0), 'composition_tenue' => $membres ?: null])->save();
            }

            return $role;
        };

        // 5e chambre civile : deux audiences passées, puis le rôle d'aujourd'hui (à tenir).
        $a11 = $creer(1611, $cinq, $auj->copy()->subDays(28));
        $a12 = $creer(1612, $cinq, $auj->copy()->subDays(28));
        $a13 = $creer(1613, $cinq, $auj->copy()->subDays(28));
        $a14 = $creer(1614, $cinq, $auj->copy()->subDays(14));
        $a15 = $creer(1615, $cinq, $auj->copy()->subDays(14));
        $publie($cinq, $auj->copy()->subDays(28), [
            [$a11, 'nouvelles', ['issue' => 'renvoi', 'renvoi_au' => $auj->copy()->subDays(14)->toDateString(), 'motif' => 'Pour conclusions du demandeur', 'presence' => 'avocats']],
            [$a12, 'nouvelles', ['issue' => 'renvoi', 'renvoi_au' => $auj->copy()->subDays(14)->toDateString(), 'motif' => 'Absence de la partie citée', 'presence' => 'demandeur']],
            [$a13, 'nouvelles', ['issue' => 'delibere', 'date_delibere' => $auj->toDateString(), 'presence' => 'toutes']],
        ], true);
        $publie($cinq, $auj->copy()->subDays(14), [
            [$a11, 'anciennes', ['issue' => 'renvoi', 'renvoi_au' => $auj->toDateString(), 'motif' => 'Pour réplique', 'presence' => 'toutes']],
            [$a12, 'anciennes', ['issue' => 'renvoi', 'renvoi_au' => $auj->toDateString(), 'motif' => 'À la demande des parties', 'presence' => 'avocats']],
            [$a14, 'nouvelles', ['issue' => 'juge', 'presence' => 'toutes']],
            [$a15, 'nouvelles', ['issue' => 'radie', 'motif' => 'Défaut de diligence du demandeur', 'presence' => 'absentes']],
        ], true);
        foreach (range(1601, 1606) as $n) {
            $creer($n, $cinq, $auj);
        }
        $aujourdhui = \App\Models\RoleAudience::create(['chambre_id' => $cinq->id, 'date_audience' => $auj->toDateString(), 'heure' => '09:00', 'salle_id' => $salle, 'fait_a' => $ville, 'date_signature' => $auj->toDateString()]);
        \App\Support\Roles\GenerateurRole::completer($aujourdhui);
        $aujourdhui->forceFill(['statut' => 'publie', 'publie_le' => $auj->copy()->subDay()])->save();
        \App\Models\RoleVersion::create(['role_id' => $aujourdhui->id, 'version' => 1, 'contenu' => json_encode(\App\Support\Roles\RoleDocument::depuis($aujourdhui->fresh()), JSON_UNESCAPED_UNICODE)]);

        // 3e chambre civile : une audience passée dont les suites créent des retards, des délibérés et une décision à rédiger.
        $b = [];
        foreach (range(1621, 1627) as $n) {
            $b[$n] = $creer($n, $trois, $auj->copy()->subDays(21));
        }
        $publie($trois, $auj->copy()->subDays(21), [
            [$b[1621], 'nouvelles', ['issue' => 'renvoi', 'renvoi_au' => $auj->copy()->subDays(7)->toDateString(), 'motif' => 'Pour production de pièces', 'presence' => 'avocats']],
            [$b[1622], 'nouvelles', ['issue' => 'delibere', 'date_delibere' => $auj->copy()->subDays(4)->toDateString(), 'presence' => 'toutes']],
            [$b[1623], 'nouvelles', ['issue' => 'delibere', 'date_delibere' => $auj->copy()->addDays(17)->toDateString(), 'proroge' => 1, 'motif' => 'Note en délibéré attendue', 'presence' => 'toutes']],
            [$b[1624], 'nouvelles', ['issue' => 'juge', 'presence' => 'toutes']],
            [$b[1625], 'nouvelles', ['issue' => 'renvoi', 'renvoi_au' => $auj->copy()->addDays(6)->toDateString(), 'motif' => 'Pour conclusions du défendeur', 'presence' => 'demandeur']],
            [$b[1626], 'nouvelles', ['issue' => 'renvoi', 'renvoi_au' => $auj->copy()->addDays(14)->toDateString(), 'motif' => 'Pour tentative de conciliation', 'presence' => 'toutes']],
            [$b[1627], 'nouvelles', []],
        ], true);

        $this->line("Données du lot K : audience d'aujourd'hui à tenir (5e chambre civile, salle n°3), 2 audiences passées closes, et la 3e chambre civile avec retards, délibérés échu et prorogé, décision à rédiger.");
    }

    private function pdf(string $titre, array $lignes): string
    {
        $encode = fn (string $t) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_convert_encoding($t, 'Windows-1252', 'UTF-8'));
        $flux = "BT /F1 20 Tf 60 780 Td (".$encode($titre).") Tj ET\n";
        $y = 740;
        foreach ($lignes as $ligne) {
            $flux .= "BT /F1 13 Tf 60 $y Td (".$encode($ligne).") Tj ET\n";
            $y -= 22;
        }
        $flux .= "BT /F1 10 Tf 60 60 Td (Document fictif - affaires:demo) Tj ET\n";

        $objets = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            "<< /Length ".strlen($flux)." >>\nstream\n".$flux."endstream",
        ];
        $pdf = "%PDF-1.4\n";
        $positions = [];
        foreach ($objets as $i => $o) {
            $positions[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$o."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objets) + 1)."\n0000000000 65535 f \n";
        foreach ($positions as $p) {
            $pdf .= sprintf("%010d 00000 n \n", $p);
        }

        return $pdf."trailer\n<< /Size ".(count($objets) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }

    /** Une petite image PNG (fond clair, texte) pour essayer le zoom et la rotation. */
    private function image(string $titre, array $lignes): string
    {
        $img = imagecreatetruecolor(900, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, 250, 248, 240));
        imagerectangle($img, 20, 20, 879, 579, imagecolorallocate($img, 120, 120, 120));
        $encre = imagecolorallocate($img, 20, 40, 30);
        imagestring($img, 5, 50, 50, iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $titre), $encre);
        foreach ($lignes as $i => $l) {
            imagestring($img, 4, 50, 100 + $i * 30, iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $l), $encre);
        }
        imagestring($img, 2, 50, 540, 'Document fictif - affaires:demo', imagecolorallocate($img, 140, 140, 140));
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }
}
