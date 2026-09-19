import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
        return pages[`./Pages/${name}.vue`]
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
        
        app.use(plugin)
        app.use(ZiggyVue)

        // Provide CSRF token globally (accessible via this.$csrfToken)
        app.mixin({
            computed: {
                csrfToken() {
                    return this.$page.props.csrf_token || ''
                }
            }
        })

        app.mount(el)
    },
})
