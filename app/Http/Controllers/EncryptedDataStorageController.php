<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class EncryptedDataStorageController extends Controller
{

    public static function storeData($data, $subFolder)
    {
        // Create a filename for the data
        $fileName = uniqid();

        // Store the image on the server (public folder in this case)
        $filePath = $subFolder . '/' . $fileName;

        Storage::disk('public')->put($filePath, $data);
        return $filePath;

    }

    
}
