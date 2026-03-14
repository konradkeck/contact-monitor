<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;

trait BuildsConvSubjectMap
{
    private function buildConvSubjectMap(array $activities): array
    {
        $extIds = [];
        foreach ($activities as $activity) {
            $m = $activity->meta_json ?? [];
            // Resolve effective channel type — meta_json may store channel_type directly,
            // or only system_type (e.g. WHMCS activities store system_type='whmcs', no channel_type key)
            $effectiveChannelType = $m['channel_type'] ?? match ($m['system_type'] ?? '') {
                'whmcs', 'metricscube' => 'ticket',
                default => null,
            };
            if ($effectiveChannelType === 'ticket' && ! empty($m['conversation_external_id'])) {
                $extIds[] = $m['conversation_external_id'];
            }
            $mcType = $m['mc_type'] ?? '';
            if (in_array($mcType, ['Opened Ticket', 'Closed Ticket', 'Ticket Replied'], true) && ! empty($m['relation_id'])) {
                $extIds[] = 'ticket_'.$m['relation_id'];
            }
        }
        if (empty($extIds)) {
            return [];
        }
        $map = [];
        DB::table('conversations')
            ->whereIn('external_thread_id', array_unique($extIds))
            ->select('id', 'external_thread_id', 'subject')
            ->get()
            ->each(function ($c) use (&$map) {
                $map[$c->external_thread_id] = ['id' => $c->id, 'subject' => $c->subject];
            });

        return $map;
    }
}
