<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder roda como super admin (bypassa RLS) para popular dados iniciais.
        DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");

        $plano = Plano::create([
            'nome' => 'Básico',
            'valor_mensal' => 199.90,
            'limites' => ['usuarios' => 5],
        ]);

        $cervejaria = Empresa::create([
            'razao_social' => 'Cervejaria Teste LTDA',
            'cnpj' => '11.111.111/0001-11',
            'slug' => 'cervejaria-teste',
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        $vinicola = Empresa::create([
            'razao_social' => 'Vinícola Teste LTDA',
            'cnpj' => '22.222.222/0001-22',
            'slug' => 'vinicola-teste',
            'plano_id' => $plano->id,
            'status' => 'ativa',
        ]);

        User::create([
            'name' => 'Admin Cervejaria',
            'email' => 'admin@cervejaria-teste.com.br',
            'password' => bcrypt('senha-teste'),
            'empresa_id' => $cervejaria->id,
            'perfil' => 'admin',
        ]);

        User::create([
            'name' => 'Admin Vinícola',
            'email' => 'admin@vinicola-teste.com.br',
            'password' => bcrypt('senha-teste'),
            'empresa_id' => $vinicola->id,
            'perfil' => 'admin',
        ]);
    }
}
