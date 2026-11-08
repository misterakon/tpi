<?php

namespace App\Http\Controllers;

use App\Mail\TestCourriel;
use App\Services\Journal;
use App\Services\Reglages;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/*
 | Page « Réglages » : modifier depuis l'application ce qui peut l'être sans risque.
 | Droits : reglages.voir (consulter), reglages.modifier (enregistrer, rétablir les défauts) et
 | reglages.secrets (rubrique « Courriels » : serveur et mot de passe SMTP).
 | Chaque modification est bornée (voir le catalogue) et inscrite au journal d'audit, sans jamais y mettre un secret.
 */
class ReglageController extends Controller
{
    public function __construct(private readonly Reglages $reglages) {}

    public function index(Request $request): View
    {
        Gate::authorize('reglages.voir');

        $rubriques = [];
        foreach ($this->rubriquesVisibles($request) as $cle => $rubrique) {
            $champs = [];
            foreach ($rubrique['champs'] as $cleChamp => $champ) {
                $secret = Reglages::estSecret($champ);
                $derniere = $this->reglages->estModifie($cleChamp) ? $this->reglages->derniereModification($cleChamp) : null;
                $champs[] = $champ + [
                    'cle' => $cleChamp,
                    'nom' => Reglages::nomChamp($cleChamp),
                    'secret' => $secret,
                    // Un secret n'est JAMAIS envoyé à la page : seulement l'information « enregistré ou non ».
                    'valeur' => $secret ? null : config($cleChamp),
                    'defaut' => $secret ? null : $this->reglages->defaut($cleChamp),
                    'definie' => $secret ? filled(config($cleChamp)) : null,
                    'defautDefini' => $secret ? filled($this->reglages->defaut($cleChamp)) : null,
                    'modifie' => $derniere !== null,
                    'derniere' => $derniere,
                ];
            }
            $rubriques[$cle] = ['champs' => $champs, 'nbModifies' => count(array_filter($champs, fn ($c) => $c['modifie']))] + $rubrique;
        }

        return view('reglage.index', [
            'rubriques' => $rubriques,
            'actif' => array_key_exists($request->query('groupe'), $rubriques) ? $request->query('groupe') : array_key_first($rubriques),
            'emailUtilisateur' => $request->user()->email,
            'modeCourriel' => config('mail.default'),
        ]);
    }

    public function update(Request $request, string $rubrique): RedirectResponse
    {
        $def = $this->autoriserRubrique($rubrique);

        $regles = [];
        $messages = [];
        $attributs = [];
        $secrets = [];
        foreach ($def['champs'] as $cle => $champ) {
            $nom = Reglages::nomChamp($cle);
            $libelle = $champ['libelle'];
            $attributs[$nom] = $libelle;
            $messages["$nom.required"] = "« $libelle » est obligatoire.";

            if (Reglages::estSecret($champ)) {
                $secrets[] = $nom;
                $regles[$nom] = ['nullable', 'string', 'max:'.$champ['max']];
                $messages["$nom.max"] = "« $libelle » : {$champ['max']} caractères au maximum.";
            } elseif ($champ['type'] === 'entier') {
                $regles[$nom] = ['required', 'integer', 'min:'.$champ['min'], 'max:'.$champ['max']];
                $messages["$nom.integer"] = "« $libelle » doit être un nombre entier.";
                $messages["$nom.min"] = "« $libelle » : minimum {$champ['min']} {$champ['unite']}.";
                $messages["$nom.max"] = "« $libelle » : maximum {$champ['max']} {$champ['unite']}.";
            } elseif ($champ['type'] === 'liste') {
                $regles[$nom] = ['required', Rule::in(array_keys($champ['options']))];
                $messages["$nom.in"] = "« $libelle » : choix invalide.";
            } else {
                $regles[$nom] = [($champ['requis'] ?? true) ? 'required' : 'nullable', 'string', 'max:'.$champ['max']];
                $messages["$nom.max"] = "« $libelle » : {$champ['max']} caractères au maximum.";
                if (($champ['format'] ?? null) === 'email') {
                    $regles[$nom][] = 'email';
                    $messages["$nom.email"] = "« $libelle » : adresse e-mail invalide.";
                } elseif (($champ['format'] ?? null) === 'police') {
                    $regles[$nom][] = 'regex:/^[\p{L}0-9][\p{L}0-9 .\-]*$/u';
                    $messages["$nom.regex"] = "« $libelle » : lettres, chiffres, espaces, point et tiret seulement.";
                } elseif (($champ['format'] ?? null) === 'hote') {
                    $regles[$nom][] = $this->regleHote($libelle);
                }
            }
        }

        $validateur = Validator::make($request->all(), $regles, $messages, $attributs);
        if ($validateur->fails()) {
            // Les mots de passe ne sont jamais renvoyés dans le formulaire après une erreur.
            return redirect()->route('reglages.index', ['groupe' => $rubrique])->withErrors($validateur)->withInput($request->except($secrets));
        }

        $valides = $validateur->validated();
        $valeurs = [];
        foreach ($def['champs'] as $cle => $champ) {
            $nom = Reglages::nomChamp($cle);
            $valeur = $valides[$nom] ?? null;

            if (Reglages::estSecret($champ)) {
                if ($request->boolean(Reglages::nomEffacer($cle))) {
                    $valeurs[$cle] = '';           // effacer
                } elseif (filled($valeur)) {
                    $valeurs[$cle] = $valeur;      // nouveau mot de passe ; champ vide = conserver
                }

                continue;
            }
            $valeurs[$cle] = is_string($valeur) ? trim($valeur) : $valeur;
        }

        $changes = $this->reglages->enregistrer($valeurs, $request->user());
        Mail::purge();   // un changement de serveur doit servir dès le prochain envoi

        return $this->conclure($rubrique, $def['libelle'], $changes, 'enregistres');
    }

