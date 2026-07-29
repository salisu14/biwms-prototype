<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\ProductionQualityCheckAttachment;
use App\Models\User;
use App\Services\Manufacturing\ProductionQualityAttachmentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionQualityAttachmentController extends Controller
{
    public function __invoke(ProductionQualityCheckAttachment $attachment, ProductionQualityAttachmentService $service): StreamedResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return $service->download($attachment, $user);
    }
}
