<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Administrador', 'email' => 'admin@uls.edu.sv', 'password' => env('ADMIN_PASSWORD', 'Admin123!'), 'role' => 'administrador'],
            ['name' => 'Gestor del repositorio', 'email' => 'gestor@uls.edu.sv', 'password' => env('GESTOR_PASSWORD', 'Gestor123!'), 'role' => 'gestor'],
            ['name' => 'Revisor', 'email' => 'revisor@uls.edu.sv', 'password' => env('REVISOR_PASSWORD', 'Revisor123!'), 'role' => 'revisor'],
            ['name' => 'Docente depositante', 'email' => 'docente@uls.edu.sv', 'password' => env('DOCENTE_PASSWORD', 'Docente123!'), 'role' => 'depositante'],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'is_active' => true,
                ],
            );
            $roleId = Role::query()->where('slug', $account['role'])->value('id');
            $user->roles()->sync([$roleId]);
        }
    }
}
