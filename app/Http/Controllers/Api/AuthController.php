<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request)
    {
        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone_number' => $request->phone_number,
                'user_type' => $request->user_type,
                'subscription_tier' => $request->subscription_tier ?? 'free',
                'credit_score' => 100, // Default credit score
            ]);

            // Create user profile
            $qrCode = $this->generateQRCode($user->id);
            
            UserProfile::create([
                'user_id' => $user->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'qr_code' => $qrCode,
                'city' => $request->city,
                'district' => $request->district,
                'province' => $request->province,
                'country' => $request->country,
                'is_location_public' => false, // Default private
            ]);

            // Assign default role - use web guard as default in Laravel
            $role = \Spatie\Permission\Models\Role::where('name', $request->user_type)
                ->where('guard_name', 'web')
                ->first();
            
            $user->assignRole($role);

            // Create token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Load profile relationship and check role
            $user->load('profile', 'roles');
            $userData = $user->toArray();
            $userData['is_host'] = $user->hasRole('host');

            return response()->json([
                'status' => 'success',
                'message' => 'Registrasi berhasil!',
                'data' => [
                    'user' => $userData,
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat registrasi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request)
    {
        try {
            // Determine if login is email or phone
            $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone_number';
            
            // Find user
            $user = User::where($loginField, $request->login)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'login' => ['Kredensial yang diberikan tidak valid.'],
                ]);
            }

            // Create token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Load relationships and check role
            $user->load('profile', 'sportRatings', 'roles');
            $userData = $user->toArray();
            $userData['is_host'] = $user->hasRole('host');

            return response()->json([
                'status' => 'success',
                'message' => 'Login berhasil!',
                'data' => [
                    'user' => $userData,
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Login gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat login.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Logout berhasil!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat logout.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user profile
     */
    public function profile(Request $request)
    {
        try {
            $user = $request->user();
            $user->load('profile', 'sportRatings.sport');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $user
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil profil.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone_number' => 'sometimes|string|regex:/^[\+]?[\d\s\-\(\)]+$/|unique:users,phone_number,' . $user->id,
                'first_name' => 'sometimes|string|max:100',
                'last_name' => 'sometimes|string|max:100',
                'bio' => 'sometimes|string|max:500',
                'city' => 'sometimes|string|max:100',
                'district' => 'sometimes|string|max:100',
                'province' => 'sometimes|string|max:100',
                'country' => 'sometimes|string|max:100',
                'is_location_public' => 'sometimes|boolean',
                'emergency_contact_name' => 'sometimes|string|max:100',
                'emergency_contact_phone' => 'sometimes|string|regex:/^[\+]?[\d\s\-\(\)]+$/',
            ]);

            // Update user data
            $user->update($request->only(['name', 'phone_number']));

            // Update profile data
            $profileData = $request->only([
                'first_name', 'last_name', 'bio', 'city', 'district', 
                'province', 'country', 'is_location_public', 
                'emergency_contact_name', 'emergency_contact_phone'
            ]);
            
            $user->profile()->update($profileData);

            // Reload relationships
            $user->load('profile');

            return response()->json([
                'status' => 'success',
                'message' => 'Profil berhasil diperbarui!',
                'data' => [
                    'user' => $user
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui profil.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate QR code for user
     */
    private function generateQRCode($userId)
    {
        // Generate a unique QR code identifier
        return 'rackethub_' . $userId . '_' . Str::random(8);
    }
}
