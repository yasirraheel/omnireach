<?php

namespace App\Services\Automation;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Automation\WorkflowNode;
use App\Models\Automation\WorkflowExecution;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ConditionEvaluatorService
{
    /**
     * Evaluate a condition node
     */
    public function evaluate(WorkflowNode $node, Contact $contact, WorkflowExecution $execution): bool
    {
        $conditionType = $node->action_type;
        $config = $node->config ?? [];

        Log::debug("Evaluating condition", [
            'condition_type' => $conditionType,
            'node_id' => $node->id,
            'contact_id' => $contact->id,
        ]);

        return match ($conditionType) {
            'has_tag' => $this->evaluateHasTag($config, $contact),
            'field_equals' => $this->evaluateFieldEquals($config, $contact),
            'in_group' => $this->evaluateInGroup($config, $contact),
            'random_split' => $this->evaluateRandomSplit($config),
            'day_of_week' => $this->evaluateDayOfWeek($config),
            'time_between' => $this->evaluateTimeBetween($config),
            default => true,
        };
    }

    /**
     * Check if contact has a specific tag
     */
    protected function evaluateHasTag(array $config, Contact $contact): bool
    {
        $tag = $config['tag'] ?? null;

        if (!$tag) {
            return false;
        }

        $metaData = $contact->meta_data ? json_decode($contact->meta_data, true) : [];
        $tags = $metaData['tags'] ?? [];

        return in_array($tag, $tags);
    }

    /**
     * Check if a contact field matches a value
     */
    protected function evaluateFieldEquals(array $config, Contact $contact): bool
    {
        $field = $config['field'] ?? null;
        $operator = $config['operator'] ?? 'equals';
        $value = $config['value'] ?? null;

        if (!$field) {
            return false;
        }

        // Get the field value
        $fieldValue = $this->getContactFieldValue($contact, $field);

        // Apply operator
        return match ($operator) {
            'equals', '=' => strtolower((string)$fieldValue) === strtolower((string)$value),
            'not_equals', '!=' => strtolower((string)$fieldValue) !== strtolower((string)$value),
            'contains' => str_contains(strtolower((string)$fieldValue), strtolower((string)$value)),
            'not_contains' => !str_contains(strtolower((string)$fieldValue), strtolower((string)$value)),
            'starts_with' => str_starts_with(strtolower((string)$fieldValue), strtolower((string)$value)),
            'ends_with' => str_ends_with(strtolower((string)$fieldValue), strtolower((string)$value)),
            'is_empty' => empty($fieldValue),
            'is_not_empty' => !empty($fieldValue),
            'greater_than', '>' => is_numeric($fieldValue) && is_numeric($value) && (float)$fieldValue > (float)$value,
            'less_than', '<' => is_numeric($fieldValue) && is_numeric($value) && (float)$fieldValue < (float)$value,
            'greater_or_equal', '>=' => is_numeric($fieldValue) && is_numeric($value) && (float)$fieldValue >= (float)$value,
            'less_or_equal', '<=' => is_numeric($fieldValue) && is_numeric($value) && (float)$fieldValue <= (float)$value,
            default => false,
        };
    }

    /**
     * Get a field value from contact (supports standard fields and meta_data)
     */
    protected function getContactFieldValue(Contact $contact, string $field): mixed
    {
        // Standard fields
        $standardFields = ['first_name', 'last_name', 'email_contact', 'sms_contact', 'whatsapp_contact', 'status', 'email_verification'];

        if (in_array($field, $standardFields)) {
            return $contact->$field;
        }

        // Meta data fields
        $metaData = $contact->meta_data ? json_decode($contact->meta_data, true) : [];

        if (isset($metaData[$field])) {
            $value = $metaData[$field];
            return is_array($value) ? ($value['value'] ?? null) : $value;
        }

        return null;
    }

    /**
     * Check if contact exists in a specific group
     */
    protected function evaluateInGroup(array $config, Contact $contact): bool
    {
        $groupId = $config['group_id'] ?? null;

        if (!$groupId) {
            return false;
        }

        // Check if contact with same identifier exists in the target group
        $query = Contact::where('group_id', $groupId);

        if ($contact->email_contact) {
            $query->orWhere(function ($q) use ($groupId, $contact) {
                $q->where('group_id', $groupId)
                  ->where('email_contact', $contact->email_contact);
            });
        }

        if ($contact->sms_contact) {
            $query->orWhere(function ($q) use ($groupId, $contact) {
                $q->where('group_id', $groupId)
                  ->where('sms_contact', $contact->sms_contact);
            });
        }

        if ($contact->whatsapp_contact) {
            $query->orWhere(function ($q) use ($groupId, $contact) {
                $q->where('group_id', $groupId)
                  ->where('whatsapp_contact', $contact->whatsapp_contact);
            });
        }

        return $query->exists();
    }

    /**
     * Random split for A/B testing
     */
    protected function evaluateRandomSplit(array $config): bool
    {
        $percentage = (int)($config['percentage'] ?? 50);

        // Generate random number 1-100
        $random = mt_rand(1, 100);

        // Return true if random falls within percentage
        return $random <= $percentage;
    }

    /**
     * Check if today is one of the specified days
     */
    protected function evaluateDayOfWeek(array $config): bool
    {
        $days = $config['days'] ?? [];
        $timezone = $config['timezone'] ?? config('app.timezone');

        if (empty($days)) {
            return true;
        }

        $today = Carbon::now($timezone)->dayOfWeek;

        // Map day names to numbers (0 = Sunday, 6 = Saturday)
        $dayMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        // Convert day names to numbers
        $allowedDays = collect($days)->map(function ($day) use ($dayMap) {
            if (is_numeric($day)) {
                return (int)$day;
            }
            return $dayMap[strtolower($day)] ?? null;
        })->filter()->values()->toArray();

        return in_array($today, $allowedDays);
    }

    /**
     * Check if current time is between specified hours
     */
    protected function evaluateTimeBetween(array $config): bool
    {
        $startTime = $config['start_time'] ?? '09:00';
        $endTime = $config['end_time'] ?? '17:00';
        $timezone = $config['timezone'] ?? config('app.timezone');

        $now = Carbon::now($timezone);
        $start = Carbon::parse($startTime, $timezone);
        $end = Carbon::parse($endTime, $timezone);

        // Handle overnight ranges (e.g., 22:00 to 06:00)
        if ($end->lt($start)) {
            // Either after start OR before end
            return $now->gte($start) || $now->lte($end);
        }

        return $now->between($start, $end);
    }

    /**
     * Evaluate multiple conditions with AND/OR logic
     */
    public function evaluateMultiple(array $conditions, Contact $contact, WorkflowExecution $execution, string $logic = 'AND'): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $results = [];
        foreach ($conditions as $condition) {
            $node = new WorkflowNode([
                'action_type' => $condition['type'],
                'config' => $condition['config'] ?? [],
            ]);
            $results[] = $this->evaluate($node, $contact, $execution);
        }

        if ($logic === 'OR') {
            return in_array(true, $results, true);
        }

        // AND logic (default)
        return !in_array(false, $results, true);
    }

    /**
     * Get list of available condition types with metadata
     */
    public static function getAvailableConditions(): array
    {
        return WorkflowNode::CONDITION_TYPES;
    }

    /**
     * Get available operators for field comparison
     */
    public static function getFieldOperators(): array
    {
        return [
            'equals' => 'Equals',
            'not_equals' => 'Not Equals',
            'contains' => 'Contains',
            'not_contains' => 'Does Not Contain',
            'starts_with' => 'Starts With',
            'ends_with' => 'Ends With',
            'is_empty' => 'Is Empty',
            'is_not_empty' => 'Is Not Empty',
            'greater_than' => 'Greater Than',
            'less_than' => 'Less Than',
            'greater_or_equal' => 'Greater Than or Equal',
            'less_or_equal' => 'Less Than or Equal',
        ];
    }
}
