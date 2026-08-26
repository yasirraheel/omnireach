@extends('admin.layouts.app')
@section('panel')

<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Dashboard') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('admin.campaign.index') }}">{{ translate('Campaigns') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $campaign->name }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right d-flex gap-2">
                @if($campaign->canEdit())
                <a href="{{ route('admin.campaign.edit', $campaign->uid) }}" class="i-btn btn--info outline btn--md">
                    <i class="ri-pencil-line me-1"></i> {{ translate('Edit') }}
                </a>
                @endif

                @if($campaign->canPause())
                <form action="{{ route('admin.campaign.pause', $campaign->uid) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="i-btn btn--warning outline btn--md">
                        <i class="ri-pause-line me-1"></i> {{ translate('Pause') }}
                    </button>
                </form>
                @endif

                @if($campaign->status === \App\Enums\Campaign\UnifiedCampaignStatus::PAUSED)
                <form action="{{ route('admin.campaign.resume', $campaign->uid) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="i-btn btn--success outline btn--md">
                        <i class="ri-play-line me-1"></i> {{ translate('Resume') }}
                    </button>
                </form>
                @endif

                <a href="{{ route('admin.campaign.duplicate', $campaign->uid) }}" class="i-btn btn--primary outline btn--md">
                    <i class="ri-file-copy-line me-1"></i> {{ translate('Duplicate') }}
                </a>
            </div>
        </div>

        {{-- Status & Progress Card --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="campaign-status-icon {{ $campaign->status === \App\Enums\Campaign\UnifiedCampaignStatus::RUNNING ? 'pulsing' : '' }}">
                                <i class="{{ $campaign->status->icon() }}"></i>
                            </div>
                            <div>
                                <span class="badge {{ $campaign->status->badgeClass() }} mb-1">
                                    {{ $campaign->status->label() }}
                                </span>
                                <h5 class="mb-0">{{ $campaign->name }}</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row g-3">
                            <div class="col-6 col-md-3 text-center">
                                <div class="py-2 rounded-3 bg--primary-light">
                                    <div class="stat-number text-primary">{{ number_format($statistics['total']) }}</div>
                                    <small class="text-muted">{{ translate('Total') }}</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 text-center">
                                <div class="py-2 rounded-3 bg--info-light">
                                    <div class="stat-number text--info">{{ number_format($statistics['sent']) }}</div>
                                    <small class="text-muted">{{ translate('Sent') }}</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 text-center">
                                <div class="py-2 rounded-3 bg--success-light">
                                    <div class="stat-number" style="color: #22c55e;">{{ number_format($statistics['delivered']) }}</div>
                                    <small class="text-muted">{{ translate('Delivered') }}</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 text-center">
                                <div class="py-2 rounded-3 bg--warning-light">
                                    <div class="stat-number text-warning">{{ number_format($statistics['failed']) }}</div>
                                    <small class="text-muted">{{ translate('Failed') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($campaign->total_contacts > 0)
                <div class="mt-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="small text-muted">{{ translate('Progress') }}</span>
                        <span class="small fw-semibold">{{ $campaign->getProgressPercentage() }}%</span>
                    </div>
                    <div class="campaign-progress-bar">
                        <div class="campaign-progress-fill" style="width: {{ $campaign->getProgressPercentage() }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">{{ number_format($campaign->processed_contacts) }} {{ translate('processed') }}</small>
                        <small class="text-muted">{{ number_format($campaign->total_contacts) }} {{ translate('total') }}</small>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Channel Performance --}}
                <div class="card">
                    <div class="form-header">
                        <h4 class="card-title">{{ translate('Channel Performance') }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach($statistics['by_channel'] as $channel => $channelStats)
                            @php $channelEnum = \App\Enums\Campaign\CampaignChannel::tryFrom($channel); @endphp
                            <div class="col-md-4">
                                <div class="channel-performance-card">
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <div class="channel-perf-icon channel-{{ $channel }}">
                                            <i class="{{ $channelEnum->icon() }}"></i>
                                        </div>
                                        <h6 class="mb-0">{{ $channelEnum->label() }}</h6>
                                    </div>
                                    <div class="row g-2 text-center">
                                        <div class="col-6">
                                            <div class="card py-2">
                                                <div class="fw-bold">{{ number_format($channelStats['total']) }}</div>
                                                <small class="text-muted">{{ translate('Total') }}</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card py-2">
                                                <div class="fw-bold" style="color: #22c55e;">{{ number_format($channelStats['delivered']) }}</div>
                                                <small class="text-muted">{{ translate('Delivered') }}</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card py-2">
                                                <div class="fw-bold" style="color: #3b82f6;">{{ number_format($channelStats['sent']) }}</div>
                                                <small class="text-muted">{{ translate('Sent') }}</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card py-2">
                                                <div class="fw-bold" style="color: #ef4444;">{{ number_format($channelStats['failed']) }}</div>
                                                <small class="text-muted">{{ translate('Failed') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Recent Dispatches --}}
                <div class="card mt-4">
                    <div class="form-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">{{ translate('Recent Dispatches') }}</h4>
                        <select id="dispatchFilter" class="form-select form-select-sm" style="width: auto;">
                            <option value="">{{ translate('All Status') }}</option>
                            @foreach(\App\Enums\Campaign\DispatchStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">{{ translate('Contact') }}</th>
                                        <th>{{ translate('Channel') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                        <th class="pe-4">{{ translate('Sent At') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="dispatchesTable">
                                    @forelse($campaign->dispatches()->with('contact')->orderBy('created_at', 'desc')->limit(10)->get() as $dispatch)
                                    <tr>
                                        <td class="ps-4">
                                            <div>
                                                <span class="fw-semibold">{{ $dispatch->contact?->full_name ?? '-' }}</span>
                                                <br>
                                                <small class="text-muted">{{ $dispatch->getContactAddress() }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            @php $chEnum = \App\Enums\Campaign\CampaignChannel::tryFrom($dispatch->channel->value); @endphp
                                            <span class="channel-badge-sm channel-{{ $dispatch->channel->value }}">
                                                <i class="{{ $chEnum->icon() }}"></i> {{ $chEnum->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="badge {{ $dispatch->status->badgeClass() }}">
                                                    {{ $dispatch->status->label() }}
                                                </span>
                                                @if($dispatch->status->value === \App\Enums\Campaign\DispatchStatus::FAILED->value && $dispatch->error_message)
                                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2 p-0 px-1 border-0" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $dispatch->error_message }}" onclick="showErrorModal('{{ str_replace('\'', '\\\'', $dispatch->error_message) }}')">
                                                        <i class="ri-error-warning-line"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="pe-4">
                                            <small>{{ $dispatch->sent_at?->format('M d, H:i') ?? '-' }}</small>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="ri-mail-send-line fs-2 d-block mb-2"></i>
                                            {{ translate('No dispatches yet') }}
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Recurring Execution History Card --}}
                @if($campaign->type->value === 'recurring' || (isset($runs) && $runs->count() > 0))
                <div class="card mt-4">
                    <div class="form-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">
                            <i class="ri-history-line text-primary me-2"></i> {{ translate('Recurring Execution History') }}
                        </h4>
                        @if(isset($runs))
                        <span class="badge bg-soft-info text-info">{{ $runs->total() ?? $runs->count() }} {{ translate('Total Runs') }}</span>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">{{ translate('Run #') }}</th>
                                        <th>{{ translate('Scheduled Time') }}</th>
                                        <th>{{ translate('Run Completed') }}</th>
                                        <th>{{ translate('Processed') }}</th>
                                        <th>{{ translate('Sent / Failed') }}</th>
                                        <th class="text-end pe-4">{{ translate('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($runs as $run)
                                    <tr>
                                        <td class="ps-4">
                                            <span class="badge bg-soft-primary text-primary fw-bold">#{{ $run->run_number }}</span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><i class="ri-time-line me-1"></i>{{ $run->scheduled_at ? $run->scheduled_at->format('M d, Y h:i A') : '-' }}</small>
                                        </td>
                                        <td>
                                            <small class="text-muted"><i class="ri-check-double-line me-1 text-success"></i>{{ $run->completed_at ? $run->completed_at->format('M d, Y h:i A') : '-' }}</small>
                                        </td>
                                        <td>
                                            <small class="fw-semibold">{{ $run->processed_contacts }} / {{ $run->total_contacts }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-success text-success me-1"><i class="ri-send-plane-line"></i> {{ $run->sent_count }}</span>
                                            @if($run->failed_count > 0)
                                            <span class="badge bg-soft-danger text-danger"><i class="ri-error-warning-line"></i> {{ $run->failed_count }}</span>
                                            @else
                                            <span class="badge bg-soft-secondary text-muted"><i class="ri-close-circle-line"></i> 0</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            <button type="button" class="i-btn btn--primary outline btn--sm" onclick="showRunLogModal('{{ $campaign->uid }}', {{ $run->id }}, {{ $run->run_number }})">
                                                <i class="ri-file-list-3-line me-1"></i> {{ translate('View Log') }}
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="ri-history-line fs-2 d-block mb-2"></i>
                                            {{ translate('No execution runs recorded yet for this recurring campaign') }}
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if(isset($runs) && method_exists($runs, 'links'))
                        <div class="p-3">
                            {{ $runs->links() }}
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                <div class="sticky-side-div">
                    {{-- Campaign Details --}}
                    <div class="card">
                        <div class="form-header">
                            <h4 class="card-title">{{ translate('Campaign Details') }}</h4>
                        </div>
                        <div class="card-body pt-0">
                            <div class="detail-item d-flex justify-content-between flex-wrap">
                                <small class="text-muted">{{ translate('Contact Group') }}</small>
                                <div class="fw-semibold fs-14">{{ $campaign->contactGroup?->name ?? '-' }}</div>
                            </div>
                            <div class="detail-item d-flex justify-content-between flex-wrap">
                                <small class="text-muted">{{ translate('Type') }}</small>
                                <div class="fw-semibold fs-14">
                                    <i class="{{ $campaign->type->icon() }} me-1"></i>
                                    {{ $campaign->type->label() }}
                                </div>
                            </div>
                            <div class="detail-item d-flex justify-content-between flex-wrap">
                                <small class="text-muted">{{ translate('Channels') }}</small>
                                <div class="d-flex gap-1 flex-wrap mt-1">
                                    @foreach($campaign->channels as $ch)
                                    @php $chEnum = \App\Enums\Campaign\CampaignChannel::tryFrom($ch); @endphp
                                    <span class="channel-badge-sm channel-{{ $ch }}">
                                        <i class="{{ $chEnum->icon() }}"></i> {{ $chEnum->label() }}
                                    </span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="detail-item d-flex justify-content-between flex-wrap">
                                <small class="text-muted">{{ translate('Delivery Mode') }}</small>
                                <div class="fw-semibold fs-14">
                                    <i class="{{ $campaign->channel_detection_mode->icon() }} me-1"></i>
                                    {{ $campaign->channel_detection_mode->label() }}
                                </div>
                            </div>
                            @if($campaign->schedule_at)
                            @php
                                $tz = $campaign->timezone ?: (site_settings('time_zone') ?: 'UTC');
                                $localSchedule = \Carbon\Carbon::parse($campaign->schedule_at)->setTimezone($tz);
                            @endphp
                            <div class="detail-item d-flex justify-content-between flex-wrap">
                                <small class="text-muted">{{ translate('Scheduled For') }}</small>
                                <div class="fw-semibold fs-14">
                                    <i class="ri-calendar-event-line me-1"></i>
                                    {{ $localSchedule->format('M d, Y H:i') }} ({{ $tz }})
                                </div>
                            </div>
                            @endif
                            @if($campaign->started_at)
                            <div class="detail-item d-flex justify-content-between flex-wrap">
                                <small class="text-muted">{{ translate('Started At') }}</small>
                                <div class="fw-semibold fs-14">{{ $campaign->started_at->format('M d, Y H:i') }}</div>
                            </div>
                            @endif
                            @if($campaign->completed_at)
                            <div class="detail-item d-flex justify-content-between flex-wrap">
                                <small class="text-muted">{{ translate('Completed At') }}</small>
                                <div class="fw-semibold fs-14">{{ $campaign->completed_at->format('M d, Y H:i') }}</div>
                            </div>
                            @endif
                            <div class="detail-item border-0 d-flex justify-content-between flex-wrap">
                                <small class="text-muted">{{ translate('Created') }}</small>
                                <div class="fw-semibold fs-14">{{ $campaign->created_at->format('M d, Y H:i') }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Delivery Rates --}}
                    <div class="card mt-4">
                        <div class="form-header">
                            <h4 class="card-title">{{ translate('Delivery Rates') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="rate-item mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small">{{ translate('Delivery Rate') }}</span>
                                    <span class="fw-bold" style="color: #22c55e;">{{ $campaign->getDeliveryRate() }}%</span>
                                </div>
                                <div class="rate-progress-bar">
                                    <div class="rate-progress-fill success" style="width: {{ $campaign->getDeliveryRate() }}%"></div>
                                </div>
                            </div>
                            <div class="rate-item">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small">{{ translate('Open Rate') }}</span>
                                    <span class="fw-bold" style="color: #3b82f6;">{{ $campaign->getOpenRate() }}%</span>
                                </div>
                                <div class="rate-progress-bar">
                                    <div class="rate-progress-fill info" style="width: {{ $campaign->getOpenRate() }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Messages Preview --}}
                    <div class="card mt-4">
                        <div class="form-header">
                            <h4 class="card-title">{{ translate('Messages') }}</h4>
                        </div>
                        <div class="card-body p-0">
                            @forelse($campaign->messages as $message)
                            @php $chEnum = \App\Enums\Campaign\CampaignChannel::tryFrom($message->channel->value); @endphp
                            <div class="message-preview-item {{ !$loop->last ? 'border-bottom' : '' }}">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="channel-badge-sm channel-{{ $message->channel->value }}">
                                        <i class="{{ $chEnum->icon() }}"></i>
                                    </span>
                                    <span class="fw-semibold">{{ $chEnum->label() }}</span>
                                </div>
                                @if($message->subject)
                                <div class="small text-muted mb-1">
                                    <strong>{{ translate('Subject') }}:</strong> {{ Str::limit($message->subject, 50) }}
                                </div>
                                @endif
                                <p class="small text-muted mb-0 message-preview-text">{{ Str::limit($message->content, 100) }}</p>
                            </div>
                            @empty
                            <div class="text-center py-4 text-muted">
                                <i class="ri-mail-line fs-2 d-block mb-2"></i>
                                {{ translate('No messages configured') }}
                            </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Quick Actions --}}
                    <div class="card mt-4">
                        <div class="card-body">
                            <a href="{{ route('admin.campaign.messages', $campaign->uid) }}" class="i-btn btn--dark outline btn--md w-100 mb-2">
                                <i class="ri-mail-settings-line me-2"></i>
                                {{ translate('View/Edit Messages') }}
                            </a>
                            <a href="{{ route('admin.campaign.index') }}" class="i-btn btn--primary outline btn--md w-100">
                                <i class="ri-arrow-left-line me-2"></i>
                                {{ translate('Back to Campaigns') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

@endsection

@push('style-push')
<style>
/* Status Icon */
.campaign-status-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background: var(--color-gray-1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--text-secondary);
}
.campaign-status-icon.pulsing {
    animation: pulse 2s infinite;
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* Stats */
.stat-number {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.2;
}

/* Progress Bar */
.campaign-progress-bar {
    height: 10px;
    background: var(--color-gray-1);
    border-radius: 5px;
    overflow: hidden;
}
.campaign-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #22c55e, #16a34a);
    border-radius: 5px;
    transition: width 0.3s ease;
}

/* Channel Performance Cards */
.channel-performance-card {
    padding: 16px;
    background: var(--site-bg);
    border-radius: 12px;
    border: 1px solid var(--color-border);
}
.channel-perf-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}
.channel-perf-icon.channel-sms { background: rgba(99, 102, 241, 0.12); color: #6366f1; }
.channel-perf-icon.channel-email { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.channel-perf-icon.channel-whatsapp { background: rgba(37, 211, 102, 0.12); color: #25d366; }

/* Channel Badges */
.channel-badge-sm {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
}
.channel-badge-sm.channel-sms { background: rgba(99, 102, 241, 0.12); color: #6366f1; }
.channel-badge-sm.channel-email { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.channel-badge-sm.channel-whatsapp { background: rgba(37, 211, 102, 0.12); color: #25d366; }

/* Detail Items */
.detail-item {
    padding: 12px 0;
    border-bottom: 1px solid var(--color-border-light);
}
.detail-item:last-child { border-bottom: none; }

/* Rate Progress */
.rate-progress-bar {
    height: 8px;
    background: var(--color-gray-1);
    border-radius: 4px;
    overflow: hidden;
}
.rate-progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}
.rate-progress-fill.success { background: linear-gradient(90deg, #22c55e, #16a34a); }
.rate-progress-fill.info { background: linear-gradient(90deg, #3b82f6, #2563eb); }

/* Message Preview */
.message-preview-item {
    padding: 16px;
}
.message-preview-text {
    background: var(--site-bg);
    padding: 10px 12px;
    border-radius: 8px;
    white-space: pre-wrap;
    line-height: 1.5;
}

/* Table */
.table th {
    background: var(--site-bg);
    font-weight: 600;
    font-size: 13px;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--color-border);
    padding: 12px 16px;
}
.table td {
    padding: 12px 16px;
    vertical-align: middle;
    border-bottom: 1px solid var(--color-border-light);
}
.table tbody tr:last-child td { border-bottom: none; }
</style>

<!-- Error Modal -->
<div class="modal fade" id="dispatchErrorModal" tabindex="-1" aria-labelledby="dispatchErrorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dispatchErrorModalLabel">
                    <i class="ri-error-warning-line text-danger me-2"></i> {{ translate('Failure Reason') }}
                </h5>
                <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle" data-bs-dismiss="modal">
                    <i class="ri-close-large-line"></i>
                </button>
            </div>
            <div class="modal-body p-4">
                <p id="dispatchErrorModalContent" class="text-danger mb-0" style="white-space: pre-wrap; font-size: 14px; background: rgba(220, 38, 38, 0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(220, 38, 38, 0.1);"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Run Log Details Modal -->
<div class="modal fade" id="runLogModal" tabindex="-1" aria-labelledby="runLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="runLogModalLabel">
                    <i class="ri-history-line text-primary me-2"></i> <span id="runLogTitle">{{ translate('Execution Run Log') }}</span>
                </h5>
                <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle" data-bs-dismiss="modal">
                    <i class="ri-close-large-line"></i>
                </button>
            </div>
            <div class="modal-body p-4">
                <div id="runLogLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="runLogContent" style="display: none;">
                    <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-6 col-md-3">
                            <small class="text-muted d-block">{{ translate('Run Status') }}</small>
                            <span id="runModalStatus" class="badge bg-success">Completed</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <small class="text-muted d-block">{{ translate('Scheduled Time') }}</small>
                            <span id="runModalScheduled" class="fw-semibold fs-13">-</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <small class="text-muted d-block">{{ translate('Completed Time') }}</small>
                            <span id="runModalCompleted" class="fw-semibold fs-13">-</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <small class="text-muted d-block">{{ translate('Sent / Failed') }}</small>
                            <span id="runModalCounts" class="fw-semibold fs-13">-</span>
                        </div>
                    </div>

                    <h6 class="mb-3">{{ translate('Contact Message Logs') }}</h6>
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table align-middle mb-0 table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ translate('Contact') }}</th>
                                    <th>{{ translate('Target Address') }}</th>
                                    <th>{{ translate('Status') }}</th>
                                    <th>{{ translate('Error / Response') }}</th>
                                </tr>
                            </thead>
                            <tbody id="runLogTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Close') }}</button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('script-push')
@if($campaign->status === \App\Enums\Campaign\UnifiedCampaignStatus::RUNNING)
<script>
// Auto-refresh statistics every 10 seconds for running campaigns
setInterval(function() {
    fetch('{{ route("admin.campaign.statistics", $campaign->uid) }}')
        .then(response => response.json())
        .then(data => {
            // Update statistics display
            if (data.total !== undefined) {
                document.querySelector('.stat-number.text-primary').textContent = new Intl.NumberFormat().format(data.total);
            }
            if (data.sent !== undefined) {
                document.querySelectorAll('.stat-number')[1].textContent = new Intl.NumberFormat().format(data.sent);
            }
            if (data.delivered !== undefined) {
                document.querySelectorAll('.stat-number')[2].textContent = new Intl.NumberFormat().format(data.delivered);
            }
            if (data.failed !== undefined) {
                document.querySelectorAll('.stat-number')[3].textContent = new Intl.NumberFormat().format(data.failed);
            }
        })
        .catch(err => console.log('Stats refresh error:', err));
}, 10000);
</script>
@endif

<script>
    function showErrorModal(message) {
        document.getElementById('dispatchErrorModalContent').textContent = message;
        var myModal = new bootstrap.Modal(document.getElementById('dispatchErrorModal'));
        myModal.show();
    }

    function showRunLogModal(campaignUid, runId, runNumber) {
        document.getElementById('runLogTitle').textContent = '{{ translate("Run #") }}' + runNumber + ' {{ translate("Execution Log") }}';
        document.getElementById('runLogLoading').style.display = 'block';
        document.getElementById('runLogContent').style.display = 'none';

        var myModal = new bootstrap.Modal(document.getElementById('runLogModal'));
        myModal.show();

        var url = '{{ route("admin.campaign.run-log", ["uid" => ":uid", "runId" => ":runId"]) }}'
            .replace(':uid', campaignUid)
            .replace(':runId', runId);

        fetch(url)
            .then(response => response.json())
            .then(res => {
                if (res.status && res.data) {
                    var data = res.data;
                    document.getElementById('runModalStatus').textContent = (data.status || 'COMPLETED').toUpperCase();
                    document.getElementById('runModalScheduled').textContent = data.scheduled_at || '-';
                    document.getElementById('runModalCompleted').textContent = data.completed_at || '-';
                    document.getElementById('runModalCounts').innerHTML = '<span class="text-success fw-bold">' + data.sent_count + ' sent</span> / <span class="text-danger fw-bold">' + data.failed_count + ' failed</span>';

                    var tbody = document.getElementById('runLogTableBody');
                    tbody.innerHTML = '';

                    if (data.dispatch_history && data.dispatch_history.length > 0) {
                        data.dispatch_history.forEach(function(item) {
                            var statusBadge = item.status === 'sent' || item.status === 'delivered' 
                                ? '<span class="badge bg-soft-success text-success"><i class="ri-check-line me-1"></i>Sent</span>' 
                                : '<span class="badge bg-soft-danger text-danger"><i class="ri-error-warning-line me-1"></i>Failed</span>';

                            var errorMsg = item.error_message 
                                ? '<span class="text-danger small"><i class="ri-error-warning-line me-1"></i>' + escapeHtml(item.error_message) + '</span>' 
                                : '<span class="text-muted small">-</span>';

                            var tr = document.createElement('tr');
                            tr.innerHTML = '<td><strong>' + escapeHtml(item.contact_name) + '</strong></td>' +
                                '<td><small class="text-muted">' + escapeHtml(item.contact_address) + '</small></td>' +
                                '<td>' + statusBadge + '</td>' +
                                '<td>' + errorMsg + '</td>';
                            tbody.appendChild(tr);
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">No dispatch history recorded for this run</td></tr>';
                    }

                    document.getElementById('runLogLoading').style.display = 'none';
                    document.getElementById('runLogContent').style.display = 'block';
                }
            })
            .catch(err => {
                document.getElementById('runLogLoading').innerHTML = '<div class="alert alert-danger mb-0">Failed to load run log details</div>';
            });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>
@endpush
