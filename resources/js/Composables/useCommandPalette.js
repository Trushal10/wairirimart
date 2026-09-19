import { reactive } from 'vue';

const STORAGE_KEY = 'cmdk:recent';

function loadRecent() {
    if (typeof localStorage === 'undefined') return [];
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch (e) {
        return [];
    }
}

const state = reactive({
    open: false,
    recent: loadRecent(),
});

function open()   { state.open = true; }
function close()  { state.open = false; }
function toggle() { state.open = !state.open; }

function trackVisit(item) {
    if (!item || !item.id) return;
    const dedup = state.recent.filter(r => r.id !== item.id);
    dedup.unshift({
        id: item.id,
        title: item.title,
        subtitle: item.subtitle,
        href: item.href,
        icon: item.icon,
        group: item.group,
    });
    state.recent = dedup.slice(0, 6);
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state.recent)); } catch (e) { /* quota */ }
}

function clearRecent() {
    state.recent = [];
    try { localStorage.removeItem(STORAGE_KEY); } catch (e) { /* ignore */ }
}

export function useCommandPalette() {
    return {
        state,
        open,
        close,
        toggle,
        trackVisit,
        clearRecent,
    };
}
