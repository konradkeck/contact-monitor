@extends('layouts.app')
@section('title', $conn ? 'Edit — ' . $conn['name'] : 'New Connection')

@section('content')

@php
    $isEdit   = !is_null($conn);
    $action   = $isEdit
        ? route('synchronizer.connections.update', $conn['id'])
        : route('synchronizer.connections.store');
    $s        = $conn['settings'] ?? [];
    $type     = old('type', $conn['type'] ?? 'whmcs');

    // Helper: get old-or-existing value
    $v = fn(string $key, mixed $default = '') => old($key, data_get($conn, $key, $default));
    $sv = fn(string $key, mixed $default = '') => old("settings.$key", $s[$key] ?? $default);
    $arr = fn(string $key) => implode("\n", (array)(old("settings.$key", $s[$key] ?? [])));
@endphp

<div class="page-header">
    <div class="flex items-center gap-3">
        <a href="{{ route('synchronizer.index') }}" class="text-gray-400 hover:text-gray-600 text-sm">← Connections</a>
        @if($isEdit)
            <span class="text-gray-300">/</span>
            <a href="{{ route('synchronizer.connections.show', $conn['id']) }}" class="text-gray-400 hover:text-gray-600 text-sm">{{ $conn['name'] }}</a>
        @endif
        <span class="text-gray-300">/</span>
        <span class="page-title">{{ $isEdit ? 'Edit' : 'New connection' }}</span>
    </div>
</div>

