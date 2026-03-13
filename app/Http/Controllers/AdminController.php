<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function index()
    {
        $stats = [
            'users_count' => User::count(),
            'roles_count' => Role::count(),
            'tables_count' => count($this->getAllTables()),
        ];
        return view('admin.dashboard', compact('stats'));
    }

    public function users(Request $request)
    {
        $query = User::with('roleModel');
        
        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // Filter by role
        if ($request->filled('role_filter')) {
            $query->where('role', $request->role_filter);
        }
        
        $users = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        $roles = Role::all();
        
        return view('admin.users', compact('users', 'roles'));
    }

    public function userStore(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'is_admin' => $request->has('is_admin'),
        ]);

        $redirect = $request->filled('redirect_url') ? redirect($request->redirect_url) : back();
        return $redirect->with('success', 'User berhasil ditambahkan.');
    }

    public function userUpdate(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'is_admin' => $request->has('is_admin'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        $redirect = $request->filled('redirect_url') ? redirect($request->redirect_url) : back();
        return $redirect->with('success', 'User berhasil diperbarui.');
    }

    public function userDelete(User $user)
    {
        $user->delete();
        return back()->with('success', 'User berhasil dihapus.');
    }

    public function roles()
    {
        $roles = Role::with('permissions')->get();
        $allTables = $this->getAllTables();
        return view('admin.roles', compact('roles', 'allTables'));
    }

    public function roleStore(Request $request)
    {
        $request->validate(['name' => 'required|unique:roles']);
        Role::create($request->only('name', 'description'));
        return back()->with('success', 'Role berhasil ditambahkan.');
    }

    public function roleUpdate(Request $request, Role $role)
    {
        $request->validate(['name' => 'required|unique:roles,name,' . $role->id]);
        $role->update($request->only('name', 'description'));
        return back()->with('success', 'Role berhasil diperbarui.');
    }

    public function roleDelete(Role $role)
    {
        $role->delete();
        return response()->json(['success' => true, 'message' => 'Role berhasil dihapus.']);
    }

    public function updatePermissions(Request $request, Role $role)
    {
        $tables = $request->input('tables', []);
        
        RolePermission::where('role_id', $role->id)->delete();
        
        foreach ($tables as $table) {
            RolePermission::create([
                'role_id' => $role->id,
                'table_name' => $table
            ]);
        }

        // Clear cache for this role
        cache()->forget("allowed_tables_role_{$role->id}");

        return response()->json(['success' => true]);
    }

    private function getAllTables()
    {
        return cache()->remember('all_db_tables_admin', 600, function() {
            $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name NOT IN ('migrations','cache','cache_locks','sessions','jobs','failed_jobs','personal_access_tokens','users','password_reset_tokens', 'roles', 'role_permissions') ORDER BY table_name");
            return array_column($tables, 'table_name');
        });
    }
}
