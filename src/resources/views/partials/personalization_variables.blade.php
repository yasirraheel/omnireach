@php
    $targetSelector = $targetSelector ?? '#message';
    $showUnsubscribe = $showUnsubscribe ?? true;
@endphp

<div class="mt-3 pt-3 border-top">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0">
            <i class="ri-user-settings-line me-1"></i>
            {{ translate('Personalization Variables') }}
        </h6>
        <small class="text-muted">{{ translate('Click to insert at cursor position') }}</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="i-btn btn--dark outline btn--sm insert-global-var" data-var="@{{first_name}}" data-target="{{ $targetSelector }}">
            <i class="ri-user-line me-1"></i>@{{first_name}}
        </button>
        <button type="button" class="i-btn btn--dark outline btn--sm insert-global-var" data-var="@{{last_name}}" data-target="{{ $targetSelector }}">
            <i class="ri-user-line me-1"></i>@{{last_name}}
        </button>
        <button type="button" class="i-btn btn--dark outline btn--sm insert-global-var" data-var="@{{full_name}}" data-target="{{ $targetSelector }}">
            <i class="ri-user-2-line me-1"></i>@{{full_name}}
        </button>
        <button type="button" class="i-btn btn--dark outline btn--sm insert-global-var" data-var="@{{email}}" data-target="{{ $targetSelector }}">
            <i class="ri-mail-line me-1"></i>@{{email}}
        </button>
        <button type="button" class="i-btn btn--dark outline btn--sm insert-global-var" data-var="@{{phone}}" data-target="{{ $targetSelector }}">
            <i class="ri-phone-line me-1"></i>@{{phone}}
        </button>
        @if($showUnsubscribe)
        <button type="button" class="i-btn btn--dark outline btn--sm insert-global-var" data-var="@{{unsubscribe_url}}" data-target="{{ $targetSelector }}">
            <i class="ri-link me-1"></i>@{{unsubscribe_url}}
        </button>
        @endif
    </div>
</div>

@push('script-push')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Helper to get CKEditor instance dynamically
    function getEditorInstance(selector) {
        if (typeof editors !== 'undefined' && editors[selector]) {
            return editors[selector];
        }
        return null;
    }

    // Use event delegation for dynamically added or multiple instances
    document.body.addEventListener('click', function(e) {
        const btn = e.target.closest('.insert-global-var');
        if (!btn) return;

        const variable = btn.dataset.var;
        const targetSelector = btn.dataset.target;
        const textarea = document.querySelector(targetSelector);
        
        // Check if it's a CKEditor instance first
        const editorInstance = getEditorInstance(targetSelector);
        if (editorInstance) {
            editorInstance.model.change(writer => {
                editorInstance.model.insertContent(writer.createText(variable));
            });
            return;
        }

        // Fallback to standard textarea insertion
        if (textarea) {
            const start = textarea.selectionStart || 0;
            const end = textarea.selectionEnd || 0;
            const text = textarea.value || '';
            textarea.value = text.substring(0, start) + variable + text.substring(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + variable.length;
            textarea.dispatchEvent(new Event('input')); // trigger change events
        }
    });
});
</script>
@endpush
