<?php

namespace Phoenix\Core\Traits;

trait Sealed
{
    // Prevents external instantiation and inheritance
    // Note: For enums, this trait is used as a marker only
    // since PHP enums already prevent external instantiation
}
