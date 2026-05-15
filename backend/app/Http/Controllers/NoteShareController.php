<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteShare;
use App\Models\User;
use App\Models\Notification;
use App\Mail\NoteSharedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class NoteShareController extends Controller
{
    /**
     * Share a note with a user.
     */
    public function store(Request $request, Note $note)
    {
        if ($note->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền chia sẻ ghi chú này!'], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'permission' => 'required|in:view,edit',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->id === Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Không thể chia sẻ với chính mình!'], 400);
        }

        $share = NoteShare::updateOrCreate(
            ['note_id' => $note->id, 'shared_with' => $user->id],
            ['shared_by' => Auth::id(), 'permission' => $request->permission]
        );

        $sharedBy = Auth::user();

        // Create in-app notification for recipient
        Notification::create([
            'user_id' => $user->id,
            'type' => 'note_shared',
            'title' => 'New note shared with you',
            'message' => ($sharedBy->display_name ?? $sharedBy->email) . ' shared "' . $note->title . '" with you (' . ($request->permission === 'edit' ? 'Can Edit' : 'View Only') . ')',
            'data' => [
                'note_id' => $note->id,
                'shared_by' => $sharedBy->id,
                'shared_by_name' => $sharedBy->display_name ?? $sharedBy->email,
                'permission' => $request->permission,
            ]
        ]);

        // Send email notification (queued)
        try {
            Mail::to($user->email)->queue(new NoteSharedMail($note, $sharedBy, $request->permission));
        } catch (\Exception $e) {
            // Don't fail the share if email fails, just log it
            \Log::warning('Failed to send share notification email: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'data' => $share->load('sharedWith')
        ]);
    }

    /**
     * Get shares for a note.
     */
    public function index(Note $note)
    {
        if ($note->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền xem chia sẻ của ghi chú này!'], 403);
        }

        $shares = $note->shares()->with('sharedWith')->get();

        return response()->json([
            'status' => 'success',
            'data' => $shares
        ]);
    }

    /**
     * Update share permission.
     */
    public function update(Request $request, NoteShare $share)
    {
        if ($share->shared_by !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền sửa chia sẻ này!'], 403);
        }

        $validator = Validator::make($request->all(), [
            'permission' => 'required|in:view,edit',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $share->update($request->only('permission'));

        return response()->json([
            'status' => 'success',
            'data' => $share->load('sharedWith')
        ]);
    }

    /**
     * Remove share.
     */
    public function destroy(NoteShare $share)
    {
        if ($share->shared_by !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền xóa chia sẻ này!'], 403);
        }

        $share->delete();

        return response()->json([
            'status' => 'success'
        ]);
    }
}
