<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'primary_domain',
        'timezone',
        'meta_json',
        'merged_into_id',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'merged_into_id');
    }

    public function mergedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'merged_into_id');
    }

    public function scopeNotMerged(Builder $query): Builder
    {
        return $query->whereNull('merged_into_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(CompanyDomain::class);
    }

    public function primaryDomain(): HasMany
    {
        return $this->hasMany(CompanyDomain::class)->where('is_primary', true);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(CompanyAlias::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function brandStatuses(): HasMany
    {
        return $this->hasMany(CompanyBrandStatus::class);
    }

    public function brandProducts(): BelongsToMany
    {
        return $this->belongsToMany(BrandProduct::class, 'company_brand_statuses')
            ->using(CompanyBrandStatus::class)
            ->withPivot(['stage', 'evaluation_score', 'evaluation_notes', 'last_evaluated_at'])
            ->withTimestamps();
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'company_person')
            ->withPivot(['role', 'started_at', 'ended_at'])
            ->withTimestamps();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function activities(): \Illuminate\Database\Eloquent\Builder
    {
        // Include this company + any companies merged into it
        $allCompanyIds = collect([$this->id])
            ->merge(DB::table('companies')->where('merged_into_id', $this->id)->pluck('id'))
            ->unique()->all();

        $personIds = DB::table('company_person')
            ->whereIn('company_id', $allCompanyIds)
            ->pluck('person_id');

        $convExtIds = DB::table('conversations')
            ->whereIn('company_id', $allCompanyIds)
            ->whereNotNull('external_thread_id')
            ->pluck('external_thread_id');

        return Activity::where(function ($q) use ($allCompanyIds, $personIds, $convExtIds) {
            $q->whereIn('company_id', $allCompanyIds);
            if ($personIds->isNotEmpty()) {
                $q->orWhereIn('person_id', $personIds);
            }
            if ($convExtIds->isNotEmpty()) {
                $q->orWhereIn(
                    DB::raw("meta_json->>'conversation_external_id'"),
                    $convExtIds
                );
            }
        });
    }

    public function notes(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        // Notes linked to this company via note_links
        return $this->hasManyThrough(
            Note::class,
            NoteLink::class,
            'linkable_id',
            'id',
            'id',
            'note_id'
        )->where('note_links.linkable_type', self::class);
    }
}
