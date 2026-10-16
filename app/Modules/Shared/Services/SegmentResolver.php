<?php

namespace App\Modules\Shared\Services;

use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Segment;
use Illuminate\Database\Eloquent\Builder;

class SegmentResolver
{
    private const ALLOWED_FIELDS = [
        'phone_e164', 'email', 'first_name', 'last_name',
        'country', 'language', 'source',
        'opt_in_whatsapp', 'opt_in_sms', 'opt_in_email',
        'created_at', 'last_seen_at',
    ];

    private const OPERATORS = ['=', '!=', 'like', 'not_like', '<', '>', '<=', '>=', 'is_null', 'is_not_null'];

    /** Build a query for all contacts matching the segment rules. */
    public function query(Segment $segment): Builder
    {
        $query = Contact::where('workspace_id', $segment->workspace_id);

        if ($segment->type === 'static') {
            return $query->whereHas('segments', fn ($q) => $q->where('segments.id', $segment->id));
        }

        $rules = $segment->rules_json ?? [];
        $this->applyRules($query, $rules);

        return $query;
    }

    /** Materialise a dynamic segment into segment_contact pivot. */
    public function materialise(Segment $segment): int
    {
        if ($segment->type !== 'dynamic') {
            return 0;
        }

        $ids = $this->query($segment)->pluck('id');
        $segment->contacts()->sync($ids);
        $segment->update(['contact_count' => $ids->count()]);

        return $ids->count();
    }

    private function applyRules(Builder $query, array $rules): void
    {
        $combinator = strtoupper($rules['combinator'] ?? 'AND');

        foreach ($rules['conditions'] ?? [] as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;

            if (! $field || ! in_array($field, self::ALLOWED_FIELDS, true)) {
                continue;
            }
            if (! in_array($operator, self::OPERATORS, true)) {
                continue;
            }

            $method = $combinator === 'OR' ? 'orWhere' : 'where';

            match ($operator) {
                'is_null' => $query->{$method.'Null'}($field),
                'is_not_null' => $query->{$method.'NotNull'}($field),
                'like' => $query->{$method}($field, 'like', '%'.$value.'%'),
                'not_like' => $query->{$method}($field, 'not like', '%'.$value.'%'),
                default => $query->{$method}($field, $operator, $value),
            };
        }
    }
}
