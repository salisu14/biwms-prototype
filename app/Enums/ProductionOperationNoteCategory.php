<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionOperationNoteCategory: string
{
    case General = 'general';
    case Safety = 'safety';
    case Quality = 'quality';
    case Material = 'material';
    case Machine = 'machine';
    case Handover = 'handover';
}
