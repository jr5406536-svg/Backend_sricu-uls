<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()->with('roles')->orderBy('name')->paginate(20);

        return UserResource::collection($users);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'slug')],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
        ]);
        $this->syncRoles($user, $data['roles']);
        $audit->log('usuario.creado', $user, ['roles' => $data['roles']], $request->user());

        return (new UserResource($user->load('roles')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        return new UserResource($user->load('roles'));
    }

    public function update(Request $request, User $user, AuditLogger $audit)
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'slug')],
        ]);

        $user->fill(collect($data)->only(['name', 'email', 'password', 'is_active'])->all());
        $dirty = array_keys($user->getDirty());
        $user->save();

        if (array_key_exists('roles', $data)) {
            $this->syncRoles($user, $data['roles']);
            $dirty[] = 'roles';
        }

        $audit->log('usuario.actualizado', $user, ['fields' => $dirty], $request->user());

        return new UserResource($user->load('roles'));
    }

    public function destroy(Request $request, User $user, AuditLogger $audit)
    {
        $this->authorize('delete', $user);

        $user->is_active = false;
        $user->save();
        $user->tokens()->delete();
        $audit->log('usuario.desactivado', $user, [], $request->user());

        return response()->json(['message' => 'Usuario desactivado.']);
    }

    private function syncRoles(User $user, array $slugs): void
    {
        $ids = Role::query()->whereIn('slug', $slugs)->pluck('id');
        $user->roles()->sync($ids);
        $user->unsetRelation('roles');
    }
}
