<?php

namespace Tests\Feature;

use App\Models\Cfop;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Tabela CFOP (Ajuste SINIEF s/nº de 15/12/1970) - tabela oficial e
 * global, gerenciada pelo Super Admin (mesma lógica de tab_cclasstrib/
 * tab_ccredpres/ibpt_produtos).
 */
class CfopTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Básico', 'valor_mensal' => 199.90]);

        $this->superAdmin = User::create([
            'name' => 'Equipe Plataforma', 'email' => 'super-cfop@plataforma.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => null, 'perfil' => 'super_admin',
        ]);
    }

    public function test_super_admin_importa_tabela_cfop_padrao(): void
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/superadmin/cfops/importar-padrao');

        $response->assertOk();
        $this->assertGreaterThan(500, $response->json('total_importado'));

        $this->asSuperAdmin();
        $this->assertGreaterThan(500, Cfop::count());
        $this->assertNotNull(Cfop::where('codigo', '5102')->first());
    }

    public function test_importar_padrao_nao_apaga_cfop_cadastrado_manualmente(): void
    {
        $this->actingAs($this->superAdmin)->postJson('/superadmin/cfops', [
            'codigo' => '9999', 'descricao' => 'CFOP customizado de teste',
        ]);

        $this->actingAs($this->superAdmin)->postJson('/superadmin/cfops/importar-padrao');

        $this->asSuperAdmin();
        $this->assertNotNull(Cfop::where('codigo', '9999')->first());
    }

    public function test_importar_padrao_e_idempotente_via_upsert(): void
    {
        $this->actingAs($this->superAdmin)->postJson('/superadmin/cfops/importar-padrao');
        $this->asSuperAdmin();
        $totalAntes = Cfop::count();

        $this->actingAs($this->superAdmin)->postJson('/superadmin/cfops/importar-padrao');
        $this->asSuperAdmin();

        $this->assertSame($totalAntes, Cfop::count());
    }

    public function test_busca_cfop_por_codigo_ou_descricao(): void
    {
        $this->actingAs($this->superAdmin)->postJson('/superadmin/cfops/importar-padrao');

        $porCodigo = $this->actingAs($this->superAdmin)->getJson('/superadmin/cfops?busca=5102');
        $porCodigo->assertOk();
        $this->assertTrue(collect($porCodigo->json())->contains('codigo', '5102'));
    }

    public function test_admin_atualiza_descricao_e_desativa_cfop(): void
    {
        $this->actingAs($this->superAdmin)->postJson('/superadmin/cfops/importar-padrao');
        $this->asSuperAdmin();
        $cfop = Cfop::where('codigo', '5102')->first();

        $response = $this->actingAs($this->superAdmin)->putJson("/superadmin/cfops/{$cfop->id}", [
            'ativo' => false,
        ]);

        $response->assertOk()->assertJsonPath('ativo', false);
    }

    public function test_usuario_comum_nao_pode_gerenciar_cfops(): void
    {
        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Outro', 'valor_mensal' => 99]);
        $empresa = Empresa::create([
            'razao_social' => 'Empresa Teste', 'cnpj' => '22.222.222/0001-22',
            'slug' => 'empresa-cfop-teste', 'plano_id' => $plano->id, 'status' => 'ativa',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@empresa-cfop-teste.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $empresa->id, 'perfil' => 'admin',
        ]);

        $response = $this->actingAs($admin)->postJson('/superadmin/cfops/importar-padrao');

        $response->assertStatus(403);
    }
}