@if($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
        <strong class="block mb-1">Validation errors:</strong>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" x-data="{ type: '{{ $type }}', slugEdited: {{ $isEdit ? 'true' : 'false' }}, testStatus: null, testMsg: '' }">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-4 max-w-2xl">

        {{-- ── Integration type ── --}}
        <div class="card p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Integration</div>
            @if($isEdit)
                <input type="hidden" name="type" value="{{ $conn['type'] }}">
                <div class="flex items-center gap-3">
                    @include('synchronizer._type_icon', ['type' => $conn['type'], 'class' => 'w-8 h-8'])
                    <span class="font-medium text-gray-800">{{ strtoupper($conn['type']) }}</span>
                    <span class="text-xs text-gray-400">Type cannot be changed after creation.</span>
                </div>
            @else
                <input type="hidden" name="type" x-bind:value="type">
                @php
                    $integrations = [
                        'whmcs'       => 'WHMCS',
                        'gmail'       => 'Gmail',
                        'imap'        => 'IMAP Email',
                        'metricscube' => 'MetricsCube',
                        'discord'     => 'Discord',
                        'slack'       => 'Slack',
                    ];
                @endphp
                <div class="grid grid-cols-3 gap-3">
                    @foreach($integrations as $t => $label)
                        <button type="button"
                                @click="type = '{{ $t }}'"
                                :class="type === '{{ $t }}' ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-400' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                                class="flex flex-col items-center gap-2 p-4 rounded-lg border-2 transition cursor-pointer">
                            @include('synchronizer._type_icon', ['type' => $t, 'class' => 'w-8 h-8'])
                            <span class="text-xs font-medium text-gray-700">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Basic ── --}}
        <div class="card p-5 space-y-4">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Basic</div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                    <input type="text" name="name" value="{{ $v('name') }}" required class="input"
                           @input="if (!slugEdited) $refs.slug.value = $event.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 50)">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">System slug</label>
                    <input type="text" name="system_slug" value="{{ $v('system_slug') }}" required
                           pattern="[a-z][a-z0-9_-]*" class="input font-mono" x-ref="slug"
                           @input="slugEdited = true">
                    <p class="text-xs text-gray-400 mt-0.5">Lowercase letters, numbers, - _</p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" class="rounded"
                           {{ $v('is_active', true) ? 'checked' : '' }}>
                    Active
                </label>
            </div>
        </div>

        {{-- ── Schedule ── --}}
        <div class="card p-5 space-y-3" x-show="type !== 'metricscube'">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Schedule</div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="flex items-center gap-2 text-sm mb-2 cursor-pointer">
                        <input type="checkbox" name="schedule_enabled" value="1" class="rounded"
                               {{ $v('schedule_enabled') ? 'checked' : '' }}>
                        Partial sync enabled
                    </label>
                    <input type="text" name="schedule_cron" value="{{ $v('schedule_cron', '*/30 * * * *') }}"
                           class="input font-mono text-xs" placeholder="*/30 * * * *">
                    <p class="text-xs text-gray-400 mt-0.5">Cron expression</p>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm mb-2 cursor-pointer">
                        <input type="checkbox" name="schedule_full_enabled" value="1" class="rounded"
                               {{ $v('schedule_full_enabled') ? 'checked' : '' }}>
                        Full sync enabled
                    </label>
                    <input type="text" name="schedule_full_cron" value="{{ $v('schedule_full_cron', '0 3 * * 0') }}"
                           class="input font-mono text-xs" placeholder="0 3 * * 0">
                    <p class="text-xs text-gray-400 mt-0.5">Cron expression</p>
                </div>
            </div>
        </div>

        {{-- ── WHMCS settings ── --}}
        <div class="card p-5 space-y-3" x-show="type === 'whmcs'">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">WHMCS Settings</div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Base URL</label>
                <input type="url" name="settings[base_url]" value="{{ $sv('base_url') }}" class="input" placeholder="https://whmcs.example.com">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">API Token {{ $isEdit ? '(leave blank to keep current)' : '' }}</label>
                <input type="password" name="settings[token]" value="" class="input font-mono" autocomplete="off">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Entities (one per line)</label>
                <textarea name="settings[entities]" rows="4" class="input font-mono text-xs">{{ $arr('entities') ?: "clients\ncontacts\nservices\ntickets" }}</textarea>
            </div>
        </div>

        {{-- ── Gmail settings ── --}}
        <div class="card p-5 space-y-3" x-show="type === 'gmail'">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Gmail Settings</div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Client ID</label>
                    <input type="text" name="settings[client_id]" value="{{ $sv('client_id') }}" class="input font-mono text-xs">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Client Secret {{ $isEdit ? '(blank = keep)' : '' }}</label>
                    <input type="password" name="settings[client_secret]" value="" class="input font-mono text-xs" autocomplete="off">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Subject email</label>
                <input type="email" name="settings[subject_email]" value="{{ $sv('subject_email') }}" class="input">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Query filter</label>
                <input type="text" name="settings[query]" value="{{ $sv('query') }}" class="input font-mono text-xs" placeholder="in:inbox">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Excluded labels (one per line)</label>
                <textarea name="settings[excluded_labels]" rows="3" class="input font-mono text-xs">{{ $arr('excluded_labels') }}</textarea>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Page size</label>
                    <input type="number" name="settings[page_size]" value="{{ $sv('page_size', 100) }}" class="input" min="1">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Max pages (0=all)</label>
                    <input type="number" name="settings[max_pages]" value="{{ $sv('max_pages', 0) }}" class="input" min="0">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Concurrent requests</label>
                    <input type="number" name="settings[concurrent_requests]" value="{{ $sv('concurrent_requests', 10) }}" class="input" min="1" max="100">
                </div>
            </div>
        </div>

        {{-- ── IMAP settings ── --}}
        <div class="card p-5 space-y-3" x-show="type === 'imap'">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">IMAP Settings</div>
            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Host</label>
                    <input type="text" name="settings[host]" value="{{ $sv('host') }}" class="input" placeholder="imap.example.com">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Port</label>
                    <input type="number" name="settings[port]" value="{{ $sv('port', 993) }}" class="input">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Encryption</label>
                    <select name="settings[encryption]" class="input">
                        @foreach(['ssl','tls','none'] as $enc)
                            <option value="{{ $enc }}" {{ ($sv('encryption','ssl') === $enc) ? 'selected' : '' }}>{{ strtoupper($enc) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Username</label>
                    <input type="text" name="settings[username]" value="{{ $sv('username') }}" class="input">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Password {{ $isEdit ? '(blank = keep)' : '' }}</label>
                    <input type="password" name="settings[password]" value="" class="input" autocomplete="off">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Excluded mailboxes (one per line)</label>
                <textarea name="settings[excluded_mailboxes]" rows="3" class="input font-mono text-xs">{{ $arr('excluded_mailboxes') }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Batch size</label>
                    <input type="number" name="settings[batch_size]" value="{{ $sv('batch_size', 100) }}" class="input" min="1">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Max batches (0=all)</label>
                    <input type="number" name="settings[max_batches]" value="{{ $sv('max_batches', 0) }}" class="input" min="0">
                </div>
            </div>
        </div>

        {{-- ── MetricsCube settings ── --}}
        <div class="card p-5 space-y-3" x-show="type === 'metricscube'">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">MetricsCube Settings</div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">App key</label>
                    <input type="text" name="settings[app_key]" value="{{ $sv('app_key') }}" class="input font-mono text-xs">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Connector key {{ $isEdit ? '(blank = keep)' : '' }}</label>
                    <input type="password" name="settings[connector_key]" value="" class="input font-mono text-xs" autocomplete="off">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Linked WHMCS connection</label>
                <select name="settings[whmcs_connection_id]" class="input">
                    <option value="0">— None —</option>
                    @foreach($whmcsConnections as $wc)
                        <option value="{{ $wc['id'] }}" {{ ((int)$sv('whmcs_connection_id') === $wc['id']) ? 'selected' : '' }}>
                            {{ $wc['name'] }} ({{ $wc['system_slug'] }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- ── Discord settings ── --}}
        <div class="card p-5 space-y-3" x-show="type === 'discord'">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Discord Settings</div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Bot token {{ $isEdit ? '(blank = keep)' : '' }}</label>
                <input type="password" name="settings[bot_token]" value="" class="input font-mono text-xs" autocomplete="off">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Guild allowlist (one per line, blank = all)</label>
                    <textarea name="settings[guild_allowlist]" rows="3" class="input font-mono text-xs">{{ $arr('guild_allowlist') }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Channel allowlist (one per line, blank = all)</label>
                    <textarea name="settings[channel_allowlist]" rows="3" class="input font-mono text-xs">{{ $arr('channel_allowlist') }}</textarea>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="settings[include_threads]" value="1" class="rounded"
                           {{ $sv('include_threads', true) ? 'checked' : '' }}>
                    Include threads
                </label>
                <div class="flex items-center gap-2">
                    <label class="text-xs font-medium text-gray-600">Max messages/run (0=all)</label>
                    <input type="number" name="settings[max_messages_per_run]" value="{{ $sv('max_messages_per_run', 0) }}"
                           class="input w-24" min="0">
                </div>
            </div>
        </div>

        {{-- ── Slack settings ── --}}
        <div class="card p-5 space-y-3" x-show="type === 'slack'">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Slack Settings</div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Bot token {{ $isEdit ? '(blank = keep)' : '' }}</label>
                <input type="password" name="settings[bot_token]" value="" class="input font-mono text-xs" autocomplete="off">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Channel allowlist (one per line, blank = all joined channels)</label>
                <textarea name="settings[channel_allowlist]" rows="3" class="input font-mono text-xs">{{ $arr('channel_allowlist') }}</textarea>
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="settings[include_threads]" value="1" class="rounded"
                           {{ $sv('include_threads', true) ? 'checked' : '' }}>
                    Include threads
                </label>
                <div class="flex items-center gap-2">
                    <label class="text-xs font-medium text-gray-600">Max messages/run (0=all)</label>
                    <input type="number" name="settings[max_messages_per_run]" value="{{ $sv('max_messages_per_run', 0) }}"
                           class="input w-24" min="0">
                </div>
            </div>
        </div>

        {{-- ── Actions ── --}}
        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ $isEdit ? 'Save changes' : 'Create connection' }}
            </button>
            <template x-if="['whmcs','imap','discord','slack','metricscube'].includes(type)">
                <button type="button" class="btn btn-secondary"
                        @click="
                            testStatus = 'testing'; testMsg = '';
                            const fd = new FormData($el.closest('form'));
                            fd.delete('_method');
                            const params = new URLSearchParams(window.location.search);
                            const server = params.get('server') || '';
                            fetch('{{ route('synchronizer.connections.test') }}' + (server ? '?server=' + server : ''), {
                                method: 'POST',
                                headers: {'X-CSRF-TOKEN': document.querySelector('[name=_token]').value},
                                body: fd
                            })
                            .then(r => r.json())
                            .then(d => { testStatus = d.ok ? 'ok' : 'fail'; testMsg = d.message || d.error || ''; })
                            .catch(e => { testStatus = 'fail'; testMsg = e.message; })
                        ">
                    <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Test connection
                </button>
            </template>
            <span x-show="testStatus === 'testing'" class="text-xs text-gray-400">Testing…</span>
            <span x-show="testStatus === 'ok'" class="text-xs" style="color:#1a7f37" x-text="'✓ ' + (testMsg || 'Connected')"></span>
            <span x-show="testStatus === 'fail'" class="text-xs" style="color:#cf222e" x-text="'✗ ' + (testMsg || 'Failed')"></span>
            <a href="{{ $isEdit ? route('synchronizer.connections.show', $conn['id']) : route('synchronizer.index') }}"
               class="btn btn-muted">Cancel</a>
        </div>

    </div>
</form>

@endsection
