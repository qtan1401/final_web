<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Mail\ActivationEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request) 
{
    $validator = Validator::make($request->all(), [
        'username' => 'required|string|max:255', 
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8', 
    ]);

    if ($validator->fails()) {
        return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
    }

    $user = \App\Models\User::create([
        'display_name' => $request->username,
        'email' => $request->email,
        'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        'email_verified_at' => null, 
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    try {
        $activationUrl = url("/api/activate/{$user->id}"); 
        \Illuminate\Support\Facades\Mail::to($user->email)->queue(new \App\Mail\ActivationEmail($activationUrl));
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::warning('Activation email failed: ' . $e->getMessage());
    }
    
    return response()->json([
        'status' => 'success',
        'access_token' => $token,
        'user' => [
            'id' => $user->id,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'email_verified_at' => null, // BẮT BUỘC phải trả về null ở đây
        ]
    ], 201);
}

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => Auth::user(),
            'token' => $token,
        ], 200);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email not found or invalid'
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $user->otp_code = $otp;
        $user->otp_time = now();
        $user->save();

        try {
            $user->notify(new \App\Notifications\OTPNotification($otp));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('OTP email failed: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'OTP sent to your email'
        ]);
    }

    public function verifyOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid data'
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('otp_code', $request->otp)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid OTP'
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'OTP verified'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('otp_code', $request->otp)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid OTP or email'
            ], 400);
        }

        $user->password = Hash::make($request->password);
        $user->otp_code = null;
        $user->otp_time = null;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset successful',
            'user' => [
                'id' => $user->id,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at, 
            ]
        ]);
    }
}
