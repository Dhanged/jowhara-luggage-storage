<?php
namespace App\Services;

use App\Repositories\LuggageRepository;

class LuggageService
{
    private LuggageRepository $luggageRepo;

    public function __construct(?LuggageRepository $luggageRepo = null)
    {
        $this->luggageRepo = $luggageRepo ?? new LuggageRepository();
    }

    public function generateTagCode(int $luggageId): string
    {
        return "LS" . str_pad((string)$luggageId, 6, "0", STR_PAD_LEFT);
    }
}
