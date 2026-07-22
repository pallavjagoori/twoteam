<?php

namespace App\Support;

use App\Models\AutomationRule;

class AutomationPayload
{
    public static function make(AutomationRule $rule): array
    {
        return [
            'id' => $rule->id, 'account_id' => $rule->account_id, 'name' => $rule->name,
            'description' => $rule->description, 'event_name' => $rule->event_name,
            'conditions' => $rule->conditions, 'actions' => $rule->actions,
            'created_on' => $rule->created_at->timestamp, 'active' => $rule->active, 'files' => [],
        ];
    }
}
