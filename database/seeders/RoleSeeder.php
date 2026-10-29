<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['slug' => 'administrador', 'name' => 'Administrador', 'description' => 'Administra cuentas, roles y catálogos.'],
            ['slug' => 'gestor', 'name' => 'Gestor del repositorio', 'description' => 'Publica recursos aprobados y gestiona retiros.'],
            ['slug' => 'revisor', 'name' => 'Revisor', 'description' => 'Aprueba depósitos o los devuelve con observaciones.'],
            ['slug' => 'depositante', 'name' => 'Depositante', 'description' => 'Registra documentos y proyectos.'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
