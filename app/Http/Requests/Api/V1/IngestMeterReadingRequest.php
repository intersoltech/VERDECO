<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Organization\Models\Plant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IngestMeterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        // Check Sanctum ability
        if (! $user->tokenCan('ingest:meter-readings') && ! $user->tokenCan('*')) {
            return false;
        }

        // Check plant access
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
            'meter_type' => ['required', 'string', 'in:electricity_kwh,natural_gas_m3,water_m3,nitrogen_m3'],
            'reading_value' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:16'],
            'is_delta' => ['nullable', 'boolean'],
            'external_id' => ['nullable', 'string', 'max:128'],
            'read_at' => ['nullable', 'date'],
        ];
    }
}
