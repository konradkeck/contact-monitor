@extends('layouts.app')
@section('title', 'Add Filter — Smart Notes')

@section('content')

<div class="page-header">
    <div>
        <nav aria-label="Breadcrumb" class="page-breadcrumb">
            <a href="{{ route('smart-notes.config.index') }}">Smart Notes</a>
            <span class="sep">/</span>
            <span class="cur" aria-current="page">Add Filter</span>
        </nav>
        <h1 class="page-title mt-1">Add Filter</h1>
    </div>
</div>

@if($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
        <ul class="space-y-1">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card p-5 max-w-lg" x-data="{ filterType: '{{ old('type', 'email_message') }}' }">
    <form method="POST" action="{{ route('smart-notes.config.filters.store') }}">
        @csrf

        <div class="space-y-4">
            <div>
                <label class="label" for="f-type">Filter Type</label>
                <select id="f-type" name="type" x-model="filterType" class="input w-full">
                    <option value="email_message">Email Message</option>
                    <option value="email_subject">Email Subject Keyword</option>
                    <option value="discord_any">Discord</option>
                    <option value="slack_any">Slack</option>
                </select>
            </div>

            {{-- email_message: choose mailboxes + address condition --}}
            <div x-show="filterType === 'email_message'" class="space-y-3">
                <div>
                    <label class="label">Mailboxes <span class="text-gray-400 font-normal">(leave all unchecked to match any mailbox)</span></label>
                    @if($emailMailboxes->isEmpty())
                        <p class="text-sm text-gray-400 italic mt-1">No email connections found — filter will apply to all email messages.</p>
                    @else
                        <div class="mt-1.5 space-y-1.5 border border-gray-200 rounded-lg p-3">
                            @foreach($emailMailboxes as $mailbox)
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox" name="mailbox_slugs[]"
                                           value="{{ $mailbox->system_slug }}"
                                           {{ in_array($mailbox->system_slug, (array) old('mailbox_slugs', [])) ? 'checked' : '' }}
                                           class="rounded border-gray-300">
                                    <span class="text-sm text-gray-700">
                                        {{ $mailbox->system_slug }}
                                        <span class="text-gray-400 text-xs">({{ $mailbox->system_type }})</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div>
                    <label class="label" for="f-address">Email Address</label>
                    <input id="f-address" type="text" name="address" class="input w-full"
                           placeholder="notes@example.com" value="{{ old('address') }}">
                    <p class="text-xs text-gray-400 mt-1">Messages where this address appears as sender or recipient will be captured.</p>
                    @error('address')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="f-direction">Direction</label>
                    <select id="f-direction" name="direction" class="input w-full">
                        <option value="any" {{ old('direction', 'any') === 'any' ? 'selected' : '' }}>Any (to or from)</option>
                        <option value="from" {{ old('direction') === 'from' ? 'selected' : '' }}>From this address</option>
                        <option value="to" {{ old('direction') === 'to' ? 'selected' : '' }}>To this address</option>
                    </select>
                </div>
            </div>

            {{-- email_subject criteria --}}
            <div x-show="filterType === 'email_subject'" x-cloak>
                <label class="label" for="f-keyword">Subject Keyword</label>
                <input id="f-keyword" type="text" name="keyword" class="input w-full"
                       placeholder="NOTES" value="{{ old('keyword') }}">
                @error('keyword')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- discord_any: choose connection --}}
            <div x-show="filterType === 'discord_any'" x-cloak>
                <label class="label" for="f-discord-slug">Discord Connection <span class="text-gray-400 font-normal">(leave blank for all)</span></label>
                @if($discordConnections->isEmpty())
                    <p class="text-sm text-gray-400 italic mt-1">No Discord connections found — filter will apply to all Discord messages when available.</p>
                    <input type="hidden" name="connection_slug" value="">
                @else
                    <select id="f-discord-slug" name="connection_slug" class="input w-full">
                        <option value="">All Discord connections</option>
                        @foreach($discordConnections as $slug)
                            <option value="{{ $slug }}" {{ old('connection_slug') === $slug ? 'selected' : '' }}>
                                {{ $slug }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            {{-- slack_any: choose workspace --}}
            <div x-show="filterType === 'slack_any'" x-cloak>
                <label class="label" for="f-slack-slug">Slack Workspace <span class="text-gray-400 font-normal">(leave blank for all)</span></label>
                @if($slackWorkspaces->isEmpty())
                    <p class="text-sm text-gray-400 italic mt-1">No Slack workspaces found — filter will apply to all Slack messages when available.</p>
                    <input type="hidden" name="connection_slug" value="">
                @else
                    <select id="f-slack-slug" name="connection_slug" class="input w-full">
                        <option value="">All Slack workspaces</option>
                        @foreach($slackWorkspaces as $slug)
                            <option value="{{ $slug }}" {{ old('connection_slug') === $slug ? 'selected' : '' }}>
                                {{ $slug }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="as_internal_note" id="f-internal" value="1"
                       class="rounded border-gray-300" {{ old('as_internal_note') ? 'checked' : '' }}>
                <label for="f-internal" class="text-sm text-gray-700 cursor-pointer">Tag matched notes as <strong>Internal Notes</strong></label>
            </div>
        </div>

        <div class="flex gap-2 mt-6">
            <button type="submit" class="btn btn-primary">Add Filter</button>
            <a href="{{ route('smart-notes.config.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
