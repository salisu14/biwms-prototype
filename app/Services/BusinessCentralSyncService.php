<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class BusinessCentralSyncService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('dynamics.connections.business_central');
    }

    /**
     * Get OAuth Token for Business Central
     */
    protected function getToken(): string
    {
        return Cache::remember('bc_auth_token', 3500, function () {
            $response = Http::asForm()->post($this->config['oauth']['token_url'], [
                'client_id' => $this->config['oauth']['client_id'],
                'client_secret' => $this->config['oauth']['client_secret'],
                'grant_type' => 'client_credentials',
                'scope' => $this->config['oauth']['scope'],
            ]);

            if ($response->failed()) {
                Log::error('Business Central Auth Failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new Exception('Failed to authenticate with Business Central');
            }

            return $response->json('access_token');
        });
    }

    /**
     * Make a request to Business Central OData API
     */
    public function request(string $endpoint, string $method = 'GET', array $data = [])
    {
        $url = rtrim($this->config['base_url'], '/') . '/' . ltrim($endpoint, '/');
        
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->send($method, $url, ['query' => $data]);

        if ($response->failed()) {
            Log::error('Business Central API Request Failed', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new Exception("Business Central API error: {$response->status()}");
        }

        return $response->json();
    }

    /**
     * Sync Employees from Business Central
     */
    public function syncEmployeesFromBC(?string $lastSyncDate = null): array
    {
        $endpoint = "Company('{$this->config['company_id']}')/Employees";
        
        $params = [];
        if ($lastSyncDate) {
            $params['$filter'] = "Last_Modified_Date_Time gt {$lastSyncDate}";
        }

        try {
            $data = $this->request($endpoint, 'GET', $params);
            
            $processed = 0;
            $synced = 0;
            $errors = [];

            foreach ($data['value'] ?? [] as $bcEmployee) {
                $processed++;
                try {
                    // Map BC fields to local Employee model
                    \App\Models\Employee::updateOrCreate(
                        ['employee_number' => $bcEmployee['No']],
                        [
                            'first_name' => $bcEmployee['First_Name'] ?? '',
                            'last_name' => $bcEmployee['Last_Name'] ?? '',
                            'job_title' => $bcEmployee['Job_Title'] ?? '',
                            'email' => $bcEmployee['Company_EMail'] ?? '',
                            'phone' => $bcEmployee['Phone_No'] ?? '',
                            'is_active' => ($bcEmployee['Status'] ?? '') === 'Active',
                        ]
                    );
                    $synced++;
                } catch (Exception $e) {
                    $errors[] = [
                        'employee_no' => $bcEmployee['No'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return [
                'processed' => $processed,
                'synced' => $synced,
                'errors' => $errors
            ];

        } catch (Exception $e) {
            Log::error('Employee Sync Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
