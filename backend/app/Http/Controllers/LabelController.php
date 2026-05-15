<?php

namespace App\Http\Controllers;

use App\Models\Label;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class LabelController extends Controller
{
    public function index()
    {
        $labels = Label::where('user_id', Auth::id())->get();
        return response()->json([
            'status' => 'success',
            'data' => $labels
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $label = Label::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Label created successfully',
            'data' => $label
        ], 201);
    }

    public function update(Request $request, Label $label)
    {
        if ($label->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $label->update(['name' => $request->name]);

        return response()->json([
            'status' => 'success',
            'message' => 'Label updated successfully',
            'data' => $label
        ]);
    }

    public function destroy(Label $label)
    {
        if ($label->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $label->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Label deleted successfully'
        ]);
    }
}
