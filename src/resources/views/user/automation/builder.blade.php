@extends('user.layouts.app')
@push("style-include")
<style>
    /* Page Header Fix */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .page-header-right {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    /* Visual Builder Styles */
    .builder-container {
        display: flex;
        height: calc(100vh - 120px);
        background: var(--body-bg, #f8fafc);
        border-radius: 12px;
        overflow: hidden;
    }

    /* Sidebar */
    .builder-sidebar {
        width: 320px;
        background: var(--card-bg, #fff);
        border-right: 1px solid var(--border-color, #e9ecef);
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }

    .sidebar-header {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
    }

    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }

    .node-category {
        margin-bottom: 1.5rem;
    }

    .node-category-title {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-muted, #6b7280);
        margin-bottom: 0.75rem;
        letter-spacing: 0.05em;
    }

    .node-palette {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .node-template {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e9ecef);
        border-radius: 8px;
        cursor: grab;
        transition: all 0.2s ease;
    }

    .node-template:hover {
        border-color: var(--color-primary);
        box-shadow: 0 2px 8px var(--color-primary-light);
    }

    .node-template:active {
        cursor: grabbing;
    }

    .node-template-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .node-template-icon.trigger { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .node-template-icon.action { background: rgba(59, 130, 246, 0.1); color: var(--info-color, #3b82f6); }
    .node-template-icon.condition { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }
    .node-template-icon.wait { background: rgba(107, 114, 128, 0.1); color: var(--secondary-color, #6b7280); }

    .node-template-info h5 {
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 0;
        color: var(--text-color, #1f2937);
    }

    .node-template-info p {
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
        margin-bottom: 0;
    }

    /* Canvas */
    .builder-canvas {
        flex: 1;
        position: relative;
        overflow: auto;
        background:
            linear-gradient(90deg, var(--border-color, #f1f5f9) 1px, transparent 1px),
            linear-gradient(180deg, var(--border-color, #f1f5f9) 1px, transparent 1px);
        background-size: 20px 20px;
    }

    .canvas-area {
        min-width: 2000px;
        min-height: 1500px;
        position: relative;
        transition: outline-color 0.2s ease, background-color 0.2s ease;
    }

    .canvas-area.drag-over {
        background-color: rgba(80, 70, 229, 0.02);
        outline: 2px dashed var(--color-primary);
        outline-offset: -4px;
        border-radius: 8px;
    }

    /* Workflow Nodes */
    .workflow-node {
        position: absolute;
        width: 280px;
        background: var(--card-bg, #fff);
        border: 2px solid var(--border-color, #e9ecef);
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        cursor: move;
        user-select: none;
    }

    .workflow-node.is-dragging {
        opacity: 0.92;
        z-index: 100;
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
    }

    .workflow-node.selected {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px var(--color-primary-light);
    }

    .workflow-node.trigger { border-top: 3px solid var(--success-color, #10b981); }
    .workflow-node.action { border-top: 3px solid var(--info-color, #3b82f6); }
    .workflow-node.condition { border-top: 3px solid var(--warning-color, #f59e0b); }
    .workflow-node.wait { border-top: 3px solid var(--secondary-color, #6b7280); }

    .node-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
    }

    .node-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .node-header .node-icon.trigger { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .node-header .node-icon.action { background: rgba(59, 130, 246, 0.1); color: var(--info-color, #3b82f6); }
    .node-header .node-icon.condition { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }
    .node-header .node-icon.wait { background: rgba(107, 114, 128, 0.1); color: var(--secondary-color, #6b7280); }

    .node-title {
        flex: 1;
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--text-color, #1f2937);
    }

    .node-actions {
        display: flex;
        gap: 0.25rem;
    }

    .node-actions button {
        width: 24px;
        height: 24px;
        border: none;
        background: transparent;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted, #6b7280);
        transition: all 0.2s;
    }

    .node-actions button:hover {
        background: var(--border-color, #e9ecef);
        color: var(--text-color, #1f2937);
    }

    .node-actions button.delete:hover {
        background: #fee2e2;
        color: #ef4444;
    }

    .node-body {
        padding: 1rem;
        font-size: 0.813rem;
        color: var(--text-muted, #6b7280);
    }

    .node-connector {
        position: absolute;
        width: 16px;
        height: 16px;
        background: #fff;
        border: 3px solid var(--border-color, #e9ecef);
        border-radius: 50%;
        cursor: crosshair;
        z-index: 10;
        transition: all 0.2s ease;
    }

    .node-connector.output {
        bottom: -8px;
        left: 50%;
        transform: translateX(-50%);
    }

    .node-connector.output-true {
        bottom: -8px;
        left: 30%;
        border-color: var(--success-color, #10b981);
        background: rgba(16, 185, 129, 0.1);
    }

    .node-connector.output-false {
        bottom: -8px;
        right: 30%;
        border-color: var(--danger-color, #ef4444);
        background: rgba(239, 68, 68, 0.1);
    }

    .node-connector.input {
        top: -8px;
        left: 50%;
        transform: translateX(-50%);
    }

    .node-connector:hover {
        border-color: var(--color-primary);
        background: var(--color-primary-light);
        transform: translateX(-50%) scale(1.3);
    }

    .node-connector.output-true:hover,
    .node-connector.output-false:hover {
        transform: scale(1.3);
    }

    .node-connector.can-connect {
        border-color: var(--success-color, #10b981) !important;
        background: rgba(16, 185, 129, 0.3) !important;
        animation: pulse 0.5s infinite alternate;
    }

    @keyframes pulse {
        from { transform: translateX(-50%) scale(1); }
        to { transform: translateX(-50%) scale(1.4); }
    }

    .connecting-line {
        fill: none;
        stroke: var(--color-primary);
        stroke-width: 3;
        stroke-dasharray: 8 4;
        pointer-events: none;
    }

    /* Connections SVG */
    .connections-svg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        overflow: visible;
    }

    .connections-svg path {
        pointer-events: stroke;
    }

    .connection-line {
        fill: none;
        stroke: var(--text-muted, #94a3b8);
        stroke-width: 3;
        cursor: pointer;
        transition: stroke 0.2s;
    }

    .connection-line:hover {
        stroke: var(--color-primary);
        stroke-width: 4;
    }

    .connection-line.true { stroke: var(--success-color, #10b981); }
    .connection-line.true:hover { stroke: var(--success-color, #059669); }
    .connection-line.false { stroke: var(--danger-color, #ef4444); }
    .connection-line.false:hover { stroke: var(--danger-color, #dc2626); }

    /* Properties Panel */
    .builder-properties {
        width: 320px;
        background: var(--card-bg, #fff);
        border-left: 1px solid var(--border-color, #e9ecef);
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }

    .properties-header {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
    }

    .properties-content {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }

    .property-group {
        margin-bottom: 1.25rem;
    }

    .property-label {
        font-size: 0.813rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-color, #1f2937);
    }

    /* Toolbar */
    .builder-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        background: var(--card-bg, #fff);
        border-bottom: 1px solid var(--border-color, #e9ecef);
        margin-bottom: 1rem;
        border-radius: 8px;
    }

    .toolbar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .toolbar-right {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .zoom-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem;
        background: var(--border-color, #e9ecef);
        border-radius: 6px;
    }

    .zoom-controls button {
        width: 28px;
        height: 28px;
        border: none;
        background: transparent;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .zoom-controls button:hover {
        background: #fff;
    }

    .zoom-level {
        min-width: 50px;
        text-align: center;
        font-size: 0.813rem;
        font-weight: 500;
    }

    /* Template Modal */
    .template-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1050;
        align-items: center;
        justify-content: center;
    }

    .template-modal.show {
        display: flex;
    }

    .template-modal-content {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .template-modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .template-modal-header h4 {
        margin: 0;
        font-weight: 600;
    }

    .template-modal-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex: 1;
    }

    .template-categories {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .template-category-btn {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        border: 1px solid var(--border-color, #e9ecef);
        background: var(--card-bg, #fff);
        cursor: pointer;
        font-weight: 500;
        font-size: 0.875rem;
        transition: all 0.2s ease;
    }

    .template-category-btn:hover,
    .template-category-btn.active {
        background: var(--color-primary);
        color: #fff;
        border-color: var(--color-primary);
    }

    .template-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }

    .template-card {
        background: var(--card-bg, #fff);
        border: 2px solid var(--border-color, #e9ecef);
        border-radius: 12px;
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .template-card:hover {
        border-color: var(--color-primary);
        box-shadow: 0 4px 15px var(--color-primary-light);
    }

    .template-card.selected {
        border-color: var(--color-primary);
        background: var(--color-primary-light);
    }

    .template-card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .template-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .template-card-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 0.25rem;
    }

    .template-card-category {
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
    }

    .template-card-description {
        font-size: 0.875rem;
        color: var(--text-muted, #6b7280);
        margin-bottom: 0.75rem;
    }

    .template-card-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
    }

    .template-modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border-color, #e9ecef);
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }

    /* Confirm Modal */
    .confirm-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1060;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(2px);
    }

    .confirm-modal-overlay.show {
        display: flex;
    }

    .confirm-modal-box {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        width: 90%;
        max-width: 400px;
        padding: 2rem;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        animation: confirmEnter 0.2s ease-out;
    }

    @keyframes confirmEnter {
        from { opacity: 0; transform: scale(0.95) translateY(10px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }

    .confirm-modal-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.5rem;
    }

    .confirm-modal-icon.warning {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .confirm-modal-icon.info {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }

    .confirm-modal-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-color, #1f2937);
    }

    .confirm-modal-message {
        color: var(--text-muted, #6b7280);
        margin-bottom: 1.5rem;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .confirm-modal-actions {
        display: flex;
        gap: 0.75rem;
        justify-content: center;
    }

    .confirm-modal-actions .btn {
        min-width: 100px;
        border-radius: 8px;
        font-weight: 500;
    }
</style>
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
                            @foreach($breadcrumbs as $breadcrumb)
                                @if(isset($breadcrumb['url']))
                                    <li class="breadcrumb-item">
                                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['name'] }}</a>
                                    </li>
                                @else
                                    <li class="breadcrumb-item active" aria-current="page">{{ $breadcrumb['name'] }}</li>
                                @endif
                            @endforeach
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

    <!-- Toolbar -->
    <div class="builder-toolbar">
        <div class="toolbar-left">
            <input type="text"
                   id="workflowName"
                   class="form-control"
                   placeholder="{{ translate('Workflow Name') }}"
                   value="{{ $workflow->name ?? '' }}"
                   style="width: 250px;">

            <div class="zoom-controls">
                <button type="button" onclick="zoomOut()"><i class="ri-subtract-line"></i></button>
                <span class="zoom-level" id="zoomLevel">100%</span>
                <button type="button" onclick="zoomIn()"><i class="ri-add-line"></i></button>
            </div>
        </div>

        <div class="toolbar-right">
            <button type="button" class="i-btn btn--info outline btn--md" onclick="showTemplateModal()">
                <i class="ri-file-copy-2-line me-1"></i>{{ translate('Use Template') }}
            </button>
            <a href="{{ route('user.automation.index') }}" class="i-btn btn--dark outline btn--md">
                <i class="ri-arrow-left-line me-1"></i>{{ translate('Back') }}
            </a>
            <button type="button" class="i-btn btn--primary outline btn--md" onclick="saveWorkflow(true)">
                <i class="ri-save-line me-1"></i>{{ translate('Save Draft') }}
            </button>
            <button type="button" class="i-btn btn--primary btn--md" onclick="saveWorkflow(false)">
                <i class="ri-check-line me-1"></i>{{ translate('Save & Activate') }}
            </button>
        </div>
    </div>

    <!-- Builder -->
    <div class="builder-container">
        <!-- Sidebar - Node Palette -->
        <div class="builder-sidebar">
            <div class="sidebar-header">
                <h5 class="mb-1">{{ translate('Add Nodes') }}</h5>
                <p class="text-muted mb-0 small">{{ translate('Drag nodes to the canvas') }}</p>
            </div>
            <div class="sidebar-content">
                <!-- Triggers -->
                <div class="node-category">
                    <div class="node-category-title">{{ translate('Triggers') }}</div>
                    <div class="node-palette">
                        @foreach($triggerTypes as $key => $trigger)
                            <div class="node-template"
                                 draggable="true"
                                 data-type="trigger"
                                 data-action="{{ $key }}">
                                <div class="node-template-icon trigger">
                                    <i class="{{ $trigger['icon'] }}"></i>
                                </div>
                                <div class="node-template-info">
                                    <h5>{{ $trigger['label'] }}</h5>
                                    <p>{{ \Illuminate\Support\Str::limit($trigger['description'], 40) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Actions -->
                <div class="node-category">
                    <div class="node-category-title">{{ translate('Actions') }}</div>
                    <div class="node-palette">
                        @foreach($actionTypes as $key => $action)
                            <div class="node-template"
                                 draggable="true"
                                 data-type="action"
                                 data-action="{{ $key }}">
                                <div class="node-template-icon action">
                                    <i class="{{ $action['icon'] }}"></i>
                                </div>
                                <div class="node-template-info">
                                    <h5>{{ $action['label'] }}</h5>
                                    <p>{{ \Illuminate\Support\Str::limit($action['description'], 40) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Conditions -->
                <div class="node-category">
                    <div class="node-category-title">{{ translate('Conditions') }}</div>
                    <div class="node-palette">
                        @foreach($conditionTypes as $key => $condition)
                            <div class="node-template"
                                 draggable="true"
                                 data-type="condition"
                                 data-action="{{ $key }}">
                                <div class="node-template-icon condition">
                                    <i class="{{ $condition['icon'] }}"></i>
                                </div>
                                <div class="node-template-info">
                                    <h5>{{ $condition['label'] }}</h5>
                                    <p>{{ \Illuminate\Support\Str::limit($condition['description'], 40) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Wait -->
                <div class="node-category">
                    <div class="node-category-title">{{ translate('Wait / Delay') }}</div>
                    <div class="node-palette">
                        @foreach($waitTypes as $key => $wait)
                            <div class="node-template"
                                 draggable="true"
                                 data-type="wait"
                                 data-action="{{ $key }}">
                                <div class="node-template-icon wait">
                                    <i class="{{ $wait['icon'] }}"></i>
                                </div>
                                <div class="node-template-info">
                                    <h5>{{ $wait['label'] }}</h5>
                                    <p>{{ \Illuminate\Support\Str::limit($wait['description'], 40) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Canvas -->
        <div class="builder-canvas" id="builderCanvas">
            <svg class="connections-svg" id="connectionsSvg"></svg>
            <div class="canvas-area" id="canvasArea">
                <!-- Nodes will be rendered here -->
            </div>
        </div>

        <!-- Properties Panel -->
        <div class="builder-properties" id="propertiesPanel" style="display: none;">
            <div class="properties-header">
                <h5 class="mb-0" id="propertyTitle">{{ translate('Node Properties') }}</h5>
            </div>
            <div class="properties-content" id="propertiesContent">
                <!-- Dynamic properties form -->
            </div>
        </div>
    </div>
    </div>

<!-- Confirm Modal -->
<div class="confirm-modal-overlay" id="confirmModal">
    <div class="confirm-modal-box">
        <div class="confirm-modal-icon warning" id="confirmModalIcon">
            <i class="ri-delete-bin-line"></i>
        </div>
        <h4 class="confirm-modal-title" id="confirmModalTitle">{{ translate('Confirm') }}</h4>
        <p class="confirm-modal-message" id="confirmModalMessage">{{ translate('Are you sure?') }}</p>
        <div class="confirm-modal-actions">
            <button type="button" class="btn btn-outline-secondary" onclick="hideConfirmModal()">
                {{ translate('Cancel') }}
            </button>
            <button type="button" class="btn btn-danger" id="confirmModalBtn" onclick="confirmModalAction()">
                {{ translate('Delete') }}
            </button>
        </div>
    </div>
</div>

<!-- Template Modal -->
<div class="template-modal" id="templateModal">
    <div class="template-modal-content">
        <div class="template-modal-header">
            <h4><i class="ri-file-copy-2-line me-2"></i>{{ translate('Select a Template') }}</h4>
            <button type="button" class="btn-close" onclick="hideTemplateModal()"></button>
        </div>
        <div class="template-modal-body">
            <div class="template-categories" id="templateCategories">
                <button class="template-category-btn active" data-category="all">{{ translate('All') }}</button>
            </div>
            <div class="template-grid" id="templateGrid">
                <!-- Templates will be loaded here -->
            </div>
        </div>
        <div class="template-modal-footer">
            <button type="button" class="i-btn btn--dark outline btn--md" onclick="hideTemplateModal()">
                {{ translate('Cancel') }}
            </button>
            <button type="button" class="i-btn btn--primary btn--md" id="useTemplateBtn" onclick="useSelectedTemplate()" disabled>
                <i class="ri-check-line me-1"></i>{{ translate('Use Template') }}
            </button>
        </div>
    </div>
</div>
</main>
@endsection

@push('script-push')
<script>
    // Confirm Modal Functions
    let confirmModalCallback = null;

    function showConfirmModal(title, message, callback, options = {}) {
        const modal = document.getElementById('confirmModal');
        const iconEl = document.getElementById('confirmModalIcon');
        const titleEl = document.getElementById('confirmModalTitle');
        const messageEl = document.getElementById('confirmModalMessage');
        const btnEl = document.getElementById('confirmModalBtn');

        titleEl.textContent = title;
        messageEl.textContent = message;
        confirmModalCallback = callback;

        iconEl.className = 'confirm-modal-icon ' + (options.type || 'warning');
        iconEl.innerHTML = '<i class="' + (options.icon || 'ri-delete-bin-line') + '"></i>';
        btnEl.className = 'btn ' + (options.btnClass || 'btn-danger');
        btnEl.textContent = options.btnText || '{{ translate("Delete") }}';

        modal.classList.add('show');
    }

    function hideConfirmModal() {
        document.getElementById('confirmModal').classList.remove('show');
        confirmModalCallback = null;
    }

    function confirmModalAction() {
        if (confirmModalCallback && typeof confirmModalCallback === 'function') {
            confirmModalCallback();
        }
        hideConfirmModal();
    }

    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) hideConfirmModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') hideConfirmModal();
    });

    // Workflow Builder State
    const workflowState = {
        nodes: [],
        connections: [],
        selectedNode: null,
        zoom: 1,
        isEditing: {{ isset($workflow) ? 'true' : 'false' }},
        workflowId: '{{ $workflow->uid ?? '' }}',
        nodeIdCounter: 0
    };

    // Available resources
    const resources = {
        groups: @json($groups),
        smsGateways: @json($smsGateways),
        emailGateways: @json($emailGateways),
        whatsappDevices: @json($whatsappDevices),
        triggerTypes: @json($triggerTypes),
        actionTypes: @json($actionTypes),
        conditionTypes: @json($conditionTypes),
        waitTypes: @json($waitTypes),
        operators: @json($operators)
    };

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        initDragAndDrop();
        initCanvasEvents();

        @if(isset($workflow))
            loadWorkflowData();
        @endif
    });

    // Drag and Drop
    function initDragAndDrop() {
        document.querySelectorAll('.node-template').forEach(template => {
            template.addEventListener('dragstart', handleDragStart);
        });

        const canvasArea = document.getElementById('canvasArea');
        canvasArea.addEventListener('dragover', handleDragOver);
        canvasArea.addEventListener('drop', handleDrop);
        canvasArea.addEventListener('dragleave', function(e) {
            if (!canvasArea.contains(e.relatedTarget)) {
                canvasArea.classList.remove('drag-over');
            }
        });
    }

    function handleDragStart(e) {
        const template = e.target.closest('.node-template');
        if (!template) return;

        e.dataTransfer.setData('nodeType', template.dataset.type);
        e.dataTransfer.setData('actionType', template.dataset.action);
        e.dataTransfer.effectAllowed = 'copy';

        // Custom drag ghost
        const ghost = template.cloneNode(true);
        ghost.style.width = '260px';
        ghost.style.opacity = '0.85';
        ghost.style.position = 'absolute';
        ghost.style.top = '-9999px';
        ghost.style.borderRadius = '10px';
        ghost.style.boxShadow = '0 8px 20px rgba(0,0,0,0.15)';
        document.body.appendChild(ghost);
        e.dataTransfer.setDragImage(ghost, 130, 24);
        requestAnimationFrame(() => ghost.remove());
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        document.getElementById('canvasArea').classList.add('drag-over');

        // Auto-scroll when dragging near canvas edges
        const builderCanvas = document.getElementById('builderCanvas');
        const bcRect = builderCanvas.getBoundingClientRect();
        const margin = 50, speed = 10;
        if (e.clientX > bcRect.right - margin) builderCanvas.scrollLeft += speed;
        if (e.clientX < bcRect.left + margin) builderCanvas.scrollLeft -= speed;
        if (e.clientY > bcRect.bottom - margin) builderCanvas.scrollTop += speed;
        if (e.clientY < bcRect.top + margin) builderCanvas.scrollTop -= speed;
    }

    function handleDrop(e) {
        e.preventDefault();
        const canvasArea = document.getElementById('canvasArea');
        canvasArea.classList.remove('drag-over');

        const nodeType = e.dataTransfer.getData('nodeType');
        const actionType = e.dataTransfer.getData('actionType');
        if (!nodeType || !actionType) return;

        const rect = canvasArea.getBoundingClientRect();
        // getBoundingClientRect already accounts for scroll offset
        const x = (e.clientX - rect.left) / workflowState.zoom;
        const y = (e.clientY - rect.top) / workflowState.zoom;

        // Center node at drop point (node width = 280)
        addNode(nodeType, actionType, Math.max(10, x - 140), Math.max(10, y - 20));
    }

    // Add Node
    function addNode(type, actionType, x, y) {
        const nodeId = ++workflowState.nodeIdCounter;

        const node = {
            id: nodeId,
            type: type,
            action_type: actionType,
            config: {},
            label: getNodeLabel(type, actionType),
            position_x: Math.round(x),
            position_y: Math.round(y),
            next_node_id: null,
            condition_true_node_id: null,
            condition_false_node_id: null
        };

        workflowState.nodes.push(node);
        renderNode(node);
        selectNode(nodeId);
    }

    function getNodeLabel(type, actionType) {
        if (type === 'trigger') {
            return resources.triggerTypes[actionType]?.label || actionType;
        } else if (type === 'action') {
            return resources.actionTypes[actionType]?.label || actionType;
        } else if (type === 'condition') {
            return resources.conditionTypes[actionType]?.label || actionType;
        } else if (type === 'wait') {
            return resources.waitTypes[actionType]?.label || actionType;
        }
        return actionType;
    }

    function getNodeIcon(type, actionType) {
        if (type === 'trigger') {
            return resources.triggerTypes[actionType]?.icon || 'ri-flashlight-line';
        } else if (type === 'action') {
            return resources.actionTypes[actionType]?.icon || 'ri-play-circle-line';
        } else if (type === 'condition') {
            return resources.conditionTypes[actionType]?.icon || 'ri-git-branch-line';
        } else if (type === 'wait') {
            return resources.waitTypes[actionType]?.icon || 'ri-time-line';
        }
        return 'ri-node-tree';
    }

    // Render Node
    function renderNode(node) {
        const canvas = document.getElementById('canvasArea');

        const nodeEl = document.createElement('div');
        nodeEl.className = `workflow-node ${node.type}`;
        nodeEl.id = `node-${node.id}`;
        nodeEl.style.left = `${node.position_x}px`;
        nodeEl.style.top = `${node.position_y}px`;

        const connectors = node.type === 'condition'
            ? `<div class="node-connector output-true" data-type="true" title="Yes"></div>
               <div class="node-connector output-false" data-type="false" title="No"></div>`
            : `<div class="node-connector output"></div>`;

        nodeEl.innerHTML = `
            ${node.type !== 'trigger' ? '<div class="node-connector input"></div>' : ''}
            <div class="node-header">
                <div class="node-icon ${node.type}">
                    <i class="${getNodeIcon(node.type, node.action_type)}"></i>
                </div>
                <span class="node-title">${node.label}</span>
                <div class="node-actions">
                    <button type="button" onclick="editNode(${node.id})" title="Edit">
                        <i class="ri-settings-3-line"></i>
                    </button>
                    <button type="button" class="delete" onclick="deleteNode(${node.id})" title="Delete">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </div>
            </div>
            <div class="node-body">
                ${getNodeSummary(node)}
            </div>
            ${connectors}
        `;

        // Make draggable
        nodeEl.addEventListener('mousedown', (e) => startDragNode(e, node.id));
        nodeEl.addEventListener('click', (e) => {
            e.stopPropagation();
            selectNode(node.id);
        });

        // Connector events
        nodeEl.querySelectorAll('.node-connector').forEach(connector => {
            connector.addEventListener('mousedown', (e) => startConnection(e, node.id, connector.dataset.type));
        });

        canvas.appendChild(nodeEl);
    }

    function getNodeSummary(node) {
        const config = node.config || {};
        if (node.type === 'action') {
            if (node.action_type === 'send_sms' && config.message) {
                return `{{ translate('Message') }}: "${config.message.substring(0, 30)}..."`;
            }
            if (node.action_type === 'send_email' && config.subject) {
                return `{{ translate('Subject') }}: ${config.subject}`;
            }
        }
        if (node.type === 'wait') {
            if (config.duration && config.unit) {
                return `{{ translate('Wait') }} ${config.duration} ${config.unit}`;
            }
        }
        return '{{ translate('Click to configure') }}';
    }

    // Node Selection - only selects, doesn't open properties
    function selectNodeOnly(nodeId) {
        document.querySelectorAll('.workflow-node.selected').forEach(n => n.classList.remove('selected'));

        workflowState.selectedNode = nodeId;

        const nodeEl = document.getElementById(`node-${nodeId}`);
        if (nodeEl) {
            nodeEl.classList.add('selected');
        }
    }

    // Node Selection - selects and opens properties
    function selectNode(nodeId) {
        selectNodeOnly(nodeId);
        showPropertiesPanel(nodeId);
    }

    // Properties Panel
    function showPropertiesPanel(nodeId) {
        const node = workflowState.nodes.find(n => n.id === nodeId);
        if (!node) return;

        const panel = document.getElementById('propertiesPanel');
        const content = document.getElementById('propertiesContent');
        const title = document.getElementById('propertyTitle');

        panel.style.display = 'flex';
        title.textContent = node.label;

        content.innerHTML = generatePropertiesForm(node);
    }

    function generatePropertiesForm(node) {
        let html = `
            <div class="property-group">
                <label class="property-label">{{ translate('Label') }}</label>
                <input type="text" class="form-control" value="${node.label}"
                       onchange="updateNodeLabel(${node.id}, this.value)">
            </div>
        `;

        // Type-specific config fields
        if (node.type === 'action') {
            html += generateActionForm(node);
        } else if (node.type === 'condition') {
            html += generateConditionForm(node);
        } else if (node.type === 'wait') {
            html += generateWaitForm(node);
        } else if (node.type === 'trigger') {
            html += generateTriggerForm(node);
        }

        return html;
    }

    function generateActionForm(node) {
        const config = node.config || {};
        let html = '';

        if (node.action_type === 'send_sms') {
            html += `
                <div class="property-group">
                    <label class="property-label">{{ translate('SMS Gateway') }}</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'gateway_id', this.value)">
                        <option value="">{{ translate('Select Gateway') }}</option>
                        ${resources.smsGateways.map(g => `<option value="${g.id}" ${config.gateway_id == g.id ? 'selected' : ''}>${g.name}</option>`).join('')}
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">{{ translate('Message') }}</label>
                    <textarea class="form-control" rows="4" onchange="updateNodeConfig(${node.id}, 'message', this.value)"
                              placeholder="Use {first_name}, {phone} for personalization">${config.message || ''}</textarea>
                </div>
            `;
        } else if (node.action_type === 'send_email') {
            html += `
                <div class="property-group">
                    <label class="property-label">{{ translate('Email Gateway') }}</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'gateway_id', this.value)">
                        <option value="">{{ translate('Select Gateway') }}</option>
                        ${resources.emailGateways.map(g => `<option value="${g.id}" ${config.gateway_id == g.id ? 'selected' : ''}>${g.name}</option>`).join('')}
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">{{ translate('Subject') }}</label>
                    <input type="text" class="form-control" value="${config.subject || ''}"
                           onchange="updateNodeConfig(${node.id}, 'subject', this.value)">
                </div>
                <div class="property-group">
                    <label class="property-label">{{ translate('Message') }}</label>
                    <textarea class="form-control" rows="4" onchange="updateNodeConfig(${node.id}, 'message', this.value)">${config.message || ''}</textarea>
                </div>
            `;
        } else if (node.action_type === 'send_whatsapp') {
            html += `
                <div class="property-group">
                    <label class="property-label">{{ translate('WhatsApp Device') }}</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'device_id', this.value)">
                        <option value="">{{ translate('Select Device') }}</option>
                        ${resources.whatsappDevices.map(g => `<option value="${g.id}" ${config.device_id == g.id ? 'selected' : ''}>${g.name}</option>`).join('')}
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">{{ translate('Message') }}</label>
                    <textarea class="form-control" rows="4" onchange="updateNodeConfig(${node.id}, 'message', this.value)">${config.message || ''}</textarea>
                </div>
            `;
        } else if (node.action_type === 'add_to_group' || node.action_type === 'remove_from_group') {
            html += `
                <div class="property-group">
                    <label class="property-label">{{ translate('Contact Group') }}</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'group_id', this.value)">
                        <option value="">{{ translate('Select Group') }}</option>
                        ${resources.groups.map(g => `<option value="${g.id}" ${config.group_id == g.id ? 'selected' : ''}>${g.name}</option>`).join('')}
                    </select>
                </div>
            `;
        } else if (node.action_type === 'add_tag') {
            html += `
                <div class="property-group">
                    <label class="property-label">{{ translate('Tag Name') }}</label>
                    <input type="text" class="form-control" value="${config.tag || ''}"
                           onchange="updateNodeConfig(${node.id}, 'tag', this.value)">
                </div>
            `;
        }

        return html;
    }

    function generateConditionForm(node) {
        const config = node.config || {};
        let html = '';

        if (node.action_type === 'field_equals') {
            html += `
                <div class="property-group">
                    <label class="property-label">{{ translate('Field') }}</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'field', this.value)">
                        <option value="">{{ translate('Select Field') }}</option>
                        <option value="first_name" ${config.field === 'first_name' ? 'selected' : ''}>{{ translate('First Name') }}</option>
                        <option value="last_name" ${config.field === 'last_name' ? 'selected' : ''}>{{ translate('Last Name') }}</option>
                        <option value="email_contact" ${config.field === 'email_contact' ? 'selected' : ''}>{{ translate('Email') }}</option>
                        <option value="status" ${config.field === 'status' ? 'selected' : ''}>{{ translate('Status') }}</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">{{ translate('Operator') }}</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'operator', this.value)">
                        ${Object.entries(resources.operators).map(([k, v]) => `<option value="${k}" ${config.operator === k ? 'selected' : ''}>${v}</option>`).join('')}
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">{{ translate('Value') }}</label>
                    <input type="text" class="form-control" value="${config.value || ''}"
                           onchange="updateNodeConfig(${node.id}, 'value', this.value)">
                </div>
            `;
        } else if (node.action_type === 'has_tag') {
            html += `
                <div class="property-group">
                    <label class="property-label">{{ translate('Tag Name') }}</label>
                    <input type="text" class="form-control" value="${config.tag || ''}"
                           onchange="updateNodeConfig(${node.id}, 'tag', this.value)">
                </div>
            `;
        } else if (node.action_type === 'random_split') {
            html += `
                <div class="property-group">
                    <label class="property-label">{{ translate('Percentage (Yes path)') }}</label>
                    <input type="number" class="form-control" min="1" max="99" value="${config.percentage || 50}"
                           onchange="updateNodeConfig(${node.id}, 'percentage', parseInt(this.value))">
                    <small class="text-muted">${config.percentage || 50}% {{ translate('will go to Yes') }}, ${100 - (config.percentage || 50)}% {{ translate('to No') }}</small>
                </div>
            `;
        }

        return html;
    }

    function generateWaitForm(node) {
        const config = node.config || {};

        if (node.action_type === 'delay') {
            return `
                <div class="property-group">
                    <label class="property-label">{{ translate('Duration') }}</label>
                    <input type="number" class="form-control" min="1" value="${config.duration || 1}"
                           onchange="updateNodeConfig(${node.id}, 'duration', parseInt(this.value))">
                </div>
                <div class="property-group">
                    <label class="property-label">{{ translate('Unit') }}</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'unit', this.value)">
                        <option value="minutes" ${config.unit === 'minutes' ? 'selected' : ''}>{{ translate('Minutes') }}</option>
                        <option value="hours" ${config.unit === 'hours' ? 'selected' : ''}>{{ translate('Hours') }}</option>
                        <option value="days" ${config.unit === 'days' ? 'selected' : ''}>{{ translate('Days') }}</option>
                    </select>
                </div>
            `;
        } else if (node.action_type === 'until_time') {
            return `
                <div class="property-group">
                    <label class="property-label">{{ translate('Time') }}</label>
                    <input type="time" class="form-control" value="${config.time || '09:00'}"
                           onchange="updateNodeConfig(${node.id}, 'time', this.value)">
                </div>
            `;
        }

        return '';
    }

    function generateTriggerForm(node) {
        const config = node.config || {};

        if (node.action_type === 'new_contact') {
            return `
                <div class="property-group">
                    <label class="property-label">{{ translate('Contact Group') }}</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'group_id', this.value)">
                        <option value="">{{ translate('Any Group') }}</option>
                        ${resources.groups.map(g => `<option value="${g.id}" ${config.group_id == g.id ? 'selected' : ''}>${g.name}</option>`).join('')}
                    </select>
                </div>
            `;
        }

        return '';
    }

    // Update functions
    function updateNodeLabel(nodeId, label) {
        const node = workflowState.nodes.find(n => n.id === nodeId);
        if (node) {
            node.label = label;
            const titleEl = document.querySelector(`#node-${nodeId} .node-title`);
            if (titleEl) titleEl.textContent = label;
        }
    }

    function updateNodeConfig(nodeId, key, value) {
        const node = workflowState.nodes.find(n => n.id === nodeId);
        if (node) {
            node.config[key] = value;
            const bodyEl = document.querySelector(`#node-${nodeId} .node-body`);
            if (bodyEl) bodyEl.textContent = getNodeSummary(node);
        }
    }

    // Delete Node
    function deleteNode(nodeId) {
        showConfirmModal(
            '{{ translate("Delete Node") }}',
            '{{ translate("Are you sure you want to delete this node? All connections to and from it will also be removed.") }}',
            () => {
                workflowState.nodes = workflowState.nodes.filter(n => n.id !== nodeId);
                workflowState.connections = workflowState.connections.filter(c => c.from !== nodeId && c.to !== nodeId);

                const nodeEl = document.getElementById(`node-${nodeId}`);
                if (nodeEl) nodeEl.remove();

                document.getElementById('propertiesPanel').style.display = 'none';
                renderConnections();
                notify('success', '{{ translate("Node deleted") }}');
            },
            { type: 'warning', icon: 'ri-delete-bin-line', btnClass: 'btn-danger', btnText: '{{ translate("Delete") }}' }
        );
    }

    // Node Dragging
    let dragState = { isDragging: false, nodeId: null, offsetX: 0, offsetY: 0, rafId: null };

    function startDragNode(e, nodeId) {
        if (e.target.closest('.node-actions') || e.target.closest('.node-connector')) return;

        const nodeEl = document.getElementById(`node-${nodeId}`);
        const rect = nodeEl.getBoundingClientRect();

        dragState = {
            isDragging: true,
            nodeId: nodeId,
            offsetX: e.clientX - rect.left,
            offsetY: e.clientY - rect.top,
            rafId: null
        };

        nodeEl.classList.add('is-dragging');
        selectNodeOnly(nodeId);

        document.addEventListener('mousemove', dragNode);
        document.addEventListener('mouseup', stopDragNode);
    }

    function dragNode(e) {
        if (!dragState.isDragging) return;

        // Auto-scroll when dragging near canvas edges
        const builderCanvas = document.getElementById('builderCanvas');
        const bcRect = builderCanvas.getBoundingClientRect();
        const margin = 50, speed = 10;
        if (e.clientX > bcRect.right - margin) builderCanvas.scrollLeft += speed;
        if (e.clientX < bcRect.left + margin) builderCanvas.scrollLeft -= speed;
        if (e.clientY > bcRect.bottom - margin) builderCanvas.scrollTop += speed;
        if (e.clientY < bcRect.top + margin) builderCanvas.scrollTop -= speed;

        if (dragState.rafId) cancelAnimationFrame(dragState.rafId);

        dragState.rafId = requestAnimationFrame(() => {
            const canvas = document.getElementById('canvasArea');
            const canvasRect = canvas.getBoundingClientRect();

            // getBoundingClientRect already accounts for scroll offset
            const x = (e.clientX - canvasRect.left - dragState.offsetX) / workflowState.zoom;
            const y = (e.clientY - canvasRect.top - dragState.offsetY) / workflowState.zoom;

            const node = workflowState.nodes.find(n => n.id === dragState.nodeId);
            if (node) {
                node.position_x = Math.max(0, Math.round(x));
                node.position_y = Math.max(0, Math.round(y));

                const nodeEl = document.getElementById(`node-${dragState.nodeId}`);
                nodeEl.style.left = `${node.position_x}px`;
                nodeEl.style.top = `${node.position_y}px`;

                renderConnections();
            }
        });
    }

    function stopDragNode() {
        if (dragState.rafId) cancelAnimationFrame(dragState.rafId);

        const nodeEl = document.getElementById(`node-${dragState.nodeId}`);
        if (nodeEl) nodeEl.classList.remove('is-dragging');

        dragState.isDragging = false;
        document.removeEventListener('mousemove', dragNode);
        document.removeEventListener('mouseup', stopDragNode);
    }

    // Connections
    let connectionState = { isConnecting: false, fromNode: null, fromType: null, tempLine: null };

    function startConnection(e, nodeId, type) {
        e.stopPropagation();
        e.preventDefault();

        connectionState = { isConnecting: true, fromNode: nodeId, fromType: type, tempLine: null };

        // Highlight all input connectors as potential targets
        document.querySelectorAll('.node-connector.input').forEach(input => {
            const inputNodeId = parseInt(input.closest('.workflow-node').id.replace('node-', ''));
            if (inputNodeId !== nodeId) {
                input.classList.add('can-connect');
            }
        });

        // Create temporary line
        const svg = document.getElementById('connectionsSvg');
        const tempLine = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        tempLine.setAttribute('class', 'connecting-line');
        tempLine.setAttribute('id', 'tempConnectionLine');
        svg.appendChild(tempLine);
        connectionState.tempLine = tempLine;

        // Get starting position
        const connector = e.target;
        const nodeEl = document.getElementById(`node-${nodeId}`);
        const canvas = document.getElementById('canvasArea');
        const canvasRect = canvas.getBoundingClientRect();
        const connectorRect = connector.getBoundingClientRect();

        connectionState.startX = (connectorRect.left + connectorRect.width / 2 - canvasRect.left) / workflowState.zoom;
        connectionState.startY = (connectorRect.top + connectorRect.height / 2 - canvasRect.top) / workflowState.zoom;

        document.addEventListener('mousemove', drawTempConnection);
        document.addEventListener('mouseup', finishConnection);
    }

    function drawTempConnection(e) {
        if (!connectionState.isConnecting || !connectionState.tempLine) return;

        const canvas = document.getElementById('canvasArea');
        const canvasRect = canvas.getBoundingClientRect();

        // getBoundingClientRect already accounts for scroll offset
        const endX = (e.clientX - canvasRect.left) / workflowState.zoom;
        const endY = (e.clientY - canvasRect.top) / workflowState.zoom;

        const startX = connectionState.startX;
        const startY = connectionState.startY;
        const midY = (startY + endY) / 2;

        connectionState.tempLine.setAttribute('d', `M ${startX} ${startY} C ${startX} ${midY}, ${endX} ${midY}, ${endX} ${endY}`);
    }

    function finishConnection(e) {
        document.removeEventListener('mousemove', drawTempConnection);
        document.removeEventListener('mouseup', finishConnection);

        // Remove highlights
        document.querySelectorAll('.node-connector.can-connect').forEach(c => c.classList.remove('can-connect'));

        // Remove temp line
        if (connectionState.tempLine) {
            connectionState.tempLine.remove();
        }

        if (!connectionState.isConnecting) return;

        const targetConnector = e.target.closest('.node-connector.input');
        if (targetConnector) {
            const toNodeId = parseInt(targetConnector.closest('.workflow-node').id.replace('node-', ''));

            if (connectionState.fromNode !== toNodeId) {
                const fromNode = workflowState.nodes.find(n => n.id === connectionState.fromNode);

                if (connectionState.fromType === 'true') {
                    fromNode.condition_true_node_id = toNodeId;
                } else if (connectionState.fromType === 'false') {
                    fromNode.condition_false_node_id = toNodeId;
                } else {
                    fromNode.next_node_id = toNodeId;
                }

                renderConnections();
                notify('success', '{{ translate("Connection created") }}');
            }
        }

        connectionState = { isConnecting: false, fromNode: null, fromType: null, tempLine: null };
    }

    function renderConnections() {
        const svg = document.getElementById('connectionsSvg');
        svg.innerHTML = '';

        workflowState.nodes.forEach(node => {
            if (node.next_node_id) {
                drawConnection(node.id, node.next_node_id, 'output', '');
            }
            if (node.condition_true_node_id) {
                drawConnection(node.id, node.condition_true_node_id, 'output-true', 'true');
            }
            if (node.condition_false_node_id) {
                drawConnection(node.id, node.condition_false_node_id, 'output-false', 'false');
            }
        });
    }

    function drawConnection(fromId, toId, outputType, pathClass) {
        const fromEl = document.getElementById(`node-${fromId}`);
        const toEl = document.getElementById(`node-${toId}`);
        const svg = document.getElementById('connectionsSvg');

        if (!fromEl || !toEl) return;

        // Find the specific output connector
        let outputConnector = fromEl.querySelector(`.node-connector.${outputType}`);
        if (!outputConnector) {
            outputConnector = fromEl.querySelector('.node-connector.output');
        }

        const inputConnector = toEl.querySelector('.node-connector.input');
        if (!outputConnector || !inputConnector) return;

        const canvas = document.getElementById('canvasArea');
        const canvasRect = canvas.getBoundingClientRect();

        const fromConnectorRect = outputConnector.getBoundingClientRect();
        const toConnectorRect = inputConnector.getBoundingClientRect();

        // getBoundingClientRect already accounts for scroll offset
        const startX = (fromConnectorRect.left + fromConnectorRect.width / 2 - canvasRect.left) / workflowState.zoom;
        const startY = (fromConnectorRect.top + fromConnectorRect.height / 2 - canvasRect.top) / workflowState.zoom;
        const endX = (toConnectorRect.left + toConnectorRect.width / 2 - canvasRect.left) / workflowState.zoom;
        const endY = (toConnectorRect.top + toConnectorRect.height / 2 - canvasRect.top) / workflowState.zoom;

        // Calculate control points for smooth curve
        const deltaY = Math.abs(endY - startY);
        const curveOffset = Math.min(deltaY * 0.5, 100);

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', `M ${startX} ${startY} C ${startX} ${startY + curveOffset}, ${endX} ${endY - curveOffset}, ${endX} ${endY}`);
        path.setAttribute('class', `connection-line ${pathClass}`);
        path.setAttribute('data-from', fromId);
        path.setAttribute('data-to', toId);
        path.setAttribute('data-type', outputType);

        // Double-click to delete connection
        path.addEventListener('dblclick', function() {
            showConfirmModal(
                '{{ translate("Delete Connection") }}',
                '{{ translate("Are you sure you want to remove this connection between nodes?") }}',
                () => {
                    const fromNode = workflowState.nodes.find(n => n.id === parseInt(fromId));
                    if (fromNode) {
                        if (outputType === 'output-true') {
                            fromNode.condition_true_node_id = null;
                        } else if (outputType === 'output-false') {
                            fromNode.condition_false_node_id = null;
                        } else {
                            fromNode.next_node_id = null;
                        }
                        renderConnections();
                        notify('success', '{{ translate("Connection deleted") }}');
                    }
                },
                { type: 'warning', icon: 'ri-link-unlink', btnClass: 'btn-danger', btnText: '{{ translate("Remove") }}' }
            );
        });

        svg.appendChild(path);
    }

    // Canvas Events
    function initCanvasEvents() {
        document.getElementById('canvasArea').addEventListener('click', function(e) {
            if (e.target === this) {
                document.querySelectorAll('.workflow-node.selected').forEach(n => n.classList.remove('selected'));
                document.getElementById('propertiesPanel').style.display = 'none';
                workflowState.selectedNode = null;
            }
        });
    }

    // Zoom
    function zoomIn() {
        workflowState.zoom = Math.min(2, workflowState.zoom + 0.1);
        applyZoom();
    }

    function zoomOut() {
        workflowState.zoom = Math.max(0.5, workflowState.zoom - 0.1);
        applyZoom();
    }

    function applyZoom() {
        document.getElementById('canvasArea').style.transform = `scale(${workflowState.zoom})`;
        document.getElementById('canvasArea').style.transformOrigin = '0 0';
        document.getElementById('zoomLevel').textContent = `${Math.round(workflowState.zoom * 100)}%`;
    }

    // Load Workflow
    function loadWorkflowData() {
        fetch(`{{ route('user.automation.data', $workflow->uid ?? '') }}`)
            .then(r => r.json())
            .then(data => {
                if (!data.status) return;

                document.getElementById('workflowName').value = data.data.name;

                // Create ID mapping for node references
                const idMap = {};
                data.data.nodes.forEach((n, i) => {
                    idMap[n.id] = ++workflowState.nodeIdCounter;
                });

                // Load nodes
                data.data.nodes.forEach(n => {
                    const node = {
                        id: idMap[n.id],
                        type: n.type,
                        action_type: n.action_type,
                        config: n.config || {},
                        label: n.label || getNodeLabel(n.type, n.action_type),
                        position_x: n.position_x || 100,
                        position_y: n.position_y || 100,
                        next_node_id: n.next_node_id ? idMap[n.next_node_id] : null,
                        condition_true_node_id: n.condition_true_node_id ? idMap[n.condition_true_node_id] : null,
                        condition_false_node_id: n.condition_false_node_id ? idMap[n.condition_false_node_id] : null
                    };

                    workflowState.nodes.push(node);
                    renderNode(node);
                });

                renderConnections();
            });
    }

    // Save Workflow
    function saveWorkflow(asDraft = true) {
        const name = document.getElementById('workflowName').value.trim();
        if (!name) {
            notify('error', '{{ translate("Please enter a workflow name") }}');
            return;
        }

        if (workflowState.nodes.length === 0) {
            notify('error', '{{ translate("Please add at least one node") }}');
            return;
        }

        // Find trigger node
        const triggerNode = workflowState.nodes.find(n => n.type === 'trigger');
        if (!triggerNode) {
            notify('error', '{{ translate("Please add a trigger node") }}');
            return;
        }

        // Build node index mapping
        const nodeIndexMap = {};
        workflowState.nodes.forEach((n, i) => {
            nodeIndexMap[n.id] = i;
        });

        const payload = {
            name: name,
            description: '',
            trigger_type: triggerNode.action_type,
            trigger_config: triggerNode.config || {},
            nodes: workflowState.nodes.map(n => ({
                type: n.type,
                action_type: n.action_type,
                config: n.config,
                label: n.label,
                position_x: n.position_x,
                position_y: n.position_y,
                next_node_index: n.next_node_id ? nodeIndexMap[n.next_node_id] : null,
                condition_true_index: n.condition_true_node_id ? nodeIndexMap[n.condition_true_node_id] : null,
                condition_false_index: n.condition_false_node_id ? nodeIndexMap[n.condition_false_node_id] : null
            }))
        };

        const url = workflowState.isEditing
            ? `{{ url('user/automation/update') }}/${workflowState.workflowId}`
            : '{{ route('user.automation.store') }}';

        const method = workflowState.isEditing ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.status) {
                notify('success', data.message);

                if (!asDraft && data.data?.workflow_id) {
                    // Activate workflow
                    fetch(`{{ url('user/automation/activate') }}/${data.data.workflow_id}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    }).then(() => {
                        if (data.data?.redirect_url) {
                            window.location.href = data.data.redirect_url;
                        }
                    });
                } else if (data.data?.redirect_url) {
                    setTimeout(() => window.location.href = data.data.redirect_url, 1000);
                }
            } else {
                notify('error', data.message);
            }
        })
        .catch(err => {
            notify('error', '{{ translate("An error occurred") }}');
        });
    }

    function editNode(nodeId) {
        selectNode(nodeId);
    }

    // Template Modal Functions
    let templates = [];
    let selectedTemplate = null;
    const categoryColors = {
        'welcome': '#10b981',
        'engagement': '#f59e0b',
        'sales': '#3b82f6',
        'reminder': '#8b5cf6',
        'birthday': '#ec4899',
        'feedback': '#06b6d4'
    };

    const categoryLabels = {
        'welcome': '{{ translate("Welcome & Onboarding") }}',
        'engagement': '{{ translate("Re-engagement") }}',
        'sales': '{{ translate("Sales & Promotions") }}',
        'reminder': '{{ translate("Reminders") }}',
        'birthday': '{{ translate("Birthday & Anniversary") }}',
        'feedback': '{{ translate("Feedback & Survey") }}'
    };

    function showTemplateModal() {
        if (workflowState.nodes.length > 0) {
            showConfirmModal(
                '{{ translate("Load Template") }}',
                '{{ translate("Loading a template will replace your current workflow. All existing nodes and connections will be removed. Continue?") }}',
                () => {
                    document.getElementById('templateModal').classList.add('show');
                    loadTemplates();
                },
                { type: 'info', icon: 'ri-file-copy-2-line', btnClass: 'btn-primary', btnText: '{{ translate("Continue") }}' }
            );
            return;
        }

        document.getElementById('templateModal').classList.add('show');
        loadTemplates();
    }

    function hideTemplateModal() {
        document.getElementById('templateModal').classList.remove('show');
        selectedTemplate = null;
        document.getElementById('useTemplateBtn').disabled = true;
    }

    function loadTemplates() {
        fetch('{{ route("user.automation.templates.list") }}')
            .then(r => r.json())
            .then(data => {
                if (data.status && data.data) {
                    templates = data.data;
                    renderCategories();
                    renderTemplates('all');
                }
            })
            .catch(err => {
                console.error('Error loading templates:', err);
                notify('error', '{{ translate("Failed to load templates") }}');
            });
    }

    function renderCategories() {
        const container = document.getElementById('templateCategories');
        container.innerHTML = '<button class="template-category-btn active" data-category="all" onclick="filterTemplates(\'all\', this)">{{ translate("All") }}</button>';

        const categories = [...new Set(templates.map(t => t.category))];
        categories.forEach(cat => {
            const label = categoryLabels[cat] || cat;
            container.innerHTML += `<button class="template-category-btn" data-category="${cat}" onclick="filterTemplates('${cat}', this)">${label}</button>`;
        });
    }

    function filterTemplates(category, btn) {
        document.querySelectorAll('.template-category-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderTemplates(category);
    }

    function renderTemplates(category) {
        const grid = document.getElementById('templateGrid');
        const filtered = category === 'all' ? templates : templates.filter(t => t.category === category);

        if (filtered.length === 0) {
            grid.innerHTML = `<div class="text-center py-4 text-muted"><i class="ri-folder-line fs-2 d-block mb-2"></i>{{ translate("No templates found") }}</div>`;
            return;
        }

        grid.innerHTML = filtered.map(template => {
            const color = categoryColors[template.category] || '#6b7280';
            const nodeCount = template.nodes ? template.nodes.length : 0;
            const catLabel = categoryLabels[template.category] || template.category;

            return `
                <div class="template-card" onclick="selectTemplate('${template.slug}')" data-slug="${template.slug}">
                    <div class="template-card-header">
                        <div class="template-card-icon" style="background: ${color}20; color: ${color};">
                            <i class="${template.icon || 'ri-flow-chart'}"></i>
                        </div>
                        <div>
                            <div class="template-card-title">${template.name}</div>
                            <div class="template-card-category">${catLabel}</div>
                        </div>
                    </div>
                    <div class="template-card-description">${template.description || ''}</div>
                    <div class="template-card-meta">
                        <span><i class="ri-node-tree me-1"></i>${nodeCount} {{ translate("nodes") }}</span>
                        <span><i class="ri-download-2-line me-1"></i>${template.usage_count || 0} {{ translate("uses") }}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function selectTemplate(slug) {
        document.querySelectorAll('.template-card').forEach(card => card.classList.remove('selected'));
        const card = document.querySelector(`.template-card[data-slug="${slug}"]`);
        if (card) {
            card.classList.add('selected');
            selectedTemplate = templates.find(t => t.slug === slug);
            document.getElementById('useTemplateBtn').disabled = false;
        }
    }

    function useSelectedTemplate() {
        if (!selectedTemplate) return;

        // Clear current workflow
        workflowState.nodes = [];
        workflowState.connections = [];
        workflowState.nodeIdCounter = 0;
        document.getElementById('canvasArea').innerHTML = '';
        document.getElementById('connectionsSvg').innerHTML = '';

        // Set workflow name from template
        document.getElementById('workflowName').value = selectedTemplate.name;

        // Load template nodes
        if (selectedTemplate.nodes && selectedTemplate.nodes.length > 0) {
            const nodeIdMap = {};

            selectedTemplate.nodes.forEach((templateNode, index) => {
                const nodeId = ++workflowState.nodeIdCounter;
                nodeIdMap[index] = nodeId;

                const node = {
                    id: nodeId,
                    type: templateNode.type || 'action',
                    action_type: templateNode.action || templateNode.action_type || 'send_sms',
                    config: templateNode.config || {},
                    label: templateNode.label || getNodeLabel(templateNode.type, templateNode.action),
                    position_x: templateNode.position_x || 400,
                    position_y: templateNode.position_y || 50 + (index * 130),
                    next_node_id: null,
                    condition_true_node_id: null,
                    condition_false_node_id: null
                };

                workflowState.nodes.push(node);
                renderNode(node);
            });

            // Create connections between sequential nodes
            for (let i = 0; i < workflowState.nodes.length - 1; i++) {
                const currentNode = workflowState.nodes[i];
                const nextNode = workflowState.nodes[i + 1];

                if (currentNode.type !== 'condition') {
                    currentNode.next_node_id = nextNode.id;
                    workflowState.connections.push({
                        from: currentNode.id,
                        to: nextNode.id,
                        type: 'default'
                    });
                }
            }

            // Redraw connections
            drawAllConnections();
        }

        hideTemplateModal();
        notify('success', '{{ translate("Template loaded successfully") }}');
    }

    function drawAllConnections() {
        const svg = document.getElementById('connectionsSvg');
        svg.innerHTML = '';

        workflowState.connections.forEach(conn => {
            drawConnection(conn.from, conn.to, conn.type);
        });
    }
</script>
@endpush
