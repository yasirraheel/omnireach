<?php

namespace App\Managers;

use App\Enums\ServiceType;
use App\Models\User;
use App\Models\Campaign;
use App\Enums\SettingKey;
use App\Models\DispatchLog;
use App\Models\AndroidSession;
use Illuminate\Support\Collection;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\CommunicationStatusEnum;
use App\Exceptions\ApplicationException;
use App\Models\AndroidSim;
use App\Models\DispatchUnit;
use App\Models\Gateway;
use App\Models\Message;
use App\Service\Admin\Core\CustomerService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class CommunicationManager
{
    /**
     * getDispatchLogs
     *
     * @param ChannelTypeEnum $channel
     * @param int|string|null $campaign_id
     * @param User|null $user
     * 
     * @return LengthAwarePaginator
     */
    public function getDispatchLogs(
        ChannelTypeEnum $channel,
        int|string|null $campaign_id = null,
        ?User $user = null
    ): LengthAwarePaginator {

        if(request()->query("identifier")) $user = User::where("uid", request()->query("identifier"))->first();
        return DispatchLog::select([
            'id',
            'user_id',
            'message_id',
            'contact_id',
            'campaign_id',
            'gatewayable_type',
            'gatewayable_id',
            'type',
            'status',
            'response_message',
            'scheduled_at',
            'sent_at',
            'processed_at',
            'retry_count',
            'created_at',
        ])->filter(['status'])
            ->search([
                "contact:{$channel->value}_contact"
            ])
            ->date()
            ->when($campaign_id, fn(Builder $q): Builder => $q->where("campaign_id", $campaign_id))
            ->where('type', $channel->value)
            ->when(request()->query("status"), fn(Builder $q): Builder => $q->where("status", request()->query("status")))
            ->when($user, fn($query) => $query->where('user_id', $user->id))
            ->with([
                'user:id,name',
                'message:id,subject,main_body,message',
                'contact:id,first_name,last_name,email_contact,sms_contact,whatsapp_contact',
                'campaign:id,name',
                'gatewayable' => function ($query) {
                    $query->when(
                        $query->getModel()->gatewayable_type === Gateway::class,
                        fn($q) => $q->select(['id', 'name', 'type', 'channel'])
                    )
                    ->when(
                        $query->getModel()->gatewayable_type === AndroidSim::class,
                        fn($q) => $q->select(['id', 'sim_number', 'android_session_id'])
                            ->with(['androidSession:id,name'])
                    );
                },
            ])->orderBy("id", "DESC")
            // ->orderByRaw('
            //     CASE 
            //         WHEN processed_at IS NULL THEN 0 
            //         ELSE 1 
            //     END,
            //     CASE 
            //         WHEN processed_at IS NULL THEN created_at 
            //         ELSE NULL 
            //     END DESC,
            //     processed_at ASC
            // ')
            ->paginate(site_settings(SettingKey::PAGINATE_NUMBER->value, 10))
            ->appends(request()->all());
    }

    /**
     * fetchPendingSmsForAndroid
     *
     * @param AndroidSession $androidSession
     * @param int $limit
     * @param array $simIds
     * 
     * @return array
     */
    public function fetchPendingSmsForAndroid(AndroidSession $androidSession, int $limit, array|null $simIds): array
    {
        $currentTime = Carbon::now();
        return DB::transaction(function () use ($androidSession, $limit, $simIds, $currentTime) {

            $dispatchLogsQuery = DispatchLog::select([
                                                'id',
                                                'user_id',
                                                'message_id',
                                                'contact_id',
                                                'campaign_id',
                                                'gatewayable_type',
                                                'gatewayable_id',
                                                'type',
                                                'status',
                                                'scheduled_at',
                                                'sent_at',
                                                'retry_count',
                                                'created_at',
                                            ])->where('type', ChannelTypeEnum::SMS->value)
                                                ->where('gatewayable_type', AndroidSim::class)
                                                ->whereNotNull('gatewayable_id')
                                                ->where('status', CommunicationStatusEnum::PENDING->value)
                                                ->where(function ($query) use($currentTime) {
                                                    
                                                    $query->whereNull('scheduled_at')
                                                        ->orWhere('scheduled_at', '<=', $currentTime);
                                                })->when("gatewayable_type" instanceof AndroidSim, fn($query) 
                                                        => $query->whereHas('gatewayable', function ($query) use ($androidSession) {
                                                            $query->where('android_session_id', $androidSession->id);
                                                        }))
                                                ->whereIn('gatewayable_id', $simIds)
                                                ->orderBy('created_at', 'asc')
                                                ->take($limit);

            $dispatchLogIds = $dispatchLogsQuery->pluck('id')->toArray();
            if (empty($dispatchLogIds)) return [];

            $gatewayableIds         = $dispatchLogsQuery->pluck('gatewayable_id')
                                                        ->unique()
                                                        ->toArray();
            $existingAndroidSims    = AndroidSim::whereIn('id', $gatewayableIds)
                                                    ->pluck('id')
                                                    ->toArray();
            $missingAndroidSims     = array_diff($gatewayableIds, $existingAndroidSims);

            if (!empty($missingAndroidSims)) {
                $missingId = reset($missingAndroidSims);
                throw new ApplicationException("AndroidSim with ID {$missingId} not found", 404);
            }

            $dispatchLogs = DispatchLog::select([
                                            'id',
                                            'user_id',
                                            'message_id',
                                            'contact_id',
                                            'campaign_id',
                                            'gatewayable_type',
                                            'gatewayable_id',
                                            'type',
                                            'status',
                                            'scheduled_at',
                                            'sent_at',
                                            'retry_count',
                                            'created_at',
                                        ])->whereIn('id', $dispatchLogIds)
                                            ->with([
                                                'user:id,name',
                                                'message:id,subject,main_body,message',
                                                'contact:id,first_name,last_name,email_contact,sms_contact,whatsapp_contact,meta_data',
                                                'campaign:id,name',
                                                'gatewayable:id,sim_number',
                                            ])
                                            ->orderBy('created_at', 'asc')
                                            ->get();
            $dispatchLogs = $dispatchLogs->map(function ($log) {
                $logArray = $log->toArray();
                if (isset($logArray['message']['message'])) {
                    $logArray['message']['message'] = textSpinner($logArray['message']['message']);
                }
                return $logArray;
            });

            DispatchLog::whereIn('id', $dispatchLogIds)
                            ->update([
                                'status'  => CommunicationStatusEnum::PROCESSING->value,
                                'sent_at' => Carbon::now()
                            ]);

            return $dispatchLogs->toArray();
        });
    }

    /**
     * updateDispatchLogStatuses
     *
     * @param AndroidSession $androidSession
     * @param array $logs
     * 
     * @return int
     */
    public function updateDispatchLogStatuses(AndroidSession $androidSession, array $logs): int
    {
        $logIds = array_column($logs, 'id');
        $statusMap = array_column($logs, 'status', 'id');
        $errorMap = array_column($logs, 'response_message', 'id');
        $retryMap = array_column($logs, 'retry', 'id');

        $totalUpdated = 0;

        DB::transaction(function () use ($androidSession, $logIds, $statusMap, $errorMap, $retryMap, &$totalUpdated) {
            
            // Reconnect if connection lost
            try {
                DB::reconnect();
            } catch (\Exception $e) {
                // Ignore reconnection errors
            }

            DispatchLog::whereIn('id', $logIds)
                                ->where('gatewayable_type', AndroidSim::class)
                                ->where(function ($query) use ($androidSession) {
                                    $query->when("gatewayable_type" instanceof AndroidSim, fn(Builder $q): Builder =>
                                    $q->orWhereHas('gatewayable', function ($subQuery) use ($androidSession) {
                                        $subQuery->where('android_session_id', $androidSession->id);
                                    }));
                                })
                                ->chunkById(100, function ($dispatchLogs) use ($statusMap, $errorMap, $retryMap, &$totalUpdated) {
                                    
                                    foreach ($dispatchLogs as $dispatchLog) {
                                        
                                        $status = $statusMap[$dispatchLog->id] ?? null;
                                        $error  = $errorMap[$dispatchLog->id] ?? null;
                                        $retry  = $retryMap[$dispatchLog->id] ?? false;

                                        if ($status) {
                                            $updateData = [
                                                'status' => $status,
                                                'processed_at' => Carbon::now(),
                                            ];

                                            if ($error) {
                                                $updateData['response_message'] = $error;
                                            }

                                            if($retry) {
                                                $updateData['retry_count'] = $dispatchLog->retry_count + 1;
                                            }

                                            $updated = DispatchLog::where('id', $dispatchLog->id)->update($updateData);

                                            if ($updated) {
                                                $totalUpdated++;

                                                if ($status === CommunicationStatusEnum::FAIL->value) {
                                                    
                                                    if($dispatchLog->user_id) {
                                                        
                                                        $customerService = new CustomerService();
                                                        $customerService->deductCredit(
                                                            user: $dispatchLog->user,
                                                            serviceType: ServiceType::SMS->value,
                                                            manual: false,
                                                            message: translate("Refunded 1 SMS credit for failed dispatch log ID {$dispatchLog->id}")
                                                        );
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
        });

        return $totalUpdated;
    }

    /**
     * Fetch campaigns for a specific channel with their dispatch logs.
     *
     * @param ChannelTypeEnum $channel
     * @param User|null $user
     * @return Collection
     */
    public function getCampaignLogs(ChannelTypeEnum $channel, ?User $user = null): LengthAwarePaginator
    {
        return Campaign::select([
                'id',
                'name',
                'type',
                'user_id',
                'message_id',
                'group_id',
                'priority',
                'status',
                'schedule_at',
                'created_at',
            ])->filter(['status'])
                ->search(['name'])
                ->date()
            ->where('type', $channel)
            ->when($user, fn($query) => $query->where('user_id', $user->id))
            ->with([
                'user'          => fn($query) => $query->select('id', 'name'),
                'message'       => fn($query) => $query->select('id', 'type', 'subject', 'main_body', 'message'),
                'group'         => fn($query) => $query->select('id', 'name'),
                'dispatchLogs'  => fn($query) => $query->select('id', 'campaign_id', 'contact_id', 'gatewayable_type', 'gatewayable_id', 'status'),
                'dispatchLogs.contact'  => fn($query) => $query->select('id', 'first_name', 'last_name','sms_contact', 'email_contact', 'whatsapp_contact'),
            ])->withCount(["dispatchLogs"])
            ->whereHas('dispatchLogs', fn(Builder $query): Builder => $query->where('type', $channel))
            ->orderBy('created_at', 'desc')
            ->paginate(site_settings(SettingKey::PAGINATE_NUMBER->value, 10))
            ->appends(request()->all());
    }

    /**
     * getSpecificDispatchLog
     *
     * @param string|int|null $id
     * @param User|null $user
     * 
     * @return DispatchLog
     */
    public function getSpecificDispatchLog(string|int|null $id, ?User $user = null): DispatchLog|null {

        return DispatchLog::when($user, fn(Builder $q): Builder => 
                                $q->where("user_id", $user->id))
                                ->where('id', $id)
                                ->with(["message", "contact"])
                                ->first();
    }

    /**
     * getSpecificCampaignLog
     *
     * @param string|int|null $id
     * @param User|null $user
     * 
     * @return Campaign
     */
    public function getSpecificCampaignLog(string|int|null $id, ?User $user = null): Campaign|null {

        return Campaign::when($user, fn(Builder $q): Builder => 
                                $q->where("user_id", $user->id))
                                ->where('id', $id)
                                ->with([
                                    "message:id,type,subject,main_body,message",
                                    "dispatchLogs:id"
                                ])
                                ->first();
    }

    /**
     * dispatchLogsExists
     *
     * @param string $column
     * @param string $value
     * @param User|null $user
     * 
     * @return bool
     */
    public function dispatchLogsExists(string $column, string $value, ?User $user = null) : bool {
        return DispatchLog::when($user, 
                            fn(Builder $q): Builder => 
                                $q->where("user_id", $user->id))
                                    ->where($column, $value)
                                    ->exists();
    }

    /**
     * createDispatchUnit
     *
     * @param int|string $gatewayId
     * @param Message $message
     * @param ChannelTypeEnum $channel
     * @param int $size
     * 
     * @return DispatchUnit
     */
    public function createDispatchUnit(int|string $gatewayId, Message $message, ChannelTypeEnum $channel, int $size): DispatchUnit {

        $unit = DispatchUnit::create([
            'gateway_id'    => $gatewayId,
            'message_id'    => $message->id,
            'type'          => $channel->value,
            'log_count'     => $size
        ]);

        return $unit;
    }

    /**
     * getMappedDispatchLogs
     *
     * @param array $insertedLogs
     * @param ChannelTypeEnum $type
     * 
     * @return array
     */
    public function getMappedDispatchLogs(array $insertedLogs, ChannelTypeEnum $type): array {

        $logIds = collect($insertedLogs)->pluck('id')->toArray();
        $logs = DispatchLog::whereIn('id', $logIds)
                                 ->with([
                                      'message' => function ($query) use ($type) {
                                           if ($type == ChannelTypeEnum::EMAIL) {
                                                $query->select('id', 'subject', 'main_body');
                                           } else {
                                                $query->select('id', 'message', 'file_info');
                                           }
                                      },
                                      'contact' => function ($query) use ($type) {
                                           $query->select('id', "{$type->value}_contact");
                                      }
                                 ])
                                 ->get(['id', 'message_id', 'contact_id', 'created_at', 'status']);
                                 

        return $logs->map(function ($log) use ($type) {
             return [
                  'id'          => $log->id,
                  'created_at'  => $log->created_at->toDateTimeString(),
                  'status'      => $log->status,
                  'message'     => $type == ChannelTypeEnum::EMAIL
                       ? [
                            'subject'   => @$log?->message?->subject,
                            'main_body' => @$log?->message?->main_body,
                       ]
                       : [
                            'message' => @$log?->message?->message,
                            'file_info' => @$log?->message?->file_info
                       ],
                  'contact' => [
                      "first_name"             => @$log?->contact?->first_name,
                      "last_name"              => @$log?->contact?->last_name,
                       "{$type->value}_contact" => @$log?->contact?->{"{$type->value}_contact"},
                       "meta_data"              => @$log?->contact?->meta_data,
                  ],
             ];
        })->toArray();
    }
}