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

                <!-- Recurring Execution History Card -->
                @if($campaign->type->value === 'recurring' || (isset($runs) && $runs->count() > 0))
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">
                            <i class="ri-history-line text-primary me-2"></i> {{ translate('Recurring Execution History') }}
                        </h4>
                        @if(isset($runs))
                        <span class="i-badge info-soft pill">{{ $runs->total() ?? $runs->count() }} {{ translate('Total Runs') }}</span>
                        @endif
                    </div>
                    <div class="card-body px-0 pt-0">
                        <div class="table-container">
                            <table>
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
                                            <span class="i-badge primary-soft pill">#{{ $run->run_number }}</span>
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
                                            <span class="i-badge success-soft pill me-1"><i class="ri-send-plane-line me-1"></i>{{ $run->sent_count }}</span>
                                            @if($run->failed_count > 0)
                                            <span class="i-badge danger-soft pill"><i class="ri-error-warning-line me-1"></i>{{ $run->failed_count }}</span>
                                            @else
                                            <span class="i-badge secondary-soft pill">0</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            <button type="button" class="i-btn btn--primary outline btn--sm" onclick="showUserRunLogModal({{ $campaign->id }}, {{ $run->id }}, {{ $run->run_number }})">
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
                            @if($campaign->schedule_at)
                            @php
                                $tz = $campaign->timezone ?: (site_settings('time_zone') ?: 'UTC');
                                $localSchedule = \Carbon\Carbon::parse($campaign->schedule_at)->setTimezone($tz);
                            @endphp
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">{{ translate('Scheduled For') }}</span>
                                <span class="fw-medium">{{ $localSchedule->format('M d, Y H:i') }} ({{ $tz }})</span>
                            </li>
                            @endif
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

<!-- Run Log Details Modal -->
<div class="modal fade" id="userRunLogModal" tabindex="-1" aria-labelledby="userRunLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userRunLogModalLabel">
                    <i class="ri-history-line text-primary me-2"></i> <span id="userRunLogTitle">{{ translate('Execution Run Log') }}</span>
                </h5>
                <button type="button" class="icon-btn btn-ghost btn-sm danger-soft circle" data-bs-dismiss="modal">
                    <i class="ri-close-large-line"></i>
                </button>
            </div>
            <div class="modal-body p-4">
                <div id="userRunLogLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="userRunLogContent" style="display: none;">
                    <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-6 col-md-3">
                            <small class="text-muted d-block">{{ translate('Run Status') }}</small>
                            <span id="userRunModalStatus" class="i-badge success-soft pill">Completed</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <small class="text-muted d-block">{{ translate('Scheduled Time') }}</small>
                            <span id="userRunModalScheduled" class="fw-semibold fs-13">-</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <small class="text-muted d-block">{{ translate('Completed Time') }}</small>
                            <span id="userRunModalCompleted" class="fw-semibold fs-13">-</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <small class="text-muted d-block">{{ translate('Sent / Failed') }}</small>
                            <span id="userRunModalCounts" class="fw-semibold fs-13">-</span>
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
                            <tbody id="userRunLogTableBody">
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
@endsection

@push('script-push')
<script>
    function showUserRunLogModal(campaignId, runId, runNumber) {
        document.getElementById('userRunLogTitle').textContent = '{{ translate("Run #") }}' + runNumber + ' {{ translate("Execution Log") }}';
        document.getElementById('userRunLogLoading').style.display = 'block';
        document.getElementById('userRunLogContent').style.display = 'none';

        var myModal = new bootstrap.Modal(document.getElementById('userRunLogModal'));
        myModal.show();

        var url = '{{ route("user.campaign.run-log", ["id" => ":id", "runId" => ":runId"]) }}'
            .replace(':id', campaignId)
            .replace(':runId', runId);

        fetch(url)
            .then(response => response.json())
            .then(res => {
                if (res.status && res.data) {
                    var data = res.data;
                    document.getElementById('userRunModalStatus').textContent = (data.status || 'COMPLETED').toUpperCase();
                    document.getElementById('userRunModalScheduled').textContent = data.scheduled_at || '-';
                    document.getElementById('userRunModalCompleted').textContent = data.completed_at || '-';
                    document.getElementById('userRunModalCounts').innerHTML = '<span class="text-success fw-bold">' + data.sent_count + ' sent</span> / <span class="text-danger fw-bold">' + data.failed_count + ' failed</span>';

                    var tbody = document.getElementById('userRunLogTableBody');
                    tbody.innerHTML = '';

                    if (data.dispatch_history && data.dispatch_history.length > 0) {
                        data.dispatch_history.forEach(function(item) {
                            var statusBadge = item.status === 'sent' || item.status === 'delivered' 
                                ? '<span class="i-badge success-soft pill"><i class="ri-check-line me-1"></i>Sent</span>' 
                                : '<span class="i-badge danger-soft pill"><i class="ri-error-warning-line me-1"></i>Failed</span>';

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

                    document.getElementById('userRunLogLoading').style.display = 'none';
                    document.getElementById('userRunLogContent').style.display = 'block';
                }
            })
            .catch(err => {
                document.getElementById('userRunLogLoading').innerHTML = '<div class="alert alert-danger mb-0">Failed to load run log details</div>';
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
