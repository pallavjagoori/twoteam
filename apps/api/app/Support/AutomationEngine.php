<?php

namespace App\Support;

use App\Models\AutomationRule;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class AutomationEngine
{
    public static function dispatch(string $event, Conversation $conversation, string $eventKey, ?Message $message = null): int
    {
        $conversation->refresh();
        $executed = 0;
        $rules = AutomationRule::query()->where('account_id', $conversation->account_id)->where('event_name', $event)->where('active', true)->get();
        foreach ($rules as $rule) {
            if (! self::matches($rule->conditions, $conversation, $message)) {
                continue;
            }
            $created = DB::table('automation_executions')->insertOrIgnore(['automation_rule_id' => $rule->id, 'conversation_id' => $conversation->id, 'event_key' => $eventKey, 'created_at' => now(), 'updated_at' => now()]);
            if (! $created) {
                continue;
            }
            $user = $conversation->assignee ?: $conversation->account->users()->first();
            if ($user) {
                MacroExecutor::run($rule, $conversation, $user);
                $executed++;
            }
        }

        return $executed;
    }

    private static function matches(array $conditions, Conversation $conversation, ?Message $message): bool
    {
        if ($conditions === []) {
            return true;
        }
        $result = null;
        foreach ($conditions as $condition) {
            $actual = self::value($condition['attribute_key'], $conversation, $message);
            $expected = $condition['values'] ?? [];
            $matched = self::compare($actual, $condition['filter_operator'], $expected);
            $result = $result === null || strtoupper($condition['query_operator'] ?? 'AND') === 'OR' ? ($result ?? false) || $matched : $result && $matched;
        }

        return (bool) $result;
    }

    private static function value(string $key, Conversation $conversation, ?Message $message): mixed
    {
        return match ($key) {
            'content' => $message?->content,
            'message_type' => $message?->message_type,
            'status' => $conversation->status,
            'priority' => $conversation->priority,
            'inbox_id' => $conversation->inbox_id,
            'labels' => $conversation->labels()->pluck('title')->all(),
        };
    }

    private static function compare(mixed $actual, string $operator, array $expected): bool
    {
        $values = is_array($actual) ? $actual : [(string) $actual];
        $matches = array_intersect(array_map('strval', $values), array_map('strval', $expected)) !== [];

        return match ($operator) {
            'equal_to', 'contains' => $matches,
            'not_equal_to', 'does_not_contain' => ! $matches,
        };
    }
}
