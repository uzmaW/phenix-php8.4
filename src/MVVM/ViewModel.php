<?php

namespace Phoenix\MVVM;

use Phoenix\Core\Traits\Observable;

abstract class ViewModel
{
    use Observable;

    protected function set(string $property, mixed $value): void
    {
        $this->$property = $value;
        $this->notify('propertyChanged', (object) [
            'property' => $property,
            'value' => $value,
        ]);
    }
}
