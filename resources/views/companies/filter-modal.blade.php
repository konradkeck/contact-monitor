<div class="p-5" style="min-width:360px">
    <h3 class="text-base font-semibold text-gray-900 mb-1">Filter Companies</h3>
    <p class="text-sm text-gray-500 mb-4">
        Filtering {{ count($ids) }} company(ies).
        Optionally add a domain filter rule to automatically filter similar ones.
    </p>

    <form method="POST" action="{{ route('filtering.apply-rule') }}" id="cfm-form">
        @csrf
        @foreach($ids as $id)
            <input type="hidden" name="ids[]" value="{{ $id }}">
        @endforeach
        <input type="hidden" name="rule_type" id="cfm-rule-type" value="none">
        <input type="hidden" name="rule_value" id="cfm-rule-value" value="">

        {{-- Rule type tabs --}}
        <div class="flex flex-wrap gap-1.5 mb-4">
            @php $tabs = ['none' => 'No rule', 'domain' => 'Domain']; @endphp
            @foreach($tabs as $type => $label)
                <button type="button"
                        onclick="cfmSetType('{{ $type }}')"
                        id="cfm-tab-{{ $type }}"
                        class="px-3 py-1 rounded-full text-xs font-medium border transition
                               {{ $type === 'none' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-300 hover:border-gray-500' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- None --}}
        <div id="cfm-sec-none" class="text-sm text-gray-400 italic mb-4">
            No filter rule will be added.
        </div>

        {{-- Domain --}}
        <div id="cfm-sec-domain" class="hidden mb-4 space-y-3">
            @if($domains->isNotEmpty())
                <div>
                    <p class="text-xs text-gray-500 font-medium mb-1.5">Suggested domains:</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($domains as $d)
                            <button type="button"
                                    onclick="cfmPickChip('domain', '{{ $d }}')"
                                    class="cfm-chip px-2.5 py-1 rounded-full text-xs border border-gray-300 bg-gray-50 hover:bg-gray-100 text-gray-700 transition">
                                {{ $d }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
            <div>
                <label class="text-xs text-gray-500 font-medium block mb-1">Or enter domain manually:</label>
                <input type="text" id="cfm-domain-input" placeholder="example.com"
                       oninput="cfmSetValue('domain', this.value)"
                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-brand-400">
            </div>
        </div>

        {{-- Preview --}}
        <div id="cfm-rule-preview" class="hidden mb-4 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
            Rule: <span id="cfm-rule-preview-text"></span>
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
    window.cfmSetType = function(type) {
        document.querySelectorAll('[id^="cfm-tab-"]').forEach(function(btn) {
            var t = btn.id.replace('cfm-tab-', '');
            if (t === type) {
                btn.className = btn.className.replace('bg-white text-gray-600 border-gray-300 hover:border-gray-500', 'bg-gray-800 text-white border-gray-800');
            } else {
                btn.className = btn.className.replace('bg-gray-800 text-white border-gray-800', 'bg-white text-gray-600 border-gray-300 hover:border-gray-500');
            }
        });
        ['none','domain'].forEach(function(t) {
            var sec = document.getElementById('cfm-sec-' + t);
            if (sec) sec.classList.toggle('hidden', t !== type);
        });
        document.getElementById('cfm-rule-type').value = type;
        document.getElementById('cfm-rule-value').value = '';
        cfmUpdatePreview();
    };

    window.cfmSetValue = function(type, val) {
        document.querySelectorAll('.cfm-chip').forEach(function(c) {
            c.classList.remove('bg-brand-100', 'border-brand-400', 'text-brand-700');
            c.classList.add('bg-gray-50', 'border-gray-300', 'text-gray-700');
        });
        document.getElementById('cfm-rule-type').value = type;
        document.getElementById('cfm-rule-value').value = val;
        cfmUpdatePreview();
    };

    window.cfmPickChip = function(type, val) {
        cfmSetType(type);
        var inp = document.getElementById('cfm-' + type + '-input');
        if (inp) inp.value = val;
        document.getElementById('cfm-rule-type').value = type;
        document.getElementById('cfm-rule-value').value = val;
        document.querySelectorAll('.cfm-chip').forEach(function(c) {
            c.classList.remove('bg-brand-100', 'border-brand-400', 'text-brand-700');
            c.classList.add('bg-gray-50', 'border-gray-300', 'text-gray-700');
        });
        document.querySelectorAll('.cfm-chip').forEach(function(c) {
            var oc = c.getAttribute('onclick') || '';
            if (oc.indexOf(JSON.stringify(val)) !== -1 || oc.indexOf("'" + val + "'") !== -1) {
                c.classList.add('bg-brand-100', 'border-brand-400', 'text-brand-700');
                c.classList.remove('bg-gray-50', 'border-gray-300', 'text-gray-700');
            }
        });
        cfmUpdatePreview();
    };

    function cfmUpdatePreview() {
        var type = document.getElementById('cfm-rule-type').value;
        var val  = document.getElementById('cfm-rule-value').value.trim();
        var preview = document.getElementById('cfm-rule-preview');
        var text    = document.getElementById('cfm-rule-preview-text');
        if (type !== 'none' && val !== '') {
            preview.classList.remove('hidden');
            text.textContent = type + ': ' + val;
        } else {
            preview.classList.add('hidden');
        }
    }
})();
</script>
