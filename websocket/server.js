/**
 * WebSocket Server for Real-time Note Collaboration
 */

import { createServer } from 'http';
import { WebSocketServer } from 'ws';

const PORT = process.env.PORT || 6001;
const rooms = new Map();

const server = createServer((req, res) => {
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

    ws.on('message', (raw) => {
        let msg;
        try { msg = JSON.parse(raw.toString()); } catch { return; }

        switch (msg.type) {
            case 'join': {
                if (!msg.noteId || !msg.userId) {
                    ws.send(JSON.stringify({ type: 'error', message: 'Missing noteId or userId' }));
                    return;
                }
                currentUser = { id: msg.userId, name: msg.userName || 'User ' + msg.userId };
                currentRoom = String(msg.noteId);
                if (!rooms.has(currentRoom)) rooms.set(currentRoom, new Set());
                rooms.get(currentRoom).add({ ws, userId: currentUser.id, userName: currentUser.name });
                broadcast(currentRoom, { type: 'user_joined', userId: currentUser.id, userName: currentUser.name, activeUsers: getActiveUsers(currentRoom) }, ws);
                ws.send(JSON.stringify({ type: 'joined', activeUsers: getActiveUsers(currentRoom) }));
                console.log(`[WS] "${currentUser.name}" joined note ${currentRoom}`);
                break;
            }
            case 'update': {
                if (!currentRoom || !currentUser) return;
                broadcast(currentRoom, { type: 'note_updated', userId: currentUser.id, userName: currentUser.name, field: msg.field, value: msg.value, timestamp: Date.now() }, ws);
                break;
            }
            case 'leave': { leaveRoom(ws); break; }
        }
    });

    ws.on('close', () => leaveRoom(ws));
    ws.on('error', () => leaveRoom(ws));
});

function leaveRoom(ws) {
    for (const [roomId, clients] of rooms.entries()) {
        for (const client of clients) {
            if (client.ws === ws) {
                clients.delete(client);
                broadcast(roomId, { type: 'user_left', userId: client.userId, userName: client.userName, activeUsers: getActiveUsers(roomId) }, ws);
                if (clients.size === 0) rooms.delete(roomId);
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
        if (client.ws !== excludeWs && client.ws.readyState === 1) client.ws.send(data);
    }
}

function getActiveUsers(roomId) {
    const clients = rooms.get(roomId);
    if (!clients) return [];
    return [...clients].map(c => ({ id: c.userId, name: c.userName }));
}

server.listen(PORT, () => console.log(`[WS] Running on port ${PORT}`));
