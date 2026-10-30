<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Organization\Models\Plant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IngestDowntimeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if (! $user->tokenCan('ingest:downtime-events') && ! $user->tokenCan('*')) {
            return false;
        }

        $plantId = $this->input('plant_id');
        $plantCode = $this->input('plant_code');

        if ($plantCode && ! $plantId) {
            $plantId = Plant::where('code', $plantCode)->value('id');
            $this->merge(['plant_id' => $plantId]);
        }

        if (! $plantId) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return $user->plants()->where('plants.id', $plantId)->exists();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plant_id' => ['required_without:plant_code', 'integer', 'exists:plants,id'],
            'plant_code' => ['required_without:plant_id', 'string', 'exists:plants,code'],
            'line_id' => ['required_without:line_code', 'integer', 'exists:production_lines,id'],
            'line_code' => ['required_without:line_id', 'string'],
            'downtime_type' => ['nullable', 'string', 'in:planned,unplanned'],
            'reason_code' => ['required_without:downtime_reason', 'string', 'max:64'],
            'downtime_reason' => ['nullable', 'string', 'max:64'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'external_id' => ['nullable', 'string', 'max:128'],
        ];
    }
}
