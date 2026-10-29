<?php

return [
    'max_upload_kb' => (int) env('MAX_UPLOAD_KB', 20480),
    'allowed_extensions' => ['pdf', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'zip', 'txt'],
    'semantic_enabled' => (bool) env('SEMANTIC_SEARCH_ENABLED', false),
];
