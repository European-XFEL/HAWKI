<?php
if (!function_exists('asset_with_time')) {
    function asset_with_time($path, $secure = null){
        try {
            $t = filemtime(public_path($path));    
        }
        catch (\Exception $exception){
            $t = 0;
        }
        return app('url')->asset($path . '?v='.$t , $secure);
    }
}

if (!function_exists('app_url_path')) {
    function app_url_path(string $path = ''): string
    {
        $parts = array_filter([
            trim((string) config('app.base_path'), '/'),
            trim($path, '/'),
        ], static fn (string $part): bool => $part !== '');

        return '/'.implode('/', $parts);
    }
}
