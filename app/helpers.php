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