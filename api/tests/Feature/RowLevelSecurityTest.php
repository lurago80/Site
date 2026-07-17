<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Garante que o isolamento multi-tenant é imposto pelo próprio PostgreSQL
 * (Row-Level Security), não apenas por filtros de aplicação — ver
 * Escopo v2, seção 3.6. Se algum dia alguém remover um WHERE empresa_id
 * de uma query, é este teste que ainda protege contra o vazamento.
 */
class RowLevelSecurityTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresaA;

    private Empresa $empresaB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Básico', 'valor_mensal' => 199.90]);

        $this->empresaA = Empresa::create([
            'razao_social' => 'Empresa A',
            'cnpj' => '11.111.111/0001-11',
            'slug' => 'empresa-a',
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        $this->empresaB = Empresa::create([
            'razao_social' => 'Empresa B',
            'cnpj' => '22.222.222/0001-22',
            'slug' => 'empresa-b',
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        User::create([
            'name' => 'Usuário A',
            'email' => 'a@empresa-a.com',
            'password' => bcrypt('senha'),
            'empresa_id' => $this->empresaA->id,
            'perfil' => 'admin',
        ]);

        User::create([
            'name' => 'Usuário B',
            'email' => 'b@empresa-b.com',
            'password' => bcrypt('senha'),
            'empresa_id' => $this->empresaB->id,
            'perfil' => 'admin',
        ]);
    }

    public function test_sem_contexto_de_tenant_nao_retorna_nenhuma_linha(): void
    {
        $this->semContextoDeTenant();

        $this->assertSame(0, User::count());
    }

    public function test_empresa_a_so_enxerga_seus_proprios_usuarios(): void
    {
        $this->asEmpresa($this->empresaA->id);

        $usuarios = User::all();

        $this->assertCount(1, $usuarios);
        $this->assertSame('a@empresa-a.com', $usuarios->first()->email);
    }

    public function test_empresa_b_nao_enxerga_usuarios_da_empresa_a(): void
    {
        $this->asEmpresa($this->empresaB->id);

        $usuarios = User::all();

        $this->assertCount(1, $usuarios);
        $this->assertSame('b@empresa-b.com', $usuarios->first()->email);
    }

    public function test_empresa_a_nao_encontra_usuario_da_empresa_b_por_id(): void
    {
        $usuarioB = User::where('email', 'b@empresa-b.com')->first();

        $this->asEmpresa($this->empresaA->id);

        $this->assertNull(User::find($usuarioB->id));
    }

    public function test_super_admin_enxerga_usuarios_de_todas_as_empresas(): void
    {
        $this->asSuperAdmin();

        $this->assertSame(2, User::count());
    }

    public function test_nao_e_possivel_inserir_registro_com_empresa_id_de_outro_tenant(): void
    {
        $this->asEmpresa($this->empresaA->id);

        // WITH CHECK da policy deve rejeitar a tentativa de gravar um
        // registro que pertence à empresa B enquanto o contexto é da A.
        $this->expectException(\Illuminate\Database\QueryException::class);

        User::create([
            'name' => 'Invasor',
            'email' => 'invasor@empresa-b.com',
            'password' => bcrypt('senha'),
            'empresa_id' => $this->empresaB->id,
            'perfil' => 'admin',
        ]);
    }
}
