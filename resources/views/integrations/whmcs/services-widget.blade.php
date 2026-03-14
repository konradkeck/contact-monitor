{{--
    WHMCS services widget panel.
    Variables: $sys (array), $slug (string)
--}}
{{-- KPI row --}}
<div class="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100">
    <div class="px-5 py-4">
        <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold mb-1">Revenue</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums">
            ${{ number_format($sys['revenue'], 0) }}
        </p>
        <p class="text-xs text-gray-400 mt-0.5">lifetime</p>
    </div>
    <div class="px-5 py-4">
        <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold mb-1">Active</p>
        <p class="text-2xl font-bold text-green-600 tabular-nums">{{ $sys['active'] }}</p>
        <p class="text-xs text-gray-400 mt-0.5">services</p>
    </div>
    <div class="px-5 py-4">
        <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold mb-1">Total</p>
        <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ $sys['total'] }}</p>
        <p class="text-xs text-gray-400 mt-0.5">all services</p>
    </div>
</div>

{{-- Services table --}}
<table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
            <th class="px-5 py-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Product</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">Since</th>
            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Renewals</th>
            <th class="px-5 py-2 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Revenue</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
        @foreach($sys['services'] as $svc)
            <tr class="hover:bg-gray-50/60">
                <td class="px-5 py-2.5 font-medium text-gray-800">{{ $svc['product_name'] ?? '—' }}</td>
                <td class="px-3 py-2.5">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium {{ match(strtolower($svc['status'] ?? '')) { 'active' => 'bg-green-100 text-green-700', 'pending' => 'bg-yellow-100 text-yellow-700', 'suspended' => 'bg-red-100 text-red-600', default => 'bg-gray-100 text-gray-500' } }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ match(strtolower($svc['status'] ?? '')) { 'active' => 'bg-green-400', 'pending' => 'bg-yellow-400', 'suspended' => 'bg-red-400', default => 'bg-gray-300' } }}"></span>
                        {{ ucfirst($svc['status'] ?? '—') }}
                    </span>
                </td>
                <td class="px-3 py-2.5 text-xs text-gray-500">{{ $svc['start_date'] ? \Carbon\Carbon::parse($svc['start_date'])->format('M Y') : '—' }}</td>
                <td class="px-3 py-2.5 text-xs text-gray-500 text-right tabular-nums">{{ $svc['renewal_count'] ?? 0 }}×</td>
                <td class="px-5 py-2.5 text-right font-semibold text-gray-800 tabular-nums">
                    ${{ number_format((float)($svc['total_revenue'] ?? 0), 2) }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
