<?php

namespace App\Http\Controllers\Admin\Core;

use Exception;
use App\Models\AndroidApkVersion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Controller;

class AndroidApkController extends Controller
{
    private const MAX_APK_BYTES = 104857600;

    public function uploadChunk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload_id'     => ['required', 'alpha_dash', 'max:80'],
            'original_name' => ['required', 'string', 'max:255', 'regex:/\.apk$/i'],
            'version'       => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', 'unique:android_apk_versions,version'],
            'file_size'     => ['required', 'integer', 'min:1', 'max:' . self::MAX_APK_BYTES],
            'chunk_index'   => ['required', 'integer', 'min:0'],
            'total_chunks'  => ['required', 'integer', 'min:1', 'max:100'],
            'chunk'         => ['required', 'file', 'max:5120'],
        ]);

        if ($data['chunk_index'] >= $data['total_chunks']) {
            return response()->json(['message' => translate('Invalid APK chunk number')], 422);
        }

        $chunkPath = $this->chunkPath($data['upload_id']);
        if ((int) $data['chunk_index'] === 0) {
            $this->cleanupStaleChunks();
            File::deleteDirectory($chunkPath);
        }
        File::ensureDirectoryExists($chunkPath, 0755, true);
        $request->file('chunk')->move($chunkPath, $data['chunk_index'] . '.part');

        return response()->json([
            'status' => true,
            'message' => translate('APK chunk uploaded successfully'),
        ]);
    }

    public function completeUpload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload_id'     => ['required', 'alpha_dash', 'max:80'],
            'original_name' => ['required', 'string', 'max:255', 'regex:/\.apk$/i'],
            'version'       => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', 'unique:android_apk_versions,version'],
            'file_size'     => ['required', 'integer', 'min:1', 'max:' . self::MAX_APK_BYTES],
            'total_chunks'  => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $chunkPath = $this->chunkPath($data['upload_id']);
        $targetPath = $this->apkPath();
        File::ensureDirectoryExists($targetPath, 0755, true);

        $fileName = $this->safeFilePart(site_settings('site_name', 'app'))
            . '-android-gateway-' . $this->safeFilePart($data['version'])
            . '-' . time() . '.apk';
        $destination = $targetPath . DIRECTORY_SEPARATOR . $fileName;
        $output = fopen($destination, 'wb');

        if (!$output) {
            return response()->json(['message' => translate('Unable to create APK file')], 500);
        }

        try {
            for ($index = 0; $index < $data['total_chunks']; $index++) {
                $part = $chunkPath . DIRECTORY_SEPARATOR . $index . '.part';
                if (!File::exists($part)) {
                    throw new Exception(translate('APK upload is incomplete. Please upload again.'));
                }

                $input = fopen($part, 'rb');
                stream_copy_to_stream($input, $output);
                fclose($input);
            }
        } catch (Exception $exception) {
            fclose($output);
            File::delete($destination);
            File::deleteDirectory($chunkPath);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        fclose($output);
        File::deleteDirectory($chunkPath);

        if (File::size($destination) !== (int) $data['file_size']) {
            File::delete($destination);

            return response()->json(['message' => translate('Uploaded APK file size does not match. Please upload again.')], 422);
        }

        $apkVersion = AndroidApkVersion::create([
            'version'       => $data['version'],
            'file_name'     => $fileName,
            'original_name' => $data['original_name'],
            'file_size'     => $data['file_size'],
        ]);

        return response()->json([
            'status' => true,
            'message' => translate('APK version uploaded. Save settings to make it active.'),
            'version' => [
                'id' => $apkVersion->id,
                'version' => $apkVersion->version,
                'file_name' => $apkVersion->file_name,
                'file_size' => $apkVersion->file_size,
                'created_at' => $apkVersion->created_at->format('M d, Y H:i'),
                'url' => asset(config('setting.file_path.android_apk_file.path') . '/' . $apkVersion->file_name),
            ],
        ]);
    }

    private function chunkPath(string $uploadId): string
    {
        return storage_path('app/android-apk-chunks/' . $uploadId);
    }

    private function cleanupStaleChunks(): void
    {
        $rootPath = storage_path('app/android-apk-chunks');
        if (!File::isDirectory($rootPath)) {
            return;
        }

        foreach (File::directories($rootPath) as $directory) {
            if (File::lastModified($directory) < now()->subDay()->timestamp) {
                File::deleteDirectory($directory);
            }
        }
    }

    private function apkPath(): string
    {
        return base_path('../' . config('setting.file_path.android_apk_file.path'));
    }

    private function safeFilePart(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $value), '-_.') ?: 'app';
    }
}
