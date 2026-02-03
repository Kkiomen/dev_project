<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserPasswordRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['name', 'email', 'created_at', 'is_admin'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $users = $query
            ->withCount(['brands', 'socialPosts', 'notifications'])
            ->paginate($request->input('per_page', 15));

        return AdminUserResource::collection($users);
    }

    public function show(User $user)
    {
        $user->loadCount(['brands', 'socialPosts', 'notifications']);
        $user->load(['brands' => function ($q) {
            $q->latest()->limit(5);
        }]);

        return new AdminUserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user->update($request->validated());

        $user->loadCount(['brands', 'socialPosts', 'notifications']);

        return new AdminUserResource($user);
    }

    public function updatePassword(UpdateUserPasswordRequest $request, User $user)
    {
        $user->update([
            'password' => $request->input('password'),
        ]);

        return response()->json(['message' => 'Password updated']);
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }

    public function notifications(User $user)
    {
        $notifications = $user->notifications()
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }
}
