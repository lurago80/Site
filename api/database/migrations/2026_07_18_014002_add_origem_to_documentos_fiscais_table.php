<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            // Preenchido quando uma NFe (modelo 55) é emitida para
            // "regularizar"/formalizar uma venda já documentada por uma
            // NFC-e (modelo 65) - CFOP 5929/6929, ver Fiscal\CfopResolver.
            $table->foreignId('documento_fiscal_origem_id')
                ->nullable()
                ->after('venda_id')
                ->constrained('documentos_fiscais');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('documento_fiscal_origem_id');
        });
    }
};
