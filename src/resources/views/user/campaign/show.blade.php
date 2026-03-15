@extends('user.layouts.app')

@push('style-include')
@include('user.partials.lead-stat-styles')
@endpush

@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">{{ translate('Dashboard') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('user.campaign.index') }}">{{ translate('Campaigns') }}</a></li>
                            <li class="breadcrumb-item active">{{ translate('Details') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="page-header-right">
                @if($campaign->canEdit())
                <a href="{{ route('user.campaign.edit', $campaign->id) }}" class="i-btn btn--primary outline btn--md">
                    <i class="ri-pencil-line"></i> {{ translate('Edit') }}
                </a>
                @endif
                <a href="{{ route('user.campaign.index') }}" class="i-btn btn--dark outline btn--md">
                    <i class="ri-arrow-left-line"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="lead-stats-grid mb-4">
            <div class="lead-stat-card stat-primary">
                <div class="lead-stat-icon icon-primary">
                    <i class="ri-group-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['total_contacts'] ?? $campaign->total_contacts) }}</h3>
                    <p>{{ translate('Total Contacts') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-info">
                <div class="lead-stat-icon icon-info">
                    <i class="ri-send-plane-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['sent'] ?? 0) }}</h3>
                    <p>{{ translate('Sent') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-success">
                <div class="lead-stat-icon icon-success">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['delivered'] ?? 0) }}</h3>
                    <p>{{ translate('Delivered') }}</p>
                </div>
            </div>
            <div class="lead-stat-card stat-danger">
                <div class="lead-stat-icon icon-danger">
                    <i class="ri-close-circle-line"></i>
                </div>
                <div class="lead-stat-content">
                    <h3>{{ number_format($statistics['failed'] ?? 0) }}</h3>
                    <p>{{ translate('Failed') }}</p>
                </div>
            </div>
        </div>

        <!-- Progress -->
        @php $progress = $campaign->getProgressPercentage(); @endphp
        <div class="card mb-4">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-medium">{{ translate('Campaign Progress') }}</span>
                    <span class="text-muted">{{ $progress }}%</span>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $progress }}%"></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">{{ translate('Campaign Details') }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">{{ translate('Campaign Name') }}</label>
                                <p class="fw-semibold mb-0">{{ $campaign->name }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">{{ translate('Status') }}</label>
                                <p class="mb-0">
                                    @php
                                        $statusColor = match($campaign->status->value) {
                                            'draft' => 'secondary',
                                            'scheduled' => 'warning',
                                            'running' => 'info',
                                            'paused' => 'danger',
                                            'completed' => 'success',
                                            'cancelled' => 'secondary',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <span class="i-badge {{ $statusColor }}-soft pill">{{ $campaign->status->label() }}</span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">{{ translate('Contact Group') }}</label>
                                <p class="fw-semibold mb-0">{{ $campaign->contactGroup?->name ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">{{ translate('Campaign Type') }}</label>
                                <p class="fw-semibold mb-0">{{ ucfirst($campaign->type->value ?? $campaign->type) }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">{{ translate('Scheduled At') }}</label>
                                <p class="fw-semibold mb-0">
                                    @if($campaign->scheduled_at)
                                        {{ $campaign->scheduled_at->format('M d, Y h:i A') }}
                                    @else
                                        <span class="text-muted">{{ translate('Not scheduled') }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">{{ translate('Created') }}</label>
                                <p class="fw-semibold mb-0">{{ $campaign->created_at->format('M d, Y h:i A') }}</p>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted mb-1">{{ translate('Channels') }}</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    @foreach($campaign->channels ?? [] as $channel)
                                        @php $channelEnum = \App\Enums\Campaign\CampaignChannel::tryFrom($channel); @endphp
                                        @if($channelEnum)
                                            @php
                                                $badgeColor = match($channel) {
                                                    'whatsapp' => 'success',
                                                    'email' => 'info',
                                                    'sms' => 'primary',
                                                    default => 'secondary'
                                                };
                                            @endphp
                                            <span class="i-badge {{ $badgeColor }}-soft pill">
                                                <i class="{{ $channelEnum->icon() }} me-1"></i>{{ $channelEnum->label() }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                            @if($campaign->description)
                            <div class="col-12">
                                <label class="form-label text-muted mb-1">{{ translate('Description') }}</label>
                                <p class="mb-0">{{ $campaign->description }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Messages Preview -->
                @if($campaign->messages->count() > 0)
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">{{ translate('Messages') }}</h4>
                        @if($campaign->canEdit())
                        <a href="{{ route('user.campaign.messages', $campaign->id) }}" class="i-btn btn--primary btn--sm">
                            <i class="ri-settings-3-line"></i> {{ translate('Configure') }}
                        </a>
                        @endif
                    </div>
                    <div class="card-body px-0 pt-0">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>{{ translate('Channel') }}</th>
                                        <th>{{ translate('Subject/Preview') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($campaign->messages as $message)
                                    <tr>
                                        <td>
                                            @php
                                                $msgChannel = $message->channel instanceof \App\Enums\Campaign\CampaignChannel
                                                    ? $message->channel
                                                    : \App\Enums\Campaign\CampaignChannel::tryFrom($message->channel);
                                                $channelValue = $msgChannel?->value ?? $message->channel;
                                                $msgBadgeColor = match($channelValue) {
                                                    'whatsapp' => 'success',
                                                    'email' => 'info',
                                                    'sms' => 'primary',
                                                    default => 'secondary'
                                                };
                                            @endphp
                                            <span class="i-badge {{ $msgBadgeColor }}-soft pill">
                                                {{ $msgChannel?->label() ?? ucfirst($channelValue) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-dark">{{ Str::limit($message->subject ?? $message->content ?? '-', 50) }}</span>
                                        </td>
                                        <td>
                                            <span class="i-badge {{ $message->is_active ? 'success' : 'secondary' }}-soft pill">
                                                {{ $message->is_active ? translate('Active') : translate('Inactive') }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate('Quick Actions') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            @if($campaign->canEdit())
                            <a href="{{ route('user.campaign.messages', $campaign->id) }}" class="i-btn btn--primary btn--md w-100">
                                <i class="ri-message-2-line me-1"></i>{{ translate('Configure Messages') }}
                            </a>
                            @if($campaign->messages->count() > 0)
                            <a href="{{ route('user.campaign.review', $campaign->id) }}" class="i-btn btn--success btn--md w-100">
                                <i class="ri-rocket-line me-1"></i>{{ translate('Review & Launch') }}
                            </a>
                            @endif
                            @endif

                            @if($campaign->status->value === 'draft' && $campaign->messages->isEmpty())
                            <div class="alert alert-info py-2 mb-0">
                                <small><i class="ri-information-line me-1"></i>{{ translate('Configure messages before launching') }}</small>
                            </div>
                            @endif

                            @if($campaign->status->value === 'running')
                            <form action="{{ route('user.campaign.pause', $campaign->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="i-btn btn--warning btn--md w-100">
                                    <i class="ri-pause-line me-1"></i>{{ translate('Pause Campaign') }}
                                </button>
                            </form>
                            @endif

                            @if($campaign->status->value === 'paused')
                            <form action="{{ route('user.campaign.resume', $campaign->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="i-btn btn--info btn--md w-100">
                                    <i class="ri-play-line me-1"></i>{{ translate('Resume Campaign') }}
                                </button>
                            </form>
                            @endif

                            @if($campaign->canCancel())
                            <form action="{{ route('user.campaign.cancel', $campaign->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="i-btn btn--warning outline btn--md w-100" onclick="return confirm('{{ translate('Are you sure you want to cancel this campaign?') }}')">
                                    <i class="ri-close-circle-line me-1"></i>{{ translate('Cancel Campaign') }}
                                </button>
                            </form>
                            @endif

                            <form action="{{ route('user.campaign.duplicate', $campaign->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="i-btn btn--primary outline btn--md w-100">
                                    <i class="ri-file-copy-line me-1"></i>{{ translate('Duplicate Campaign') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Campaign Info -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate('Campaign Info') }}</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">{{ translate('Messages') }}</span>
                                <span class="fw-medium">{{ $campaign->messages->count() }}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">{{ translate('Total Contacts') }}</span>
                                <span class="fw-medium">{{ number_format($campaign->total_contacts) }}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">{{ translate('Progress') }}</span>
                                <span class="fw-medium">{{ $progress }}%</span>
                            </li>
                            @if($campaign->started_at)
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">{{ translate('Started At') }}</span>
                                <span class="fw-medium">{{ $campaign->started_at->format('M d, Y') }}</span>
                            </li>
                            @endif
                            @if($campaign->completed_at)
                            <li class="d-flex justify-content-between py-2">
                                <span class="text-muted">{{ translate('Completed At') }}</span>
                                <span class="fw-medium">{{ $campaign->completed_at->format('M d, Y') }}</span>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
