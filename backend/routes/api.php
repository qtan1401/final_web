<?php
use App\Models\User;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NoteShareController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- NHÓM PUBLIC (Ai cũng truy cập được) ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Password Reset
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-otp', [AuthController::class, 'verifyOTP']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/activate/{id}', function ($id) {
    $user = User::findOrFail($id);
    $user->email_verified_at = now();
    $user->save();

    // Sau khi kích hoạt xong, chuyển hướng họ về trang Dashboard
    return redirect('/frontend/dashboard.html?activated=true');
});

Route::middleware('auth:sanctum')->get('/user-profile', function (Request $request) {
    return response()->json([
        'status' => 'success',
        'user' => $request->user() // Trả về data mới nhất từ DB
    ]);
});

Route::middleware('auth:sanctum')->get('/profile', [ProfileController::class, 'show']);
Route::middleware('auth:sanctum')->post('/profile', [ProfileController::class, 'update']);

// --- NHÓM PRIVATE (Bắt buộc phải có Token mới được vào) ---
Route::middleware('auth:sanctum')->group(function () {
    
    // Đăng xuất
    Route::post('/logout', [AuthController::class, 'logout']);

    // Quản lý Note (Tự động map 5 route: index, store, show, update, destroy)
    Route::get('/notes/shared', [NoteController::class, 'shared']);
    Route::apiResource('notes', NoteController::class);

    // Các tính năng mở rộng của Note
    Route::post('/notes/{note}/toggle-pin', [NoteController::class, 'togglePin']);
    Route::post('/notes/{note}/verify-password', [NoteController::class, 'verifyPassword']);
    Route::delete('/notes/{note}/images/{imageId}', [NoteController::class, 'deleteImage']);

    // Quản lý Nhãn (Labels) - Cái này cũng phải của riêng từng người!
    Route::apiResource('labels', LabelController::class);

    // Quản lý chia sẻ ghi chú
    Route::apiResource('notes.shares', NoteShareController::class)->except(['show']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    
});