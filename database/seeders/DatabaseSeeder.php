<?php

namespace Database\Seeders;

use App\Enums\AccessLevel;
use App\Enums\ResourceStatus;
use App\Enums\ResourceType;
use App\Models\Author;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Keyword;
use App\Models\License;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            CatalogSeeder::class,
        ]);

        $owner = User::query()->where('email', 'docente@uls.edu.sv')->first();
        $license = License::query()->where('slug', 'cc-by-4.0')->first();
        $category = Category::query()->where('slug', 'ciencias-de-la-computacion')->first();
        $collection = Collection::query()->where('slug', 'trabajos-de-graduacion')->first();
        $author = Author::query()->where('name', 'Comunidad académica ULS')->first();
        $keyword = Keyword::query()->where('slug', 'repositorio')->first();

        if (! $owner || ! $license || ! $category || ! $author || ! $keyword) {
            return;
        }

        $resource = Resource::query()->updateOrCreate(
            ['title' => 'Guía inicial del repositorio universitario'],
            [
                'description' => 'Ficha de ejemplo para comprobar la consulta pública del repositorio.',
                'type' => ResourceType::Documento,
                'resource_date' => '2026-10-01',
                'language' => 'es',
                'license_id' => $license->id,
                'category_id' => $category->id,
                'collection_id' => $collection?->id,
                'status' => ResourceStatus::Publicado,
                'access_level' => AccessLevel::Publico,
                'provenance' => 'Registro de demostración',
                'owner_id' => $owner->id,
                'published_at' => now(),
            ],
        );

        $resource->syncAuthors([$author->id]);
        $resource->syncKeywords([$keyword->id]);
    }
}
