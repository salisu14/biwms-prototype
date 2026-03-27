<?php
// app/Traits/HasEnums.php

namespace App\Traits;

trait HasEnums
{
    /**
     * Cast enum to object in serialization
     */
    public function serializeEnum($enumClass, $value): ?array
    {
        if (!$value) return null;

        $enum = $enumClass::tryFrom($value);

        return $enum ? [
            'value' => $enum->value,
            'label' => $enum->label(),
            'color' => $enum->color(),
            'icon' => $enum->icon(),
        ] : null;
    }
}
