<div class="p-5" style="min-width:580px;max-width:820px">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-base font-semibold text-gray-900">Merge Companies</h3>
        <button type="button" onclick="closeActivityModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
    </div>

    <p class="text-sm text-gray-500 mb-4">
        Select which company to keep as the <strong>primary</strong>. All conversations, activities and accounts from the others
        will appear under the primary. The others will be hidden from lists. You can undo this at any time.
    </p>

    <div id="merge-companies-list" class="space-y-2 mb-5">
        @foreach($companies as $co)
        @php
            $domain = $co->domains->firstWhere('is_primary', true) ?? $co->domains->first();
        @endphp
        <div class="merge-card border-2 rounded-xl p-4 cursor-pointer transition select-none"
             data-id="{{ $co->id }}"
             style="border-color:#e5e7eb">
            <div class="flex items-start gap-3">
                <div class="mt-0.5">
                    <input type="radio" name="merge_primary" value="{{ $co->id }}"
                           class="w-4 h-4 text-brand-600 merge-radio" style="accent-color:#A40057">
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-gray-900">{{ $co->name }}</span>
                        @if($domain)
                            <span class="text-xs text-gray-400 font-mono">{{ $domain->domain }}</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-3 mt-2 text-xs text-gray-500">
                        <span title="Contacts">
                            <span class="font-semibold text-gray-700">{{ $co->people->count() }}</span> contact{{ $co->people->count() === 1 ? '' : 's' }}
                        </span>
                        <span title="Conversations">
                            <span class="font-semibold text-gray-700">{{ $co->conversations->count() }}</span> conversation{{ $co->conversations->count() === 1 ? '' : 's' }}
                        </span>
                        <span title="External accounts">
                            <span class="font-semibold text-gray-700">{{ $co->accounts->count() }}</span> account{{ $co->accounts->count() === 1 ? '' : 's' }}
                        </span>
                        @if($co->domains->count() > 1)
                        <span title="Domains">
                            <span class="font-semibold text-gray-700">{{ $co->domains->count() }}</span> domain{{ $co->domains->count() === 1 ? '' : 's' }}
                        </span>
                        @endif
                    </div>
                    @if($co->accounts->isNotEmpty())
                    <div class="flex flex-wrap gap-1 mt-1.5">
                        @foreach($co->accounts->groupBy('system_type') as $type => $accs)
                            <span class="px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-600">{{ $type }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div id="merge-companies-warning" class="hidden mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
        <strong>Warning:</strong> The non-primary companies will be hidden from the companies list.
        Their data (accounts, conversations, activities) will be grouped under the primary.
        You can unmerge them individually from the primary company's detail page.
    </div>

    <div class="flex items-center justify-between">
        <span id="merge-companies-hint" class="text-xs text-gray-400">Select a primary company above</span>
        <div class="flex gap-2">
            <button type="button" onclick="closeActivityModal()" class="btn btn-secondary btn-sm">Cancel</button>
            <button type="button" id="merge-companies-btn" onclick="doMergeCompanies()"
                    class="btn btn-primary btn-sm" disabled style="opacity:.45">
                Merge
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    var mergeUrl = '{{ route('companies.merge') }}';
    var csrfToken = '{{ csrf_token() }}';

    function updateMergeCards() {
        var checked = document.querySelector('.merge-radio:checked');
        var btn = document.getElementById('merge-companies-btn');
        var hint = document.getElementById('merge-companies-hint');
        var warning = document.getElementById('merge-companies-warning');

        document.querySelectorAll('.merge-card').forEach(function(card) {
            if (checked && card.dataset.id === checked.value) {
                card.style.borderColor = '#A40057';
                card.style.background = '#fff7fb';
            } else {
                card.style.borderColor = '#e5e7eb';
                card.style.background = '';
            }
        });

        if (checked) {
            btn.disabled = false;
            btn.style.opacity = '1';
            hint.textContent = 'Primary: ' + checked.closest('.merge-card').querySelector('.font-semibold').textContent.trim();
            warning.classList.remove('hidden');
        } else {
            btn.disabled = true;
            btn.style.opacity = '.45';
            hint.textContent = 'Select a primary company above';
            warning.classList.add('hidden');
        }
    }

    document.querySelectorAll('.merge-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var radio = card.querySelector('.merge-radio');
            radio.checked = true;
            updateMergeCards();
        });
    });

    document.querySelectorAll('.merge-radio').forEach(function(radio) {
        radio.addEventListener('change', updateMergeCards);
    });

    window.doMergeCompanies = function() {
        var primary = document.querySelector('.merge-radio:checked');
        if (!primary) return;
        var primaryId = primary.value;
        var mergeIds = [];
        document.querySelectorAll('.merge-radio').forEach(function(r) {
            if (r.value !== primaryId) mergeIds.push(r.value);
        });

        var btn = document.getElementById('merge-companies-btn');
        btn.disabled = true;
        btn.textContent = 'Merging…';

        var body = new URLSearchParams();
        body.append('_token', csrfToken);
        body.append('primary_id', primaryId);
        mergeIds.forEach(function(id) { body.append('merge_ids[]', id); });

        fetch(mergeUrl, { method: 'POST', body: body })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) window.location.href = data.redirect;
                else { btn.disabled = false; btn.textContent = 'Merge'; alert('Error: ' + JSON.stringify(data)); }
            })
            .catch(function(e) { btn.disabled = false; btn.textContent = 'Merge'; alert('Request failed.'); });
    };
})();
</script>
