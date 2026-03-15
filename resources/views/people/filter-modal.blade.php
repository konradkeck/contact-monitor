<div class="p-5" style="min-width:380px">
    <h3 class="text-base font-semibold text-gray-900 mb-1">Filter People</h3>
    <p class="text-sm text-gray-500 mb-4">
        Filtering {{ count($ids) }} person(s).
        Optionally add a filter rule to automatically filter similar ones in the future.
    </p>

    <form method="POST" action="{{ route('filtering.apply-rule') }}" id="pfm-form"
          onsubmit="return pfmBeforeSubmit()">
        @csrf
        @foreach($ids as $id)
            <input type="hidden" name="ids[]" value="{{ $id }}">
        @endforeach
        <input type="hidden" name="rule_type" id="pfm-rule-type" value="none">

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
        <div id="pfm-sec-domain" class="hidden mb-4 space-y-2">
            @if($domains->isNotEmpty())
                <p class="text-xs text-gray-500 font-medium">Suggested:</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($domains as $d)
                        <button type="button"
                                data-add-tag="domain" data-val="{{ $d }}"
                                class="px-2.5 py-1 rounded-full text-xs border border-gray-300 bg-gray-50 hover:bg-brand-50 hover:border-brand-300 text-gray-700 transition">
                            {{ $d }}
                        </button>
                    @endforeach
                </div>
            @endif
            <div id="pfm-domain-tag-wrap"></div>
        </div>

        {{-- Email --}}
        <div id="pfm-sec-email" class="hidden mb-4 space-y-2">
            @if($emails->isNotEmpty())
                <p class="text-xs text-gray-500 font-medium">Suggested:</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($emails as $e)
                        <button type="button"
                                data-add-tag="email" data-val="{{ $e }}"
                                class="px-2.5 py-1 rounded-full text-xs border border-gray-300 bg-gray-50 hover:bg-brand-50 hover:border-brand-300 text-gray-700 transition">
                            {{ $e }}
                        </button>
                    @endforeach
                </div>
            @endif
            <div id="pfm-email-tag-wrap"></div>
        </div>

        {{-- Contact --}}
        <div id="pfm-sec-contact" class="hidden mb-4 space-y-2">
            <p class="text-xs text-gray-500 font-medium mb-1.5">Add person to filter list:</p>
            @foreach($contacts as $personId => $name)
                <label class="flex items-center gap-2 text-sm cursor-pointer hover:text-gray-900">
                    <input type="radio" name="pfm-contact-radio" value="{{ $personId }}"
                           class="accent-brand-600 pfm-contact-radio">
                    {{ $name }}
                </label>
            @endforeach
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
    var pfmTags = { domain: [], email: [] };

    function render(type) {
        var wrap = document.getElementById('pfm-' + type + '-tag-wrap');
        if (!wrap) return;
        var tags = pfmTags[type] || [];
        var placeholder = type === 'domain' ? 'example.com' : 'user@example.com';

        var div = document.createElement('div');
        div.className = 'w-full min-h-[40px] bg-white border border-gray-200 rounded-lg px-2 py-1.5 flex flex-wrap gap-1.5 focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-100 cursor-text transition';

        tags.forEach(function(tag) {
            var span = document.createElement('span');
            span.className = 'inline-flex items-center gap-1 bg-brand-100 text-brand-800 text-xs font-mono px-2 py-0.5 rounded-full max-w-[200px]';
            var text = document.createElement('span');
            text.className = 'truncate';
            text.textContent = tag;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'text-brand-400 hover:text-brand-700 shrink-0 leading-none';
            btn.textContent = '×';
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                pfmTags[type] = pfmTags[type].filter(function(t) { return t !== tag; });
                render(type);
                pfmSync();
            });
            span.appendChild(text);
            span.appendChild(btn);
            div.appendChild(span);
        });

        var inp = document.createElement('input');
        inp.type = 'text';
        inp.id = 'pfm-' + type + '-input';
        inp.placeholder = placeholder;
        inp.className = 'flex-1 min-w-[140px] text-xs text-gray-700 font-mono outline-none border-none bg-transparent py-0.5 placeholder-gray-300';
        inp.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                e.stopPropagation();
                var val = inp.value.trim();
                if (val) {
                    if (!pfmTags[type].includes(val)) pfmTags[type].push(val);
                    render(type);
                    pfmSync();
                }
            } else if (e.key === 'Backspace' && inp.value === '' && pfmTags[type].length) {
                pfmTags[type].pop();
                render(type);
                pfmSync();
            }
        });
        div.appendChild(inp);
        div.addEventListener('click', function() { inp.focus(); });

        var p = document.createElement('p');
        p.className = 'text-xs text-gray-400 mt-1';
        p.textContent = 'Press Enter or , to add. Click × to remove.';

        wrap.innerHTML = '';
        wrap.appendChild(div);
        wrap.appendChild(p);
    }

    function pfmSync() {
        document.querySelectorAll('#pfm-form input[name="rule_values[]"]').forEach(function(el) { el.remove(); });
        var form = document.getElementById('pfm-form');
        if (!form) return;
        var type = document.getElementById('pfm-rule-type').value;
        var values = [];
        if (type === 'contact') {
            var radio = form.querySelector('.pfm-contact-radio:checked');
            if (radio) values = [radio.value];
        } else if (pfmTags[type]) {
            values = pfmTags[type];
        }
        values.forEach(function(val) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'rule_values[]';
            inp.value = val;
            form.appendChild(inp);
        });
    }

    window.pfmSetType = function(type) {
        document.querySelectorAll('[id^="pfm-tab-"]').forEach(function(btn) {
            var t = btn.id.replace('pfm-tab-', '');
            var active = t === type;
            btn.classList.toggle('bg-gray-800', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('border-gray-800', active);
            btn.classList.toggle('bg-white', !active);
            btn.classList.toggle('text-gray-600', !active);
            btn.classList.toggle('border-gray-300', !active);
            btn.classList.toggle('hover:border-gray-500', !active);
        });
        ['none','domain','email','contact'].forEach(function(t) {
            var sec = document.getElementById('pfm-sec-' + t);
            if (sec) sec.classList.toggle('hidden', t !== type);
        });
        document.getElementById('pfm-rule-type').value = type;
        pfmSync();
    };

    document.querySelectorAll('[data-add-tag]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type = btn.getAttribute('data-add-tag');
            var val  = btn.getAttribute('data-val');
            pfmSetType(type);
            if (!pfmTags[type].includes(val)) pfmTags[type].push(val);
            render(type);
            pfmSync();
        });
    });

    document.querySelectorAll('.pfm-contact-radio').forEach(function(r) {
        r.addEventListener('change', pfmSync);
    });

    window.pfmBeforeSubmit = function() { pfmSync(); return true; };

    ['domain','email'].forEach(render);
})();
</script>
