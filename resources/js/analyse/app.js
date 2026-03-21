import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Laravel Echo with Reverb
window.Pusher = Pusher
window.Echo = new Echo({
    broadcaster:       'reverb',
    key:               import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:            import.meta.env.VITE_REVERB_HOST,
    wsPort:            import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort:           import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS:          (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
})

createInertiaApp({
    resolve: (name) => resolvePageComponent(
        `./pages/${name}.vue`,
        import.meta.glob('./pages/**/*.vue'),
    ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
    progress: { color: '#A40057' },
})
