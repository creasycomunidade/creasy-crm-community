<?php

namespace App\Modules\Leads\Models;

use Illuminate\Database\Eloquent\Model;

class LeadScrapeJob extends Model
{
    protected $table = 'lead_scrape_jobs';

    protected $fillable = ['workspace_id', 'source', 'keyword', 'location', 'radius_meters', 'result_limit', 'status', 'leads_found', 'error', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
