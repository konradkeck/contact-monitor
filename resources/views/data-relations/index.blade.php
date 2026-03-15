@extends('layouts.app')
@section('title', 'Mapping')

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Mapping</h1>
        <p class="text-xs text-gray-400 mt-0.5">Link external accounts and identities to companies and people.</p>
    </div>
    <a href="{{ route('our-company.index') }}" class="btn btn-secondary">Our Organization</a>
</div>

{{-- Global stats --}}
<div class="grid grid-cols-3 gap-4 mb-8">
    @foreach($cards as $card)
        <div class="rounded-lg border p-4 {{ $card['value'] === 0 ? 'border-green-200 bg-green-50' : 'border-amber-300 bg-amber-50' }}">
            <p class="text-sm text-gray-600">{{ $card['label'] }}</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($card['value']) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">of {{ number_format($card['total']) }} total</p>
        </div>
    @endforeach
</div>

{{-- Account-based systems breakdown --}}
@if($accountSystems->isNotEmpty())
<div class="card mb-6">
    <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">Account-based Systems</h2>
        <p class="text-xs text-gray-400 mt-0.5">WHMCS, MetricsCube — accounts link to companies</p>
    </div>
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2.5 text-left">System / Slug</th>
                <th class="col-mobile-hidden px-4 py-2.5 text-right">Companies unlinked</th>
                <th class="col-mobile-hidden px-4 py-2.5 text-right">Contacts unlinked</th>
                <th class="px-4 py-2.5 text-left">Linked %</th>
                <th class="px-4 py-2.5"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($accountSystems as $sys)
                <tr class="tbl-row">
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-2">
                            <x-channel-badge :type="$sys->system_type" :label="false" />
                            <span class="font-mono text-xs text-gray-700">{{ $sys->system_slug }}</span>
                        </div>
                    </td>
                    <td class="col-mobile-hidden px-4 py-2 text-right {{ $sys->unlinked > 0 ? 'text-amber-600 font-semibold' : 'text-green-600' }}">
                        {{ number_format($sys->unlinked) }}
                    </td>
                    <td class="col-mobile-hidden px-4 py-2 text-right {{ $sys->contacts_unlinked > 0 ? 'text-amber-600 font-semibold' : 'text-green-600' }}">
                        {{ number_format($sys->contacts_unlinked) }}
                    </td>
                    <td class="px-4 py-2"><x-linked-pct-bar :pct="$sys->total > 0 ? round(($sys->total - $sys->unlinked) / $sys->total * 100) : 100" /></td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('data-relations.mapping', [$sys->system_type, $sys->system_slug]) }}"
                           class="btn btn-sm btn-muted" title="Manage Mapping">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <span class="hidden sm:inline">Manage Mapping</span>
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Identity-based systems breakdown --}}
@if($identitySystems->isNotEmpty())
<div class="card mb-6">
    <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">Identity-based Systems</h2>
        <p class="text-xs text-gray-400 mt-0.5">IMAP, Slack, Discord — identities link to people</p>
    </div>
    <table class="w-full text-sm">
        <thead class="tbl-header">
            <tr>
                <th class="px-4 py-2.5 text-left">System / Slug</th>
                <th class="col-mobile-hidden px-4 py-2.5 text-right">Unlinked</th>
                <th class="col-mobile-hidden px-4 py-2.5 text-right">Total</th>
                <th class="px-4 py-2.5 text-left">Linked %</th>
                <th class="px-4 py-2.5"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($identitySystems as $sys)
                <tr class="tbl-row">
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-2">
                            <x-channel-badge :type="$sys->type" :label="false" />
                            <span class="font-mono text-xs text-gray-700">{{ $sys->system_slug }}</span>
                        </div>
                    </td>
                    <td class="col-mobile-hidden px-4 py-2 text-right {{ $sys->unlinked > 0 ? 'text-amber-600 font-semibold' : 'text-green-600' }}">
                        {{ number_format($sys->unlinked) }}
                    </td>
                    <td class="col-mobile-hidden px-4 py-2 text-right text-gray-500">{{ number_format($sys->total) }}</td>
                    <td class="px-4 py-2"><x-linked-pct-bar :pct="$sys->total > 0 ? round(($sys->total - $sys->unlinked) / $sys->total * 100) : 100" /></td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('data-relations.mapping', [$sys->system_type, $sys->system_slug]) }}"
                           class="btn btn-sm btn-muted" title="Manage Mapping">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <span class="hidden sm:inline">Manage Mapping</span>
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@endsection
