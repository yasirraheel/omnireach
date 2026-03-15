@extends('admin.layouts.app')
@section("panel")

@push('style-push')
<link rel="stylesheet" href="{{ asset('assets/theme/update/css/update-system.css') }}">
@endpush

<main class="main-body">
    <div class="container-fluid px-0">

        <!-- Current Version & Status -->
        <div class="i-card-md mt-3">
            <div class="card--header">
                <h4 class="card-title">
                    {{ translate("System Update") }}
                </h4>
            </div>
            <div class="card-body">
                <div class="us-version-banner">
                    <div class="us-version-left">
                        <div class="us-version-icon">
                            <i class="ri-shield-check-line"></i>
                        </div>
                        <div>
                            <div class="us-version-label">{{ translate("Current Version") }}</div>
                            <div class="us-version-value">
                                <span class="us-version-number">{{ translate("V") }}{{ site_settings("app_version", 1.1) }}</span>
                                <span class="us-version-dot"></span>
                                <span class="us-version-date">{{ translate("Installed") }}: {{ get_date_time(site_settings("system_installed_at", \Carbon\Carbon::now())) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pre-Update Checklist -->
                <div class="us-checklist">
                    <div class="us-checklist-title">
                        <i class="ri-error-warning-line"></i>
                        {{ translate("Before You Update") }}
                    </div>
                    <div class="us-checklist-items">
                        <label class="us-check-item">
                            <input type="checkbox" class="us-checkbox" data-check="backup">
                            <span class="us-check-mark"><i class="ri-check-line"></i></span>
                            <span>{{ translate("I have taken a full backup (files & database)") }}</span>
                        </label>
                        <label class="us-check-item">
                            <input type="checkbox" class="us-checkbox" data-check="connection">
                            <span class="us-check-mark"><i class="ri-check-line"></i></span>
                            <span>{{ translate("I have a stable internet connection") }}</span>
                        </label>
                        <label class="us-check-item">
                            <input type="checkbox" class="us-checkbox" data-check="tab">
                            <span class="us-check-mark"><i class="ri-check-line"></i></span>
                            <span>{{ translate("I will not close this tab during the update") }}</span>
                        </label>
                    </div>
                </div>

                <!-- Upload & Update -->
                <form action="{{ route('admin.system.update') }}" method="post" enctype="multipart/form-data" id="updateForm">
                    @csrf
                    <label for="updateFile" class="us-upload-area" id="uploadArea">
                        <div class="us-upload-default" id="uploadDefault">
                            <i class="ri-upload-cloud-2-line us-upload-icon"></i>
                            <div class="us-upload-text">{{ translate("Upload Update Package") }}</div>
                            <div class="us-upload-subtext">{{ translate("Drag & drop or click to browse") }} &mdash; .zip {{ translate("only") }}</div>
                        </div>
                        <div class="us-upload-selected" id="uploadSelected" style="display:none;">
                            <i class="ri-file-zip-line us-upload-icon us-upload-icon--file"></i>
                            <div class="us-upload-text" id="uploadFileName"></div>
                            <div class="us-upload-subtext" id="uploadFileSize"></div>
                        </div>
                        <input type="file" id="updateFile" name="updateFile" accept=".zip" hidden>
                    </label>

                    <!-- Progress Overlay (hidden by default) -->
                    <div class="us-progress-overlay" id="progressOverlay" style="display:none;">
                        <div class="us-progress-content">
                            <div class="us-progress-spinner">
                                <svg viewBox="0 0 50 50">
                                    <circle cx="25" cy="25" r="20" fill="none" stroke-width="4" stroke="currentColor" stroke-dasharray="80 200" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <div class="us-progress-title">{{ translate("Updating System...") }}</div>
                            <div class="us-progress-desc">{{ translate("Please wait. This may take a few minutes. Do not close this tab or navigate away.") }}</div>
                            <div class="us-progress-steps">
                                <div class="us-step active" id="step1">
                                    <i class="ri-upload-2-line"></i>
                                    <span>{{ translate("Uploading") }}</span>
                                </div>
                                <div class="us-step" id="step2">
                                    <i class="ri-folder-zip-line"></i>
                                    <span>{{ translate("Extracting") }}</span>
                                </div>
                                <div class="us-step" id="step3">
                                    <i class="ri-database-2-line"></i>
                                    <span>{{ translate("Migrating") }}</span>
                                </div>
                                <div class="us-step" id="step4">
                                    <i class="ri-check-double-line"></i>
                                    <span>{{ translate("Finalizing") }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="us-btn-update" id="updateBtn" disabled>
                            <i class="ri-download-cloud-line"></i>
                            {{ translate("Update Now") }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Post-Update Instructions -->
        <div class="i-card-md mt-3">
            <div class="card--header">
                <h4 class="card-title">
                    {{ translate("Post-Update Instructions") }}
                </h4>
            </div>
            <div class="card-body">
                <div class="us-instructions">
                    <p class="us-instructions-intro">
                        {{ translate("After a successful update, follow these steps to ensure everything works correctly:") }}
                    </p>

                    <div class="us-instruction-group">
                        <div class="us-instruction-step">
                            <div class="us-step-number">1</div>
                            <div class="us-step-content">
                                <h6>{{ translate("Clear Application Cache") }}</h6>
                                <p>{{ translate("Click the refresh button in the top-left corner of the admin header, or run the following command from the") }} <code>src</code> {{ translate("directory of your project:") }}</p>
                                <div class="us-code-block">
                                    <code>php artisan optimize:clear</code>
                                    <button type="button" class="us-copy-btn" data-copy="php artisan optimize:clear"><i class="ri-file-copy-line"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="us-instruction-step">
                            <div class="us-step-number">2</div>
                            <div class="us-step-content">
                                <h6>{{ translate("Restart Node.js Services") }}</h6>
                                <p>{{ translate("If you are using PM2 for WhatsApp QR scanning or other Node.js services, restart them:") }}</p>
                                <div class="us-code-block">
                                    <code>pm2 restart all</code>
                                    <button type="button" class="us-copy-btn" data-copy="pm2 restart all"><i class="ri-file-copy-line"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="us-instruction-step">
                            <div class="us-step-number">3</div>
                            <div class="us-step-content">
                                <h6>{{ translate("Hard Refresh Your Browser") }}</h6>
                                <p>{{ translate("If styles or layouts appear broken after the update, perform a hard refresh to clear your browser cache:") }}</p>
                                <div class="us-key-combo">
                                    <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>R</kbd>
                                    <span class="us-key-or">{{ translate("or") }}</span>
                                    <kbd>Cmd</kbd> + <kbd>Shift</kbd> + <kbd>R</kbd>
                                    <span class="us-key-label">(macOS)</span>
                                </div>
                            </div>
                        </div>

                        <div class="us-instruction-step">
                            <div class="us-step-number">4</div>
                            <div class="us-step-content">
                                <h6>{{ translate("Purge CDN Cache (If Applicable)") }}</h6>
                                <p>{{ translate("If you use Cloudflare or any other CDN, purge the cache from your CDN dashboard to ensure updated assets are served to all users. In Cloudflare:") }}</p>
                                <div class="us-note-box">
                                    <i class="ri-cloud-line"></i>
                                    <span>{{ translate("Cloudflare Dashboard") }} &rarr; {{ translate("Caching") }} &rarr; {{ translate("Configuration") }} &rarr; <strong>{{ translate("Purge Everything") }}</strong></span>
                                </div>
                            </div>
                        </div>

                        <div class="us-instruction-step">
                            <div class="us-step-number">5</div>
                            <div class="us-step-content">
                                <h6>{{ translate("Verify the Update") }}</h6>
                                <p>{{ translate("Check the version number displayed above to confirm the update was applied successfully. If the version has not changed, try clearing cache again and refreshing.") }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

@push('script-push')
<script>
"use strict";
(function($) {

    var formSubmitting = false;
    var allChecked = false;

    // Checklist validation
    function validateChecklist() {
        var checked = $('.us-checkbox:checked').length;
        var total = $('.us-checkbox').length;
        allChecked = checked === total;

        var hasFile = $('#updateFile').val() !== '';
        $('#updateBtn').prop('disabled', !(allChecked && hasFile));
    }

    $('.us-checkbox').on('change', function() {
        var item = $(this).closest('.us-check-item');
        if ($(this).is(':checked')) {
            item.addClass('checked');
        } else {
            item.removeClass('checked');
        }
        validateChecklist();
    });

    // File upload
    $('#updateFile').on('change', function() {
        var file = this.files[0];
        if (file) {
            var sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            $('#uploadFileName').text(file.name);
            $('#uploadFileSize').text(sizeMB + ' MB');
            $('#uploadDefault').hide();
            $('#uploadSelected').show();
            $('#uploadArea').addClass('us-file-selected');
        } else {
            $('#uploadDefault').show();
            $('#uploadSelected').hide();
            $('#uploadArea').removeClass('us-file-selected');
        }
        validateChecklist();
    });

    // Form submit — show progress, prevent double submit
    $('#updateForm').on('submit', function(e) {
        if (formSubmitting) {
            e.preventDefault();
            return false;
        }

        if (!allChecked) {
            e.preventDefault();
            notify('error', '{{ translate("Please complete the pre-update checklist first.") }}');
            return false;
        }

        if (!$('#updateFile').val()) {
            e.preventDefault();
            notify('error', '{{ translate("Please select an update file.") }}');
            return false;
        }

        formSubmitting = true;

        // Show progress overlay
        $('#progressOverlay').fadeIn(200);
        $('#updateBtn').prop('disabled', true).html('<i class="ri-loader-4-line us-spin"></i> {{ translate("Updating...") }}');

        // Simulate step progression
        setTimeout(function() { $('#step1').addClass('done'); $('#step2').addClass('active'); }, 2000);
        setTimeout(function() { $('#step2').addClass('done'); $('#step3').addClass('active'); }, 5000);
        setTimeout(function() { $('#step3').addClass('done'); $('#step4').addClass('active'); }, 10000);
    });

    // Copy button for code blocks
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;left:-9999px;top:-9999px';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
        return Promise.resolve();
    }

    $('.us-copy-btn').on('click', function(e) {
        e.preventDefault();
        var text = $(this).data('copy');
        var $btn = $(this);
        copyText(text).then(function() {
            $btn.html('<i class="ri-check-line"></i>');
            setTimeout(function() { $btn.html('<i class="ri-file-copy-line"></i>'); }, 1500);
            notify('success', '{{ translate("Copied to clipboard") }}');
        });
    });

})(jQuery);
</script>
@endpush

@endsection
