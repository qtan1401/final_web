import { createServer } from 'http';
import { WebSocketServer } from 'ws';

const PORT = process.env.PORT || process.env.WS_PORT || 6001;

// Store rooms: noteId -> Set of { ws, userId, userName }
const rooms = new Map();

const server = createServer((req, res) => {
    // Simple health check endpoint
    if (req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ status: 'ok', rooms: rooms.size }));
        return;
    }
    res.writeHead(404);
    res.end();
});

const wss = new WebSocketServer({ server });

wss.on('connection', (ws, req) => {
    let currentRoom = null;
    let currentUser = null;

    console.log('[WS] New connection from', req.socket.remoteAddress);

    ws.on('message', (raw) => {
        let msg;
        try {
            msg = JSON.parse(raw.toString());
        } catch {
            return;
        }

        switch (msg.type) {
            case 'join': {
                if (!msg.noteId || !msg.userId) {
                    ws.send(JSON.stringify({ type: 'error', message: 'Missing noteId or userId' }));
                    return;
                }

                currentUser = { id: msg.userId, name: msg.userName || 'User ' + msg.userId };
                currentRoom = String(msg.noteId);

                if (!rooms.has(currentRoom)) {
                    rooms.set(currentRoom, new Set());
                }

                rooms.get(currentRoom).add({ ws, userId: currentUser.id, userName: currentUser.name });

                // Notify others that someone joined
                broadcast(currentRoom, {
                    type: 'user_joined',
                    userId: currentUser.id,
                    userName: currentUser.name,
                    activeUsers: getActiveUsers(currentRoom)
                }, ws);

                // Send current active users to the joiner
                ws.send(JSON.stringify({
                    type: 'joined',
                    activeUsers: getActiveUsers(currentRoom)
                }));

                console.log(`[WS] User "${currentUser.name}" (id:${currentUser.id}) joined note ${currentRoom}. Room size: ${rooms.get(currentRoom).size}`);
                break;
            }

            case 'update': {
                if (!currentRoom || !currentUser) return;

                // Broadcast the update to all other clients in the same room
                broadcast(currentRoom, {
                    type: 'note_updated',
                    userId: currentUser.id,
                    userName: currentUser.name,
                    field: msg.field,
                    value: msg.value,
                    timestamp: Date.now()
                }, ws);

                console.log(`[WS] User "${currentUser.name}" updated ${msg.field} in note ${currentRoom}`);
                break;
            }

            case 'cursor': {
                if (!currentRoom || !currentUser) return;
                broadcast(currentRoom, {
                    type: 'cursor_update',
                    userId: currentUser.id,
                    userName: currentUser.name,
                    position: msg.position
                }, ws);
                break;
            }

            case 'leave': {
                leaveRoom(ws);
                break;
            }
        }
    });

    ws.on('close', () => {
        leaveRoom(ws);
    });

    ws.on('error', (err) => {
        console.error('[WS] Socket error:', err.message);
        leaveRoom(ws);
    });
});

function leaveRoom(ws) {
    for (const [roomId, clients] of rooms.entries()) {
        for (const client of clients) {
            if (client.ws === ws) {
                clients.delete(client);
                console.log(`[WS] User "${client.userName}" left note ${roomId}. Room size: ${clients.size}`);

                // Notify others
                broadcast(roomId, {
                    type: 'user_left',
                    userId: client.userId,
                    userName: client.userName,
                    activeUsers: getActiveUsers(roomId)
                }, ws);

                // Clean up empty rooms
                if (clients.size === 0) {
                    rooms.delete(roomId);
                }
                return;
            }
        }
    }
}

function broadcast(roomId, message, excludeWs) {
    const clients = rooms.get(roomId);
    if (!clients) return;

    const data = JSON.stringify(message);
    for (const client of clients) {
        if (client.ws !== excludeWs && client.ws.readyState === 1) {
            client.ws.send(data);
        }
    }
}

function getActiveUsers(roomId) {
    const clients = rooms.get(roomId);
    if (!clients) return [];
    return [...clients].map(c => ({ id: c.userId, name: c.userName }));
}

server.listen(PORT, () => {
    console.log(`[WS] WebSocket server running on ws://localhost:${PORT}`);
    console.log(`[WS] Health check: http://localhost:${PORT}/health`);
});
