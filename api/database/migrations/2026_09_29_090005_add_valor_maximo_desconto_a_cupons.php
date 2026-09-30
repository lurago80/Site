<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cupons', function (Blueprint $table) {
            // Teto do desconto em reais quando o carrinho tem 2+ tickets de
            // visitação; com exatamente 1 ticket, o teto aplicado é a metade
            // deste valor (regra de negócio fixa, ver Cupom::calcularDescontoVisita).
            // null = sem teto (usa só o percentual/valor fixo do cupom).
            $table->decimal('valor_maximo_desconto', 10, 2)->nullable()->after('valor');
        });
    }

    public function down(): void
    {
        Schema::table('cupons', function (Blueprint $table) {
            $table->dropColumn('valor_maximo_desconto');
        });
    }
};
