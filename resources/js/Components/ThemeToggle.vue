<template>
    <button
        type="button"
        @click="toggleTheme"
        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white/95 dark:hover:bg-white/[0.06] transition-colors focus-ring"
        :aria-label="isDarkMode ? 'Switch to light mode' : 'Switch to dark mode'"
        :title="isDarkMode ? 'Light mode' : 'Dark mode'"
    >
        <Moon v-if="!isDarkMode" :size="17" />
        <Sun v-else :size="17" />
    </button>
</template>

<script>
import { Moon, Sun } from '@lucide/vue';

export default {
    components: { Moon, Sun },
    data() {
        return { isDarkMode: false };
    },
    methods: {
        toggleTheme() {
            this.isDarkMode = !this.isDarkMode;
            document.documentElement.classList.toggle('dark', this.isDarkMode);
            try {
                localStorage.setItem('theme', this.isDarkMode ? 'dark' : 'light');
            } catch (_) { /* no-op if storage disabled */ }
        },
    },
    mounted() {
        let saved = null;
        try { saved = localStorage.getItem('theme'); } catch (_) { /* no-op */ }
        const prefersDark =
            saved
                ? saved === 'dark'
                : typeof window !== 'undefined' &&
                  window.matchMedia?.('(prefers-color-scheme: dark)').matches;
        this.isDarkMode = !!prefersDark;
        document.documentElement.classList.toggle('dark', this.isDarkMode);
        // Clean up any legacy body classes from the old toggle
        document.body.classList.remove('dark', 'bg-gray-900');
    },
};
</script>
