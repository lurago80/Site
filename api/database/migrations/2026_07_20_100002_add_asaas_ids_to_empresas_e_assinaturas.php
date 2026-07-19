<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('asaas_customer_id')->nullable()->after('status');
        });

        Schema::table('assinaturas', function (Blueprint $table) {
            $table->string('asaas_subscription_id')->nullable()->after('proxima_cobranca');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('asaas_customer_id');
        });

        Schema::table('assinaturas', function (Blueprint $table) {
            $table->dropColumn('asaas_subscription_id');
        });
    }
};
