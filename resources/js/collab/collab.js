import * as Y from 'yjs';
import { Awareness, applyAwarenessUpdate, encodeAwarenessUpdate, removeAwarenessStates } from 'y-protocols/awareness';
import Collaboration from '@tiptap/extension-collaboration';
import CollaborationCaret from '@tiptap/extension-collaboration-caret';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const CHUNK = 6000; // characters per whisper (Pusher/Reverb client events are size-limited)
const FLUSH_MS = 120; // batch document updates (client events are rate-limited, ~10/s)
const AWARENESS_MS = 300; // throttle cursor/presence updates
const RESYNC_MS = 8000; // periodic state-vector exchange heals any dropped message

const toBase64 = (bytes) => {
    let binary = '';
    for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
    return btoa(binary);
};
const fromBase64 = (value) => Uint8Array.from(atob(value), (c) => c.charCodeAt(0));

function makeEcho(config) {
    if (window.Echo) return window.Echo;
    const b = config.broadcaster || {};
    window.Pusher = Pusher;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const tls = (b.scheme || 'https') === 'https';

    return new Echo({
        broadcaster: 'reverb',
        key: b.key,
        wsHost: b.host || location.hostname,
        wsPort: b.port || 80,
        wssPort: b.port || 443,
        forceTLS: tls,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: config.authEndpoint,
        auth: { headers: token ? { 'X-CSRF-TOKEN': token } : {} },
    });
}

/**
 * Yjs provider that relays document and awareness updates as presence-channel
 * whispers. The first member in the room seeds the document from the stored
 * HTML; later members sync state from their peers.
 */
class EchoYjsProvider {
    constructor(echo, channelName, doc, user) {
        this.echo = echo;
        this.doc = doc;
        this.awareness = new Awareness(doc);
        this.awareness.setLocalStateField('user', { name: user.name, color: user.color, id: user.id });
        this.members = new Map(); // member id → awareness client ids
        this.buffers = new Map();
        this.listeners = new Set();
        this.channel = echo.join(channelName);

        this.pending = [];
        this.flushTimer = null;
        this.awarenessTimer = null;
        this.awarenessChanged = new Set();

        this.onDocUpdate = (update, origin) => {
            if (origin === this) return;
            this.pending.push(update);
            this.flushTimer ??= setTimeout(() => this.flush(), FLUSH_MS);
        };
        this.onAwarenessUpdate = ({ added, updated, removed }, origin) => {
            if (origin === this) return;
            [...added, ...updated, ...removed].forEach((id) => this.awarenessChanged.add(id));
            this.awarenessTimer ??= setTimeout(() => {
                this.awarenessTimer = null;
                const ids = [...this.awarenessChanged];
                this.awarenessChanged.clear();
                if (ids.length) this.send('aw', encodeAwarenessUpdate(this.awareness, ids));
            }, AWARENESS_MS);
        };
        // Anti-entropy: periodically compare state with peers so a lost message self-heals.
        this.resyncTimer = setInterval(() => {
            if (this.members.size > 1 || this.peers().length) this.send('sv', Y.encodeStateVector(this.doc));
        }, RESYNC_MS);
        doc.on('update', this.onDocUpdate);
        this.awareness.on('update', this.onAwarenessUpdate);
        this.awareness.on('change', () => this.listeners.forEach((fn) => fn(this.peers())));
    }

    peers() {
        return [...this.awareness.getStates().entries()]
            .filter(([id]) => id !== this.doc.clientID)
            .map(([, state]) => state.user)
            .filter(Boolean);
    }

    onPeers(fn) {
        this.listeners.add(fn);
        return () => this.listeners.delete(fn);
    }

