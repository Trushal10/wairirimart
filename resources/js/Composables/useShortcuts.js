import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useCommandPalette } from './useCommandPalette';

const state = reactive({
    helpOpen: false,
});

function isEditableTarget(target) {
    if (!target) return false;
    const tag = (target.tagName || '').toLowerCase();
    if (['input', 'textarea', 'select'].includes(tag)) return true;
    if (target.isContentEditable) return true;
    return false;
}

let installed = false;
let pendingKey = null;
let pendingTimer = null;

const CHORDS = {
    'g d': () => router.visit(route('admin.dashboard')),
    'g o': () => router.visit(route('admin.orders')),
    'g p': () => router.visit(route('admin.products')),
    'g c': () => router.visit(route('admin.category')),
    'g r': () => router.visit(route('admin.returns.index')),
    'g s': () => router.visit(route('admin.setting')),
    'g u': () => router.visit(route('admin.coupons.index')),
};

const SHORTCUT_LEGEND = [
    { keys: ['⌘', 'K'], label: 'Open command palette', group: 'General' },
    { keys: ['?'],       label: 'Show this help',       group: 'General' },
    { keys: ['G', 'D'],  label: 'Go to dashboard',      group: 'Navigate' },
    { keys: ['G', 'O'],  label: 'Go to orders',         group: 'Navigate' },
    { keys: ['G', 'P'],  label: 'Go to products',       group: 'Navigate' },
    { keys: ['G', 'C'],  label: 'Go to categories',     group: 'Navigate' },
    { keys: ['G', 'R'],  label: 'Go to returns',        group: 'Navigate' },
    { keys: ['G', 'U'],  label: 'Go to coupons',        group: 'Navigate' },
    { keys: ['G', 'S'],  label: 'Go to settings',       group: 'Navigate' },
];

function handleKeydown(e) {
    if (isEditableTarget(e.target)) {
        // Only ⌘K / Ctrl+K should still fire from within inputs
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            useCommandPalette().toggle();
        }
        return;
    }

    // ⌘K / Ctrl+K → toggle palette
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        useCommandPalette().toggle();
        return;
    }

    // ? → open help
    if (e.key === '?' && !e.metaKey && !e.ctrlKey && !e.altKey) {
        e.preventDefault();
        state.helpOpen = true;
        return;
    }

    // Escape → close help
    if (e.key === 'Escape' && state.helpOpen) {
        state.helpOpen = false;
        return;
    }

    // Two-key chord: g <letter>
    if (!e.metaKey && !e.ctrlKey && !e.altKey && e.key.length === 1) {
        const key = e.key.toLowerCase();
        if (pendingKey) {
            const chord = `${pendingKey} ${key}`;
            clearTimeout(pendingTimer);
            pendingKey = null;
            pendingTimer = null;
            const fn = CHORDS[chord];
            if (fn) {
                e.preventDefault();
                fn();
            }
            return;
        }
        if (key === 'g') {
            pendingKey = 'g';
            pendingTimer = setTimeout(() => {
                pendingKey = null;
                pendingTimer = null;
            }, 900);
        }
    }
}

export function useShortcuts() {
    return {
        state,
        legend: SHORTCUT_LEGEND,
        openHelp: () => { state.helpOpen = true; },
        closeHelp: () => { state.helpOpen = false; },
        install() {
            if (installed || typeof window === 'undefined') return;
            window.addEventListener('keydown', handleKeydown);
            installed = true;
        },
        uninstall() {
            if (!installed || typeof window === 'undefined') return;
            window.removeEventListener('keydown', handleKeydown);
            installed = false;
        },
    };
}
