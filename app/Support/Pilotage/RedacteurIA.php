<?php

namespace App\Support\Pilotage;

use App\Models\SynthesePilotage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/*
 | Analyse et recommandations rédigées par l'IA (Claude). Facultatif et encadré :
 |  - désactivée tant qu'on ne l'a pas activée dans Réglages > Pilotage et IA ET qu'une clé d'API est dans le .env du serveur ;
 |  - n'agit que sur demande (bouton), dans la limite quotidienne réglée ;
 |  - ne reçoit que des TOTAUX : nombres d'affaires, délais, retards par chambre. Jamais un nom de partie, un numéro d'affaire, un objet de dossier,
 |    un nom d'agent ni un document ;
 |  - en cas d'échec (clé absente, service injoignable, réponse mal formée), l'appelant retombe sur l'analyse automatique.
 */
class RedacteurIA
{
    private const SYSTEME = "Tu es un analyste de gestion de la performance judiciaire. Tu rédiges, pour la Présidente d'un tribunal de première instance de Côte d'Ivoire, une note de synthèse sur l'activité du tribunal. "
        ."Tu t'appuies UNIQUEMENT sur les chiffres fournis en JSON. Règles : n'invente aucun chiffre, aucun nom, aucune cause ; si une information manque, dis-le ; reste factuel, sobre et professionnel ; "
        ."emploie une forme impersonnelle (jamais le tutoiement) ; arrondis à une décimale ; 450 mots au maximum. "
        ."Structure OBLIGATOIRE, dans cet ordre, avec ces quatre titres exacts et aucun autre : « ## Situation d'ensemble » suivi d'un paragraphe de 3 à 5 phrases qui interprète les chiffres sans les réciter tous ; "
        ."« ## Points forts » avec 1 à 4 puces commençant par « - » ; « ## Points d'attention » avec 2 à 5 puces, du plus grave au moins grave ; « ## Recommandations » avec 3 à 5 puces d'actions concrètes, "
        ."chacune commençant par un verbe à l'infinitif et reliée à un chiffre précis ou à une chambre précise. "
        ."Ne remplis jamais une section pour faire nombre : un fait banal n'est pas un point fort ; s'il n'y a aucun point fort notable, écris une seule puce « - Aucun point fort notable sur la période. ». "
        ."Vocabulaire : une affaire « enrôlée » est une affaire enregistrée au tribunal ; une affaire « terminée » est jugée, radiée, jointe à une autre ou désistée ; le taux de traitement est le nombre d'affaires terminées pour 100 enrôlées. Compare les chambres entre elles quand les écarts sont nets. N'utilise ni tableau, ni émoji, ni autre mise en forme que ces titres et ces puces. Termine toujours par la section Recommandations.";

    public static function disponible(): bool
    {
        return config('pilotage.ia_active') === 'oui' && filled(config('pilotage.ia_cle'));
    }

    public static function quotaRestant(): int
    {
        $utilise = SynthesePilotage::where('source', 'ia')->whereDate('created_at', today()->toDateString())->count();

        return max(0, (int) config('pilotage.ia_generations_par_jour') - $utilise);
    }

    /** Ce qui part vers l'IA : des agrégats uniquement. @param  array<string, mixed>  $d  @return array<string, mixed> */
    public static function charge(array $d, array $analyse): array
    {
        return [
            'periode' => $d['periode']['libelle'], 'jours' => $d['periode']['jours'], 'perimetre' => $d['perimetre'], 'date_du_jour' => today()->format('d/m/Y'),
            'indicateurs' => self::renommer($d['kpis']) + ['agents_sans_compte' => $d['agents_sans_compte']],
            'indice_de_sante' => ['score' => $analyse['sante']['score'], 'facteurs_de_baisse' => $analyse['sante']['facteurs']],
            'par_chambre' => array_map(fn ($c) => ['chambre' => $c['libelle'], 'en_cours' => $c['stock'], 'affaires_enrolees' => $c['entrees'], 'affaires_terminees' => $c['sorties'], 'taux_de_traitement_pct' => $c['taux_sortie'],
                'delai_moyen_jours' => $c['delai_moyen'], 'affaires_en_retard' => $c['retards'], 'decisions_a_rediger' => $c['a_rediger'], 'taux_de_renvoi_pct' => $c['renvois_pct'], 'alertes_critiques' => $c['critiques']], $d['par_chambre']),
            'evolution_mensuelle' => array_map(fn ($m, $e, $s) => ['mois' => $m, 'affaires_enrolees' => $e, 'affaires_terminees' => $s], $d['mensuel']['mois'], $d['mensuel']['entrees'], $d['mensuel']['sorties']),
            'repartition_des_suites' => collect($d['suites'])->pluck('n', 'libelle')->all(),
            'alertes_par_type' => collect($d['alertes_par_type'])->pluck('n', 'libelle')->all(),
        ];
    }

