<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('full_name', 'asc')->get();
        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users,username',
            'full_name' => 'required|string|max:100',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,staff,sub-admin',
        ]);

        $user = User::create([
            'username' => strtolower(trim($validated['username'])),
            'full_name' => $validated['full_name'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        logActivity('Create User Account', 'users', $user->id, "Created account for {$user->username} ({$user->role})");

        return back()->with('success', 'User account created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:100',
            'role' => 'required|in:admin,staff,sub-admin',
            'password' => 'nullable|string|min:6',
        ]);

        $user->full_name = $validated['full_name'];
        $user->role = $validated['role'];
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        logActivity('Update User Account', 'users', $user->id, "Updated account for {$user->username}");

        return back()->with('success', 'User details updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account while logged in.');
        }

        logActivity('Delete User Account', 'users', $user->id, "Deleted account {$user->username}");
        $user->delete();

        return back()->with('success', 'User account deleted.');
    }
}
