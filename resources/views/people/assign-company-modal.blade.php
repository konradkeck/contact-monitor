<div class="p-5" style="min-width:380px">
    <h3 class="text-base font-semibold text-gray-900 mb-4">Assign to Company</h3>

    @foreach($ids as $id)
        <input type="hidden" class="acm-person-id" value="{{ $id }}">
    @endforeach

    {{-- Mode selector --}}
    <div class="grid grid-cols-2 gap-2 mb-5">
        <button type="button" onclick="acmSetMode('existing')" id="acm-btn-existing"
                class="flex flex-col items-center gap-1.5 p-3 rounded-lg border-2 border-brand-500 bg-brand-50 transition text-brand-700 cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H3m7-4h4"/>
            </svg>
            <span class="text-xs font-medium">Assign to Existing</span>
        </button>
        <button type="button" onclick="acmSetMode('new')" id="acm-btn-new"
                class="flex flex-col items-center gap-1.5 p-3 rounded-lg border-2 border-gray-200 hover:border-gray-300 transition text-gray-500 cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="text-xs font-medium">Create New Company</span>
        </button>
    </div>

    {{-- Assign existing --}}
    <div id="acm-sec-existing">
        <label class="text-xs font-medium text-gray-500 block mb-1.5">Search company</label>
        <input type="text" id="acm-company-search" placeholder="Type company name…" autocomplete="off"
               oninput="acmSearch(this.value)"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-brand-400 mb-1">
        <div id="acm-search-results" class="border border-gray-200 rounded-lg overflow-hidden hidden max-h-48 overflow-y-auto"></div>
        <input type="hidden" id="acm-company-id" value="">
        <p id="acm-chosen" class="text-xs text-brand-700 mt-1 hidden"></p>
    </div>

    {{-- Create new --}}
    <div id="acm-sec-new" class="hidden">
        <label class="text-xs font-medium text-gray-500 block mb-1.5">Company name</label>
        <input type="text" id="acm-new-name" placeholder="e.g. Acme Corp"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-brand-400">
    </div>

    <p id="acm-error" class="text-xs text-red-500 mt-2 hidden"></p>

    <div class="flex items-center gap-2 mt-5">
        <button type="button" onclick="acmSubmit()" id="acm-submit"
                class="btn btn-primary btn-sm">Assign</button>
        <button type="button" onclick="closeActivityModal()"
                class="btn btn-muted btn-sm">Cancel</button>
        <span id="acm-spinner" class="hidden text-xs text-gray-400">Saving…</span>
    </div>
</div>

<script>
(function () {
    let acmMode = 'existing';
    let acmSearchTimeout = null;

    window.acmSetMode = function (mode) {
        acmMode = mode;
        const isNew = mode === 'new';
        document.getElementById('acm-sec-existing').classList.toggle('hidden', isNew);
        document.getElementById('acm-sec-new').classList.toggle('hidden', !isNew);

        const btnNew = document.getElementById('acm-btn-new');
        const btnEx  = document.getElementById('acm-btn-existing');
        [btnNew, btnEx].forEach(b => {
            b.classList.remove('border-brand-500', 'bg-brand-50', 'text-brand-700',
                               'border-gray-200', 'hover:border-gray-300', 'text-gray-500');
            b.classList.add('border-gray-200', 'hover:border-gray-300', 'text-gray-500');
        });
        const active = isNew ? btnNew : btnEx;
        active.classList.remove('border-gray-200', 'hover:border-gray-300', 'text-gray-500');
        active.classList.add('border-brand-500', 'bg-brand-50', 'text-brand-700');

        document.getElementById('acm-submit').textContent = isNew ? 'Create & Assign' : 'Assign';
    };

    window.acmSearch = function (q) {
        clearTimeout(acmSearchTimeout);
        const results = document.getElementById('acm-search-results');
        if (q.length < 2) { results.classList.add('hidden'); return; }
        acmSearchTimeout = setTimeout(async () => {
            const res  = await fetch('{{ route('companies.search') }}?q=' + encodeURIComponent(q),
                                     { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!data.length) {
                results.innerHTML = '<div class="px-3 py-2 text-xs text-gray-400">No results</div>';
            } else {
                results.innerHTML = data.map(c =>
                    `<button type="button" onclick="acmPickCompany(${c.id}, ${JSON.stringify(c.name)})"
                             class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 transition">${c.name}</button>`
                ).join('');
            }
            results.classList.remove('hidden');
        }, 220);
    };

    window.acmPickCompany = function (id, name) {
        document.getElementById('acm-company-id').value = id;
        document.getElementById('acm-company-search').value = name;
        document.getElementById('acm-chosen').textContent = '✓ ' + name;
        document.getElementById('acm-chosen').classList.remove('hidden');
        document.getElementById('acm-search-results').classList.add('hidden');
    };

    window.acmSubmit = async function () {
        const ids = [...document.querySelectorAll('.acm-person-id')].map(i => i.value);
        const err = document.getElementById('acm-error');
        err.classList.add('hidden');

        let body;
        if (acmMode === 'new') {
            const name = document.getElementById('acm-new-name').value.trim();
            if (!name) { err.textContent = 'Enter company name.'; err.classList.remove('hidden'); return; }
            body = { mode: 'new', name, ids };
        } else {
            const companyId = document.getElementById('acm-company-id').value;
            if (!companyId) { err.textContent = 'Select a company.'; err.classList.remove('hidden'); return; }
            body = { mode: 'existing', company_id: companyId, ids };
        }

        document.getElementById('acm-submit').disabled = true;
        document.getElementById('acm-spinner').classList.remove('hidden');

        const url  = '{{ route('people.bulk-assign-company') }}';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const res  = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        const data = await res.json();

        if (data.ok) {
            closeActivityModal();
            window.location.reload();
        } else {
            err.textContent = data.error || 'Error';
            err.classList.remove('hidden');
            document.getElementById('acm-submit').disabled = false;
            document.getElementById('acm-spinner').classList.add('hidden');
        }
    };
})();
</script>
