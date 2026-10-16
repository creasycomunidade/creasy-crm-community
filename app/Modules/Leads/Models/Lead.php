<?php

namespace App\Modules\Leads\Models;

use App\Support\Concerns\MasksDemoData;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory, MasksDemoData;

    protected static function newFactory()
    {
        return LeadFactory::new();
    }

    protected $table = 'leads';

    /**
     * Scraped-lead PII masked in demo mode (see App\Support\Concerns\MasksDemoData).
     *
     * @return array<string, string>
     */
    protected function demoMask(): array
    {
        return [
            'name' => 'name',
            'phone' => 'phone',
            'whatsapp_phone' => 'phone',
            'contact_channels' => 'null',
            'email' => 'email',
            'website' => 'redact',
            'address' => 'redact',
            'lat' => 'null',
            'lng' => 'null',
        ];
    }

    protected $fillable = ['workspace_id', 'source_provider', 'source_id', 'name', 'phone', 'email', 'whatsapp_phone', 'contact_channels', 'website', 'address', 'city', 'country', 'lat', 'lng', 'category', 'rating', 'review_count', 'google_place_id', 'whatsapp_status', 'pushed_to_contacts'];

    protected function casts(): array
    {
        return [
            'pushed_to_contacts' => 'boolean',
            'rating' => 'float',
            'contact_channels' => 'array',
        ];
    }

    public function scrapeJob()
    {
        return $this->belongsTo(LeadScrapeJob::class, 'workspace_id', 'workspace_id');
    }
}
