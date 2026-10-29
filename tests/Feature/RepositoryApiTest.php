<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Keyword;
use App\Models\License;
use App\Models\Resource;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RepositoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_el_visitante_busca_filtra_y_consulta_solo_recursos_publicados(): void
    {
        $catalog = $this->catalog();
        $owner = $this->userWithRole('depositante');
        $visible = $this->resource($owner, $catalog, [
            'title' => 'Algebra lineal aplicada',
            'status' => 'publicado',
            'access_level' => 'publico',
            'published_at' => now(),
        ]);
        $this->resource($owner, $catalog, [
            'title' => 'Borrador oculto',
            'status' => 'borrador',
        ]);
        $otra = Category::query()->create([
            'name' => 'Educación',
            'slug' => 'educacion',
        ]);
        $this->resource($owner, $catalog, [
            'title' => 'Didactica universitaria',
            'status' => 'publicado',
            'access_level' => 'publico',
            'category_id' => $otra->id,
            'published_at' => now(),
            'resource_date' => '2020-01-01',
        ]);

        $this->getJson('/api/resources?q=algebra')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Algebra lineal aplicada')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissing(['title' => 'Borrador oculto']);

        $this->getJson('/api/resources?category_id='.$catalog['category']->id.'&type=documento&author_id='.$catalog['author']->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/resources')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/resources/'.$visible->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Algebra lineal aplicada')
            ->assertJsonPath('data.license.allows_download', true)
            ->assertJsonPath('data.authors.0.name', 'Ada Lovelace');

        $this->getJson('/api/collections')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Trabajos']);

        $this->getJson('/api/collections/'.$catalog['collection']->id.'/resources')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_el_flujo_de_deposito_revision_y_publicacion_queda_registrado(): void
    {
        Storage::fake('local');
        $catalog = $this->catalog();
        $depositante = $this->userWithRole('depositante');
        $revisor = $this->userWithRole('revisor');
        $gestor = $this->userWithRole('gestor');

        Sanctum::actingAs($depositante);
        $this->postJson('/api/resources', ['title' => ''])->assertStatus(422);

        $created = $this->postJson('/api/resources', $this->payload($catalog, [
            'title' => 'Sistema de inventario',
            'type' => 'proyecto',
            'technical_description' => 'API REST y base relacional.',
            'technologies' => 'Laravel, PostgreSQL',
            'external_url' => 'https://example.edu/inventario',
        ]))->assertCreated()
            ->assertJsonPath('data.status', 'borrador');

        $id = $created->json('data.id');

        $this->postJson("/api/resources/{$id}/files", [
            'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ])->assertStatus(422);

        $fileId = $this->postJson("/api/resources/{$id}/files", [
            'file' => UploadedFile::fake()->create('codigo.zip', 20, 'application/zip'),
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/resources/{$id}/submit")->assertOk()
            ->assertJsonPath('data.status', 'revision');

        Sanctum::actingAs($revisor);
        $this->postJson("/api/resources/{$id}/review", [
            'decision' => 'devuelto',
        ])->assertStatus(422);

        $this->postJson("/api/resources/{$id}/review", [
            'decision' => 'devuelto',
            'observations' => 'Falta la licencia en la descripción.',
        ])->assertOk()->assertJsonPath('data.status', 'observado');

        $this->assertDatabaseHas('reviews', [
            'resource_id' => $id,
            'reviewer_id' => $revisor->id,
            'decision' => 'devuelto',
        ]);

        Sanctum::actingAs($depositante);
        $this->putJson("/api/resources/{$id}", $this->payload($catalog, [
            'title' => 'Sistema de inventario',
            'type' => 'proyecto',
            'technical_description' => 'API REST, PostgreSQL y licencia CC BY.',
        ]))->assertOk();
        $this->postJson("/api/resources/{$id}/submit")->assertOk();

        Sanctum::actingAs($revisor);
        $this->postJson("/api/resources/{$id}/review", [
            'decision' => 'aprobado',
            'observations' => 'Metadatos completos.',
        ])->assertOk()->assertJsonPath('data.status', 'aprobado');

        Sanctum::actingAs($depositante);
        $this->postJson("/api/resources/{$id}/publish", [
            'access_level' => 'publico',
        ])->assertForbidden();

        Sanctum::actingAs($gestor);
        $this->postJson("/api/resources/{$id}/publish", [
            'access_level' => 'publico',
        ])->assertOk()->assertJsonPath('data.status', 'publicado');

        $this->assertNotNull(Review::query()->where('resource_id', $id)->where('decision', 'aprobado')->first());

        $this->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonFragment(['action' => 'recurso.publicado']);

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/resources?q=inventario')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Sistema de inventario');

        $this->getJson('/api/resources/'.$id.'/files/'.$fileId.'/download')->assertOk();
    }

    public function test_los_permisos_impiden_operaciones_administrativas_y_la_busqueda_semantica_es_opcional(): void
    {
        $catalog = $this->catalog();
        $depositante = $this->userWithRole('depositante');
        $gestor = $this->userWithRole('gestor');
        $admin = $this->userWithRole('administrador');

        $this->postJson('/api/users', [
            'name' => 'Intruso',
            'email' => 'intruso@uls.edu.sv',
            'password' => 'Secreta123!',
            'password_confirmation' => 'Secreta123!',
            'roles' => ['administrador'],
        ])->assertUnauthorized();

        Sanctum::actingAs($depositante);
        $this->postJson('/api/users', [
            'name' => 'Intruso',
            'email' => 'intruso@uls.edu.sv',
            'password' => 'Secreta123!',
            'password_confirmation' => 'Secreta123!',
            'roles' => ['administrador'],
        ])->assertForbidden();
        $this->getJson('/api/stats')->assertForbidden();

        Sanctum::actingAs($admin);
        $this->postJson('/api/users', [
            'name' => 'Nuevo gestor',
            'email' => 'nuevo@uls.edu.sv',
            'password' => 'Secreta123!',
            'password_confirmation' => 'Secreta123!',
            'roles' => ['gestor'],
        ])->assertCreated();

        $resource = $this->resource($depositante, $catalog, [
            'title' => 'Visible para retirar',
            'status' => 'publicado',
            'access_level' => 'publico',
            'published_at' => now(),
        ]);

        Sanctum::actingAs($gestor);
        $this->putJson('/api/resources/'.$resource->id, $this->payload($catalog, [
            'title' => 'Visible corregido',
        ]))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'recurso.actualizado']);

        $this->postJson('/api/resources/'.$resource->id.'/withdraw', [
            'reason' => 'El autor retiró la autorización.',
        ])->assertOk();

        auth()->forgetGuards();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/resources/'.$resource->id)->assertNotFound();

        $this->getJson('/api/resources/semantic?q=repositorio')
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $this->getJson('/api/resources?q=corregido')->assertOk()->assertJsonPath('meta.total', 0);

        Sanctum::actingAs($gestor);
        $this->getJson('/api/stats?from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonStructure(['resources_created', 'published', 'views', 'downloads', 'by_status']);
    }

    public function test_el_login_entrega_token_y_rechaza_credenciales_invalidas(): void
    {
        $user = $this->userWithRole('depositante', [
            'email' => 'ada@uls.edu.sv',
            'password' => 'Secreta123!',
        ]);

        $this->postJson('/api/login', [
            'email' => 'ada@uls.edu.sv',
            'password' => 'incorrecta',
        ])->assertStatus(422);

        $this->postJson('/api/login', [
            'email' => 'ada@uls.edu.sv',
            'password' => 'Secreta123!',
        ])->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonFragment(['depositante']);

        $user->update(['is_active' => false]);
        $this->postJson('/api/login', [
            'email' => 'ada@uls.edu.sv',
            'password' => 'Secreta123!',
        ])->assertForbidden();
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail());

        return $user;
    }

    private function catalog(): array
    {
        return [
            'license' => License::query()->create([
                'name' => 'CC BY',
                'slug' => 'cc-by',
                'allows_download' => true,
            ]),
            'category' => Category::query()->create([
                'name' => 'Computación',
                'slug' => 'computacion',
            ]),
            'collection' => Collection::query()->create([
                'name' => 'Trabajos',
                'slug' => 'trabajos',
            ]),
            'author' => Author::query()->create(['name' => 'Ada Lovelace']),
            'keyword' => Keyword::query()->create(['name' => 'algoritmos', 'slug' => 'algoritmos']),
        ];
    }

    private function payload(array $catalog, array $override = []): array
    {
        return array_merge([
            'title' => 'Recurso de prueba',
            'description' => 'Descripción del recurso de prueba.',
            'type' => 'documento',
            'resource_date' => '2026-03-01',
            'language' => 'es',
            'license_id' => $catalog['license']->id,
            'category_id' => $catalog['category']->id,
            'collection_id' => $catalog['collection']->id,
            'author_ids' => [$catalog['author']->id],
            'keyword_ids' => [$catalog['keyword']->id],
            'provenance' => 'Asignatura de prueba',
        ], $override);
    }

    private function resource(User $owner, array $catalog, array $override = []): Resource
    {
        $data = $this->payload($catalog, $override);
        $authorIds = $data['author_ids'];
        $keywordIds = $data['keyword_ids'];
        unset($data['author_ids'], $data['keyword_ids']);

        $resource = Resource::query()->create([
            ...$data,
            'owner_id' => $owner->id,
            'status' => $override['status'] ?? 'borrador',
            'access_level' => $override['access_level'] ?? 'restringido',
        ]);
        $resource->syncAuthors($authorIds);
        $resource->syncKeywords($keywordIds);

        return $resource;
    }
}
