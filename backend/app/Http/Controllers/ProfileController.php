<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
            ]
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'avatar' => 'sometimes|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ]);

        if ($request->has('display_name')) {
            $user->display_name = $request->display_name;
        }

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && file_exists(public_path('frontend/images/' . basename($user->avatar)))) {
                unlink(public_path('frontend/images/' . basename($user->avatar)));
            }

            $file = $request->file('avatar');
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('frontend/images'), $filename);
            $user->avatar = 'images/' . $filename;
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
            ]
        ]);
    }
}
