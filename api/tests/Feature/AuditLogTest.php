<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenantContext;
use Tests\TestCase;

/**
 * Confirma que criar/alterar/excluir um model sensível (Cliente, User, ...)
 * gera automaticamente uma linha em `logs` - ver Escopo v2, seção 4, e
 * App\Providers\AppServiceProvider::MODELS_AUDITADOS.
 */
class AuditLogTest extends TestCase
{
    use InteractsWithTenantContext, RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asSuperAdmin();
        $plano = Plano::create(['nome' => 'Básico', 'valor_mensal' => 199.90]);

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa Teste',
            'cnpj' => '11.111.111/0001-11',
            'slug' => 'empresa-teste',
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        $this->asEmpresa($this->empresa->id);
    }

    public function test_criar_cliente_gera_log_de_auditoria(): void
    {
        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Cliente Teste',
            'email' => 'cliente@teste.com',
            'consentimento_lgpd' => true,
        ]);

        $log = DB::table('logs')
            ->where('tabela_afetada', 'clientes')
            ->where('registro_id', $cliente->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('create', $log->acao);
        $this->assertSame($this->empresa->id, $log->empresa_id);
    }

    public function test_atualizar_cliente_gera_log_com_dados_anteriores_e_novos(): void
    {
        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Nome Original',
            'consentimento_lgpd' => true,
        ]);

        $cliente->update(['nome' => 'Nome Atualizado']);

        $log = DB::table('logs')
            ->where('tabela_afetada', 'clientes')
            ->where('acao', 'update')
            ->where('registro_id', $cliente->id)
            ->first();

        $this->assertNotNull($log);
        $novos = json_decode($log->dados_novos, true);
        $this->assertSame('Nome Atualizado', $novos['nome']);
    }

    public function test_excluir_cliente_gera_log(): void
    {
        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Cliente a excluir',
            'consentimento_lgpd' => true,
        ]);
        $clienteId = $cliente->id;

        $cliente->delete();

        $log = DB::table('logs')
            ->where('tabela_afetada', 'clientes')
            ->where('acao', 'delete')
            ->where('registro_id', $clienteId)
            ->first();

        $this->assertNotNull($log);
    }

    public function test_criar_usuario_redige_a_senha_no_log(): void
    {
        $usuario = User::create([
            'name' => 'Usuário Teste',
            'email' => 'usuario@teste.com',
            'password' => bcrypt('senha-secreta'),
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
        ]);

        $log = DB::table('logs')
            ->where('tabela_afetada', 'users')
            ->where('registro_id', $usuario->id)
            ->first();

        $novos = json_decode($log->dados_novos, true);
        $this->assertSame('[redigido]', $novos['password']);
        $this->assertStringNotContainsString('senha-secreta', $log->dados_novos);
    }

    public function test_produto_nao_esta_na_lista_de_models_auditados_e_nao_gera_log(): void
    {
        \App\Models\Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Produto sem auditoria',
            'tipo' => 'fisico',
            'preco_venda' => 10,
        ]);

        $this->assertSame(0, DB::table('logs')->where('tabela_afetada', 'produtos')->count());
    }
}
