<div class="p-5" style="min-width:360px">
    <h3 class="text-base font-semibold text-gray-900 mb-1">Filter People</h3>
    <p class="text-sm text-gray-500 mb-4">
        Filtering {{ count($ids) }} person(s).
        Optionally add a filter rule to automatically filter similar ones in the future.
    </p>

    <form method="POST" action="{{ route('filtering.apply-rule') }}" id="pfm-form">
        @csrf
        @foreach($ids as $id)
            <input type="hidden" name="ids[]" value="{{ $id }}">
        @endforeach
        <input type="hidden" name="rule_type" id="pfm-rule-type" value="none">
        <input type="hidden" name="rule_value" id="pfm-rule-value" value="">

        {{-- Rule type tabs --}}
        <div class="flex flex-wrap gap-1.5 mb-4">
            @foreach($tabs as $type => $label)
                <button type="button"
                        onclick="pfmSetType('{{ $type }}')"
                        id="pfm-tab-{{ $type }}"
                        class="px-3 py-1 rounded-full text-xs font-medium border transition
                               {{ $type === 'none' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-300 hover:border-gray-500' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- None --}}
        <div id="pfm-sec-none" class="text-sm text-gray-400 italic mb-4">
            No filter rule will be added.
        </div>

        {{-- Domain --}}
        <div id="pfm-sec-domain" class="hidden mb-4 space-y-3">
            @if($domains->isNotEmpty())
                <div>
                    <p class="text-xs text-gray-500 font-medium mb-1.5">Suggested domains:</p>
                    <div class="flex flex-wrap gap-1.5" id="pfm-domain-chips">
                        @foreach($domains as $d)
                            <button type="button"
                                    onclick="pfmPickChip('domain', '{{ $d }}')"
                                    class="pfm-chip px-2.5 py-1 rounded-full text-xs border border-gray-300 bg-gray-50 hover:bg-gray-100 text-gray-700 transition">
                                {{ $d }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
            <div>
                <label class="text-xs text-gray-500 font-medium block mb-1">Or enter domain manually:</label>
                <input type="text" id="pfm-domain-input" placeholder="example.com"
                       oninput="pfmSetValue('domain', this.value)"
                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-brand-400">
            </div>
        </div>

        {{-- Email --}}
        <div id="pfm-sec-email" class="hidden mb-4 space-y-3">
            @if($emails->isNotEmpty())
                <div>
                    <p class="text-xs text-gray-500 font-medium mb-1.5">Suggested emails:</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($emails as $e)
                            <button type="button"
                                    onclick="pfmPickChip('email', '{{ $e }}')"
                                    class="pfm-chip px-2.5 py-1 rounded-full text-xs border border-gray-300 bg-gray-50 hover:bg-gray-100 text-gray-700 transition">
                                {{ $e }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
            <div>
                <label class="text-xs text-gray-500 font-medium block mb-1">Or enter email manually:</label>
                <input type="text" id="pfm-email-input" placeholder="user@example.com"
                       oninput="pfmSetValue('email', this.value)"
                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-brand-400">
            </div>
        </div>

        {{-- Contact --}}
        <div id="pfm-sec-contact" class="hidden mb-4 space-y-2">
            <p class="text-xs text-gray-500 font-medium mb-1.5">Add person to filter list:</p>
            @foreach($contacts as $personId => $name)
                <label class="flex items-center gap-2 text-sm cursor-pointer hover:text-gray-900">
                    <input type="radio" name="pfm-contact-radio" value="{{ $personId }}"
                           onchange="pfmSetValue('contact', '{{ $personId }}')"
                           class="accent-brand-600">
                    {{ $name }}
                </label>
            @endforeach
        </div>

        {{-- Preview --}}
        <div id="pfm-rule-preview" class="hidden mb-4 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
            Rule: <span id="pfm-rule-preview-text"></span>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
            <button type="button" onclick="closeActivityModal()"
                    class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
            <button type="submit"
                    class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition">
                Apply Rule
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    window.pfmSetType = function(type) {
        document.querySelectorAll('[id^="pfm-tab-"]').forEach(function(btn) {
            var t = btn.id.replace('pfm-tab-', '');
            if (t === type) {
                btn.className = btn.className.replace('bg-white text-gray-600 border-gray-300 hover:border-gray-500', 'bg-gray-800 text-white border-gray-800');
            } else {
                btn.className = btn.className.replace('bg-gray-800 text-white border-gray-800', 'bg-white text-gray-600 border-gray-300 hover:border-gray-500');
            }
        });
        ['none','domain','email','contact'].forEach(function(t) {
            var sec = document.getElementById('pfm-sec-' + t);
            if (sec) sec.classList.toggle('hidden', t !== type);
        });
        document.getElementById('pfm-rule-type').value = type;
        document.getElementById('pfm-rule-value').value = '';
        pfmUpdatePreview();
    };

    window.pfmSetValue = function(type, val) {
        document.querySelectorAll('.pfm-chip').forEach(function(c) {
            c.classList.remove('bg-brand-100', 'border-brand-400', 'text-brand-700');
            c.classList.add('bg-gray-50', 'border-gray-300', 'text-gray-700');
        });
        document.getElementById('pfm-rule-type').value = type;
        document.getElementById('pfm-rule-value').value = val;
        pfmUpdatePreview();
    };

    window.pfmPickChip = function(type, val) {
        pfmSetType(type);
        var inp = document.getElementById('pfm-' + type + '-input');
        if (inp) inp.value = val;
        document.getElementById('pfm-rule-type').value = type;
        document.getElementById('pfm-rule-value').value = val;
        document.querySelectorAll('.pfm-chip').forEach(function(c) {
            c.classList.remove('bg-brand-100', 'border-brand-400', 'text-brand-700');
            c.classList.add('bg-gray-50', 'border-gray-300', 'text-gray-700');
        });
        document.querySelectorAll('.pfm-chip').forEach(function(c) {
            var oc = c.getAttribute('onclick') || '';
            if (oc.indexOf(JSON.stringify(val)) !== -1 || oc.indexOf("'" + val + "'") !== -1) {
                c.classList.add('bg-brand-100', 'border-brand-400', 'text-brand-700');
                c.classList.remove('bg-gray-50', 'border-gray-300', 'text-gray-700');
            }
        });
        pfmUpdatePreview();
    };

    function pfmUpdatePreview() {
        var type = document.getElementById('pfm-rule-type').value;
        var val  = document.getElementById('pfm-rule-value').value.trim();
        var preview = document.getElementById('pfm-rule-preview');
        var text    = document.getElementById('pfm-rule-preview-text');
        if (type !== 'none' && val !== '') {
            preview.classList.remove('hidden');
            text.textContent = type + ': ' + val;
        } else {
            preview.classList.add('hidden');
        }
    }
})();
</script>
