<?php

namespace Tests\Unit;

use App\Support\Redaction\Accords;
use App\Support\Redaction\DateEnLettres;
use App\Support\Redaction\Gabarit;
use App\Support\Redaction\NombreEnLettres as N;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RedactionTest extends TestCase
{
    public static function nombres(): array
    {
        return [
            [0, 'zéro'], [1, 'un'], [16, 'seize'], [17, 'dix-sept'], [21, 'vingt et un'], [22, 'vingt-deux'], [31, 'trente et un'], [61, 'soixante et un'],
            [70, 'soixante-dix'], [71, 'soixante et onze'], [72, 'soixante-douze'], [77, 'soixante-dix-sept'], [80, 'quatre-vingts'], [81, 'quatre-vingt-un'],
            [91, 'quatre-vingt-onze'], [99, 'quatre-vingt-dix-neuf'], [100, 'cent'], [101, 'cent un'], [200, 'deux cents'], [201, 'deux cent un'],
            [280, 'deux cent quatre-vingts'], [1000, 'mille'], [1001, 'mille un'], [2000, 'deux mille'], [2025, 'deux mille vingt-cinq'],
            [80000, 'quatre-vingt mille'], [200000, 'deux cent mille'], [1_000_000, 'un million'], [2_000_000, 'deux millions'],
            [80_000_000, 'quatre-vingts millions'], [200_000_000, 'deux cents millions'], [1_200_000, 'un million deux cent mille'], [1_000_000_000, 'un milliard'],
            [1200, 'mille deux cents'], [999, 'neuf cent quatre-vingt-dix-neuf'],
        ];
    }

    #[DataProvider('nombres')]
    public function test_nombres_en_lettres(int $n, string $attendu): void
    {
        $this->assertSame($attendu, N::convertir($n));
    }

    public function test_nombres_au_feminin(): void
    {
        $this->assertSame('une', N::convertir(1, true));
        $this->assertSame('vingt et une', N::convertir(21, true));
        $this->assertSame('quatre-vingt-une', N::convertir(81, true));
        $this->assertSame('cent une', N::convertir(101, true));
        $this->assertSame('mille une', N::convertir(1001, true));
    }

    public function test_annees_avec_mil(): void
    {
        $this->assertSame('deux mil vingt-cinq', N::annee(2025));
        $this->assertSame('mil neuf cent quatre-vingt-dix-neuf', N::annee(1999));
        $this->assertSame('deux mil', N::annee(2000));
        $this->assertSame('deux mil vingt-six', N::annee(2026));
    }

    public function test_chiffres_et_lettres_montants_et_heures(): void
    {
        $this->assertSame('trois (03) enfants', N::chiffresEtLettres(3, 'enfant'));
        $this->assertSame('un (01) enfant', N::chiffresEtLettres(1, 'enfant'));
        $this->assertSame('douze (12)', N::chiffresEtLettres(12));
        $this->assertSame('mille deux cents (1 200)', N::chiffresEtLettres(1200));

        $this->assertSame('un million deux cent mille (1 200 000) francs CFA', N::montant(1_200_000));
        $this->assertSame('deux millions (2 000 000) de francs CFA', N::montant(2_000_000));
        $this->assertSame('un (1) franc CFA', N::montant(1));

        $this->assertSame('neuf heures', N::heure(9));
        $this->assertSame('quatorze heures trente minutes', N::heure(14, 30));
        $this->assertSame('une heure', N::heure(1));
        $this->assertSame('vingt et une heures une minute', N::heure(21, 1));
    }

    public function test_nombre_hors_limites_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        N::convertir(-1);
    }

    public function test_dates_en_lettres(): void
    {
        $this->assertSame('vendredi quatre juillet deux mil vingt-cinq', DateEnLettres::complete('2025-07-04'));
        $this->assertSame('jeudi premier octobre deux mil vingt-six', DateEnLettres::complete('2026-10-01'));
        $this->assertSame('douze octobre deux mil vingt-six', DateEnLettres::sansJour('2026-10-12'));
        $this->assertSame('04/07/2025', DateEnLettres::numerique('2025-07-04'));
        $this->assertSame('lundi douze octobre deux mil vingt-six (12/10/2026)', DateEnLettres::lettresEtChiffres('2026-10-12'));
        $this->assertSame('jeudi vingt-neuf février deux mil vingt-quatre', DateEnLettres::complete('2024-02-29'));
    }

    public function test_accords_de_genre_et_de_nombre(): void
    {
        $this->assertSame('demandeur', Accords::accorder('demandeur', 'm'));
        $this->assertSame('demanderesse', Accords::accorder('demandeur', 'f'));
        $this->assertSame('demanderesses', Accords::accorder('demandeur', ['f', 'f']));
        $this->assertSame('demandeurs', Accords::accorder('demandeur', ['m', 'f']));   // le masculin l'emporte
        $this->assertSame('Demanderesse', Accords::accorder('Demandeur', 'f'));
        $this->assertSame('défenderesse', Accords::accorder('défenderesse', 'f'));   // donné au féminin
        $this->assertSame('comparante', Accords::accorder('comparant', 'f'));
        $this->assertSame('comparaissant', Accords::accorder('comparaissant', ['f', 'f']));   // participe présent invariable
        $this->assertSame('ministère public', Accords::accorder('ministère public', ['m', 'm']));
        $this->assertSame('ivoiriennes', Accords::accorder('ivoirien', ['f', 'f']));
        $this->assertSame('vendeuse', Accords::accorder('vendeur', 'f'));
        $this->assertSame('boulangère', Accords::accorder('boulanger', 'f'));
        $this->assertSame('née', Accords::ne('f'));
        $this->assertSame('nés', Accords::ne(['m', 'm']));
        $this->assertSame('nées', Accords::ne(['f', 'f']));
    }

    public function test_civilites(): void
    {
        $this->assertSame('Monsieur', Accords::civilite('m'));
        $this->assertSame('Madame', Accords::civilite('f'));
        $this->assertSame('Mesdames', Accords::civilite(['f', 'f']));
        $this->assertSame('Messieurs', Accords::civilite(['m', 'm']));
        $this->assertSame('Monsieur et Madame', Accords::civilite(['m', 'f']));
        $this->assertSame('Mme', Accords::civilite('f', true));
        $this->assertSame('M. et Mme', Accords::civilite(['m', 'f'], true));
    }

    public function test_genre_inconnu_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Accords::accorder('demandeur', 'x');
    }

    public function test_enumeration(): void
    {
        $this->assertSame('A, B et C', Accords::enumerer(['A', 'B', 'C']));
        $this->assertSame('A et B', Accords::enumerer(['A', 'B']));
        $this->assertSame('A', Accords::enumerer(['A']));
        $this->assertSame('', Accords::enumerer([]));
    }

    public function test_gabarit_remplit_les_variables_et_applique_les_filtres(): void
    {
        $modele = 'Audience publique du {{ audience|date }}. {{ parties|civilite }} {{ noms|liste }}, {{ parties|accord:demandeur }}, '
            .'{{ parties|ne }} à {{ lieu }}, père de {{ enfants|nombre_chiffres:enfant }}, condamné à {{ montant|montant }}, à {{ heure|heure }}.';
        $r = Gabarit::rendre($modele, [
            'audience' => '2025-07-04', 'parties' => ['f', 'f'], 'noms' => ['KONE Awa', 'DIALLO Fanta'], 'lieu' => 'Bingerville', 'enfants' => 3, 'montant' => '1 200 000', 'heure' => '09:30',
        ]);

        $this->assertSame([], $r['manquantes']);
        $this->assertSame('Audience publique du vendredi quatre juillet deux mil vingt-cinq. Mesdames KONE Awa et DIALLO Fanta, demanderesses, nées à Bingerville, '
            .'père de trois (03) enfants, condamné à un million deux cent mille (1 200 000) francs CFA, à neuf heures trente minutes.', $r['texte']);
    }

    public function test_gabarit_signale_les_variables_manquantes_sans_les_inventer(): void
    {
        $r = Gabarit::rendre('RG n° {{ numero }} du {{ date_rg|date_numerique }}, {{ inconnu }}.', ['numero' => 'RG-0001/26']);

        $this->assertSame('RG n° RG-0001/26 du {{ date_rg|date_numerique }}, {{ inconnu }}.', $r['texte']);
        $this->assertSame(['date_rg', 'inconnu'], $r['manquantes']);
    }

    public function test_gabarit_blocs_reutilisables_et_protection_contre_la_boucle(): void
    {
        $blocs = ['cloture' => 'Ainsi fait, jugé et prononcé le {{ date|date_sans_jour }}.', 'boucle' => 'a {{> boucle }}'];

        $r = Gabarit::rendre('Texte. {{> cloture }} {{> absent }}', ['date' => '2026-10-12'], $blocs);
        $this->assertSame('Texte. Ainsi fait, jugé et prononcé le douze octobre deux mil vingt-six. {{> absent }}', $r['texte']);
        $this->assertSame(['bloc absent'], $r['manquantes']);

        $this->assertStringContainsString('a a', Gabarit::rendre('{{> boucle }}', [], $blocs)['texte']);   // s'arrête sans planter

        $this->assertSame(['date'], Gabarit::variables('{{> cloture }}', $blocs));
    }

    public function test_gabarit_n_execute_aucun_code_et_refuse_un_filtre_inconnu(): void
    {
        $r = Gabarit::rendre('{{ x|system }} {{ y }}', ['x' => 'ls', 'y' => '<?php echo 1; ?> {{ x }}']);

        $this->assertStringContainsString('{{ x|system }}', $r['texte']);
        $this->assertContains('filtre system', $r['manquantes']);
        $this->assertStringContainsString('<?php echo 1; ?> {{ x }}', $r['texte']);   // la valeur est copiée telle quelle, jamais réinterprétée
    }

    public function test_valeur_incorrecte_est_signalee(): void
    {
        $r = Gabarit::rendre('{{ h|heure }}', ['h' => 'pas une heure']);
        $this->assertStringContainsString('valeur incorrecte', implode(' ', $r['manquantes']));
    }
}
