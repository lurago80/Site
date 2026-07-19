<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * "Esqueci minha senha" - usa o broker de senha nativo do Laravel
 * (password_reset_tokens), que já existia desde a migration inicial
 * mas nunca tinha rotas/controller ligados a ele.
 */
class PasswordResetTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Básico', 'valor_mensal' => 199.90]);

        $empresa = Empresa::create([
            'razao_social' => 'Empresa Reset Teste', 'cnpj' => '44.444.444/0001-44',
            'slug' => 'reset-teste', 'plano_id' => $plano->id, 'status' => 'ativa',
        ]);

        $this->usuario = User::create([
            'name' => 'Usuário Teste', 'email' => 'usuario@reset-teste.com',
            'password' => bcrypt('senha-antiga'), 'empresa_id' => $empresa->id, 'perfil' => 'admin',
        ]);
    }

    public function test_solicitar_link_para_email_existente_envia_notificacao(): void
    {
        Notification::fake();

        $response = $this->post('/esqueci-senha', ['email' => $this->usuario->email]);

        $response->assertRedirect();
        Notification::assertSentTo($this->usuario, ResetPassword::class);
    }

    public function test_solicitar_link_para_email_inexistente_nao_revela_se_existe(): void
    {
        Notification::fake();

        $response = $this->post('/esqueci-senha', ['email' => 'nao-existe@nada.com']);

        // Mesma resposta (redirect com status genérico) para não revelar
        // se o e-mail está cadastrado ou não - evita enumeração de usuários.
        $response->assertRedirect();
        Notification::assertNothingSent();
    }

    public function test_redefinir_senha_com_token_valido_atualiza_a_senha(): void
    {
        Notification::fake();
        $this->post('/esqueci-senha', ['email' => $this->usuario->email]);

        $this->asSuperAdmin();
        $tokenRow = DB::table('password_reset_tokens')->where('email', $this->usuario->email)->first();
        $this->assertNotNull($tokenRow);

        // O token bruto só existe na notificação (o banco guarda o hash) -
        // capturamos ele via Notification::fake() antes de simular o clique
        // no link do e-mail.
        Notification::assertSentTo($this->usuario, ResetPassword::class, function ($notification) {
            $response = $this->post('/redefinir-senha', [
                'token' => $notification->token,
                'email' => $this->usuario->email,
                'password' => 'senha-nova-123',
                'password_confirmation' => 'senha-nova-123',
            ]);

            $response->assertRedirect('/login');

            return true;
        });

        $this->asSuperAdmin();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('senha-nova-123', $this->usuario->fresh()->password));
    }

    public function test_redefinir_senha_com_token_invalido_falha(): void
    {
        $response = $this->post('/redefinir-senha', [
            'token' => 'token-invalido',
            'email' => $this->usuario->email,
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('senha-antiga', $this->usuario->fresh()->password));
    }

    public function test_login_funciona_com_a_senha_redefinida(): void
    {
        Notification::fake();
        $this->post('/esqueci-senha', ['email' => $this->usuario->email]);

        Notification::assertSentTo($this->usuario, ResetPassword::class, function ($notification) {
            $this->post('/redefinir-senha', [
                'token' => $notification->token,
                'email' => $this->usuario->email,
                'password' => 'senha-nova-123',
                'password_confirmation' => 'senha-nova-123',
            ]);

            return true;
        });

        $response = $this->post('/login', ['email' => $this->usuario->email, 'password' => 'senha-nova-123']);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->usuario->fresh());
    }
}
