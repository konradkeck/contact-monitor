<td class="px-2 py-2 {{ $cellBg }} cursor-pointer"
    onclick="openPopup('popup-bp-{{ $companyId }}-{{ $bpId }}')">
    <div class="flex items-center justify-start gap-1.5 flex-wrap">
        <span class="inline-flex w-6 h-6 rounded-full items-center justify-center
                     text-[11px] font-bold shrink-0 leading-none"
              style="background:{{ $scClr }};color:{{ $scTxtClr }}">
            {{ $sc ?? '—' }}
        </span>
        <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $stageBadge }} shrink-0">
            {{ $status->stage }}
        </span>
    </div>
</td>
