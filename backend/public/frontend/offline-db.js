/**
 * Offline Database for Note Manager PWA
 * IndexedDB wrapper for caching notes and queuing offline operations.
 */

const DB_NAME = 'NoteManagerDB';
const DB_VERSION = 3;
const NOTES_STORE = 'notes';
const QUEUE_STORE = 'syncQueue';

let _db = null;

function getDB() {
    return new Promise((resolve, reject) => {
        if (_db) return resolve(_db);
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (db.objectStoreNames.contains(NOTES_STORE)) db.deleteObjectStore(NOTES_STORE);
            if (db.objectStoreNames.contains(QUEUE_STORE)) db.deleteObjectStore(QUEUE_STORE);
            db.createObjectStore(NOTES_STORE, { keyPath: 'id' });
            db.createObjectStore(QUEUE_STORE, { keyPath: 'id', autoIncrement: true });
        };
        req.onsuccess = (e) => { _db = e.target.result; resolve(_db); };
        req.onerror = (e) => reject(e.target.error);
    });
}

// ===== Notes Cache =====

async function cacheNotes(notes) {
    const db = await getDB();
    const tx = db.transaction(NOTES_STORE, 'readwrite');
    const store = tx.objectStore(NOTES_STORE);
    store.clear();
    notes.forEach(n => store.put(n));
    return new Promise((res, rej) => { tx.oncomplete = res; tx.onerror = rej; });
}

async function getCachedNotes() {
    const db = await getDB();
    const tx = db.transaction(NOTES_STORE, 'readonly');
    const req = tx.objectStore(NOTES_STORE).getAll();
    return new Promise((res, rej) => {
        req.onsuccess = () => res(req.result || []);
        req.onerror = () => rej(req.error);
    });
}

async function getCachedNote(id) {
    const db = await getDB();
    const tx = db.transaction(NOTES_STORE, 'readonly');
    const req = tx.objectStore(NOTES_STORE).get(Number(id));
    return new Promise((res, rej) => {
        req.onsuccess = () => {
            if (req.result) return res(req.result);
            // Fallback: scan all (for temp IDs stored as different type)
            const allReq = tx.objectStore(NOTES_STORE).getAll();
            allReq.onsuccess = () => res((allReq.result || []).find(n => n.id == id) || null);
            allReq.onerror = () => res(null);
        };
        req.onerror = () => rej(req.error);
    });
}

async function updateCachedNote(note) {
    const db = await getDB();
    const tx = db.transaction(NOTES_STORE, 'readwrite');
    tx.objectStore(NOTES_STORE).put(note);
    return new Promise((res, rej) => { tx.oncomplete = res; tx.onerror = rej; });
}

async function deleteCachedNote(id) {
    const db = await getDB();
    const tx = db.transaction(NOTES_STORE, 'readwrite');
    tx.objectStore(NOTES_STORE).delete(Number(id));
    return new Promise((res, rej) => { tx.oncomplete = res; tx.onerror = rej; });
}

// ===== Sync Queue =====

async function addToSyncQueue(operation) {
    const db = await getDB();
    const tx = db.transaction(QUEUE_STORE, 'readwrite');
    tx.objectStore(QUEUE_STORE).add({ ...operation, timestamp: Date.now() });
    return new Promise((res, rej) => { tx.oncomplete = res; tx.onerror = rej; });
}

async function getSyncQueue() {
    const db = await getDB();
    const tx = db.transaction(QUEUE_STORE, 'readonly');
    const req = tx.objectStore(QUEUE_STORE).getAll();
    return new Promise((res, rej) => {
        req.onsuccess = () => res(req.result || []);
        req.onerror = () => rej(req.error);
    });
}

async function clearSyncQueue() {
    const db = await getDB();
    const tx = db.transaction(QUEUE_STORE, 'readwrite');
    tx.objectStore(QUEUE_STORE).clear();
    return new Promise((res, rej) => { tx.oncomplete = res; tx.onerror = rej; });
}

// ===== Sync Execution =====

function isTempId(id) {
    return id > 1700000000000; // Timestamp-based temp IDs
}

async function processSyncQueue() {
    const token = localStorage.getItem('access_token');
    if (!token) return;

    const queue = await getSyncQueue();
    if (queue.length === 0) return;

    console.log(`[Sync] Processing ${queue.length} operations...`);
    const baseUrl = window.location.origin;
    const headers = { 'Accept': 'application/json', 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' };

    // Collect temp IDs that have a create operation — skip update/toggle-pin/delete for these
    const createdTempIds = new Set();
    queue.forEach(op => {
        if (op.action === 'create-note' && op.tempId) createdTempIds.add(op.tempId);
    });

    for (const op of queue) {
        try {
            let res;
            switch (op.action) {
                case 'create-note':
                    // Merge: find the latest data for this note from cache
                    let createData = op.data;
                    if (op.tempId) {
                        // Find any subsequent updates for this temp ID and merge
                        const laterUpdates = queue.filter(q => q.action === 'update-note' && q.serverNoteId == op.tempId);
                        if (laterUpdates.length > 0) {
                            createData = { ...createData, ...laterUpdates[laterUpdates.length - 1].data };
                        }
                    }
                    res = await fetch(`${baseUrl}/api/notes`, { method: 'POST', headers, body: JSON.stringify(createData) });
                    break;

                case 'update-note':
                    // Skip if this is updating a temp ID (already merged into create above)
                    if (isTempId(op.serverNoteId)) continue;
                    res = await fetch(`${baseUrl}/api/notes/${op.serverNoteId}`, { method: 'PUT', headers, body: JSON.stringify(op.data) });
                    break;

                case 'delete-note':
                    // Skip if deleting a note that was created offline (never existed on server)
                    if (isTempId(op.serverNoteId)) continue;
                    res = await fetch(`${baseUrl}/api/notes/${op.serverNoteId}`, { method: 'DELETE', headers });
                    break;

                case 'toggle-pin':
                    // Skip if toggling a note created offline
                    if (isTempId(op.serverNoteId)) continue;
                    res = await fetch(`${baseUrl}/api/notes/${op.serverNoteId}/toggle-pin`, { method: 'POST', headers });
                    break;
            }
            if (res && !res.ok) {
                console.warn(`[Sync] Failed: ${op.action} ${op.serverNoteId || ''}`, res.status);
            }
        } catch (e) {
            console.error(`[Sync] Error:`, e);
        }
    }

    await clearSyncQueue();
    console.log('[Sync] Done.');
}
