<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Organization\Models\Plant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IngestProductionTickRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if (! $user->tokenCan('ingest:production-ticks') && ! $user->tokenCan('*')) {
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
            'line_id' => ['nullable', 'integer', 'exists:production_lines,id'],
            'line_code' => ['nullable', 'string'],
            'production_batch_id' => ['nullable', 'integer', 'exists:production_batches,id'],
            'batch_number' => ['nullable', 'string'],
            'weight' => ['required_without:reading_value', 'numeric', 'min:0'],
            'reading_value' => ['required_without:weight', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'in:kg,lb,lbs'],
            'external_id' => ['nullable', 'string', 'max:128'],
            'read_at' => ['nullable', 'date'],
        ];
    }
}
