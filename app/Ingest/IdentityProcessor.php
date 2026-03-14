<?php

namespace App\Ingest;

use App\Models\Account;
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
        $type = $payload['identity_type'] ?? 'email';
        $value = trim($payload['value'] ?? '');

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
            if ($identity && ! $identity->sync_protected) {
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
            if ($personId === null && ! empty($payload['email_hint'])) {
                $personId = $this->findPersonByEmail(strtolower(trim($payload['email_hint'])));
            }

            $identity = Identity::create([
                'person_id' => $personId,
                'type' => $type,
                'system_slug' => $item->system_slug,
                'value' => $value,
                'value_normalized' => $valueNorm,
                'is_bot' => (bool) ($payload['is_bot'] ?? false),
                'meta_json' => [
                    'display_name' => $payload['display_name'] ?? null,
                    'email_hint' => $payload['email_hint'] ?? null,
                    'avatar' => $payload['avatar'] ?? null,
                    'system_type' => $item->system_type,
                    'account_external_id' => isset($payload['account_external_id'])
                        ? (string) $payload['account_external_id'] : null,
                ],
            ]);
        } else {
            if ($identity->trashed()) {
                $identity->restore();
            }

            // Update meta_json fields that may have changed
            $existingMeta = $identity->meta_json ?? [];
            $newMeta = $existingMeta;
            if (isset($payload['display_name'])) {
                $newMeta['display_name'] = $payload['display_name'];
            }
            if (isset($payload['email_hint'])) {
                $newMeta['email_hint'] = $payload['email_hint'];
            }
            if (array_key_exists('avatar', $payload)) {
                $newMeta['avatar'] = $payload['avatar']; // allow null to clear
            }
            if (isset($payload['account_external_id'])) {
                $newMeta['account_external_id'] = (string) $payload['account_external_id'];
            }
            if ($newMeta !== $existingMeta) {
                $identity->update(['meta_json' => $newMeta]);
            }
            if (isset($payload['is_bot'])) {
                $identity->update(['is_bot' => (bool) $payload['is_bot']]);
            }
        }

        $item->update([
            'status' => 'done',
            'entity_type' => Identity::class,
            'entity_id' => $identity->id,
            'processed_at' => now(),
        ]);

        // Auto-link person → company for WHMCS/MetricsCube identities.
        // If this identity has an account_external_id and that account is already
        // linked to a company, immediately attach the person to that company.
        $this->autoLinkPersonToCompany($identity, $item);
    }

    private function autoLinkPersonToCompany(Identity $identity, IngestItem $item): void
    {
        if (! $identity->person_id) {
            return;
        }

        $accountExtId = $identity->meta_json['account_external_id'] ?? null;
        if (! $accountExtId) {
            return;
        }

        $account = Account::where('system_slug', $item->system_slug)
            ->where('external_id', (string) $accountExtId)
            ->whereNotNull('company_id')
            ->first();

        if (! $account) {
            return;
        }

        $alreadyLinked = DB::table('company_person')
            ->where('company_id', $account->company_id)
            ->where('person_id', $identity->person_id)
            ->exists();

        if (! $alreadyLinked) {
            DB::table('company_person')->insert([
                'company_id' => $account->company_id,
                'person_id' => $identity->person_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function findPersonByEmail(string $email): ?int
    {
        return Identity::where('type', 'email')
            ->where('value_normalized', $email)
            ->whereNotNull('person_id')
            ->value('person_id');
    }
}
