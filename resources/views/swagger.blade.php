<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SRICU-ULS API</title>
    <link rel="stylesheet" href="/vendor/swagger-ui/swagger-ui.css">
    <style>
        html { box-sizing: border-box; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin: 0; background: #f3f6fa; }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="/vendor/swagger-ui/swagger-ui-bundle.js"></script>
<script src="/vendor/swagger-ui/swagger-ui-standalone-preset.js"></script>
<script>
    window.ui = SwaggerUIBundle({
        url: '/api/openapi.yaml',
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
        layout: 'StandaloneLayout'
    });
</script>
</body>
</html>
