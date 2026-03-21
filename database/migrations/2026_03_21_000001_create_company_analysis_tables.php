<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop placeholder table
        Schema::dropIfExists('company_analyses');

        // Configurable analysis step templates
        Schema::create('analysis_steps', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->text('prompt_template');
            $table->boolean('is_enabled')->default(true);
            $table->smallInteger('sort_order')->unsigned()->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        // One row per manual analysis trigger
        Schema::create('analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->jsonb('base_context_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
        });

        // One row per step execution within a run
        Schema::create('analysis_step_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('analysis_steps')->nullOnDelete();
            $table->string('step_key', 80);
            $table->string('status', 20)->default('pending');
            $table->text('prompt_template_used')->nullable();
            $table->text('rendered_prompt')->nullable();
            $table->text('raw_response')->nullable();
            $table->jsonb('parsed_response')->nullable();
            $table->text('error_message')->nullable();
            $table->string('model_name', 100)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['run_id', 'step_key']);
        });

        // Scalar structured result fields
        Schema::create('analysis_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->foreignId('step_run_id')->constrained('analysis_step_runs')->cascadeOnDelete();
            $table->string('field_group', 50)->default('general');
            $table->string('field_key', 100);
            $table->text('field_value')->nullable();
            $table->string('field_type', 30)->default('string');
            $table->string('confidence', 10)->nullable();
            $table->boolean('is_inferred')->default(false);
            $table->smallInteger('sort_order')->unsigned()->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['run_id', 'field_key']);
            $table->index(['company_id', 'field_key']);
        });

        // Repeatable structured entities
        Schema::create('analysis_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('analysis_runs')->cascadeOnDelete();
            $table->foreignId('step_run_id')->constrained('analysis_step_runs')->cascadeOnDelete();
            $table->string('entity_type', 50);
            $table->string('display_name', 255)->nullable();
            $table->jsonb('data_json')->default('{}');
            $table->string('confidence', 10)->nullable();
            $table->smallInteger('sort_order')->unsigned()->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'entity_type']);
        });

        // Seed default analysis steps
        $this->seedDefaultSteps();
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_entities');
        Schema::dropIfExists('analysis_fields');
        Schema::dropIfExists('analysis_step_runs');
        Schema::dropIfExists('analysis_runs');
        Schema::dropIfExists('analysis_steps');

        // Restore placeholder table
        Schema::create('company_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->text('content');
            $table->string('model_name', 100);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function seedDefaultSteps(): void
    {
        $now = now();

        DB::table('analysis_steps')->insert([
            [
                'key' => 'company_identity_resolution',
                'name' => 'Company Identity Resolution',
                'description' => 'Resolve the most likely company and domain from weak or outdated lead data',
                'prompt_template' => 'You are a company identification agent.

Your task is to identify the most likely company behind a lead using weak and possibly outdated signals.

Input:
- Person name: {{person_name}}
- Claimed company name: {{company_name}}
- Email: {{email}}
- Email domain: {{email_domain}}
- Is free email domain: {{is_free_email_domain}}
- Is disposable email domain: {{is_disposable_email_domain}}
- Domain found in last message or signature: {{domain_from_last_message}}
- Last message excerpt: {{last_message_excerpt}}
- Postal address: {{address}}

Rules:
1. Prefer company-owned domains over claimed company name when they conflict.
2. Ignore email domain if it is free or disposable.
3. Use domain found in signature, website URL, or message body as a strong signal.
4. Claimed company name may be outdated, misspelled, partial, or belong to an old employer.
5. Do not guess. If evidence is weak, say so.
6. Keep output compact.
7. Return JSON only.

Return JSON in this schema:
{
  "resolved_company_name": string|null,
  "resolved_domain": string|null,
  "other_known_names": string[],
  "country_hint": string|null,
  "city_hint": string|null,
  "confidence": "high"|"medium"|"low",
  "evidence": [
    {"type": "email_domain|message_domain|company_name|address|other", "value": string, "note": string}
  ],
  "needs_external_lookup": boolean,
  "ambiguity_note": string|null
}',
                'is_enabled' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'company_profile_enrichment',
                'name' => 'Company Profile Enrichment',
                'description' => 'Build a compact commercial profile for the resolved company',
                'prompt_template' => 'You are a B2B company research agent.

Your task is to create a compact company profile using the identified company.
Be conservative. Do not invent. If a field cannot be supported with reasonable confidence, return null.

Identified company:
- Company name: {{previous.company_identity_resolution.resolved_company_name}}
- Domain: {{previous.company_identity_resolution.resolved_domain}}
- Country hint: {{previous.company_identity_resolution.country_hint}}
- City hint: {{previous.company_identity_resolution.city_hint}}
- Confidence: {{previous.company_identity_resolution.confidence}}
- Known aliases: {{previous.company_identity_resolution.other_known_names}}

Person context:
- Person name: {{person_name}}
- Email: {{email}}

Goals:
Find only the most useful commercial facts:
- official company name
- country
- city or HQ
- operating countries or main market
- market position
- approximate customer count
- owner type
- major shareholders or investors
- funding history
- likely job title of the person if inferable
- C-level people
- founded year

Rules:
1. Prefer precision over completeness.
2. Separate confirmed facts from inferred facts.
3. Do not output long explanations.
4. Return null instead of guessing.
5. Keep lists short.
6. Return JSON only.

Return JSON in this schema:
{
  "official_company_name": string|null,
  "domain": string|null,
  "hq_country": string|null,
  "hq_city": string|null,
  "operating_countries": string[],
  "market_position": string|null,
  "customer_count_estimate": {
    "value": string|null,
    "confidence": "high"|"medium"|"low"|null,
    "note": string|null
  },
  "owner_type": "founder_owned"|"private_company"|"public_company"|"private_equity"|"venture_backed"|"subsidiary"|"unknown"|null,
  "owners_or_parent_entities": string[],
  "major_shareholders_or_investors": string[],
  "funding_history": [
    {
      "type": string,
      "date": string|null,
      "amount": string|null,
      "investors": string[]
    }
  ],
  "person_likely_role": {
    "name": string|null,
    "role": string|null,
    "confidence": "high"|"medium"|"low"|null
  },
  "c_level_people": [
    {
      "name": string,
      "role": string
    }
  ],
  "founded_year": string|null,
  "ambiguity_flag": boolean,
  "notes": string[]
}',
                'is_enabled' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'gap_fill_missing_fields',
                'name' => 'Gap Fill Missing Fields',
                'description' => 'Fill only missing or low-confidence fields from previous steps',
                'prompt_template' => 'You are a company enrichment gap-filling agent.

You already have a partial company profile.
Your task is to fill only missing fields and improve only low-confidence fields.
Do not repeat existing fields unless you are correcting them.

Known company profile:
{{previous.company_profile_enrichment}}

Resolved identity:
{{previous.company_identity_resolution}}

Rules:
1. Update only missing or weak fields.
2. Do not rewrite everything.
3. Return JSON only.
4. If nothing reliable can be added, return {"no_update": true}',
                'is_enabled' => true,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
};
