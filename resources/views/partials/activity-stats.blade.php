{{--
    Shared activity stats widget.
    Parameters:
      $typeCounts  — collection: [{type, cnt}]   (non-conversation activity types)
      $convCounts  — collection: [{channel_type, system_slug, cnt}]
      $totalConv   — int (total conversation count)
--}}
@if($totalConv > 0)
<div class="mb-3">
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-1 mb-1">Conversations</p>
    @foreach($convCounts as $row)
        <div class="flex items-center gap-2 py-1 px-2 rounded-lg hover:bg-gray-50">
            <x-channel-badge :type="$row->channel_type" :label="false" class="shrink-0" />
            <span class="text-xs text-gray-500 truncate flex-1">{{ $row->system_slug ?: $row->channel_type }}</span>
            <span class="text-xs font-semibold text-gray-700 tabular-nums">{{ number_format($row->cnt) }}</span>
        </div>
    @endforeach
</div>
@endif

@if($typeCounts->isNotEmpty())
<div>
    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-1 mb-1">Activity</p>
    @foreach($typeCounts as $row)
        <div class="flex items-center gap-2 py-1 px-2 rounded-lg hover:bg-gray-50">
            <span class="w-2 h-2 rounded-full shrink-0 {{ $dotColors[$row->type] ?? 'bg-slate-300' }}"></span>
            <span class="text-xs text-gray-600 flex-1 truncate">{{ ucfirst(str_replace('_', ' ', $row->type)) }}</span>
            <span class="text-xs font-semibold text-gray-700 tabular-nums">{{ number_format($row->cnt) }}</span>
        </div>
    @endforeach
</div>
@endif

@if($totalConv === 0 && $typeCounts->isEmpty())
    <p class="text-xs text-gray-400 italic px-2 py-2">No activity.</p>
@endif
