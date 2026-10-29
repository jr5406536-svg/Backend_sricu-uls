<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Keyword;
use App\Models\License;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        License::query()->updateOrCreate(['slug' => 'cc-by-4.0'], [
            'name' => 'Creative Commons Atribución 4.0',
            'url' => 'https://creativecommons.org/licenses/by/4.0/',
            'allows_download' => true,
            'description' => 'Permite la consulta y la descarga con atribución.',
        ]);

        License::query()->updateOrCreate(['slug' => 'todos-los-derechos'], [
            'name' => 'Todos los derechos reservados',
            'url' => null,
            'allows_download' => false,
            'description' => 'Solo se consulta la ficha. La descarga no está autorizada.',
        ]);

        Category::query()->updateOrCreate(['slug' => 'ciencias-de-la-computacion'], [
            'name' => 'Ciencias de la Computación',
            'description' => 'Producción académica de la carrera.',
        ]);

        Collection::query()->updateOrCreate(['slug' => 'trabajos-de-graduacion'], [
            'name' => 'Trabajos de graduación',
            'description' => 'Documentos de graduación autorizados.',
        ]);

        Collection::query()->updateOrCreate(['slug' => 'proyectos-de-software'], [
            'name' => 'Proyectos de software',
            'description' => 'Proyectos y código fuente de la comunidad universitaria.',
        ]);

        Keyword::query()->updateOrCreate(['slug' => 'repositorio'], ['name' => 'repositorio']);
        Keyword::query()->updateOrCreate(['slug' => 'software-libre'], ['name' => 'software libre']);

        Author::query()->updateOrCreate(['name' => 'Comunidad académica ULS'], [
            'affiliation' => 'Universidad Luterana Salvadoreña',
        ]);
    }
}
