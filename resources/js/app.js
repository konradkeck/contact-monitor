import './bootstrap'

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from 'ziggy-js'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// ── Easepick date range picker ──
import { easepick, RangePlugin, PresetPlugin } from '@easepick/bundle'
window._EP = { easepick, RangePlugin, PresetPlugin }

// ── Date range picker helper (uses easepick) ──
window.drp = (function () {
    const CDN = 'https://cdn.jsdelivr.net/npm/@easepick/bundle@1.2.1/dist/index.css'

    const COMPACT = [
        ':host{--day-width:30px;--day-height:26px}',
        '.container{font-size:11px !important}',
        '.calendar{padding:5px !important}',
        '.calendar>.header{padding:3px 4px !important}',
        '.month-name{font-size:11px !important}',
        '.previous-button,.next-button{padding:1px 5px !important;font-size:13px !important}',
        '.dayname{font-size:10px !important;padding:2px 0 !important}',
        '.day{font-size:11px !important;padding:2px 0 !important}',
        '.preset-plugin-container{padding:6px !important;width:110px !important;flex-direction:column !important;justify-content:flex-start !important;gap:2px !important}',
        '.preset-plugin-container>button{padding:9px 10px !important;font-size:11px !important;margin:0 !important;display:block;width:100% !important;text-align:left !important;white-space:nowrap !important}',
    ].join('')

    function ld(d) {
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0')
    }
    function parseYmd(s) {
        const p = s.split('-'); return new Date(+p[0], +p[1]-1, +p[2])
    }

    function init(inputId, onApply, opts) {
        opts = opts || {}
        const EP = window._EP
        const input = document.getElementById(inputId)
        const wrap  = document.getElementById(inputId + '-wrap')
        const clearBtn = wrap ? wrap.querySelector('.drp-clear') : null

        const n = new Date()
        const presets = opts.presets || {
            'Today':          [new Date(n.getFullYear(), n.getMonth(), n.getDate()), n],
            'Last 7 days':    [new Date(+n - 6*86400000),   n],
            'Last 30 days':   [new Date(+n - 29*86400000),  n],
            'Last 90 days':   [new Date(+n - 89*86400000),  n],
            'Last 365 days':  [new Date(+n - 364*86400000), n],
            'This year':      [new Date(n.getFullYear(), 0, 1), n],
        }

        let startDate, endDate, fireInitial = false
        if (opts.defaultDays) {
            startDate = new Date(+n - (opts.defaultDays - 1) * 86400000)
            endDate   = n
            fireInitial = true
        } else if (opts.defaultFrom && opts.defaultTo) {
            startDate = parseYmd(opts.defaultFrom)
            endDate   = parseYmd(opts.defaultTo)
        }

        const cfg = {
            element: input,
            css: function () {
                const sr = this.ui.shadowRoot, wrapper = this.ui.wrapper
                const link = document.createElement('link')
                link.href = CDN; link.rel = 'stylesheet'
                const done = function () { wrapper.style.display = '' }
                link.addEventListener('load', done)
                link.addEventListener('error', done)
                sr.append(link)
                const style = document.createElement('style')
                style.textContent = COMPACT
                sr.append(style)
            },
            plugins: [EP.RangePlugin, EP.PresetPlugin],
            format: 'D MMM YYYY',
            zIndex: 9999,
            calendars: 1,
            RangePlugin: { tooltip: true },
            PresetPlugin: { position: 'left', customPreset: presets },
            setup: function (picker) {
                picker.on('show', function () {
                    const inp = document.getElementById(inputId)
                    const wrapper = picker.ui.wrapper
                    const container = picker.ui.container
                    const iRect = inp.getBoundingClientRect()
                    const cRect = container.getBoundingClientRect()
                    if (iRect.left + cRect.width > window.innerWidth - 8) {
                        const wRect = wrapper.getBoundingClientRect()
                        container.style.left = Math.round(iRect.right - wRect.left - cRect.width) + 'px'
                    }
                })
                picker.on('select', function (e) {
                    const s = e.detail.start, en = e.detail.end
                    if (s && en) {
                        if (clearBtn) clearBtn.classList.remove('hidden')
                        onApply(ld(new Date(s)), ld(new Date(en)))
                    }
                })
            }
        }
        if (startDate) {
            cfg.RangePlugin.startDate = startDate
            cfg.RangePlugin.endDate   = endDate
        }

        const picker = new EP.easepick.create(cfg)

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                picker.clear()
                clearBtn.classList.add('hidden')
                onApply('', '')
            })
        }

        if (fireInitial) { onApply(ld(startDate), ld(endDate)) }

        return picker
    }

    return { init }
}())

// ── Laravel Echo with Reverb ──
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

// ── Vue + Inertia ──
createInertiaApp({
    resolve: (name) => resolvePageComponent(
        `./pages/${name}.vue`,
        import.meta.glob('./pages/**/*.vue'),
    ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el)
    },
    progress: { color: '#A40057' },
})
