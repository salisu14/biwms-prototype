<?php

namespace App\DTOs;

class FactoryDTO
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly int|string $businessId,
        public readonly bool $isActive = true
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: strtoupper($data['code']),
            name: $data['name'],
            businessId: $data['business_id'],
            isActive: $data['is_active'] ?? true
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'business_id' => $this->businessId,
            'is_active' => $this->isActive,
        ];
    }
}
