<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnifiedCampaignRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'run_number',
        'status',
        'started_at',
        'completed_at',
        'scheduled_at',
        'next_schedule_at',
        'total_contacts',
        'processed_contacts',
        'sent_count',
        'failed_count',
        'delivered_count',
        'dispatch_history',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'next_schedule_at' => 'datetime',
        'dispatch_history' => 'array',
        'run_number' => 'integer',
        'total_contacts' => 'integer',
        'processed_contacts' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'delivered_count' => 'integer',
    ];

    /**
     * Get the campaign that owns this run log
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(UnifiedCampaign::class, 'campaign_id');
    }
}
