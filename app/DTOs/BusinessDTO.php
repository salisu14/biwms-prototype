<?php

namespace App\DTOs;

class BusinessDTO
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly bool $isActive = true
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: strtoupper($data['code']),
            name: $data['name'],
            isActive: $data['is_active'] ?? true
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->isActive,
        ];
    }
}
