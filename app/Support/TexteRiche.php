<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Texte mis en forme (gras, italique, listes…) saisi dans l'éditeur de l'application.
 * Tout passe par une liste blanche de balises : rien d'exécutable n'est jamais conservé ni affiché.
 * Les anciens textes bruts (sans balise) restent lisibles : les retours à la ligne sont conservés à l'affichage.
 */
class TexteRiche
{
    private const BALISES = ['p', 'br', 'strong', 'em', 'u', 's', 'ol', 'ul', 'li', 'h3', 'h4', 'blockquote', 'a'];

    private const PROTOCOLES = ['http', 'https', 'mailto'];

    /** À l'enregistrement : HTML propre, ou null si le contenu est vide. */
    public static function nettoyer(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        if (! preg_match('/<[a-z!\/]/i', $html)) {
            return $html;   // texte brut : conservé tel quel
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $ancien = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($ancien);

        $corps = $doc->getElementsByTagName('body')->item(0);
        if (! $corps) {
            return null;
        }

        self::assainir($corps);

        $sortie = '';
        foreach ($corps->childNodes as $enfant) {
            $sortie .= $doc->saveHTML($enfant);
        }
        $sortie = trim(preg_replace('#(<p><br></p>\s*)+$#', '', trim($sortie)));

        return self::texte($sortie) === '' ? null : $sortie;
    }

    /** À l'affichage : HTML sûr, prêt à être inséré avec {!! !!}. */
    public static function html(?string $valeur): string
    {
        $valeur = (string) $valeur;
        if (trim($valeur) === '') {
            return '';
        }

        if (! preg_match('/<[a-z!\/]/i', $valeur)) {
            return nl2br(e($valeur));
        }

        return (string) self::nettoyer($valeur);
    }

    /** Texte seul, sans mise en forme (listes, info-bulles, journal). */
    public static function texte(?string $valeur): string
    {
        $valeur = (string) $valeur;
        if (! preg_match('/<[a-z!\/]/i', $valeur)) {
            return trim($valeur);
        }

        $valeur = preg_replace('#</(p|li|h3|h4|blockquote)>|<br\s*/?>#i', ' ', $valeur);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($valeur), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function assainir(DOMNode $noeud): void
    {
        foreach (iterator_to_array($noeud->childNodes) as $enfant) {
            if (! $enfant instanceof DOMElement) {
                if ($enfant->nodeType !== XML_TEXT_NODE) {
                    $noeud->removeChild($enfant);   // commentaires, instructions…
                }

                continue;
            }

            $nom = strtolower($enfant->nodeName);

            // Le texte collé d'un autre logiciel utilise <b> et <i> : on les ramène aux balises de l'éditeur.
            if (in_array($nom, ['b', 'i'], true)) {
                $remplacant = $enfant->ownerDocument->createElement($nom === 'b' ? 'strong' : 'em');
                while ($enfant->firstChild) {
                    $remplacant->appendChild($enfant->firstChild);
                }
                $noeud->replaceChild($remplacant, $enfant);
                $enfant = $remplacant;
                $nom = $remplacant->nodeName;
            }

            if (in_array($nom, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'template', 'noscript'], true)) {
                $noeud->removeChild($enfant);

                continue;
            }

            self::assainir($enfant);

            if (! in_array($nom, self::BALISES, true)) {
                // Balise inconnue : on garde le texte et les balises permises qu'elle contient.
                while ($enfant->firstChild) {
                    $noeud->insertBefore($enfant->firstChild, $enfant);
                }
                $noeud->removeChild($enfant);

                continue;
            }

            $alignement = in_array($nom, ['p', 'h3', 'h4', 'blockquote', 'li'], true) && preg_match('/ql-align-(center|right|justify)/', $enfant->getAttribute('class'), $al) ? 'ql-align-'.$al[1] : null;
            $href = $nom === 'a' ? self::lienSur($enfant->getAttribute('href')) : null;

            foreach (iterator_to_array($enfant->attributes) as $attribut) {
                $enfant->removeAttributeNode($attribut);
            }

            if ($alignement) {
                $enfant->setAttribute('class', $alignement);
            }

            if ($href) {
                $enfant->setAttribute('href', $href);
                $enfant->setAttribute('rel', 'noopener noreferrer');
                $enfant->setAttribute('target', '_blank');
            }
        }
    }

    private static function lienSur(string $url): ?string
    {
        $schema = strtolower((string) parse_url(trim($url), PHP_URL_SCHEME));

        return in_array($schema, self::PROTOCOLES, true) ? trim($url) : null;
    }
}
