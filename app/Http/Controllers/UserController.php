<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Get paginated users list with search functionality.
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('search') && !empty($request->search)) {
            $search = strtolower($request->search);
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
        }

        // Return users with their roles eager-loaded (Spatie)
        $users = $query->with('roles')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Update user's global role.
     */
    public function updateRole(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|string|in:Super Admin,User',
        ]);

        $user = User::findOrFail($id);

        if ($request->role === 'Super Admin') {
            $user->syncRoles(['Super Admin']);
        } else {
            // Remove Super Admin role
            $user->syncRoles([]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Global role updated successfully',
        ]);
    }
}
