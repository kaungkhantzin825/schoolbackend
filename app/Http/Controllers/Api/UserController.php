<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('university');

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('university_id')) {
            $query->where('university_id', $request->university_id);
        }

        $users = $query->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:super_admin,university_admin',
            'university_id' => 'required_if:role,university_admin|exists:universities,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'university_id' => $request->university_id,
        ]);

        return response()->json($user->load('university'), 201);
    }

    public function show(User $user)
    {
        return response()->json($user->load('university'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|nullable|string|min:8',
            'role' => 'sometimes|required|in:super_admin,university_admin',
            'university_id' => 'required_if:role,university_admin|exists:universities,id',
        ]);

        $data = $request->except('password');
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json($user->load('university'));
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
