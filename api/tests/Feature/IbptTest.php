<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Tabela do IBPT (Lei da Transparência Fiscal, 12.741/2012) - tabela
 * oficial e global, importada em bloco a partir do .csv que o IBPT
 * distribui. Gerenciada só pelo Super Admin (Escopo v2, decisão de
 * 2026-07-26).
 */
class IbptTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Básico', 'valor_mensal' => 199.90]);

        $this->superAdmin = User::create([
            'name' => 'Equipe Plataforma', 'email' => 'super-ibpt@plataforma.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => null, 'perfil' => 'super_admin',
        ]);
    }

    private function arquivoCsvDeTeste(): UploadedFile
    {
        $conteudo = "codigo;ex;tipo;descricao;nacionalfederal;importadosfederal;estadual;municipal;vigenciainicio;vigenciafim;chave;versao;fonte\r\n"
            ."22030000;;0;\"Cervejas de malte\";19.53;27.75;22.00;0.00;20/06/2026;31/07/2026;42CA5A;26.1.L;IBPT/empresometro.com.br\r\n"
            ."101011000;;1;\"Servi\xe7os de constru\xe7\xe3o\";13.45;15.45;0.00;4.33;20/06/2026;31/07/2026;42CA5A;26.1.L;IBPT/empresometro.com.br\r\n";

        $caminho = sys_get_temp_dir().'/ibpt_teste_'.uniqid().'.csv';
        File::put($caminho, $conteudo);

        return new UploadedFile($caminho, 'ibpt.csv', 'text/csv', null, true);
    }

    public function test_super_admin_importa_tabela_ibpt(): void
    {
        $response = $this->actingAs($this->superAdmin)->post('/superadmin/ibpt/importar', [
            'arquivo' => $this->arquivoCsvDeTeste(),
        ]);

        $response->assertOk()->assertJsonPath('total_importado', 2);

        $this->asSuperAdmin();
        $this->assertSame(2, \App\Models\IbptProduto::count());

        $cerveja = \App\Models\IbptProduto::where('codigo', '22030000')->first();
        $this->assertSame('Cervejas de malte', $cerveja->descricao);
        $this->assertEquals(22.00, $cerveja->aliquota_estadual);

        // Confere a conversão de encoding Latin-1 -> UTF-8 (acentos).
        $servico = \App\Models\IbptProduto::where('codigo', '101011000')->first();
        $this->assertSame('Serviços de construção', $servico->descricao);
    }

    public function test_importar_substitui_a_tabela_inteira(): void
    {
        $this->actingAs($this->superAdmin)->post('/superadmin/ibpt/importar', ['arquivo' => $this->arquivoCsvDeTeste()]);
        $this->actingAs($this->superAdmin)->post('/superadmin/ibpt/importar', ['arquivo' => $this->arquivoCsvDeTeste()]);

        $this->asSuperAdmin();
        $this->assertSame(2, \App\Models\IbptProduto::count());
    }

    public function test_usuario_comum_nao_pode_importar_ibpt(): void
    {
        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Outro', 'valor_mensal' => 99]);
        $empresa = Empresa::create([
            'razao_social' => 'Empresa Teste', 'cnpj' => '11.111.111/0001-11',
            'slug' => 'empresa-ibpt-teste', 'plano_id' => $plano->id, 'status' => 'ativa',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@empresa-ibpt-teste.com',
            'password' => bcrypt('senha-teste'), 'empresa_id' => $empresa->id, 'perfil' => 'admin',
        ]);

        $response = $this->actingAs($admin)->post('/superadmin/ibpt/importar', ['arquivo' => $this->arquivoCsvDeTeste()]);

        $response->assertStatus(403);
    }

    public function test_status_reflete_total_e_versao_importados(): void
    {
        $this->actingAs($this->superAdmin)->post('/superadmin/ibpt/importar', ['arquivo' => $this->arquivoCsvDeTeste()]);

        $response = $this->actingAs($this->superAdmin)->getJson('/superadmin/ibpt/status');

        $response->assertOk()->assertJsonPath('total', 2)->assertJsonPath('versao', '26.1.L');
    }

    public function test_buscar_por_ncm_retorna_apenas_codigos_compativeis(): void
    {
        $this->actingAs($this->superAdmin)->post('/superadmin/ibpt/importar', ['arquivo' => $this->arquivoCsvDeTeste()]);

        $response = $this->actingAs($this->superAdmin)->getJson('/superadmin/ibpt/buscar?ncm=2203');

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.codigo', '22030000');
    }
}
