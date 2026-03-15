<?php

namespace App\Http\Controllers;

use App\Traits\InstallerManager;
use Carbon\Carbon;
use Illuminate\Http\Request;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use ZipArchive;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SystemUpdateController extends Controller
{
    use InstallerManager;

    public function __construct(){

    }

    public function init() :View {

        Session::put("menu_active", true);
        return view('admin.setting.system_update',[
            "title" => translate("Update System")
        ]);
    }


    /**
     * Summary of checkUpdate
     * @return array{data: array, message: string, success: bool|array{data: mixed, message: string, success: bool}|array{message: mixed, success: bool}|array{message: string, success: bool}}
     */
    public function checkUpdate(): array
    {
        $params = [
            'domain'            => url('/'),
            'software_id'       => config('installer.software_id'),
            'version'           => config('installer.version'),
            'purchase_key'      => site_settings('purchase_key') ?: env('PURCHASE_KEY'),
            'envato_username'   => site_settings('envato_username') ?: env('ENVATO_USERNAME')
        ];

        try {
            $url = 'https://verifylicense.online/api/licence-verification/get-update-versions';
            // Use withOptions for Laravel 8.x compatibility
            $response = Http::withOptions([
                'timeout' => 30,
                'connect_timeout' => 15,
            ])->post($url, $params);
            $data = $response->json();
            

            if (!isset($data['success'], $data['code'], $data['message'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid API response structure',
                ];
            }

            if ($data['success'] === true) {
                if (!empty($data['data'])) {
                    return [
                        'success' => true,
                        'message' => 'Update available',
                        'data' => $data['data'],
                    ];
                } else {
                    return [
                        'success' => true,
                        'message' => 'No updates available',
                        'data' => [],
                    ];
                }
            }

            $errorMessage = $data['message'] ?? 'Unknown error';
            if (isset($data['data']['errors'])) {
                $errors = $data['data']['errors'];
                $errorMessage .= ': ' . json_encode($errors);
            }

            return [
                'success' => false,
                'message' => $errorMessage,
            ];

        } catch (\Exception $e) {
            
            return [
                'success' => false,
                'message' => 'Failed to connect to API: ' . $e->getMessage(),
            ];
        }
    }


    public function installUpdate(Request $request)
    {
        // Set extended execution time for update process
        ini_set('memory_limit', '-1');
        ini_set('max_input_time', '300');
        ini_set('max_execution_time', '300');

        $params = [
            'domain'            => url('/'),
            'software_id'       => config('installer.software_id'),
            'version'           => $request->input('version'),
            'purchase_key'      => site_settings('purchase_key') ?: env('PURCHASE_KEY'),
            'envato_username'   => site_settings('envato_username') ?: env('ENVATO_USERNAME')
        ];

        $status = false;
        $message = translate('Update failed');
        $errorMessage = '';
        $basePath = base_path('/storage/app/public/temp_update/');
        $migrationResults = [];
        $seederResults = [];

        try {
            $url = 'https://verifylicense.online/api/licence-verification/download-version';
            // Use withOptions for Laravel 8.x compatibility, longer timeout for downloads
            $response = Http::withOptions([
                'timeout' => 180,
                'connect_timeout' => 30,
            ])->post($url, $params);

            // Check if response is JSON (error) or binary (file)
            $contentType = $response->header('Content-Type');
            $isJsonResponse = str_contains($contentType ?? '', 'application/json');

            if ($isJsonResponse) {
                $data = $response->json();
                $errorMessage = $data['message'] ?? 'Unknown error';
                if (isset($data['data']['errors'])) {
                    $errorMessage .= ': ' . json_encode($data['data']['errors']);
                }
                if (isset($data['data']['error'])) {
                    $errorMessage = $data['data']['error'];
                }

                \Log::warning('Update API returned error', ['response' => $data]);

                return [
                    'success' => false,
                    'message' => $errorMessage
                ];
            }

            if ($response->successful() && !$isJsonResponse) {
                // Create temp directory
                if (!file_exists($basePath)) {
                    mkdir($basePath, 0777, true);
                }

                $filename = 'default_update.zip';
                if ($response->hasHeader('Content-Disposition')) {
                    $disposition = $response->header('Content-Disposition');
                    if (preg_match('/filename="(.+?)"/', $disposition, $matches)) {
                        $filename = $matches[1];
                    }
                }

                $filePath = $basePath . '/' . $filename;
                file_put_contents($filePath, $response->body());

                \Log::info('Update file downloaded', ['path' => $filePath, 'size' => filesize($filePath)]);

                $zip = new ZipArchive;
                $res = $zip->open($filePath);

                if (!$res) {
                    $this->deleteDirectory($basePath);
                    \Log::error('Failed to open update zip file', ['path' => $filePath]);
                    return [
                        'success' => false,
                        'message' => translate('Error! Could not open update file. The download may be corrupted.')
                    ];
                }

                $zip->extractTo($basePath);
                $zip->close();

                $configFilePath = $basePath . 'config.json';

                if (!file_exists($configFilePath)) {
                    $this->deleteDirectory($basePath);
                    \Log::error('Config.json not found in update package');
                    return [
                        'success' => false,
                        'message' => translate('Error! Invalid update package - no configuration file found')
                    ];
                }

                $configJson = json_decode(file_get_contents($configFilePath), true);

                if (empty($configJson) || empty($configJson['version'])) {
                    $this->deleteDirectory($basePath);
                    \Log::error('Invalid config.json in update package', ['config' => $configJson]);
                    return [
                        'success' => false,
                        'message' => translate('Error! Invalid configuration file in update package')
                    ];
                }

                $newVersion = (double) $configJson['version'];
                $currentVersion = (double) @site_settings("app_version") ?? 1.1;

                \Log::info('Version check', ['current' => $currentVersion, 'new' => $newVersion]);

                if ($newVersion <= $currentVersion) {
                    $this->deleteDirectory($basePath);
                    return [
                        'success' => true,
                        'message' => translate('Your system is already running the latest version')
                    ];
                }

                $src = $basePath;
                $dst = dirname(base_path());

                // Copy new files
                if ($this->copyDirectory($src, $dst)) {
                    \Log::info('Files copied successfully');

                    // Run migrations with error handling
                    $migrationResults = $this->_runMigrations($configJson);
                    $seederResults = $this->_runSeeder($configJson);

                    // Update version in database
                    DB::table('settings')->upsert([
                        ['key' => 'app_version', 'value' => $newVersion],
                        ['key' => 'system_installed_at', 'value' => Carbon::now()],
                    ], ['key'], ['value']);

                    $status = true;
                    $message = translate('Your system updated successfully to version') . ' ' . $newVersion;

                    // Add migration/seeder warnings if any
                    if (!$migrationResults['success']) {
                        $message .= '. ' . translate('Some migrations had issues - please check the logs.');
                    }
                    if (!$seederResults['success']) {
                        $message .= '. ' . translate('Some seeders had issues - please check the logs.');
                    }

                    \Log::info('Update completed successfully', [
                        'version' => $newVersion,
                        'migration_success' => $migrationResults['success'],
                        'seeder_success' => $seederResults['success']
                    ]);
                } else {
                    \Log::error('Failed to copy update files');
                    $message = translate('Failed to copy update files. Please check file permissions.');
                }
            } else {
                $errorMessage = translate('Failed to download update from server. Status: ') . $response->status();
                \Log::error('Update download failed', ['status' => $response->status()]);
            }

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            \Log::error('Update exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }

        // Build response
        $updateResponse = [
            'success' => $status,
            'message' => $status ? $message : ($errorMessage ?: $message)
        ];

        // CRITICAL: Clear all caches BEFORE returning response
        // This ensures the new code is loaded on next request
        try {
            optimize_clear();
            \Log::info('Cache cleared after update');
        } catch (\Exception $e) {
            \Log::warning('Cache clear warning: ' . $e->getMessage());
        }

        // Clean up temp directory
        if (file_exists($basePath)) {
            $this->deleteDirectory($basePath);
        }

        return $updateResponse;
    }



    /**
     * update the system (Manual Upload)
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Request $request) :RedirectResponse {

        ini_set('memory_limit', '-1');
        ini_set('max_input_time', '300');
        ini_set('max_execution_time', '300');
        ini_set('upload_max_filesize', '1G');
        ini_set('post_max_size', '1G');

        $request->validate([
            'updateFile' => ['required', 'mimes:zip'],
        ],[
            'updateFile.required' => translate('File field is required')
        ]);

        $response = [];
        $basePath = base_path('/storage/app/public/temp_update/');

        try {
            if ($request->hasFile('updateFile')) {

                $zipFile = $request->file('updateFile');

                // Create temp directory with proper permissions
                if (!file_exists($basePath)) {
                    mkdir($basePath, 0777, true);
                }

                $originalFilename = $zipFile->getClientOriginalName();
                $zipFile->move($basePath, $originalFilename);

                \Log::info('Update file uploaded', ['filename' => $originalFilename]);

                $zip = new ZipArchive;
                $res = $zip->open($basePath . $originalFilename);

                if (!$res) {
                    $this->deleteDirectory($basePath);
                    \Log::error('Failed to open uploaded zip file');
                    return back()->with("error", translate('Error! Could not open the uploaded file. Please ensure it is a valid ZIP file.'));
                }

                $zip->extractTo($basePath);
                $zip->close();

                // Read configuration file
                $configFilePath = $basePath . 'config.json';

                if (!file_exists($configFilePath)) {
                    $this->deleteDirectory($basePath);
                    \Log::error('Config.json not found in uploaded package');
                    return back()->with("error", translate('Error! Invalid update package - no config.json file found.'));
                }

                $configJson = json_decode(file_get_contents($configFilePath), true);

                if (empty($configJson) || empty($configJson['version'])) {
                    $this->deleteDirectory($basePath);
                    \Log::error('Invalid config.json in uploaded package');
                    return back()->with("error", translate('Error! Invalid configuration file in update package.'));
                }

                $newVersion = (double) $configJson['version'];
                $currentVersion = (double) @site_settings("app_version") ?? 1.1;

                \Log::info('Manual update version check', ['current' => $currentVersion, 'new' => $newVersion]);

                if ($newVersion <= $currentVersion) {
                    $this->deleteDirectory($basePath);
                    $response[] = response_status(translate('Your system is already running the latest version or newer.'), 'info');
                    return redirect()->back()->withNotify($response);
                }

                $src = storage_path('app/public/temp_update');
                $dst = dirname(base_path());

                // Copy new files
                if ($this->copyDirectory($src, $dst)) {
                    \Log::info('Update files copied successfully');

                    // Run migrations with error handling
                    $migrationResults = $this->_runMigrations($configJson);
                    $seederResults = $this->_runSeeder($configJson);

                    // Update version in database
                    DB::table('settings')->upsert([
                        ['key' => 'app_version', 'value' => $newVersion],
                        ['key' => 'system_installed_at', 'value' => Carbon::now()],
                    ], ['key'], ['value']);

                    $successMessage = translate('Your system updated successfully to version') . ' ' . $newVersion;

                    // Add warnings if any issues
                    if (!empty($migrationResults['errors'])) {
                        $successMessage .= '. ' . translate('Some migrations had issues - please check the logs.');
                        \Log::warning('Migration issues during manual update', ['errors' => $migrationResults['errors']]);
                    }
                    if (!empty($seederResults['errors'])) {
                        $successMessage .= '. ' . translate('Some seeders had issues - please check the logs.');
                        \Log::warning('Seeder issues during manual update', ['errors' => $seederResults['errors']]);
                    }

                    $response[] = response_status($successMessage);

                    \Log::info('Manual update completed successfully', ['version' => $newVersion]);
                } else {
                    $response[] = response_status(translate('Failed to copy update files. Please check file permissions.'), 'error');
                    \Log::error('Failed to copy update files during manual update');
                }
            } else {
                $response[] = response_status(translate('No update file was uploaded.'), 'error');
            }
        } catch (\Exception $ex) {
            $response = [];
            $response[] = response_status(translate('Update failed: ') . strip_tags($ex->getMessage()), 'error');
            \Log::error('Manual update exception', ['error' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]);
        }

        // CRITICAL: Clear all caches to ensure new code is loaded
        try {
            optimize_clear();
            \Log::info('Cache cleared after manual update');
        } catch (\Exception $e) {
            \Log::warning('Cache clear warning during manual update: ' . $e->getMessage());
        }

        // Clean up temp directory
        if (file_exists($basePath)) {
            $this->deleteDirectory($basePath);
        }

        return redirect()->back()->withNotify($response);
    }


    private function _runMigrations(array $json) :array{
        $results = ['success' => true, 'errors' => []];

        try {
            // Use SafeMigrationRunner for enterprise-level migration handling
            $runner = new \App\Services\Core\SafeMigrationRunner();
            $migrationResults = $runner->runFromConfig($json);

            // Map results to expected format
            $results['success'] = $runner->isSuccessful();
            $results['errors'] = array_map(function($item) {
                return is_array($item) ? ($item['file'] . ': ' . ($item['error'] ?? 'Unknown error')) : $item;
            }, $migrationResults['failed'] ?? []);

            // Log summary
            \Log::info("[SystemUpdate] Migration summary", [
                'success_count' => count($migrationResults['success'] ?? []),
                'skipped_count' => count($migrationResults['skipped'] ?? []),
                'failed_count' => count($migrationResults['failed'] ?? []),
                'warnings_count' => count($migrationResults['warnings'] ?? []),
            ]);

            // Also run default migrations as fallback (will skip already ran)
            try {
                Artisan::call('migrate', ['--force' => true]);
                \Log::info("Default migrations executed successfully");
            } catch (\Exception $e) {
                // This is expected if all migrations are already done
                \Log::info("Default migration note: " . $e->getMessage());
            }

        } catch (\Exception $e) {
            $results['success'] = false;
            $results['errors'][] = "Migration system error: " . $e->getMessage();
            \Log::error("Migration system error", ['error' => $e->getMessage()]);

            // Fallback to basic migration
            try {
                Artisan::call('migrate', ['--force' => true]);
            } catch (\Exception $fallbackError) {
                \Log::warning("Fallback migration failed: " . $fallbackError->getMessage());
            }
        }

        return $results;
    }

    private function _runSeeder(array $json) :array{
        $results = ['success' => true, 'errors' => []];

        $seeders = Arr::get($json , 'seeder' ,[]);

        if(count($seeders) > 0){
            $seederFiles = $this->_getFormattedFiles($seeders);
            foreach ($seederFiles as $seeder) {
                try {
                    Artisan::call('db:seed',
                        array(
                            '--class' => $seeder,
                            '--force' => true));
                    \Log::info("Seeder executed successfully: {$seeder}");
                } catch (\Exception $e) {
                    $results['success'] = false;
                    $results['errors'][] = "Seeder failed ({$seeder}): " . $e->getMessage();
                    \Log::error("Seeder failed: {$seeder}", ['error' => $e->getMessage()]);
                }
            }
        }

        return $results;
    }

    private function _getFormattedFiles (array $files) :array{

        $currentVersion  = (double) site_settings(key : "app_version",default :1.1);
        $formattedFiles = [];
        foreach($files as $version => $file){
           if(version_compare($version, (string)$currentVersion, '>')){
              $formattedFiles [] =  $file;
           }
        }

        return array_unique(Arr::collapse($formattedFiles));

    }



    /**
     * Copy directory
     *
     * @param string $src
     * @param string $dst
     * @return boolean
     */
    public function copyDirectory(string $src, string $dst) :bool {

        try {
            $dir = opendir($src);
            @mkdir($dst);
            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                    if (is_dir($src . '/' . $file)) {
                        $this->copyDirectory($src . '/' . $file, $dst . '/' . $file);
                    } else {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
            closedir($dir);
        } catch (\Exception $e) {
           return false;
        }

        return true;
    }



    /**
     * delete directory
     *
     * @param string $dirname
     * @return boolean
     */
    public function deleteDirectory(string $dirname) :bool {

        try{
            if (!is_dir($dirname)){
                return false;
            }
            $dir_handle = opendir($dirname);

            if (!$dir_handle)
                return false;
            while ($file = readdir($dir_handle)) {
                if ($file != "." && $file != "..") {
                    if (!is_dir($dirname . "/" . $file))
                        unlink($dirname . "/" . $file);
                    else
                        $this->deleteDirectory($dirname . '/' . $file);
                }
            }
            closedir($dir_handle);
            rmdir($dirname);
            return true;
        }
        catch (\Exception $e) {
            return false;
        }
    }


    public function removeDirectory($basePath) {

        if (File::exists($basePath)) {
            File::deleteDirectory($basePath);
        }
    }

}
