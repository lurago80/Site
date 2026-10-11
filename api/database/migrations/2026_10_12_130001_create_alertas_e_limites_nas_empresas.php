<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Limites dos alertas (cortesia e cancelamento alertam sempre).
        Schema::table('empresas', function (Blueprint $table) {
            $table->decimal('alerta_desconto_percentual', 5, 2)->default(30)->after('operacao_inicio');
            $table->decimal('alerta_diferenca_caixa', 10, 2)->default(5)->after('alerta_desconto_percentual');
            $table->decimal('alerta_sangria', 10, 2)->default(200)->after('alerta_diferenca_caixa');
        });

        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo', 30); // cortesia | desconto | cancelamento | diferenca_caixa | sangria
            $table->string('mensagem');
            $table->decimal('valor', 12, 2)->nullable();
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lida_em')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'lida_em', 'created_at']);
        });

        DB::statement('ALTER TABLE alertas ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE alertas FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON alertas
            USING (
                current_setting('app.is_super_admin', true) = 'true'
                OR empresa_id = NULLIF(current_setting('app.current_empresa_id', true), '')::bigint
            )
            WITH CHECK (
                current_setting('app.is_super_admin', true) = 'true'
                OR empresa_id = NULLIF(current_setting('app.current_empresa_id', true), '')::bigint
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['alerta_desconto_percentual', 'alerta_diferenca_caixa', 'alerta_sangria']);
        });
    }
};
