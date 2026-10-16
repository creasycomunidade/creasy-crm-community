<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Demo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $demo = Demo::active();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'phone_e164' => Demo::phone($this->phone_e164),
            'email' => Demo::email($this->email),
            'website_url' => $demo ? null : $this->website_url,
            'first_name' => Demo::name($this->first_name),
            'last_name' => Demo::name($this->last_name),
            'full_name' => Demo::name($this->full_name),
            'country' => $this->country,
            'language' => $this->language,
            'opt_in_whatsapp' => (bool) $this->opt_in_whatsapp,
            'opt_in_sms' => (bool) $this->opt_in_sms,
            'opt_in_email' => (bool) $this->opt_in_email,
            'custom_fields' => $demo ? Demo::maskArrayValues($this->custom_fields ?? []) : ($this->custom_fields ?? []),
            'social_profiles' => $demo ? [] : ($this->social_profiles ?? []),
            'contact_links' => $demo ? [] : ($this->contact_links ?? []),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'avatar_url' => $demo ? null : $this->avatar_url,
            'workspace_id' => $this->workspace_id,
            'source' => $this->source,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
