<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class DocumentationController extends Controller
{
    public function ui()
    {
        return response()->view('swagger');
    }

    public function spec()
    {
        $path = base_path('openapi/openapi.yaml');

        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'application/yaml',
        ]);
    }
}
