<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Le texte des lignes (une page de 100 au plus) que renvoie une liste paginée côté serveur : sert aux tests qui vérifient
     * ce qu'une liste montre ou cache. Le HTML des cellules est conservé (pour y retrouver un titre ou une classe).
     */
    protected function lignesServeur(User $u, string $route, array $params = []): string
    {
        $rep = $this->actingAs($u)->withSession(['derniere_activite' => now()->timestamp])->getJson(route($route, $params + ['length' => 100]))->assertOk();

        return implode(' ', array_map(fn ($l) => implode(' ', $l), $rep->json('data')));
    }
}
