<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Login único da plataforma (Escopo v2, seção 2.2).
 */
class LoginTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Básico', 'valor_mensal' => 199.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa Login Teste',
            'cnpj' => '11.111.111/0001-11',
            'slug' => 'login-teste',
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);
    }

    public function test_login_com_credenciais_validas_redireciona_ao_painel_da_propria_empresa(): void
    {
        User::create([
            'name' => 'Usuário Teste',
            'email' => 'usuario@login-teste.com',
            'password' => bcrypt('senha-correta'),
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'usuario@login-teste.com',
            'password' => 'senha-correta',
        ]);

        $response->assertRedirect('/fiscal/login-teste/painel');
        $this->assertAuthenticated();
    }

    public function test_login_com_senha_errada_falha(): void
    {
        User::create([
            'name' => 'Usuário Teste',
            'email' => 'usuario@login-teste.com',
            'password' => bcrypt('senha-correta'),
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
        ]);

        $response = $this->post('/login', [
            'email' => 'usuario@login-teste.com',
            'password' => 'senha-errada',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_usuario_inativo_nao_consegue_logar(): void
    {
        User::create([
            'name' => 'Usuário Inativo',
            'email' => 'inativo@login-teste.com',
            'password' => bcrypt('senha-correta'),
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'inativo@login-teste.com',
            'password' => 'senha-correta',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_usuario_de_empresa_suspensa_nao_consegue_logar(): void
    {
        $this->asSuperAdmin();
        $this->empresa->update(['status' => 'suspensa']);

        User::create([
            'name' => 'Usuário Teste',
            'email' => 'suspenso@login-teste.com',
            'password' => bcrypt('senha-correta'),
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
        ]);

        $response = $this->post('/login', [
            'email' => 'suspenso@login-teste.com',
            'password' => 'senha-correta',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_visitante_nao_autenticado_e_redirecionado_ao_login(): void
    {
        $response = $this->get("/fiscal/{$this->empresa->slug}/painel");

        $response->assertRedirect('/login');
    }

    public function test_usuario_autenticado_ve_sempre_os_dados_da_propria_empresa_mesmo_trocando_slug_na_url(): void
    {
        $this->asSuperAdmin();

        $outraEmpresa = Empresa::create([
            'razao_social' => 'Outra Empresa',
            'cnpj' => '22.222.222/0001-22',
            'slug' => 'outra-empresa',
            'plano_id' => $this->empresa->plano_id,
            'status' => 'ativa',
        ]);

        $usuario = User::create([
            'name' => 'Usuário da Empresa A',
            'email' => 'a@login-teste.com',
            'password' => bcrypt('senha-correta'),
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
        ]);

        $this->asEmpresa($this->empresa->id);
        \App\Models\ConfigFiscal::create([
            'empresa_id' => $this->empresa->id,
            'crt' => '1',
            'serie_nfce_atual' => '1',
            'numero_nfce_atual' => 0,
            'ambiente_ativo' => 'homologacao',
        ]);
        $cliente = \App\Models\Cliente::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Cliente A',
            'consentimento_lgpd' => true,
        ]);
        $venda = \App\Models\Venda::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'canal' => 'pdv',
            'tipo_doc' => 'fiscal',
            'status_pagamento' => 'pago',
            'valor_total' => 10,
            'data_venda' => now(),
        ]);
        $venda->itens()->create([
            'empresa_id' => $this->empresa->id,
            'quantidade' => 1,
            'valor_unitario' => 10,
            'valor_total' => 10,
        ]);
        (new \App\Services\Fiscal\EmissaoFiscalService(new \App\Services\Fiscal\SimuladoFiscalGateway()))
            ->emitir($venda, 65);

        // Autenticado como usuário da empresa A, mas navegando para a URL
        // (cosmética) da empresa B - o relatório deve continuar mostrando
        // os dados da empresa A, porque o contexto vem do usuário logado.
        $response = $this->actingAs($usuario)->getJson('/fiscal/outra-empresa/relatorio');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_logout_encerra_sessao(): void
    {
        $usuario = User::create([
            'name' => 'Usuário Teste',
            'email' => 'usuario@login-teste.com',
            'password' => bcrypt('senha-correta'),
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
        ]);

        $this->actingAs($usuario);
        $this->assertAuthenticated();

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
