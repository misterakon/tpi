<?php

namespace App\Support\Aide;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/*
 | Manuel d'utilisation intégré. Chaque chapitre est un fichier Markdown de resources/aide/ (relisible et modifiable sans toucher au code) :
 |
 |   ---
 |   titre: Affaires
 |   icone: fa-solid fa-folder-open
 |   couleur: vert            (vert | orange | bleu | violet)
 |   resume: Une phrase pour la carte du sommaire.
 |   droit: affaires.voir, dossiers.voir   (facultatif : un seul de ces droits suffit pour voir le chapitre)
 |   routes: affaires.*, instruction.*     (pages de l'application que ce chapitre explique : sert au bouton « ? »)
 |   ordre: 2
 |   type: faq                              (facultatif : chaque « ## » devient une question dépliable)
 |   ---
 |   Introduction du chapitre…
 |   ## Une section
 |   Texte, listes numérotées (étapes), tableaux, et notes : « > **Astuce** … », « > **Attention** … », « > **À savoir** … »
 |
 | Chacun ne voit que les chapitres dont il a le droit ; la recherche suit la même règle.
 */
class ManuelAide
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $chapitres = null;

    private static ?MarkdownConverter $convertisseur = null;

    /** Tous les chapitres, triés, avec leurs sections (HTML déjà prêt). */
    public static function tous(): array
    {
        if (self::$chapitres !== null) {
            return self::$chapitres;
        }
        $liste = [];
        foreach (File::glob(resource_path('aide/*.md')) as $fichier) {
            $slug = preg_replace('/^\d+-/', '', pathinfo($fichier, PATHINFO_FILENAME));   // « 02-affaires.md » devient « affaires »
            $chapitre = self::lire($slug, (string) File::get($fichier));
            $liste[$slug] = $chapitre;
        }
        uasort($liste, fn ($a, $b) => [$a['ordre'], $a['titre']] <=> [$b['ordre'], $b['titre']]);

        return self::$chapitres = $liste;
    }

    public static function oublier(): void
    {
        self::$chapitres = null;
    }

    /** Les chapitres que cet utilisateur a le droit de lire. */
    public static function visibles(?User $user): array
    {
        return array_filter(self::tous(), fn ($c) => self::permis($c, $user));
    }

    public static function chapitre(string $slug, ?User $user): ?array
    {
        $c = self::tous()[$slug] ?? null;

        return $c && self::permis($c, $user) ? $c : null;
    }

    /** Le chapitre qui explique la page affichée (d'après le nom de sa route), parmi ceux que l'utilisateur peut lire. */
    public static function pour(?string $nomRoute, ?User $user): ?array
    {
        if (! $nomRoute) {
            return null;
        }
        $meilleur = null;
        $longueur = -1;
        foreach (self::visibles($user) as $c) {
            foreach ($c['routes'] as $motif) {
                if (Str::is($motif, $nomRoute) && strlen($motif) > $longueur) {
                    $meilleur = $c;
                    $longueur = strlen($motif);
                }
            }
        }

        return $meilleur;
    }

    /**
     * Recherche « tous les mots » dans les sections des chapitres lisibles.
     *
     * @return list<array{chapitre: string, slug: string, icone: string, couleur: string, section: string, ancre: string, extrait: string}>
     */
    public static function chercher(string $saisie, ?User $user, int $max = 12): array
    {
        $mots = array_slice(array_values(array_filter(preg_split('/\s+/u', mb_strtolower(mb_substr(trim($saisie), 0, 100))) ?: [], fn ($m) => mb_strlen($m) >= 2)), 0, 6);
        if ($mots === []) {
            return [];
        }
        $plat = fn (string $t) => Str::ascii(mb_strtolower($t));
        $motsPlats = array_map($plat, $mots);
        $resultats = [];
        foreach (self::visibles($user) as $c) {
            foreach ($c['sections'] as $s) {
                $texte = $plat($s['titre'].' '.$s['texte']);
                $tout = true;
                $score = 0;
                foreach ($motsPlats as $m) {
                    if (! str_contains($texte, $m)) {
                        $tout = false;
                        break;
                    }
                    $score += str_contains($plat($s['titre']), $m) ? 5 : 1;
                }
                if ($tout) {
                    $resultats[] = ['score' => $score, 'chapitre' => $c['titre'], 'slug' => $c['slug'], 'icone' => $c['icone'], 'couleur' => $c['couleur'],
                        'section' => $s['titre'], 'ancre' => $s['ancre'], 'extrait' => self::extrait($s['texte'], $mots)];
                }
            }
        }
        usort($resultats, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn ($r) => array_diff_key($r, ['score' => 0]), array_slice($resultats, 0, $max));
    }

    /* ------------------------------------------------------------------ lecture d'un fichier */

    private static function permis(array $c, ?User $user): bool
    {
        if ($c['droits'] === []) {
            return $user !== null;
        }

        return $user !== null && collect($c['droits'])->contains(fn ($d) => $user->can($d));
    }

    private static function lire(string $slug, string $brut): array
    {
        $brut = str_replace("\r\n", "\n", $brut);
        $meta = [];
        if (preg_match('/\A---\n(.*?)\n---\n?(.*)\z/s', $brut, $m)) {
            foreach (explode("\n", $m[1]) as $ligne) {
                if (str_contains($ligne, ':')) {
                    [$k, $v] = explode(':', $ligne, 2);
                    $meta[trim($k)] = trim($v);
                }
            }
            $brut = $m[2];
        }
        $liste = fn (?string $v) => array_values(array_filter(array_map('trim', explode(',', (string) $v))));

        // [introduction, titre 1, corps 1, titre 2, corps 2…] : une section par titre « ## ».
        $morceaux = preg_split('/^## +(.+)$/m', $brut, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [''];
        $intro = trim((string) $morceaux[0]);
        $sections = [];
        $vus = [];
        for ($i = 1; $i < count($morceaux); $i += 2) {
            $titre = trim($morceaux[$i]);
            $corps = trim((string) ($morceaux[$i + 1] ?? ''));
            $base = Str::slug($titre) ?: 'section';
            $vus[$base] = ($vus[$base] ?? 0) + 1;
            $texte = self::texte($corps);
            $sections[] = ['titre' => $titre, 'ancre' => $vus[$base] > 1 ? $base.'-'.$vus[$base] : $base, 'html' => self::html($corps), 'texte' => $texte, 'duree' => max(1, (int) ceil(str_word_count($texte) / 180))];
        }

        return [
            'slug' => $slug, 'titre' => $meta['titre'] ?? Str::headline($slug), 'icone' => $meta['icone'] ?? 'fa-solid fa-book-open', 'couleur' => $meta['couleur'] ?? 'vert',
            'resume' => $meta['resume'] ?? '', 'droits' => $liste($meta['droit'] ?? null), 'routes' => $liste($meta['routes'] ?? null), 'ordre' => (int) ($meta['ordre'] ?? 50), 'type' => $meta['type'] ?? 'chapitre',
            'intro_html' => $intro === '' ? '' : self::html($intro), 'sections' => $sections,
            'minutes' => max(1, array_sum(array_column($sections, 'duree'))),
        ];
    }

    private static function html(string $markdown): string
    {
        self::$convertisseur ??= (function () {
            $env = new Environment(['html_input' => 'strip', 'allow_unsafe_links' => false]);
            $env->addExtension(new CommonMarkCoreExtension);
            $env->addExtension(new GithubFlavoredMarkdownExtension);

            return new MarkdownConverter($env);
        })();
        $html = self::$convertisseur->convert($markdown)->getContent();
        // Notes colorées : « > **Astuce** … », « > **Attention** … », « > **À savoir** … ».
        $html = preg_replace_callback('#<blockquote>\s*<p><strong>(Astuce|Attention|À savoir|Important)</strong>#u', function ($m) {
            $genre = ['Astuce' => 'astuce', 'Attention' => 'attention', 'À savoir' => 'savoir', 'Important' => 'attention'][$m[1]];

            return '<blockquote class="aide-note aide-note--'.$genre.'"><p><strong>'.$m[1].'</strong>';
        }, $html);

        // Captures d'écran : « ![Légende](aide/fichier.png "1@12,30=Cliquez ici | 2@50,60=Puis là") ».
        // Chaque repère « n@x,y=texte » pose une épingle numérotée à x % / y % de l'image ; les textes forment la légende.
        $html = preg_replace_callback('#<img src="(?:aide/|/aide/)?([A-Za-z0-9._-]+\.(?:png|jpe?g|webp|gif))" alt="([^"]*)"(?: title="([^"]*)")?\s*/?>#', function ($m) {
            $url = asset('assets/aide/'.$m[1]);
            $reperes = '';
            $legende = '';
            foreach (explode('|', html_entity_decode($m[3] ?? '')) as $r) {
                if (preg_match('/^\s*(\d{1,2})\s*@\s*(\d{1,3}(?:\.\d+)?)\s*,\s*(\d{1,3}(?:\.\d+)?)\s*(?:=\s*(.*?))?\s*$/u', $r, $p)) {
                    $n = (int) $p[1];
                    $reperes .= '<span class="aide-marque" data-n="'.$n.'" style="left:'.min(100, (float) $p[2]).'%;top:'.min(100, (float) $p[3]).'%;--d:'.(($n - 1) * 140).'ms"><b>'.$n.'</b></span>';
                    $legende .= '<li data-n="'.$n.'"><span>'.$n.'</span><div>'.e($p[4] ?? '').'</div></li>';
                }
            }

            return '<figure class="aide-capture"><div class="aide-capture__img"><img src="'.e($url).'" alt="'.$m[2].'" loading="lazy">'.$reperes.'</div>'
                .($legende !== '' ? '<ol class="aide-legende">'.$legende.'</ol>' : '')
                .($m[2] !== '' ? '<figcaption><i class="fa-solid fa-camera"></i> '.$m[2].'</figcaption>' : '').'</figure>';
        }, $html) ?? $html;
        $html = preg_replace('#<p>\s*(<figure class="aide-capture">.*?</figure>)\s*</p>#s', '$1', $html) ?? $html;

        return (string) $html;
    }

    private static function texte(string $markdown): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags(self::html($markdown))));
    }

    private static function extrait(string $texte, array $mots): string
    {
        $plat = Str::ascii(mb_strtolower($texte));
        $pos = 0;
        foreach ($mots as $m) {
            $p = mb_strpos($plat, Str::ascii($m));
            if ($p !== false) {
                $pos = $p;
                break;
            }
        }
        $debut = max(0, $pos - 50);
        $morceau = mb_substr($texte, $debut, 150);
        $morceau = ($debut > 0 ? '…' : '').$morceau.(mb_strlen($texte) > $debut + 150 ? '…' : '');
        $morceau = e($morceau);
        foreach ($mots as $m) {
            $morceau = preg_replace('/('.preg_quote(e($m), '/').')/iu', '<mark>$1</mark>', $morceau) ?? $morceau;
        }

        return $morceau;
    }
}
