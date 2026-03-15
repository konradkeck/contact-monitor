{{--
    Two-mode panel for linking an identity to a person.
    Parameters:
      $linkUrl — route('data-relations.identities.link-create', $identity|$contact)
    Must be rendered inside a parent Alpine scope that has `linkOpen` (bool).
--}}
<div x-data="{
    mode: 'existing',
    q: '',
    results: [],
    picked: null,
    fn: '',
    ln: '',
    isOurOrg: false,
    err: '',
    busy: false,
    timer: null,
    setMode(m) { this.mode = m; this.err = ''; },
    async doSearch(v) {
        this.picked = null;
        clearTimeout(this.timer);
        if (v.length < 2) { this.results = []; return; }
        this.timer = setTimeout(async () => {
            const r = await fetch({{ Js::from(route('people.search')) }} + '?q=' + encodeURIComponent(v),
                                  { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            this.results = await r.json();
        }, 220);
    },
    pick(id, name) { this.picked = { id, name }; this.q = name; this.results = []; },
    async submit() {
        this.err = '';
        let body = {};
        if (this.mode === 'new') {
            if (!this.fn.trim()) { this.err = 'Enter first name.'; return; }
            body = { mode: 'new', first_name: this.fn.trim(), last_name: this.ln.trim(), is_our_org: this.isOurOrg };
        } else {
            if (!this.picked) { this.err = 'Select a person.'; return; }
            body = { mode: 'existing', person_id: this.picked.id };
        }
        this.busy = true;
        try {
            const res = await fetch({{ Js::from($linkUrl) }}, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (data.ok) { window.location.reload(); }
            else { this.err = data.error || 'Error.'; this.busy = false; }
        } catch { this.err = 'Network error.'; this.busy = false; }
    }
}">
    {{-- Mode selector --}}
    <div class="grid grid-cols-2 gap-2 mb-4">
        <button type="button" @click="setMode('existing')"
                :class="mode === 'existing' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-500 hover:border-gray-300'"
                class="flex flex-col items-center gap-1.5 p-3 rounded-lg border-2 transition cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
            </svg>
            <span class="text-xs font-medium">Assign Existing</span>
        </button>
        <button type="button" @click="setMode('new')"
                :class="mode === 'new' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-500 hover:border-gray-300'"
                class="flex flex-col items-center gap-1.5 p-3 rounded-lg border-2 transition cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/>
                <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
            </svg>
            <span class="text-xs font-medium">Create New</span>
        </button>
    </div>

    {{-- Assign existing --}}
    <div x-show="mode === 'existing'">
        <label class="label mb-1.5">Search person</label>
        <div class="relative">
            <input type="text" x-model="q" @input="doSearch($event.target.value)"
                   placeholder="Type name…" autocomplete="off" class="input text-sm">
            <ul x-show="results.length" x-cloak
                class="absolute z-10 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-0.5 max-h-48 overflow-y-auto text-sm">
                <template x-for="p in results" :key="p.id">
                    <li>
                        <button type="button" @click="pick(p.id, p.name)"
                                class="w-full text-left px-3 py-2 hover:bg-gray-50 transition" x-text="p.name"></button>
                    </li>
                </template>
            </ul>
        </div>
        <p x-show="picked" x-cloak class="text-xs text-brand-700 mt-1.5">✓ <span x-text="picked?.name"></span></p>
    </div>

    {{-- Create new --}}
    <div x-show="mode === 'new'">
        <div class="flex gap-2 mb-3">
            <div class="flex-1">
                <label class="label mb-1">First name</label>
                <input type="text" x-model="fn" placeholder="Jane" class="input text-sm">
            </div>
            <div class="flex-1">
                <label class="label mb-1">Last name</label>
                <input type="text" x-model="ln" placeholder="Doe" class="input text-sm">
            </div>
        </div>
        <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" x-model="isOurOrg" class="rounded border-gray-300 text-brand-600 focus:ring-brand-400">
            <span class="text-xs text-gray-600">Our Organization member</span>
        </label>
    </div>

    <p x-show="err" x-cloak x-text="err" class="text-xs text-red-500 mt-2"></p>

    <div class="flex items-center gap-2 mt-4">
        <button type="button" @click="submit()" :disabled="busy"
                class="btn btn-primary btn-sm"
                x-text="mode === 'new' ? 'Create & Link' : 'Link'"></button>
        <button type="button" @click="$dispatch('close-link-popup')"
                class="btn btn-muted btn-sm">Cancel</button>
        <span x-show="busy" x-cloak class="text-xs text-gray-400">Saving…</span>
    </div>
</div>
