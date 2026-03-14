<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    private string $cacheKey = 'app_settings';

    private array $defaults = [
        'restaurant_name'    => 'BrewPOS Coffee',
        'restaurant_address' => '123 Coffee St.',
        'restaurant_phone'   => '',
        'restaurant_email'   => '',
        'currency'           => 'PHP',
        'currency_symbol'    => '₱',
        'tax_rate'           => 12,
        'tax_enabled'        => true,
        'tax_label'          => 'VAT',
        'receipt_footer'     => 'Thank you for visiting!',
        'logo'               => null,
        'timezone'           => 'Asia/Manila',
    ];

    public function index(): JsonResponse
    {
        $settings = Cache::get($this->cacheKey, $this->defaults);
        return response()->json($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'restaurant_name'    => 'sometimes|string|max:200',
            'restaurant_address' => 'nullable|string',
            'restaurant_phone'   => 'nullable|string',
            'restaurant_email'   => 'nullable|email',
            'currency'           => 'sometimes|string|max:10',
            'currency_symbol'    => 'sometimes|string|max:5',
            'tax_rate'           => 'sometimes|numeric|min:0|max:100',
            'tax_enabled'        => 'sometimes|boolean',
            'tax_label'          => 'sometimes|string|max:20',
            'receipt_footer'     => 'nullable|string|max:500',
            'timezone'           => 'sometimes|string',
        ]);

        $current  = Cache::get($this->cacheKey, $this->defaults);
        $updated  = array_merge($current, $data);
        Cache::forever($this->cacheKey, $updated);

        return response()->json($updated);
    }
}
