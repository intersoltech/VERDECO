<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Domain\Organization\Models\Plant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if (! $user->tokenCan('ingest:bulk') && ! $user->tokenCan('*')) {
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
            'format' => ['required', 'string', 'in:csv,json'],
            'file' => ['required_without:payload', 'file', 'max:10240'], // 10MB
            'payload' => ['required_without:file'],
        ];
    }
}
