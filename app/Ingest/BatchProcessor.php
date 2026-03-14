<?php

namespace App\Ingest;

use App\Models\IngestBatch;
use App\Models\IngestItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchProcessor
{
    private ConversationProcessor $conversationProc;

    private MessageProcessor $messageProc;

    private IdentityProcessor $identityProc;

    private AccountProcessor $accountProc;

    private ActivityProcessor $activityProc;

    public function __construct()
    {
        $this->conversationProc = new ConversationProcessor;
        $this->messageProc = new MessageProcessor;
        $this->identityProc = new IdentityProcessor;
        $this->accountProc = new AccountProcessor;
        $this->activityProc = new ActivityProcessor;
    }

    /**
     * Ingest a raw batch payload array (already validated).
     * Returns stats array.
     */
    public function ingest(array $batchPayload): array
    {
        $batchUuid = $batchPayload['batch_id'];
        $sourceType = $batchPayload['source_type'];
        $sourceSlug = $batchPayload['source_slug'];
        $items = $batchPayload['items'] ?? [];

        $batch = IngestBatch::create([
            'batch_uuid' => $batchUuid,
            'source_type' => $sourceType,
            'source_slug' => $sourceSlug,
            'item_count' => count($items),
            'status' => 'processing',
        ]);

        $created = $skipped = $failed = 0;

        // Process in an order that respects dependencies:
        // identities → accounts → conversations → messages → activities
        $ordered = $this->orderItems($items);

        foreach ($ordered as $rawItem) {
            $result = $this->ingestItem($batch, $rawItem);
            match ($result) {
                'done' => $created++,
                'skipped' => $skipped++,
                default => $failed++,
            };
        }

        $batch->update([
            'status' => $failed > 0 ? 'failed' : 'done',
            'processed_count' => $created,
            'skipped_count' => $skipped,
            'failed_count' => $failed,
            'processed_at' => now(),
        ]);

        return [
            'batch_uuid' => $batchUuid,
            'processed' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    private function ingestItem(IngestBatch $batch, array $raw): string
    {
        $idempotencyKey = $raw['idempotency_key'];

        // Check if already processed (idempotency)
        $existing = IngestItem::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            // Retry failed items; skip already-done ones
            if ($existing->status !== 'failed') {
                return 'skipped';
            }
            // Re-process the failed item in place
            try {
                DB::transaction(function () use ($existing) {
                    $existing->update(['status' => 'pending', 'error_message' => null]);
                    $this->processItem($existing);
                });

                return $existing->fresh()->status === 'skipped' ? 'skipped' : 'done';
            } catch (\Throwable $e) {
                $existing->update(['status' => 'failed', 'error_message' => substr($e->getMessage(), 0, 500), 'processed_at' => now()]);

                return 'failed';
            }
        }

        // Create item record
        $item = IngestItem::create([
            'ingest_batch_id' => $batch->id,
            'idempotency_key' => $idempotencyKey,
            'item_type' => $raw['type'],
            'action' => $raw['action'] ?? 'upsert',
            'system_type' => $raw['system_type'],
            'system_slug' => $raw['system_slug'],
            'external_id' => $raw['external_id'],
            'payload_hash' => $raw['payload_hash'],
            'payload' => $raw['payload'],
            'status' => 'pending',
        ]);

        try {
            DB::transaction(function () use ($item) {
                $this->processItem($item);
            });

            return $item->fresh()->status === 'skipped' ? 'skipped' : 'done';
        } catch (\Throwable $e) {
            Log::warning('Ingest item failed', [
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);

            $item->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 500),
                'processed_at' => now(),
            ]);

            return 'failed';
        }
    }

    private function processItem(IngestItem $item): void
    {
        match ($item->item_type) {
            'identity' => $this->identityProc->process($item),
            'account' => $this->accountProc->process($item),
            'conversation' => $this->conversationProc->process($item),
            'message' => $this->messageProc->process($item),
            'activity' => $this->activityProc->process($item),
            default => $item->update([
                'status' => 'failed',
                'error_message' => "Unknown item_type: {$item->item_type}",
                'processed_at' => now(),
            ]),
        };
    }

    /**
     * Order items so dependencies are processed first:
     * identity → account → conversation → message → activity
     */
    private function orderItems(array $items): array
    {
        $order = ['identity' => 0, 'account' => 1, 'conversation' => 2, 'message' => 3, 'activity' => 4];

        usort($items, function ($a, $b) use ($order) {
            $pa = $order[$a['type']] ?? 99;
            $pb = $order[$b['type']] ?? 99;

            return $pa <=> $pb;
        });

        return $items;
    }
}
