<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessLogoController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $settings = BusinessSetting::current();

        abort_unless(
            $settings->logo_path && Storage::disk('public')->exists($settings->logo_path),
            404,
        );

        return Storage::disk('public')->response(
            $settings->logo_path,
            null,
            ['Cache-Control' => 'public, max-age=3600'],
        );
    }
}
