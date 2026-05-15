<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth; // NEW: Thêm cái này để dùng Auth

class NoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return $this->getAccessibleNotes($request, false);
    }

    public function shared(Request $request)
    {
        return $this->getAccessibleNotes($request, true);
    }

    protected function getAccessibleNotes(Request $request, bool $sharedOnly = false)
    {
        $userId = Auth::id();

        $query = Note::with(['labels', 'shares.sharedBy', 'images'])
            ->when($sharedOnly, function ($q) use ($userId) {
                $q->where('user_id', '!=', $userId)
                  ->whereHas('shares', function ($q2) use ($userId) {
                      $q2->where('shared_with', $userId);
                  });
            }, function ($q) use ($userId) {
                $q->where(function ($q2) use ($userId) {
                    $q2->where('user_id', $userId)
                        ->orWhereHas('shares', function ($q3) use ($userId) {
                            $q3->where('shared_with', $userId);
                        });
                });
            });

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhereHas('labels', function ($ql) use ($search) {
                        $ql->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('label_id')) {
            $query->whereHas('labels', function ($q) use ($request) {
                $q->where('labels.id', $request->label_id);
            });
        }

        $notes = $query->withCount('shares')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('pinned_at', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        $notes->each(function ($note) use ($userId) {
            $note->is_shared = $note->shares_count > 0;
            $note->shared_with_me = false;
            $note->shared_permission = null;
            $note->shared_by_name = null;
            $note->shared_at = null;

            if ($note->user_id !== $userId) {
                $share = $note->shares->firstWhere('shared_with', $userId);
                if ($share) {
                    $note->shared_with_me = true;
                    $note->shared_permission = $share->permission;
                    $note->shared_by_name = $share->sharedBy?->display_name ?? $share->sharedBy?->email;
                    $note->shared_at = $share->created_at;
                    $note->is_shared = true;
                }
            }
        });

        $notes->each(function ($note) use ($userId) {
            if ($note->is_locked) {
                $note->content = null;
                $note->images = [];
                $note->attachment = null;
            }
        });

        return response()->json([
            'status' => 'success',
            'data' => $notes
        ]);
    }

    /**
     * Store new note
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'content'     => 'nullable|string',
            'color'       => 'nullable|string|max:7',
            'label_ids'   => 'nullable|array',
            'label_ids.*' => 'exists:labels,id',
            'is_locked'   => 'nullable|boolean',
            'password'    => 'nullable|string|min:4',
            'password_confirmation' => 'nullable|string|same:password',

            // IMAGES
            'images'      => 'nullable|array',
            'images.*'    => 'image|mimes:jpg,jpeg,png,gif,webp|max:4096',
            'attachment'  => 'nullable|file|max:10240'
        ]);

        $validator->sometimes('password', 'required|string|min:4', function ($input) {
            return isset($input->is_locked) && filter_var($input->is_locked, FILTER_VALIDATE_BOOLEAN);
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $noteData = [
            'user_id'   => Auth::id(), 
            'title'     => $request->title,
            'content'   => $request->content,
            'color'     => $request->color ?? '#ffffff',
            'is_locked' => $request->is_locked ?? false,
        ];

        // lock password
        if ($request->is_locked && $request->password) {
            $noteData['password'] = Hash::make($request->password);
        }

        $note = Note::create($noteData);

        // upload images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('frontend/images'), $filename);
                $note->images()->create(['path' => 'images/' . $filename]);
            }
        }

        // upload attachment
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('frontend/attachments'), $filename);
            $note->update(['attachment' => 'attachments/' . $filename]);
        }

        // Always sync labels - empty array clears all labels
        $labelIds = $request->input('label_ids', []);
        if ($labelIds === '' || $labelIds === null) $labelIds = [];
        $note->labels()->sync(array_filter((array)$labelIds));

        return response()->json([
            'status' => 'success',
            'message' => 'Note created successfully',
            'data' => $note->load('labels')
        ], 201);
    }

    /**
     * Show note
     */
    public function show(Note $note)
    {
        $userId = Auth::id();

        if ($note->user_id !== $userId) {
            $share = NoteShare::where('note_id', $note->id)->where('shared_with', $userId)->first();
            if (!$share) {
                return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền xem ghi chú này!'], 403);
            }
        }

        $note = $this->enrichNoteData($note);

        if ($note->is_locked) {
            $verifyPassword = $request->input('verify_password') ?? $request->query('verify_password');
            $isUnlocked = $verifyPassword && Hash::check($verifyPassword, $note->password);

            if (!$isUnlocked) {
                $note->content = null;
                $note->images = [];
                $note->attachment = null;
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $note
        ]);
    }

    /**
     * Update note
     */
    public function update(Request $request, Note $note)
    {
        $userId = Auth::id();
        $isOwner = $note->user_id === $userId;
        $share = null;

        if (!$isOwner) {
            $share = NoteShare::where('note_id', $note->id)->where('shared_with', $userId)->first();
            if (!$share || $share->permission !== 'edit') {
                return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền sửa ghi chú này!'], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'title'            => 'sometimes|required|string|max:255',
            'content'          => 'nullable|string',
            'color'            => 'nullable|string|max:7',
            'label_ids'        => 'nullable|array',
            'label_ids.*'      => 'exists:labels,id',
            'is_locked'        => 'nullable|boolean',
            'password'         => 'nullable|string|min:4',
            'password_confirmation' => 'nullable|string|same:password',
            'current_password' => 'nullable|string',

            // IMAGES
            'images'           => 'nullable|array',
            'images.*'         => 'image|mimes:jpg,jpeg,png,gif,webp|max:4096',
            'attachment'       => 'nullable|file|max:10240'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // locked verify
        if ($note->is_locked && $note->getOriginal('password')) {
            if (
                !$request->current_password ||
                !Hash::check($request->current_password, $note->getOriginal('password'))
            ) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Wrong password.'
                ], 403);
            }
        }

        if (!$isOwner && ($request->has('is_locked') || $request->has('password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only owner can change password protection.'
            ], 403);
        }

        $updateData = $request->only(['title', 'content', 'color']);

        // lock toggle
        if ($isOwner && $request->has('is_locked')) {
            $updateData['is_locked'] = $request->is_locked;

            if ($request->is_locked && $request->password) {
                $updateData['password'] = Hash::make($request->password);
            } elseif (!$request->is_locked) {
                $updateData['password'] = null;
            }
        }

        // upload new images (append)
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('frontend/images'), $filename);
                $note->images()->create(['path' => 'images/' . $filename]);
            }
        }

        // upload new attachment
        if ($request->hasFile('attachment')) {
            if ($note->attachment && file_exists(public_path('frontend/' . $note->attachment))) {
                unlink(public_path('frontend/' . $note->attachment));
            }

            $file = $request->file('attachment');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('frontend/attachments'), $filename);
            $updateData['attachment'] = 'attachments/' . $filename;
        }

        $note->update($updateData);

        // Always sync labels - empty array clears all labels
        $labelIds = $request->input('label_ids', []);
        if ($labelIds === '' || $labelIds === null) $labelIds = [];
        $note->labels()->sync(array_filter((array)$labelIds));

        return response()->json([
            'status' => 'success',
            'message' => 'Note updated successfully',
            'data' => $note->load('labels')
        ]);
    }

    /**
     * Delete note
     */
    public function destroy(Request $request, Note $note)
    {
        if ($note->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền xóa ghi chú này!'], 403);
        }

        if ($note->is_locked && $note->getOriginal('password')) {
            $password = $request->input('password') ?? $request->header('X-Note-Password');

            if (!$password || !Hash::check($password, $note->getOriginal('password'))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Wrong password.'
                ], 403);
            }
        }

        // delete images
        foreach ($note->images as $img) {
            if (file_exists(public_path('frontend/' . $img->path))) {
                unlink(public_path('frontend/' . $img->path));
            }
            $img->delete();
        }

        $note->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Deleted successfully'
        ]);
    }

    /**
     * Pin / Unpin
     */
    public function togglePin(Note $note)
    {
        if ($note->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền ghim / bỏ ghim ghi chú này!'], 403);
        }

        $note->is_pinned = !$note->is_pinned;
        $note->pinned_at = $note->is_pinned ? now() : null;
        $note->save();

        return response()->json([
            'status' => 'success',
            'data' => $note
        ]);
    }

    /**
     * Unlock note
     */
    public function verifyPassword(Request $request, Note $note)
    {
        $userId = Auth::id();
        if ($note->user_id !== $userId) {
            $share = NoteShare::where('note_id', $note->id)->where('shared_with', $userId)->first();
            if (!$share) {
                return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền xem ghi chú này!'], 403);
            }
        }

        if (!$note->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'This note is not locked.'
            ], 400);
        }

        if (!Hash::check($request->password, $note->getOriginal('password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wrong password.'
            ], 403);
        }

        $note = $this->enrichNoteData($note);

        return response()->json([
            'status' => 'success',
            'data' => array_merge($note->toArray(), [
                 'content' => $note->getOriginal('content'), // Ensure raw content is returned
                 'images' => $note->images,
                 'labels' => $note->labels,
                 'shared_permission' => $note->shared_permission,
                 'shared_with_me' => $note->shared_with_me
            ])
        ]);
    }

    /**
     * Delete specific image
     */
    public function deleteImage(Note $note, $imageId)
    {
        $userId = Auth::id();
        if ($note->user_id !== $userId) {
            $share = NoteShare::where('note_id', $note->id)->where('shared_with', $userId)->first();
            if (!$share || $share->permission !== 'edit') {
                return response()->json(['status' => 'error', 'message' => 'Bạn không có quyền sửa ghi chú này!'], 403);
            }
        }

        $image = $note->images()->find($imageId);
        if (!$image) {
            return response()->json(['status' => 'error', 'message' => 'Image not found.'], 404);
        }

        if (file_exists(public_path('frontend/' . $image->path))) {
            unlink(public_path('frontend/' . $image->path));
        }

        $image->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Image deleted successfully'
        ]);
    }

    /**
     * Enrich note data with shares and permissions
     */
    private function enrichNoteData(Note $note)
    {
        $userId = Auth::id();
        $note->load(['labels', 'shares.sharedBy', 'images']);
        
        $note->is_shared = $note->shares->isNotEmpty();
        $note->shared_with_me = false;
        $note->shared_permission = null;
        $note->shared_by_name = null;
        $note->shared_at = null;

        if ($note->user_id !== $userId) {
            $share = $note->shares->firstWhere('shared_with', $userId);
            if ($share) {
                $note->shared_with_me = true;
                $note->shared_permission = $share->permission;
                $note->shared_by_name = $share->sharedBy?->display_name ?? $share->sharedBy?->email;
                $note->shared_at = $share->created_at;
            }
        }

        return $note;
    }
}