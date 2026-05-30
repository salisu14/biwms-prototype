<?php

namespace App\Services\Company;

use App\Models\CompanyInformation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CompanyInformationService
{
    /**
     * Get or create the active business company profile.
     */
    public function get(?int $businessId = null): CompanyInformation
    {
        return CompanyInformation::getInstance($businessId);
    }

    /**
     * Update company information
     */
    public function update(array $data, ?int $businessId = null): CompanyInformation
    {
        $resolvedBusinessId = $businessId ?? (isset($data['business_id']) ? (int) $data['business_id'] : null);
        $company = CompanyInformation::getInstance($resolvedBusinessId);

        return $this->updateRecord($company, $data, $resolvedBusinessId);
    }

    public function updateRecord(CompanyInformation $company, array $data, ?int $businessId = null): CompanyInformation
    {
        $resolvedBusinessId = $businessId ?? (isset($data['business_id']) ? (int) $data['business_id'] : (int) $company->business_id);
        $previousLogoPath = $company->logo_path;
        $previousFaviconPath = $company->favicon_path;

        // Handle logo upload
        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $data['logo_path'] = $this->handleLogoUpload($data['logo'], $company->logo_path);
            unset($data['logo']);
        }

        // Handle favicon upload
        if (isset($data['favicon']) && $data['favicon'] instanceof UploadedFile) {
            $data['favicon_path'] = $this->handleFaviconUpload($data['favicon'], $company->favicon_path);
            unset($data['favicon']);
        }

        // Remove logo if requested
        if (($data['remove_logo'] ?? false) && $company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $data['logo_path'] = null;
        }
        unset($data['remove_logo']);

        if (($data['remove_favicon'] ?? false) && $company->favicon_path) {
            Storage::disk('public')->delete($company->favicon_path);
            $data['favicon_path'] = null;
        }
        unset($data['remove_favicon']);

        $data['business_id'] = $resolvedBusinessId;

        $company->update($data);

        // If file paths were replaced by direct FileUpload binding, remove old files.
        if (
            array_key_exists('logo_path', $data) &&
            $previousLogoPath &&
            $data['logo_path'] !== $previousLogoPath
        ) {
            Storage::disk('public')->delete($previousLogoPath);
        }

        if (
            array_key_exists('favicon_path', $data) &&
            $previousFaviconPath &&
            $data['favicon_path'] !== $previousFaviconPath
        ) {
            Storage::disk('public')->delete($previousFaviconPath);
        }

        return $company->fresh();
    }

    /**
     * Handle logo upload with validation
     */
    private function handleLogoUpload(UploadedFile $file, ?string $oldPath): string
    {
        // Validate
        $allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
        if (! in_array($file->getMimeType(), $allowedTypes)) {
            throw ValidationException::withMessages([
                'logo' => 'Logo must be a JPEG, PNG, SVG, or WebP image.',
            ]);
        }

        if ($file->getSize() > 2 * 1024 * 1024) { // 2MB max
            throw ValidationException::withMessages([
                'logo' => 'Logo must not exceed 2MB.',
            ]);
        }

        // Delete old logo
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        // Store new logo
        return $file->store('company/logos', 'public');
    }

    /**
     * Handle favicon upload
     */
    private function handleFaviconUpload(UploadedFile $file, ?string $oldPath): string
    {
        $allowedTypes = ['image/x-icon', 'image/png', 'image/svg+xml'];
        if (! in_array($file->getMimeType(), $allowedTypes)) {
            throw ValidationException::withMessages([
                'favicon' => 'Favicon must be an ICO, PNG, or SVG.',
            ]);
        }

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $file->store('company/favicons', 'public');
    }

    /**
     * Get company info for PDF/report headers
     */
    public function getReportHeader(): array
    {
        $company = $this->get();

        return [
            'name' => $company->company_name,
            'trading_name' => $company->trading_name,
            'address_lines' => $company->getAddressLines(),
            'phone' => $company->phone_no,
            'email' => $company->email,
            'website' => $company->website,
            'logo_url' => $company->logo_url,
            'logo_path' => $company->logo_path,
            'logo_abs_path' => $company->logo_path ? public_path('storage/'.$company->logo_path) : null,
            'logo_data_uri' => $this->buildLogoDataUri($company->logo_path),
            'tax_no' => $company->tax_registration_no,
            'registration_no' => $company->registration_no,
        ];
    }

    /**
     * Get company info for invoice footer
     */
    public function getInvoiceFooter(): string
    {
        $company = $this->get();

        $parts = array_filter([
            $company->company_name,
            $company->phone_no ? "Tel: {$company->phone_no}" : null,
            $company->email,
            $company->tax_registration_no ? "Tax No: {$company->tax_registration_no}" : null,
        ]);

        return implode(' | ', $parts);
    }

    private function buildLogoDataUri(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $absolutePath = public_path('storage/'.$path);
        if (! is_file($absolutePath)) {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';
        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
