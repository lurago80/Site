<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Painel Super Admin: gestão de empresas, planos e assinaturas
 * (Escopo v2, seção 2.2) - uso interno da equipe da plataforma.
 */
class SuperAdminTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private User $superAdmin;

    private Plano $plano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $this->plano = Plano::create(['nome' => 'Básico', 'valor_mensal' => 199.90]);

        $this->superAdmin = User::create([
            'name' => 'Equipe Plataforma',
            'email' => 'super@plataforma.com',
            'password' => bcrypt('senha-teste'),
            'empresa_id' => null,
            'perfil' => 'super_admin',
        ]);
    }

    public function test_login_de_super_admin_redireciona_ao_painel_super_admin(): void
    {
        $response = $this->post('/login', [
            'email' => 'super@plataforma.com',
            'password' => 'senha-teste',
        ]);

        $response->assertRedirect('/superadmin/painel');
    }

    public function test_usuario_comum_nao_acessa_o_painel_super_admin(): void
    {
        $empresa = Empresa::create([
            'razao_social' => 'Empresa Comum',
            'cnpj' => '11.111.111/0001-11',
            'slug' => 'empresa-comum',
            'plano_id' => $this->plano->id,
            'status' => 'ativa',
        ]);

        $usuarioComum = User::create([
            'name' => 'Admin Comum',
            'email' => 'admin@empresa-comum.com',
            'password' => bcrypt('senha-teste'),
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
        ]);

        $response = $this->actingAs($usuarioComum)->get('/superadmin/painel');

        $response->assertStatus(403);
    }

    public function test_visitante_nao_autenticado_e_redirecionado_ao_login(): void
    {
        $response = $this->get('/superadmin/painel');

        $response->assertRedirect('/login');
    }

    public function test_super_admin_lista_todas_as_empresas_de_todos_os_tenants(): void
    {
        Empresa::create([
            'razao_social' => 'Empresa A', 'cnpj' => '11.111.111/0001-11',
            'slug' => 'empresa-a', 'plano_id' => $this->plano->id, 'status' => 'ativa',
        ]);
        Empresa::create([
            'razao_social' => 'Empresa B', 'cnpj' => '22.222.222/0001-22',
            'slug' => 'empresa-b', 'plano_id' => $this->plano->id, 'status' => 'ativa',
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/superadmin/empresas');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_super_admin_cadastra_nova_empresa(): void
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/superadmin/empresas', [
            'razao_social' => 'Nova Empresa LTDA',
            'cnpj' => '33.333.333/0001-33',
            'slug' => 'nova-empresa',
            'plano_id' => $this->plano->id,
        ]);

        $response->assertCreated()->assertJsonPath('status', 'ativa');
    }

    public function test_nao_cadastra_empresa_com_slug_duplicado(): void
    {
        Empresa::create([
            'razao_social' => 'Empresa Existente', 'cnpj' => '11.111.111/0001-11',
            'slug' => 'ja-existe', 'plano_id' => $this->plano->id, 'status' => 'ativa',
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson('/superadmin/empresas', [
            'razao_social' => 'Outra Empresa',
            'cnpj' => '44.444.444/0001-44',
            'slug' => 'ja-existe',
            'plano_id' => $this->plano->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_super_admin_suspende_empresa(): void
    {
        $empresa = Empresa::create([
            'razao_social' => 'Empresa Suspensa', 'cnpj' => '11.111.111/0001-11',
            'slug' => 'empresa-suspensa', 'plano_id' => $this->plano->id, 'status' => 'ativa',
        ]);

        $response = $this->actingAs($this->superAdmin)->putJson("/superadmin/empresas/{$empresa->id}", [
            'status' => 'suspensa',
        ]);

        $response->assertOk()->assertJsonPath('status', 'suspensa');
    }

    public function test_super_admin_cadastra_plano(): void
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/superadmin/planos', [
            'nome' => 'Completo',
            'valor_mensal' => 299.90,
        ]);

        $response->assertCreated()->assertJsonPath('nome', 'Completo');
    }

    public function test_super_admin_edita_valor_de_um_plano(): void
    {
        $planoNovo = Plano::create(['nome' => 'Editável', 'valor_mensal' => 100]);

        $response = $this->actingAs($this->superAdmin)->putJson("/superadmin/planos/{$planoNovo->id}", [
            'valor_mensal' => 149.90,
        ]);

        $response->assertOk()->assertJsonPath('valor_mensal', '149.90');
    }

    public function test_super_admin_reassocia_empresa_a_outro_plano(): void
    {
        $empresa = Empresa::create([
            'razao_social' => 'Empresa Reassociar', 'cnpj' => '77.777.777/0001-77',
            'slug' => 'empresa-reassociar', 'plano_id' => $this->plano->id, 'status' => 'ativa',
        ]);
        $novoPlano = Plano::create(['nome' => 'Plano Novo', 'valor_mensal' => 399]);

        $response = $this->actingAs($this->superAdmin)->putJson("/superadmin/empresas/{$empresa->id}", [
            'plano_id' => $novoPlano->id,
        ]);

        $response->assertOk()->assertJsonPath('plano_id', $novoPlano->id);
    }

    public function test_super_admin_registra_assinatura(): void
    {
        $empresa = Empresa::create([
            'razao_social' => 'Empresa Assinante', 'cnpj' => '11.111.111/0001-11',
            'slug' => 'empresa-assinante', 'plano_id' => $this->plano->id, 'status' => 'ativa',
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson('/superadmin/assinaturas', [
            'empresa_id' => $empresa->id,
            'plano_id' => $this->plano->id,
            'status_pagamento' => 'em_dia',
            'inicio' => now()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('status_pagamento', 'em_dia');
    }

    public function test_suspender_empresa_por_um_super_admin_impede_login_dos_usuarios_dela(): void
    {
        $empresa = Empresa::create([
            'razao_social' => 'Empresa Alvo', 'cnpj' => '11.111.111/0001-11',
            'slug' => 'empresa-alvo', 'plano_id' => $this->plano->id, 'status' => 'ativa',
        ]);

        $usuario = User::create([
            'name' => 'Usuário Alvo',
            'email' => 'usuario@empresa-alvo.com',
            'password' => bcrypt('senha-teste'),
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
        ]);

        $this->actingAs($this->superAdmin)->putJson("/superadmin/empresas/{$empresa->id}", [
            'status' => 'suspensa',
        ]);

        $response = $this->post('/login', [
            'email' => 'usuario@empresa-alvo.com',
            'password' => 'senha-teste',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sessao_ja_aberta_e_bloqueada_com_403_se_empresa_for_suspensa_depois(): void
    {
        $empresa = Empresa::create([
            'razao_social' => 'Empresa Sessão Ativa', 'cnpj' => '22.222.222/0001-22',
            'slug' => 'empresa-sessao-ativa', 'plano_id' => $this->plano->id, 'status' => 'ativa',
        ]);

        $usuario = User::create([
            'name' => 'Usuário Sessão', 'email' => 'usuario@empresa-sessao-ativa.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $empresa->id, 'perfil' => 'admin',
        ]);

        // Empresa fica ativa até aqui - login funciona normalmente.
        $login = $this->post('/login', ['email' => $usuario->email, 'password' => 'senha-teste']);
        $login->assertRedirect();

        // Super admin suspende a empresa DEPOIS do login já feito.
        $this->actingAs($this->superAdmin)->putJson("/superadmin/empresas/{$empresa->id}", ['status' => 'suspensa']);

        // A sessão do usuário continua "logada", mas a página do painel
        // deve bloquear com 403 em vez de deixá-lo continuar navegando.
        $response = $this->actingAs($usuario)->get("/dashboard/{$empresa->slug}/painel");

        $response->assertStatus(403);
    }

    public function test_usuario_inativo_com_sessao_aberta_e_bloqueado(): void
    {
        $empresa = Empresa::create([
            'razao_social' => 'Empresa Usuário Inativo', 'cnpj' => '33.333.333/0001-33',
            'slug' => 'empresa-usuario-inativo', 'plano_id' => $this->plano->id, 'status' => 'ativa',
        ]);

        $usuario = User::create([
            'name' => 'Usuário Que Vai Ser Desativado', 'email' => 'inativo@empresa-usuario-inativo.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $empresa->id, 'perfil' => 'admin', 'ativo' => false,
        ]);

        $response = $this->actingAs($usuario)->get("/dashboard/{$empresa->slug}/painel");

        $response->assertStatus(403);
    }
}
