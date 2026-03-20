<div class="p-5" style="min-width:540px;max-width:780px">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-base font-semibold text-gray-900">Merge People</h3>
        <button type="button" onclick="closeActivityModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
    </div>

    <p class="text-sm text-gray-500 mb-4">
        Select which person to keep as the <strong>primary</strong>. All conversations and activities from the others
        will appear under the primary. The others will be hidden from the people list. You can undo this at any time.
    </p>

    <div id="merge-people-list" class="space-y-2 mb-5">
        @foreach($people as $person)
        <div class="merge-card border-2 rounded-xl p-4 cursor-pointer transition select-none"
             data-id="{{ $person->id }}"
             style="border-color:#e5e7eb">
            <div class="flex items-start gap-3">
                <div class="mt-0.5">
                    <input type="radio" name="merge_primary" value="{{ $person->id }}"
                           class="w-4 h-4 merge-radio" style="accent-color:#A40057">
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-gray-900">{{ $person->full_name ?: '(unnamed)' }}</span>
                        @if($person->is_our_org)
                            <span class="px-1.5 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700">Our Org</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1 text-xs text-gray-500">
                        @foreach($person->identities->take(4) as $identity)
                            <span class="font-mono">{{ $identity->value }}</span>
                        @endforeach
                        @if($person->identities->count() > 4)
                            <span class="text-gray-400">+{{ $person->identities->count() - 4 }} more</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-3 mt-2 text-xs text-gray-500">
                        <span>
                            <span class="font-semibold text-gray-700">{{ $person->identities->count() }}</span> identit{{ $person->identities->count() === 1 ? 'y' : 'ies' }}
                        </span>
                        <span>
                            <span class="font-semibold text-gray-700">{{ $person->companies->count() }}</span> compan{{ $person->companies->count() === 1 ? 'y' : 'ies' }}
                        </span>
                    </div>
                    @if($person->companies->isNotEmpty())
                    <div class="flex flex-wrap gap-1 mt-1.5">
                        @foreach($person->companies as $co)
                            <span class="px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-600">{{ $co->name }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div id="merge-people-warning" class="hidden mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
        <strong>Warning:</strong> The non-primary people will be hidden from the people list.
        Their conversations and activities will be grouped under the primary.
        You can unmerge them individually from the primary person's detail page.
    </div>

    <div class="flex items-center justify-between">
        <span id="merge-people-hint" class="text-xs text-gray-400">Select a primary person above</span>
        <div class="flex gap-2">
            <button type="button" onclick="closeActivityModal()" class="btn btn-secondary btn-sm">Cancel</button>
            <button type="button" id="merge-people-btn" onclick="doMergePeople()"
                    class="btn btn-primary btn-sm" disabled style="opacity:.45">
                Merge
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    var mergeUrl = '{{ route('people.merge') }}';
    var csrfToken = '{{ csrf_token() }}';

    function updateMergeCards() {
        var checked = document.querySelector('.merge-radio:checked');
        var btn = document.getElementById('merge-people-btn');
        var hint = document.getElementById('merge-people-hint');
        var warning = document.getElementById('merge-people-warning');

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
            hint.textContent = 'Select a primary person above';
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

    window.doMergePeople = function() {
        var primary = document.querySelector('.merge-radio:checked');
        if (!primary) return;
        var primaryId = primary.value;
        var mergeIds = [];
        document.querySelectorAll('.merge-radio').forEach(function(r) {
            if (r.value !== primaryId) mergeIds.push(r.value);
        });

        var btn = document.getElementById('merge-people-btn');
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
