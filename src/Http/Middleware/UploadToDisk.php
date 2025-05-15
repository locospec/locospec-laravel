<?php

namespace Locospec\LLCS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadToDisk
{
    public function handle(Request $request, Closure $next)
    {

        if (isset($request->file)) {
            $disk = config('locospec-laravel.upload_disk', 's3');

            $file = $request->file;

            if ($file && $file->isValid()) {
                $original = $file->getClientOriginalName();
                $mime = $file->getClientMimeType();

                // Store under 'uploads/' directory on the chosen disk
                $path = $file->store('', $disk);
                // $path = $file->storeAs('', $original, $disk);
                $url = Storage::disk($disk)->url($path);

                // dd("rajesh", $path, $url, $original, $mime);
                $request->merge([
                    'path_to_file' => $url,
                    'file_name' => $original,
                    'mime' => $mime,
                ]);
            }
        }

        $response = $next($request);

        dd($response['data']);

        return $next($request);
    }
}
