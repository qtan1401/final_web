// ============================================
// API & WebSocket Configuration
// ============================================
// API_BASE_URL: Dùng cùng origin (Laravel serve cả frontend + API)
// WS_BASE_URL: Sau khi deploy, thay bằng URL Railway WebSocket service

(function () {
    const isLocal = window.location.hostname === 'localhost'
        || window.location.hostname === '127.0.0.1';

    // API luôn cùng origin (Laravel serve frontend + API)
    window.API_BASE_URL = window.location.origin;

    if (isLocal) {
        window.WS_BASE_URL = 'ws://' + window.location.hostname + ':6001';
    } else {
        // PRODUCTION: Thay URL dưới đây bằng Railway WebSocket service URL
        // Ví dụ: 'wss://note-ws-production-xxxx.up.railway.app'
        window.WS_BASE_URL = 'wss://websocket-production-f101.up.railway.app';
    }
})();
