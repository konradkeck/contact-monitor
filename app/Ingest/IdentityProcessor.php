<?php

namespace App\Ingest;

use App\Models\Identity;
use App\Models\IngestItem;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * Processes ingest items of type 'identity'.
 *
 * Payload fields:
 *   identity_type  string  e.g. email, slack_user, discord_user
 *   value          string  the raw value (email address, user ID, etc.)
 *   display_name   string  optional human name
 *   email_hint     string  optional email address when identity_type != email
 */
class IdentityProcessor
{
    public function process(IngestItem $item): void
    {
        $payload = $item->payload;
        $type    = $payload['identity_type'] ?? 'email';
        $value   = trim($payload['value'] ?? '');

        if ($value === '') {
            $item->update(['status' => 'skipped', 'error_message' => 'Empty value', 'processed_at' => now()]);
            return;
        }

        $valueNorm = strtolower($value);

        // Find or create identity
        $identity = Identity::withTrashed()
            ->where('type', $type)
            ->where('system_slug', $item->system_slug)
            ->where('value_normalized', $valueNorm)
            ->first();

        if ($item->action === 'delete') {
            if ($identity && !$identity->sync_protected) {
                $identity->delete();
            }
            $item->update(['status' => 'done', 'processed_at' => now()]);
            return;
        }

        if ($identity === null) {
            // Try to find a matching person by email (if this is an email identity)
            $personId = null;
            if ($type === 'email') {
                $personId = $this->findPersonByEmail($valueNorm);
            }

            // If we have an email_hint and still no person, try that too
            if ($personId === null && !empty($payload['email_hint'])) {
                $personId = $this->findPersonByEmail(strtolower(trim($payload['email_hint'])));
            }

            $identity = Identity::create([
                'person_id'        => $personId,
                'type'             => $type,
                'system_slug'      => $item->system_slug,
                'value'            => $value,
                'value_normalized' => $valueNorm,
                'meta_json'        => [
                    'display_name' => $payload['display_name'] ?? null,
                    'email_hint'   => $payload['email_hint'] ?? null,
                    'avatar'       => $payload['avatar'] ?? null,
                    'system_type'  => $item->system_type,
                ],
            ]);
        } else {
            if ($identity->trashed()) {
                $identity->restore();
            }

            // Update meta_json fields that may have changed
            $existingMeta = $identity->meta_json ?? [];
            $newMeta      = $existingMeta;
            if (!empty($payload['display_name'])) {
                $newMeta['display_name'] = $payload['display_name'];
            }
            if (!empty($payload['email_hint'])) {
                $newMeta['email_hint'] = $payload['email_hint'];
            }
            if (array_key_exists('avatar', $payload)) {
                $newMeta['avatar'] = $payload['avatar']; // allow null to clear
            }
            if ($newMeta !== $existingMeta) {
                $identity->update(['meta_json' => $newMeta]);
            }
        }

        $item->update([
            'status'      => 'done',
            'entity_type' => Identity::class,
            'entity_id'   => $identity->id,
            'processed_at' => now(),
        ]);
    }

    private function findPersonByEmail(string $email): ?int
    {
        return Identity::where('type', 'email')
            ->where('value_normalized', $email)
            ->whereNotNull('person_id')
            ->value('person_id');
    }
}
