<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Admin Joaninha',
            'email' => 'admin@admin.com',
            'password' => bcrypt('P@ssw0rd'),
            'created_at' => '2026-05-08 10:00:00',
            'updated_at' => '2026-05-08 10:00:00',
        ]);

        // Diretora da escola: senha temporária, a ser trocada pela própria Sônia na página de Perfil.
        User::factory()->create([
            'name' => 'Sônia',
            'email' => 'sonia@escolajoaninha.com.br',
            'password' => bcrypt('Joaninha@2026'),
            'created_at' => '2026-05-08 10:05:00',
            'updated_at' => '2026-05-08 10:05:00',
        ]);

        $this->call(JoaninhaSeeder::class);
    }
}
