@extends('layouts.app')
@section('title', 'Recognize Smart Note')

@section('content')

<div class="page-header">
    <div>
        <a href="{{ route('smart-notes.index') }}" class="page-breadcrumb-back">← Smart Notes</a>
        <h1 class="page-title mt-1 flex items-center gap-2">
            <img src="/ai-icon.svg" class="w-5 h-5" alt="">
            Recognize Smart Note
        </h1>
    </div>
</div>

{{-- Original message --}}
<div class="card p-5 mb-5 max-w-4xl">
    <div class="flex items-start justify-between gap-4 mb-3">
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Original Message</p>
            @if($smartNote->sender_name || $smartNote->sender_value)
                <p class="text-sm text-gray-600 mt-0.5">
                    From:
                    @if($smartNote->sender_name)<strong>{{ $smartNote->sender_name }}</strong>@endif
                    @if($smartNote->sender_value)<span class="font-mono text-gray-500 ml-1">{{ $smartNote->sender_value }}</span>@endif
                </p>
            @endif
        </div>
        <div class="flex items-center gap-2 shrink-0 text-xs text-gray-400">
            <span class="badge badge-gray">{{ $smartNote->sourceLabel() }}</span>
            @if($smartNote->occurred_at)
                <span>{{ $smartNote->occurred_at->format('d M Y H:i') }}</span>
            @endif
        </div>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm text-gray-800 whitespace-pre-wrap leading-relaxed font-mono text-xs max-h-48 overflow-y-auto">{{ $smartNote->content }}</div>
</div>

{{-- Segments editor --}}
<div class="max-w-4xl"
     x-data="{
        segments: {{ json_encode(array_values($smartNote->segments_json ?? [['content' => $smartNote->content, 'assign_to' => null, 'entity_id' => null, 'company_id' => null, 'person_id' => null]])) }},
        companiesList: {{ json_encode($companies->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()) }},
        peopleList: {{ json_encode($people->map(fn($p) => ['id' => $p->id, 'name' => trim($p->first_name . ' ' . $p->last_name)])->values()) }},
        addSegment() {
            this.segments.push({ content: '', assign_to: null, entity_id: null });
        },
        removeSegment(index) {
            if (this.segments.length <= 1) return;
            this.segments.splice(index, 1);
        },
        filteredCompanies(q) {
            if (!q) return this.companiesList.slice(0, 10);
            const lq = q.toLowerCase();
            return this.companiesList.filter(c => c.name.toLowerCase().includes(lq)).slice(0, 10);
        },
        filteredPeople(q) {
            if (!q) return this.peopleList.slice(0, 10);
            const lq = q.toLowerCase();
            return this.peopleList.filter(p => p.name.toLowerCase().includes(lq)).slice(0, 10);
        }
     }">

    <div class="flex items-center justify-between mb-3">
        <p class="section-header-title">Note Segments</p>
        <button type="button" @click="addSegment()" class="btn btn-secondary btn-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Split / Add Segment
        </button>
    </div>

    <form method="POST" action="{{ route('smart-notes.save-recognition', $smartNote) }}">
        @csrf

        <div class="space-y-4">
            <template x-for="(seg, index) in segments" :key="index">
                <div class="card p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <label class="label">Content</label>
                            <textarea :name="'segments[' + index + '][content]'"
                                      x-model="seg.content"
                                      rows="4"
                                      class="input w-full text-xs font-mono resize-y"
                                      placeholder="Note content..."></textarea>
                        </div>
                        <button type="button"
                                @click="removeSegment(index)"
                                x-show="segments.length > 1"
                                class="shrink-0 mt-6 text-gray-400 hover:text-red-500 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">Assign to</label>
                            <select :name="'segments[' + index + '][assign_to]'"
                                    x-model="seg.assign_to"
                                    class="input w-full">
                                <option value="">— Do not assign —</option>
                                <option value="company">Company</option>
                                <option value="person">Person</option>
                            </select>
                        </div>

                        <div x-show="seg.assign_to === 'company'"
                             x-data="{ cq: '', open: false }"
                             @click.outside="open = false">
                            <label class="label">Company</label>
                            <div class="relative">
                                <input type="text"
                                       x-model="cq"
                                       @focus="open = true"
                                       @input="open = true"
                                       class="input w-full"
                                       placeholder="Search company...">
                                <input type="hidden" :name="'segments[' + index + '][entity_id]'" x-model="seg.entity_id">
                                <div x-show="open && cq.length >= 0" x-cloak
                                     class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg py-1 max-h-48 overflow-y-auto">
                                    <template x-for="c in filteredCompanies(cq)" :key="c.id">
                                        <button type="button"
                                                @click="seg.entity_id = c.id; cq = c.name; open = false"
                                                class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50"
                                                x-text="c.name"></button>
                                    </template>
                                    <p x-show="filteredCompanies(cq).length === 0" class="px-3 py-2 text-xs text-gray-400 italic">No results</p>
                                </div>
                            </div>
                        </div>

                        <div x-show="seg.assign_to === 'person'"
                             x-data="{ pq: '', open: false }"
                             @click.outside="open = false">
                            <label class="label">Person</label>
                            <div class="relative">
                                <input type="text"
                                       x-model="pq"
                                       @focus="open = true"
                                       @input="open = true"
                                       class="input w-full"
                                       placeholder="Search person...">
                                <input type="hidden" :name="'segments[' + index + '][entity_id]'" x-model="seg.entity_id">
                                <div x-show="open && pq.length >= 0" x-cloak
                                     class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg py-1 max-h-48 overflow-y-auto">
                                    <template x-for="p in filteredPeople(pq)" :key="p.id">
                                        <button type="button"
                                                @click="seg.entity_id = p.id; pq = p.name; open = false"
                                                class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50"
                                                x-text="p.name"></button>
                                    </template>
                                    <p x-show="filteredPeople(pq).length === 0" class="px-3 py-2 text-xs text-gray-400 italic">No results</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="flex gap-2 mt-5">
            <button type="submit" class="btn btn-primary">Save & Recognize</button>
            <a href="{{ route('smart-notes.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
