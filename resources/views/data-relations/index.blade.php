@extends('layouts.app')
@section('title', 'Data Relations')

@section('content')

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Data Relations — Overview</h1>
        <p class="text-sm text-gray-500 mt-1">Global linking status across all integrations.</p>
    </div>
    <a href="{{ route('our-company.index') }}"
       class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium
              text-gray-700 hover:border-brand-400 hover:text-brand-700 transition">
        🏢 Our Organization
    </a>
</div>

{{-- Global stats --}}
<div class="grid grid-cols-3 gap-4 mb-8">
    @php
        $cards = [
            ['label' => 'Conversations without company', 'value' => $stats['conversations_no_company'], 'total' => $stats['total_conversations']],
            ['label' => 'Accounts without company',      'value' => $stats['accounts_no_company'],      'total' => $stats['total_accounts']],
            ['label' => 'Identities without person',     'value' => $stats['identities_no_person'],     'total' => $stats['total_identities']],
        ];
    @endphp
    @foreach($cards as $card)
        @php $clean = $card['value'] === 0; @endphp
        <div class="rounded-lg border p-4 {{ $clean ? 'border-green-200 bg-green-50' : 'border-amber-300 bg-amber-50' }}">
            <p class="text-sm text-gray-600">{{ $card['label'] }}</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($card['value']) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">of {{ number_format($card['total']) }} total</p>
        </div>
    @endforeach
</div>

{{-- Account-based systems breakdown --}}
@if($accountSystems->isNotEmpty())
<div class="bg-white rounded-lg border border-gray-200 mb-6">
    <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">Account-based Systems</h2>
        <p class="text-xs text-gray-400 mt-0.5">WHMCS, MetricsCube — accounts link to companies</p>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
            <tr>
                <th class="px-4 py-2 text-left">System</th>
                <th class="px-4 py-2 text-left">Slug</th>
                <th class="px-4 py-2 text-right">Unlinked</th>
                <th class="px-4 py-2 text-right">Total</th>
                <th class="px-4 py-2 text-right">Linked %</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($accountSystems as $sys)
                @php
                    $pct = $sys->total > 0 ? round(($sys->total - $sys->unlinked) / $sys->total * 100) : 100;
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2"><x-channel-badge :type="$sys->system_type" /></td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-700">{{ $sys->system_slug }}</td>
                    <td class="px-4 py-2 text-right {{ $sys->unlinked > 0 ? 'text-amber-600 font-semibold' : 'text-green-600' }}">
                        {{ number_format($sys->unlinked) }}
                    </td>
                    <td class="px-4 py-2 text-right text-gray-500">{{ number_format($sys->total) }}</td>
                    <td class="px-4 py-2 text-right text-gray-500">{{ $pct }}%</td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('data-relations.mapping', [$sys->system_type, $sys->system_slug]) }}"
                           class="text-xs text-brand-600 hover:text-brand-800 font-medium">Manage →</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Identity-based systems breakdown --}}
@if($identitySystems->isNotEmpty())
<div class="bg-white rounded-lg border border-gray-200 mb-6">
    <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="font-semibold text-gray-800">Identity-based Systems</h2>
        <p class="text-xs text-gray-400 mt-0.5">IMAP, Slack, Discord — identities link to people</p>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
            <tr>
                <th class="px-4 py-2 text-left">System</th>
                <th class="px-4 py-2 text-left">Slug</th>
                <th class="px-4 py-2 text-left">Identity type</th>
                <th class="px-4 py-2 text-right">Unlinked</th>
                <th class="px-4 py-2 text-right">Total</th>
                <th class="px-4 py-2 text-right">Linked %</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($identitySystems as $sys)
                @php
                    $pct = $sys->total > 0 ? round(($sys->total - $sys->unlinked) / $sys->total * 100) : 100;
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2"><x-channel-badge :type="$sys->type" /></td>
                    <td class="px-4 py-2 font-mono text-xs text-gray-700">{{ $sys->system_slug }}</td>
                    <td class="px-4 py-2 text-xs text-gray-500">{{ $sys->type }}</td>
                    <td class="px-4 py-2 text-right {{ $sys->unlinked > 0 ? 'text-amber-600 font-semibold' : 'text-green-600' }}">
                        {{ number_format($sys->unlinked) }}
                    </td>
                    <td class="px-4 py-2 text-right text-gray-500">{{ number_format($sys->total) }}</td>
                    <td class="px-4 py-2 text-right text-gray-500">{{ $pct }}%</td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('data-relations.mapping', [$sys->system_type, $sys->system_slug]) }}"
                           class="text-xs text-brand-600 hover:text-brand-800 font-medium">Manage →</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@endsection