    /** Resolves { seed: boolean } once this client knows whether to seed the document. */
    connect() {
        return new Promise((resolve) => {
            let settled = false;
            const settle = (seed) => {
                if (settled) return;
                settled = true;
                resolve({ seed });
            };

            this.channel
                .here((members) => {
                    members.forEach((m) => this.members.set(String(m.id), new Set()));
                    // Always ask peers for their state: the same user may already have the
                    // document open in another tab (presence lists them only once).
                    this.send('sv', Y.encodeStateVector(this.doc));
                    // Announce our presence (name, colour, cursor) to everyone already here.
                    this.send('aw', encodeAwarenessUpdate(this.awareness, [this.doc.clientID]));
                    const wait = members.length <= 1 ? 1200 : 4000;
                    setTimeout(() => settle(Y.encodeStateVector(this.doc).length <= 1), wait);
                })
                .joining((member) => {
                    this.members.set(String(member.id), new Set());
                    this.send('aw', encodeAwarenessUpdate(this.awareness, [this.doc.clientID]));
                })
                .leaving((member) => {
                    const ids = [...this.awareness.getStates().entries()].filter(([, s]) => String(s.user?.id) === String(member.id)).map(([id]) => id);
                    if (ids.length) removeAwarenessStates(this.awareness, ids, this);
                    this.members.delete(String(member.id));
                })
                .listenForWhisper('desk-y', (message) => this.receive(message, () => settle(false)))
                .error?.((error) => {
                    console.error('[desk-editor] collaboration channel error', error);
                    settle(true);
                });
        });
    }

    /** Send all queued document updates as one merged update. */
    flush() {
        this.flushTimer = null;
        if (!this.pending.length) return;
        const update = this.pending.length === 1 ? this.pending[0] : Y.mergeUpdates(this.pending);
        this.pending = [];
        this.send('u', update);
    }

    send(type, bytes, to = null) {
        const data = toBase64(bytes);
        const id = Math.random().toString(36).slice(2, 10);
        const total = Math.max(1, Math.ceil(data.length / CHUNK));
        for (let i = 0; i < total; i++) {
            this.channel.whisper('desk-y', { t: type, id, i, n: total, to, from: this.doc.clientID, d: data.slice(i * CHUNK, (i + 1) * CHUNK) });
        }
    }

    receive(message, onSynced) {
        if (message.to && message.to !== this.doc.clientID) return;
        let data = message.d;
        if (message.n > 1) {
            const parts = this.buffers.get(message.id) || [];
            parts[message.i] = message.d;
            this.buffers.set(message.id, parts);
            if (parts.filter((p) => p !== undefined).length < message.n) return;
            data = parts.join('');
            this.buffers.delete(message.id);
        }
        const bytes = fromBase64(data);

        if (message.t === 'u') Y.applyUpdate(this.doc, bytes, this);
        else if (message.t === 's2') {
            Y.applyUpdate(this.doc, bytes, this);
            onSynced();
        } else if (message.t === 'sv') {
            const diff = Y.encodeStateAsUpdate(this.doc, bytes);
            // Newcomers (empty state vector) always get an answer so they know not to seed;
            // periodic resyncs are only answered when the peer is actually missing something.
            if (bytes.length <= 1 || diff.length > 2) this.send('s2', diff, message.from);
            this.send('aw', encodeAwarenessUpdate(this.awareness, [this.doc.clientID]));
        } else if (message.t === 'aw') applyAwarenessUpdate(this.awareness, bytes, this);
    }

    destroy() {
        this.flush();
        clearInterval(this.resyncTimer);
        clearTimeout(this.awarenessTimer);
        this.doc.off('update', this.onDocUpdate);
        this.awareness.off('update', this.onAwarenessUpdate);
        removeAwarenessStates(this.awareness, [this.doc.clientID], this);
        this.echo.leave(this.channel.name.replace(/^presence-/, ''));
    }
}

export async function setupCollaboration(desk) {
    const config = desk.config.collaboration;
    const user = config.user || { id: String(Math.random()), name: 'Guest', color: '#6366f1' };
    const key = String(desk.config.documentKey).replace(/[^A-Za-z0-9_-]/g, '-');
    const doc = new Y.Doc();
    const provider = new EchoYjsProvider(makeEcho(config), `${config.channelPrefix}.${key}`, doc, user);

    return {
        doc,
        provider,
        user,
        ready: provider.connect(),
        extensions: [
            Collaboration.configure({ document: doc }),
            CollaborationCaret.configure({ provider, user }),
        ],
    };
}
