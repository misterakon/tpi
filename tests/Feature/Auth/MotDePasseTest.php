<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class MotDePasseTest extends TestCase
{
    use RefreshDatabase;

    private const ANCIEN = 'Ancien#Passe2026!';

    private const NOUVEAU = 'Nouveau#Passe2027!';

    private function utilisateur(array $attributs = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'juge@tribunal.test',
            'password' => self::ANCIEN,
        ], $attributs));
    }

    public function test_la_reponse_est_la_meme_que_le_compte_existe_ou_non(): void
    {
        Notification::fake();
        $this->utilisateur();

        $existe = $this->postJson('/forgot-password', ['email' => 'juge@tribunal.test']);
        $inconnu = $this->postJson('/forgot-password', ['email' => 'inconnu@tribunal.test']);

        $this->assertSame($existe->json(), $inconnu->json());
        Notification::assertSentTimes(ResetPassword::class, 1);
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'mdp_lien_demande', 'email_saisi' => 'inconnu@tribunal.test']);
    }

    public function test_un_compte_actif_recoit_le_lien(): void
    {
        Notification::fake();
        $user = $this->utilisateur();

        $this->postJson('/forgot-password', ['email' => 'juge@tribunal.test'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_un_compte_inactif_ne_recoit_pas_de_lien(): void
    {
        Notification::fake();
        $user = $this->utilisateur(['actif' => false]);

        $this->postJson('/forgot-password', ['email' => 'juge@tribunal.test'])->assertOk();

        Notification::assertNotSentTo($user, ResetPassword::class);
    }

    public function test_le_lien_permet_de_choisir_un_mot_de_passe_et_ferme_les_sessions(): void
    {
        $user = $this->utilisateur();
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $token = Password::broker()->createToken($user);

        $this->postJson('/reset-password', [
            'token' => $token, 'email' => 'juge@tribunal.test',
            'password' => self::NOUVEAU, 'password_confirmation' => self::NOUVEAU,
        ])->assertOk()->assertJson(['redirect' => route('login')]);

        $this->assertTrue(Hash::check(self::NOUVEAU, $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'abc']);
        $this->assertDatabaseHas('journal_connexions', ['evenement' => 'mdp_modifie', 'user_id' => $user->id]);
        $this->assertDatabaseHas('journal_audit', ['action' => 'mot_de_passe_reinitialise']);
    }

    public function test_un_lien_ne_sert_qu_une_fois(): void
    {
        $user = $this->utilisateur();
        $token = Password::broker()->createToken($user);
        $donnees = ['token' => $token, 'email' => 'juge@tribunal.test', 'password' => self::NOUVEAU, 'password_confirmation' => self::NOUVEAU];

        $this->postJson('/reset-password', $donnees)->assertOk();
        $this->postJson('/reset-password', $donnees)->assertStatus(422);
    }

    public function test_un_mot_de_passe_faible_est_refuse(): void
    {
        $user = $this->utilisateur();
        $token = Password::broker()->createToken($user);

        foreach (['Court1a', 'abc', '1234567'] as $faible) {
            $this->postJson('/reset-password', [
                'token' => $token, 'email' => 'juge@tribunal.test',
                'password' => $faible, 'password_confirmation' => $faible,
            ])->assertStatus(422)->assertJsonValidationErrors('password');
        }
        $this->assertTrue(Hash::check(self::ANCIEN, $user->fresh()->password));
    }

    public function test_changement_du_mot_de_passe_depuis_mon_compte(): void
    {
        $user = $this->utilisateur();

        $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp])
            ->putJson('/mon-compte/mot-de-passe', [
                'current_password' => self::ANCIEN,
                'password' => self::NOUVEAU, 'password_confirmation' => self::NOUVEAU,
            ])->assertOk();

        $this->assertTrue(Hash::check(self::NOUVEAU, $user->fresh()->password));
        $this->assertDatabaseHas('journal_audit', ['action' => 'mot_de_passe_modifie', 'user_id' => $user->id]);
    }

    public function test_changement_refuse_si_le_mot_de_passe_actuel_est_faux(): void
    {
        $user = $this->utilisateur();

        $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp])
            ->putJson('/mon-compte/mot-de-passe', [
                'current_password' => 'faux',
                'password' => self::NOUVEAU, 'password_confirmation' => self::NOUVEAU,
            ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check(self::ANCIEN, $user->fresh()->password));
    }

    public function test_le_journal_n_enregistre_jamais_les_mots_de_passe(): void
    {
        $user = $this->utilisateur();

        $this->actingAs($user)->withSession(['derniere_activite' => now()->timestamp])
            ->putJson('/mon-compte/mot-de-passe', [
                'current_password' => self::ANCIEN,
                'password' => self::NOUVEAU, 'password_confirmation' => self::NOUVEAU,
            ]);

        $contenu = json_encode(DB::table('journal_audit')->get()).json_encode(DB::table('journal_connexions')->get());
        $this->assertStringNotContainsString(self::ANCIEN, $contenu);
        $this->assertStringNotContainsString(self::NOUVEAU, $contenu);
    }
}
