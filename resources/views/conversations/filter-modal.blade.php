<div class="p-5" style="min-width:360px">
    <h3 class="text-base font-semibold text-gray-900 mb-1">Filter</h3>
    <p class="text-sm text-gray-500 mb-4">
        Filtering {{ count($ids) }} conversation(s).
        Optionally add a filter rule to automatically filter similar ones in the future.
    </p>

    <form method="POST" action="{{ route('conversations.archive-with-rule') }}" id="filter-archive-form">
        @csrf
        @foreach($ids as $id)
            <input type="hidden" name="ids[]" value="{{ $id }}">
        @endforeach
        <input type="hidden" name="rule_type" id="fm-rule-type" value="none">
        <input type="hidden" name="rule_value" id="fm-rule-value" value="">

        {{-- Rule type tabs --}}
        <div class="flex flex-wrap gap-1.5 mb-4">
            @foreach($tabs as $type => $label)
                <button type="button"
                        onclick="fmSetType('{{ $type }}')"
                        id="fm-tab-{{ $type }}"
                        class="px-3 py-1 rounded-full text-xs font-medium border transition
                               {{ $type === 'none' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-300 hover:border-gray-500' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- None --}}
        <div id="fm-sec-none" class="text-sm text-gray-400 italic mb-4">
            Conversations will be filtered with no additional rule.
        </div>

        {{-- Domain --}}
        <div id="fm-sec-domain" class="hidden mb-4 space-y-3">
            @if($domains->isNotEmpty())
                <div class="flex flex-wrap gap-1.5" id="fm-domain-chips">
                    @foreach($domains as $d)
                        <button type="button"
                                onclick="fmPickChip('domain', {{ json_encode($d) }})"
                                class="fm-chip px-2.5 py-1 rounded-full text-xs border border-gray-300 bg-gray-50 hover:bg-gray-100 text-gray-700 transition">
                            {{ $d }}
                        </button>
                    @endforeach
                </div>
            @endif
            <input type="text" id="fm-domain-input" placeholder="example.com"
                   oninput="fmInputChanged('domain', this.value)"
                   class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-brand-400">
        </div>

        {{-- Email --}}
        <div id="fm-sec-email" class="hidden mb-4 space-y-3">
            @if($emails->isNotEmpty())
                <div class="flex flex-wrap gap-1.5">
                    @foreach($emails as $e)
                        <button type="button"
                                onclick="fmPickChip('email', {{ json_encode($e) }})"
                                class="fm-chip px-2.5 py-1 rounded-full text-xs border border-gray-300 bg-gray-50 hover:bg-gray-100 text-gray-700 transition">
                            {{ $e }}
                        </button>
                    @endforeach
                </div>
            @endif
            <input type="text" id="fm-email-input" placeholder="user@example.com"
                   oninput="fmInputChanged('email', this.value)"
                   class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-brand-400">
        </div>

        {{-- Contact --}}
        <div id="fm-sec-contact" class="hidden mb-4 space-y-2">
            <p class="text-xs text-gray-500 font-medium mb-1.5">Select contact to filter:</p>
            @foreach($contacts as $personId => $name)
                <label class="flex items-center gap-2 text-sm cursor-pointer hover:text-gray-900">
                    <input type="radio" name="fm-contact-radio" value="{{ $personId }}"
                           onchange="fmInputChanged('contact', '{{ $personId }}')"
                           class="accent-brand-600">
                    {{ $name }}
                </label>
            @endforeach
        </div>

        {{-- Subject --}}
        <div id="fm-sec-subject" class="hidden mb-4 space-y-3">
            @if($subjects->isNotEmpty())
                <div class="flex flex-wrap gap-1.5">
                    @foreach($subjects as $s)
                        <button type="button"
                                onclick="fmPickChip('subject', {{ json_encode($s) }})"
                                class="fm-chip px-2.5 py-1 rounded-full text-xs border border-gray-300 bg-gray-50 hover:bg-gray-100 text-gray-700 transition max-w-[220px] truncate"
                                title="{{ $s }}">
                            {{ $s }}
                        </button>
                    @endforeach
                </div>
            @endif
            <input type="text" id="fm-subject-input" placeholder="Subject text…"
                   oninput="fmInputChanged('subject', this.value)"
                   class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-brand-400">
        </div>

        {{-- Preview of rule --}}
        <div id="fm-rule-preview" class="hidden mb-4 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
            Rule: <span id="fm-rule-preview-text"></span>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
            <button type="button" onclick="closeActivityModal()"
                    class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
            <button type="submit"
                    class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition">
                Filter
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    var currentType = 'none';

    window.fmSetType = function(type) {
        currentType = type;
        document.querySelectorAll('[id^="fm-tab-"]').forEach(function(btn) {
            var t = btn.id.replace('fm-tab-', '');
            if (t === type) {
                btn.className = btn.className.replace('bg-white text-gray-600 border-gray-300 hover:border-gray-500', 'bg-gray-800 text-white border-gray-800');
            } else {
                btn.className = btn.className.replace('bg-gray-800 text-white border-gray-800', 'bg-white text-gray-600 border-gray-300 hover:border-gray-500');
            }
        });
        ['none','domain','email','contact','subject'].forEach(function(t) {
            var sec = document.getElementById('fm-sec-' + t);
            if (sec) sec.classList.toggle('hidden', t !== type);
        });
        document.getElementById('fm-rule-type').value = type;
        document.getElementById('fm-rule-value').value = '';
        updatePreview();
    };

    // Called when user types in an input field
    window.fmInputChanged = function(type, val) {
        // Deselect all chips when typing manually
        document.querySelectorAll('.fm-chip').forEach(function(c) {
            c.classList.remove('bg-brand-100', 'border-brand-400', 'text-brand-700');
            c.classList.add('bg-gray-50', 'border-gray-300', 'text-gray-700');
        });
        document.getElementById('fm-rule-type').value = type;
        document.getElementById('fm-rule-value').value = val;
        updatePreview();
    };

    // Called when user clicks a chip — populates the input field
    window.fmPickChip = function(type, val) {
        var inp = document.getElementById('fm-' + type + '-input');
        if (inp) {
            inp.value = val;
            inp.focus();
        }
        document.getElementById('fm-rule-type').value = type;
        document.getElementById('fm-rule-value').value = val;
        // Highlight selected chip
        document.querySelectorAll('.fm-chip').forEach(function(c) {
            c.classList.remove('bg-brand-100', 'border-brand-400', 'text-brand-700');
            c.classList.add('bg-gray-50', 'border-gray-300', 'text-gray-700');
        });
        document.querySelectorAll('.fm-chip').forEach(function(c) {
            var oc = c.getAttribute('onclick') || '';
            if (oc.indexOf(JSON.stringify(val)) !== -1) {
                c.classList.add('bg-brand-100', 'border-brand-400', 'text-brand-700');
                c.classList.remove('bg-gray-50', 'border-gray-300', 'text-gray-700');
            }
        });
        updatePreview();
    };

    function updatePreview() {
        var type = document.getElementById('fm-rule-type').value;
        var val  = document.getElementById('fm-rule-value').value.trim();
        var preview = document.getElementById('fm-rule-preview');
        var text    = document.getElementById('fm-rule-preview-text');
        if (type !== 'none' && val !== '') {
            preview.classList.remove('hidden');
            text.textContent = type + ': ' + val;
        } else {
            preview.classList.add('hidden');
        }
    }
})();
</script>
