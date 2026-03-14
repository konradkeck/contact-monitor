<div class="p-5" style="min-width:340px">
    <h3 class="text-base font-semibold text-gray-900 mb-1">Add Filter Rule</h3>
    @if($name)
        <p class="text-sm text-gray-500 mb-4">For: <span class="font-medium text-gray-700">{{ $name }}</span></p>
    @else
        <p class="text-sm text-gray-500 mb-4">Choose a rule to add to the filter list.</p>
    @endif

    <form method="POST" action="{{ route('filtering.apply-rule') }}" id="ifm-form">
        @csrf
        <input type="hidden" name="rule_type" id="ifm-rule-type" value="none">
        <input type="hidden" name="rule_value" id="ifm-rule-value" value="">

        {{-- Rule type tabs --}}
        <div class="flex flex-wrap gap-1.5 mb-4">
            @foreach($tabs as $type => $label)
                <button type="button"
                        onclick="ifmSetType('{{ $type }}')"
                        id="ifm-tab-{{ $type }}"
                        class="px-3 py-1 rounded-full text-xs font-medium border transition
                               {{ $type === 'none' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-300 hover:border-gray-500' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- None --}}
        <div id="ifm-sec-none" class="text-sm text-gray-400 italic mb-4">No rule will be added.</div>

        {{-- Domain --}}
        <div id="ifm-sec-domain" class="hidden mb-4 space-y-3">
            @if($domains->isNotEmpty())
                <div>
                    <p class="text-xs text-gray-500 font-medium mb-1.5">Suggested:</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($domains as $d)
                            <button type="button"
                                    onclick="ifmPickChip('domain', '{{ $d }}')"
                                    class="ifm-chip px-2.5 py-1 rounded-full text-xs border border-gray-300 bg-gray-50 hover:bg-gray-100 text-gray-700 transition">
                                {{ $d }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
            <div>
                <label class="text-xs text-gray-500 font-medium block mb-1">Or enter domain manually:</label>
                <input type="text" id="ifm-domain-input" placeholder="example.com"
                       value="{{ $domains->first() ?? '' }}"
                       oninput="ifmSetValue('domain', this.value)"
                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-brand-400">
            </div>
        </div>

        {{-- Email --}}
        <div id="ifm-sec-email" class="hidden mb-4 space-y-3">
            @if($emails->isNotEmpty())
                <div>
                    <p class="text-xs text-gray-500 font-medium mb-1.5">Suggested:</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($emails as $e)
                            <button type="button"
                                    onclick="ifmPickChip('email', '{{ $e }}')"
                                    class="ifm-chip px-2.5 py-1 rounded-full text-xs border border-gray-300 bg-gray-50 hover:bg-gray-100 text-gray-700 transition">
                                {{ $e }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
            <div>
                <label class="text-xs text-gray-500 font-medium block mb-1">Or enter email manually:</label>
                <input type="text" id="ifm-email-input" placeholder="user@example.com"
                       value="{{ $emails->first() ?? '' }}"
                       oninput="ifmSetValue('email', this.value)"
                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-brand-400">
            </div>
        </div>

        {{-- Preview --}}
        <div id="ifm-rule-preview" class="hidden mb-4 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
            Rule: <span id="ifm-rule-preview-text"></span>
        </div>

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
    window.ifmSetType = function(type) {
        document.querySelectorAll('[id^="ifm-tab-"]').forEach(function(btn) {
            var t = btn.id.replace('ifm-tab-', '');
            if (t === type) {
                btn.className = btn.className.replace('bg-white text-gray-600 border-gray-300 hover:border-gray-500', 'bg-gray-800 text-white border-gray-800');
            } else {
                btn.className = btn.className.replace('bg-gray-800 text-white border-gray-800', 'bg-white text-gray-600 border-gray-300 hover:border-gray-500');
            }
        });
        ['none','domain','email'].forEach(function(t) {
            var sec = document.getElementById('ifm-sec-' + t);
            if (sec) sec.classList.toggle('hidden', t !== type);
        });
        document.getElementById('ifm-rule-type').value = type;
        // Auto-populate value from input field
        var inp = document.getElementById('ifm-' + type + '-input');
        document.getElementById('ifm-rule-value').value = inp ? inp.value : '';
        ifmUpdatePreview();
    };

    window.ifmSetValue = function(type, val) {
        document.querySelectorAll('.ifm-chip').forEach(function(c) {
            c.classList.remove('bg-brand-100','border-brand-400','text-brand-700');
            c.classList.add('bg-gray-50','border-gray-300','text-gray-700');
        });
        document.getElementById('ifm-rule-type').value = type;
        document.getElementById('ifm-rule-value').value = val;
        ifmUpdatePreview();
    };

    window.ifmPickChip = function(type, val) {
        var inp = document.getElementById('ifm-' + type + '-input');
        if (inp) inp.value = val;
        document.getElementById('ifm-rule-type').value = type;
        document.getElementById('ifm-rule-value').value = val;
        document.querySelectorAll('.ifm-chip').forEach(function(c) {
            c.classList.remove('bg-brand-100','border-brand-400','text-brand-700');
            c.classList.add('bg-gray-50','border-gray-300','text-gray-700');
        });
        document.querySelectorAll('.ifm-chip').forEach(function(c) {
            var oc = c.getAttribute('onclick') || '';
            if (oc.indexOf("'" + val + "'") !== -1) {
                c.classList.add('bg-brand-100','border-brand-400','text-brand-700');
                c.classList.remove('bg-gray-50','border-gray-300','text-gray-700');
            }
        });
        ifmUpdatePreview();
    };

    function ifmUpdatePreview() {
        var type = document.getElementById('ifm-rule-type').value;
        var val  = document.getElementById('ifm-rule-value').value.trim();
        var preview = document.getElementById('ifm-rule-preview');
        var text    = document.getElementById('ifm-rule-preview-text');
        if (type !== 'none' && val !== '') {
            preview.classList.remove('hidden');
            text.textContent = type + ': ' + val;
        } else {
            preview.classList.add('hidden');
        }
    }
})();
</script>