    /** Les noms des indicateurs envoyés à l'IA sont ceux de l'application : une affaire « enrôlée » est enregistrée au tribunal, « terminée » est jugée, radiée, jointe ou désistée. */
    private static function renommer(array $kpis): array
    {
        $noms = ['stock' => 'affaires_en_cours', 'entrees' => 'affaires_enrolees', 'sorties' => 'affaires_terminees', 'taux_sortie' => 'taux_de_traitement_pct', 'retards' => 'affaires_en_retard'];
        $sortie = [];
        foreach ($kpis as $cle => $valeur) {
            $sortie[$noms[$cle] ?? $cle] = $valeur;
        }

        return $sortie;
    }

    /** @throws RuntimeException avec un message lisible par l'utilisateur */
    public static function generer(array $donnees, array $analyse, User $user): SynthesePilotage
    {
        if (! self::disponible()) {
            throw new RuntimeException("L'analyse par l'IA n'est pas activée.");
        }
        if (self::quotaRestant() <= 0) {
            throw new RuntimeException("La limite quotidienne d'analyses par l'IA est atteinte : l'analyse automatique reste disponible.");
        }

        $charge = self::charge($donnees, $analyse);
        try {
            $reponse = Http::timeout((int) config('pilotage.ia_delai_secondes', 45))->withHeaders(['x-api-key' => (string) config('pilotage.ia_cle'), 'anthropic-version' => '2023-06-01'])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => (string) config('pilotage.ia_modele'), 'max_tokens' => 4000, 'system' => self::SYSTEME,
                    'messages' => [['role' => 'user', 'content' => "Voici les chiffres du tribunal (JSON). Rédige la note de synthèse.\n\n".json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException("Le service d'IA est injoignable pour le moment : l'analyse automatique reste disponible.");
        }
        if (! $reponse->successful()) {
            throw new RuntimeException("Le service d'IA a refusé la demande (code ".$reponse->status().") : vérifiez la clé d'API et le modèle dans les réglages. L'analyse automatique reste disponible.");
        }

        $texte = self::nettoyer(collect((array) $reponse->json('content'))->where('type', 'text')->pluck('text')->implode("\n"));   // le modèle peut renvoyer d'abord un bloc de réflexion : on ne garde que le texte
        foreach (["## Situation d'ensemble", '## Points forts', "## Points d'attention", '## Recommandations'] as $titre) {
            if (! str_contains($texte, $titre)) {
                throw new RuntimeException("La réponse de l'IA est incomplète : l'analyse automatique reste disponible.");
            }
        }

        return SynthesePilotage::create([
            'user_id' => $user->id, 'source' => 'ia', 'periode_type' => $donnees['periode']['type'], 'periode_debut' => $donnees['periode']['debut'], 'periode_fin' => $donnees['periode']['fin'],
            'perimetre' => mb_substr($donnees['perimetre'], 0, 120), 'contenu' => $texte, 'chiffres' => $charge, 'modele_ia' => (string) config('pilotage.ia_modele'),
        ]);
    }

    /** Du texte simple : titres « ## » et puces « - » seulement, sans balise ni lien. */
    public static function nettoyer(string $texte): string
    {
        $texte = strip_tags(str_replace("\r", '', $texte));
        $texte = preg_replace('/\*\*(.*?)\*\*/su', '$1', $texte);
        $texte = preg_replace('/^\s*[*•]\s+/mu', '- ', $texte);

        return trim(mb_substr($texte, 0, 5000));
    }
}
