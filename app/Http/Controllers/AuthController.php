<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Default global role assignment
        $user->assignRole('User');

        $token = auth('api')->login($user);

        return $this->respondWithToken($token);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = auth('api')->user();
        
        // Include roles and permissions
        $user->load('roles', 'permissions');

        return response()->json($user);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }

    /**
     * Get a batch of users by their IDs.
     * Useful for inter-service communication (data stitching).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsersBatch(Request $request)
    {
        $ids = $request->input('ids', []);
        
        if (empty($ids) || !is_array($ids)) {
            return response()->json([], 200);
        }

        // Fetch users and their global roles
        $users = User::whereIn('id', $ids)->with('roles')->get();

        return response()->json($users);
    }

    /**
     * Find user by email or create new.
     */
    public function findOrCreate(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'required|string|max:255'
        ]);

        $user = User::firstOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->name, 
                'password' => Hash::make(uniqid()) // Random password for manually added users
            ]
        );

        if ($user->wasRecentlyCreated) {
            $user->assignRole('User');
        }

        $user->load('roles');
        return response()->json($user);
    }

    /**
     * Find user by email.
     */
    public function findByEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->with('roles')->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    /**
     * Search users by name or email.
     */
    public function search(Request $request)
    {
        $keyword = $request->input('keyword', '');

        if (empty($keyword)) {
            return response()->json([]);
        }

        $users = User::where('name', 'like', "%{$keyword}%")
            ->orWhere('email', 'like', "%{$keyword}%")
            ->pluck('id');

        return response()->json($users);
    }
}
