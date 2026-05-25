<?php

namespace App\Http\Controllers\User\Communication\Gateway;

use Exception;
use Illuminate\View\View;
use Illuminate\Support\Arr;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\AndroidSession;
use App\Models\AndroidApkVersion;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Http\Utility\Api\ApiJsonResponse;
use App\Http\Requests\Admin\AndroidApiRequest;
use App\Enums\System\Gateway\SmsGatewayTypeEnum;
use App\Http\Requests\RegisterAndroidSessionRequest;
use App\Services\System\Communication\GatewayService;

class AndroidSessionController extends Controller
{
    use ModelAction;
    protected $gatewayService;

    public function __construct()
    {
        $this->gatewayService = new GatewayService();
    }

    ## ------------- ##
    ## Web Functions ##
    ## ------------- ##

    /**
     * index
     *
     * @return View
     */
    public function index(): View|RedirectResponse
    {
        Session::put("menu_active", false);
        $user = auth()->user();
        return $this->gatewayService->loadLogs(channel: ChannelTypeEnum::SMS, type: SmsGatewayTypeEnum::ANDROID, user: $user);
    }

    /**
     * Download the currently selected Android gateway APK.
     */
    public function download(): BinaryFileResponse|RedirectResponse
    {
        $activeApkId = site_settings('active_android_apk_id');
        $activeApk = $activeApkId ? AndroidApkVersion::find($activeApkId) : null;
        $apkPath = config('setting.file_path.android_apk_file.path');

        if ($activeApk) {
            $filePath = base_path('../' . $apkPath . '/' . basename($activeApk->file_name));

            if (is_file($filePath)) {
                return response()->download(
                    $filePath,
                    $this->apkDownloadName($activeApk->version),
                    ['Content-Type' => 'application/vnd.android.package-archive']
                );
            }
        }

        $legacyApk = site_settings('android_apk_file');
        if ($legacyApk) {
            $legacyPath = base_path('../' . $apkPath . '/' . basename($legacyApk));

            if (is_file($legacyPath)) {
                return response()->download(
                    $legacyPath,
                    $this->apkDownloadName(),
                    ['Content-Type' => 'application/vnd.android.package-archive']
                );
            }
        }

        if (site_settings('app_link')) {
            return redirect()->away(site_settings('app_link'));
        }

        $notify[] = ['error', translate('No Android APK file is currently available')];
        return back()->withNotify($notify);
    }
    
    /**
     * store
     *
     * @param AndroidApiRequest $request
     * 
     * @return RedirectResponse
     */
    public function store(AndroidApiRequest $request): RedirectResponse
    {
        try {

            $data = $request->all();
            unset($data["_token"]);
            $user = auth()->user();
            return $this->gatewayService->saveAndroidSession(data: $data, user: $user);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * update
     *
     * @param AndroidApiRequest $request
     * @param mixed $id
     * 
     * @return RedirectResponse
     */
    public function update(AndroidApiRequest $request, $id): RedirectResponse
    {
        try {

            $data = $request->all();
            unset($data["_token"]);
            $data = Arr::set($data, "id", $id);
            $user = auth()->user();
            return $this->gatewayService->saveAndroidSession(data: $data, user: $user);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * destroy
     *
     * @param string|int|null|null $id
     * 
     * @return RedirectResponse
     */
    public function destroy(string|int|null $id = null): RedirectResponse
    {
        try {
            $user = auth()->user();
            return $this->gatewayService->deleteAndroidSession(id: $id, user: $user);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     *
     * @param Request $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function bulk(Request $request): RedirectResponse {

        try {
            $user = auth()->user();
            return $this->bulkAction(request: $request, dependentColumn: null,modelData: [
                "model" => new AndroidSession(),
                "filterable_attributes" => [
                    "user_id" => $user->id
                ]
            ]);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    ## ------------------- ##
    ## API ENDPOINTS START ##
    ## ------------------- ##

    /**
     * registerSession
     *
     * @param RegisterAndroidSessionRequest $request
     * 
     * @return JsonResponse
     */
    public function registerSession(RegisterAndroidSessionRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $result = $this->gatewayService->registerAndroidSessionRequest(request: $request, user: $user);
            return $result; 

        } catch (ApplicationException $e) {
            
            return ApiJsonResponse::error(
                translate($e->getMessage()),
                null,
                $e->getStatusCode()
            );

        } catch (Exception $e) {
            
            return ApiJsonResponse::error(
                getEnvironmentMessage($e->getMessage()),
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * logout
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        try {
            $user = auth()->user();
            $result = $this->gatewayService->logoutAndroidSession(request()->bearerToken(), $user);
            return $result; 

        } catch (ApplicationException $e) {
            
            return ApiJsonResponse::error(
                translate($e->getMessage()),
                null,
                $e->getStatusCode()
            );

        } catch (Exception $e) {
            
            return ApiJsonResponse::error(
                getEnvironmentMessage($e->getMessage()),
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    ## ------------------ ##
    ##  Unused Functions  ##
    ## ------------------ ##
    public function create() {}

    private function apkDownloadName(?string $version = null): string
    {
        $siteName = $this->safeFilePart(site_settings('site_name', 'app'));
        $versionName = $version ? '-' . $this->safeFilePart($version) : '';

        return $siteName . '-android-gateway' . $versionName . '.apk';
    }

    private function safeFilePart(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $value), '-_.') ?: 'app';
    }
}