    /** Rétablit les valeurs par défaut d'une rubrique. */
    public function defaut(Request $request, string $rubrique): RedirectResponse
    {
        $def = $this->autoriserRubrique($rubrique);
        $changes = $this->reglages->reinitialiser($rubrique);
        Mail::purge();

        return $this->conclure($rubrique, $def['libelle'], $changes, 'retablis');
    }

    /** Envoie un courriel de test avec la configuration ACTUELLE (à utiliser après avoir enregistré). */
    public function tester(Request $request): JsonResponse
    {
        $this->autoriserRubrique('courriels');
        $donnees = $request->validate(['destinataire' => ['required', 'string', 'email', 'max:255']], [
            'destinataire.required' => 'Saisissez une adresse de destination.',
            'destinataire.email' => 'Adresse e-mail invalide.',
        ]);

        $mode = (string) config('mail.default');
        config(['mail.mailers.smtp.timeout' => 15]);   // un serveur injoignable ne doit pas bloquer la page
        Mail::purge();

        try {
            Mail::mailer($mode)->to($donnees['destinataire'])->send(new TestCourriel(
                $request->user()->name,
                $mode,
                $mode === 'smtp' ? config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port') : 'journal du serveur',
                (string) config('mail.from.address'),
            ));
        } catch (Throwable $e) {
            $erreur = $this->nettoyer($e->getMessage());
            Journal::audit('courriel_test_echec', null, $donnees['destinataire'], [], ['mode' => $mode, 'erreur' => $erreur]);

            return response()->json(['status' => false, 'message' => "L'envoi a échoué : $erreur"]);
        }

        Journal::audit('courriel_test_envoye', null, $donnees['destinataire'], [], ['mode' => $mode]);

        return response()->json($mode === 'smtp'
            ? ['status' => true, 'message' => 'Courriel envoyé à '.$donnees['destinataire'].'. Vérifiez sa boîte de réception (et le dossier des indésirables).']
            : ['status' => true, 'message' => "Mode Test : le courriel a été écrit dans le journal du serveur, il n'a pas été envoyé. Choisissez « Envoi réel » pour envoyer vraiment."]);
    }

    /** Droits requis pour modifier une rubrique ; retourne sa définition. */
    private function autoriserRubrique(string $rubrique): array
    {
        Gate::authorize('reglages.modifier');
        $def = Reglages::catalogue()[$rubrique] ?? abort(404);
        if ($droit = Reglages::droitRubrique($rubrique)) {
            Gate::authorize($droit);
        }

        return $def;
    }

    /** Rubriques que l'utilisateur a le droit de voir (certaines exigent un droit supplémentaire). */
    private function rubriquesVisibles(Request $request): array
    {
        return array_filter(
            Reglages::catalogue(),
            fn ($rubrique) => empty($rubrique['droit']) || $request->user()->peut($rubrique['droit']),
        );
    }

    /** Nom d'hôte ou adresse IP valide. */
    private function regleHote(string $libelle): Closure
    {
        return function (string $attribut, mixed $valeur, Closure $echec) use ($libelle) {
            $v = (string) $valeur;
            if (filter_var($v, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false && filter_var($v, FILTER_VALIDATE_IP) === false) {
                $echec("« $libelle » : nom de serveur ou adresse IP invalide.");
            }
        };
    }

    /** Retire d'un message d'erreur tout ce qui ressemble à un secret, et le tronque. */
    private function nettoyer(string $message): string
    {
        foreach ([config('mail.mailers.smtp.password')] as $secret) {
            if (filled($secret)) {
                $message = str_replace((string) $secret, '********', $message);
            }
        }

        return mb_substr(trim(preg_replace('/\s+/', ' ', $message) ?? $message), 0, 300);
    }

    /** Journal d'audit (avant / après, sans secret) et message à l'utilisateur. */
    private function conclure(string $rubrique, string $libelle, array $changes, string $mode): RedirectResponse
    {
        if ($changes === []) {
            return redirect()->route('reglages.index', ['groupe' => $rubrique])->with('info', 'Aucune modification : les valeurs étaient déjà celles-ci.');
        }

        Journal::audit(
            $mode === 'retablis' ? 'reglages_retablis' : 'reglages_modifies',
            null,
            $libelle,
            array_map(fn ($v) => $v[0], $changes),
            array_map(fn ($v) => $v[1], $changes),
        );

        $n = count($changes);
        $message = $mode === 'retablis'
            ? "Valeurs par défaut rétablies pour « $libelle » ($n réglage".($n > 1 ? 's' : '').').'
            : "Réglages « $libelle » enregistrés ($n modification".($n > 1 ? 's' : '').'). Ils s\'appliquent dès maintenant.';

        return redirect()->route('reglages.index', ['groupe' => $rubrique])->with('success', $message);
    }
}
