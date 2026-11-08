<?php

namespace Tests\Feature;

use Tests\TestCase;

/** La police de l'application est installée sur le serveur : aucune dépendance à Internet. */
class PoliceTest extends TestCase
{
    public function test_les_fichiers_de_la_police_sont_presents_et_valides(): void
    {
        foreach (['latin', 'latin-ext'] as $jeu) {
            foreach ([400, 500, 600, 700] as $graisse) {
                $fichier = public_path("assets/fonts/inter/inter-$jeu-$graisse-normal.woff2");
                $this->assertFileExists($fichier);
                $this->assertSame('wOF2', file_get_contents($fichier, false, null, 0, 4), "$jeu $graisse n'est pas un fichier woff2");
            }
        }
        $this->assertFileExists(public_path('assets/fonts/inter/LICENSE.txt'));
    }

    public function test_police_css_declare_les_quatre_graisses_et_la_variable_centrale(): void
    {
        $css = file_get_contents(public_path('assets/css/police.css'));

        foreach ([400, 500, 600, 700] as $graisse) {
            $this->assertStringContainsString("font-weight: $graisse;", $css);
        }
        $this->assertStringContainsString("--police-app: 'Inter'", $css);
        $this->assertStringContainsString('--font-rubik: var(--police-app)', $css);
    }

    public function test_les_pages_chargent_la_police_sans_appeler_google(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('assets/css/police.css')
            ->assertSee('inter-latin-400-normal.woff2')
            ->assertDontSee('fonts.googleapis.com')
            ->assertDontSee('fonts.gstatic.com');
    }

    public function test_le_theme_n_impose_plus_de_police_en_dur(): void
    {
        $theme = file_get_contents(public_path('assets/css/elouange-theme.css'));

        $this->assertStringNotContainsString("'Trebuchet MS'", $theme);
        $this->assertStringNotContainsString("'Rubik'", $theme);
        $this->assertStringContainsString('var(--police-app)', $theme);
    }

    public function test_les_polices_sont_servies_par_le_site_lui_meme_dans_la_politique_de_securite(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("font-src 'self'", $csp);
    }
}
