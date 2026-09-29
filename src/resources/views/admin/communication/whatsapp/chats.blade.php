@push("style-include")
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/select2.min.css')}}">
  <link rel="stylesheet" href="{{ asset('assets/theme/global/css/chat-media.css')}}">
  <style>
    /* OmniReach WhatsApp Chat UI - Theme Adaptive Styles */
    :root, [data-bs-theme="light"] {
      --chat-primary: var(--color-primary, #7f56d9);
      --chat-accent: #00a884;
      --chat-bg: var(--site-bg, #f4f6f8);
      --chat-sidebar-bg: var(--card-bg, #ffffff);
      --chat-left-menu-bg: var(--sidebar-bg, #f8f9fa);
      --chat-header-bg: var(--card-bg, #ffffff);
      --chat-border: var(--color-border, #e5e7eb);
      --chat-border-light: rgba(0, 0, 0, 0.06);
      --chat-item-hover: rgba(127, 86, 217, 0.05);
      --chat-item-active: rgba(127, 86, 217, 0.12);
      --chat-item-unread: rgba(0, 168, 132, 0.06);
      --chat-text: var(--text-primary, #111827);
      --chat-text-secondary: var(--text-secondary, #6b7280);
      --chat-bubble-in: #ffffff;
      --chat-bubble-in-text: #111827;
      --chat-bubble-in-border: #e5e7eb;
      --chat-bubble-out: #d9fdd3;
      --chat-bubble-out-text: #111827;
      --chat-bubble-out-border: transparent;
      --chat-footer-bg: var(--card-bg, #ffffff);
      --chat-input-bg: var(--site-bg, #f3f4f6);
      --chat-input-border: var(--color-border, #e5e7eb);
      --chat-input-text: var(--text-primary, #111827);
      --chat-body-bg: #efeae2;
      --chat-pattern-color: #d4cfc6;
      --chat-pattern-opacity: 0.15;
      --device-badge-bg: rgba(0, 168, 132, 0.12);
      --device-badge-text: #008769;
      --chat-dropdown-bg: #ffffff;
      --chat-dropdown-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    [data-bs-theme="dark"] {
      --chat-primary: var(--color-primary, #9e77ed);
      --chat-accent: #00a884;
      --chat-bg: var(--site-bg, #161922);
      --chat-sidebar-bg: var(--card-bg, #1f232e);
      --chat-left-menu-bg: var(--sidebar-bg, #191c26);
      --chat-header-bg: var(--card-bg, #1f232e);
      --chat-border: var(--color-border, #2d3343);
      --chat-border-light: rgba(255, 255, 255, 0.05);
      --chat-item-hover: rgba(255, 255, 255, 0.04);
      --chat-item-active: rgba(127, 86, 217, 0.2);
      --chat-item-unread: rgba(0, 168, 132, 0.12);
      --chat-text: var(--text-primary, #f9fafb);
      --chat-text-secondary: var(--text-secondary, #9ca3af);
      --chat-bubble-in: #222733;
      --chat-bubble-in-text: #f3f4f6;
      --chat-bubble-in-border: rgba(255, 255, 255, 0.06);
      --chat-bubble-out: #005c4b;
      --chat-bubble-out-text: #f3f4f6;
      --chat-bubble-out-border: transparent;
      --chat-footer-bg: var(--card-bg, #1f232e);
      --chat-input-bg: var(--site-bg, #161922);
      --chat-input-border: var(--color-border, #2d3343);
      --chat-input-text: var(--text-primary, #f9fafb);
      --chat-body-bg: #0b141a;
      --chat-pattern-color: #1f2c34;
      --chat-pattern-opacity: 0.35;
      --device-badge-bg: rgba(0, 168, 132, 0.2);
      --device-badge-text: #25d366;
      --chat-dropdown-bg: #1f232e;
      --chat-dropdown-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    }

    /* Fix scroll containment & layout */
    .chat-wrapper {
      height: calc(100vh - 75px);
      max-height: calc(100vh - 75px);
      overflow: hidden;
      display: flex;
      background: var(--chat-bg);
      border: 1px solid var(--chat-border);
      border-radius: 12px;
      margin: 10px;
    }
    .chat-left {
      height: 100%;
      width: 380px;
      min-width: 340px;
      max-width: 420px;
      overflow: hidden;
      display: flex;
      border-right: 1px solid var(--chat-border);
      background: var(--chat-sidebar-bg);
    }
    .chat-left-sidebar {
      width: 60px;
      background: var(--chat-left-menu-bg);
      border-right: 1px solid var(--chat-border);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 12px 0;
      flex-shrink: 0;
    }
    .chat-left-sidebar .chat-menus {
      display: flex;
      flex-direction: column;
      gap: 8px;
      width: 100%;
      align-items: center;
    }
    .chat-left-sidebar .nav-link {
      width: 42px;
      height: 42px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--chat-text-secondary);
      font-size: 20px;
      transition: all 0.2s ease;
      border: none;
      background: transparent;
      text-decoration: none;
    }
    .chat-left-sidebar .nav-link:hover {
      color: var(--chat-text);
      background: var(--chat-item-hover);
    }
    .chat-left-sidebar .nav-link.active {
      color: #ffffff;
      background: var(--chat-primary);
      box-shadow: 0 2px 8px rgba(127, 86, 217, 0.35);
    }
    .chat-sidebar-wrapper {
      flex: 1;
      height: 100%;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      background: var(--chat-sidebar-bg);
    }
    .chat-sidebar-content {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      background: var(--chat-sidebar-bg);
    }
    .chat-body {
      flex: 1;
      height: 100%;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      background: var(--chat-bg);
    }
    .chat-interface {
      height: 100%;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .chatting-body {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      background-color: var(--chat-body-bg);
      background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23999999' fill-opacity='0.08'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      padding: 16px 20px;
    }
    .chatting {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    /* Message Input Area */
    .chat-body-footer {
      background: var(--chat-footer-bg);
      border-top: 1px solid var(--chat-border);
      padding: 10px 16px;
      flex-shrink: 0;
    }
    .chat-body-footer .nav-main-chat {
      margin-bottom: 6px;
    }
    .chat-body-footer .nav-main-chat ul {
      list-style: none;
      padding: 0;
      margin: 0;
      display: flex;
    }
    .chat-body-footer .nav-main-chat button {
      background: transparent;
      border: none;
      color: var(--chat-text);
      font-size: 13px;
      font-weight: 600;
      padding: 4px 10px;
      border-bottom: 2px solid var(--chat-primary);
      cursor: pointer;
    }
    .message-box-wrapper {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 0;
    }
    .template-btn, .media-btn {
      background: var(--chat-input-bg);
      border: 1px solid var(--chat-input-border);
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s ease;
      color: var(--chat-text-secondary);
      flex-shrink: 0;
    }
    .template-btn:hover, .media-btn:hover {
      background: var(--chat-item-hover);
      color: var(--chat-primary);
      border-color: var(--chat-primary);
    }
    .template-btn i, .media-btn i {
      font-size: 19px;
    }
    .white-space-pre-line {
      white-space: pre-line;
    }

    /* Device Selector */
    .device-selector-wrapper {
      padding: 10px 16px;
      background: var(--chat-sidebar-bg);
      border-bottom: 1px solid var(--chat-border);
      flex-shrink: 0;
    }
    .device-selector {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .device-selector label {
      font-size: 12px;
      font-weight: 600;
      color: var(--chat-text-secondary) !important;
      white-space: nowrap;
    }
    .device-selector select,
    .device-selector .form-select {
      flex: 1;
      padding: 6px 12px;
      border-radius: 8px;
      border: 1px solid var(--chat-input-border);
      background: var(--chat-input-bg);
      font-size: 12px;
      color: var(--chat-input-text) !important;
      cursor: pointer;
      outline: none;
    }
    .device-selector select:focus {
      border-color: var(--chat-primary);
      box-shadow: 0 0 0 2px rgba(127, 86, 217, 0.2);
    }

    /* Device Badge */
    .device-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 2px 7px;
      font-size: 10px;
      font-weight: 600;
      border-radius: 4px;
      background: var(--device-badge-bg);
      color: var(--device-badge-text);
      margin-top: 4px;
    }
    .device-badge i {
      font-size: 10px;
    }

    /* Contact List */
    .chat-contacts {
      list-style: none;
      margin: 0;
      padding: 0;
    }
    .chat-contact {
      display: flex;
      align-items: flex-start;
      padding: 12px 16px;
      cursor: pointer;
      transition: background 0.15s ease, border-color 0.15s ease;
      border-bottom: 1px solid var(--chat-border-light);
      gap: 12px;
      background: transparent;
      position: relative;
    }
    .chat-contact:hover {
      background: var(--chat-item-hover) !important;
    }
    .chat-contact.active {
      background: var(--chat-item-active) !important;
      border-left: 3px solid var(--chat-primary);
    }
    .chat-contact.unread {
      background: var(--chat-item-unread);
    }
    .chat-contact-info {
      flex: 1;
      min-width: 0;
      overflow: hidden;
    }
    .chat-contact .contact-name {
      font-weight: 600;
      color: var(--chat-text) !important;
      font-size: 14px;
      margin-bottom: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: block;
    }
    .chat-contact .contact-number {
      font-size: 12px;
      color: var(--chat-text-secondary) !important;
      display: block;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .chat-contact .last-message {
      font-size: 12px;
      color: var(--chat-text-secondary) !important;
      display: block;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-top: 3px;
      line-height: 1.3;
    }
    .chat-contact.unread .last-message,
    .chat-contact .last-message.unread-message {
      color: var(--chat-text) !important;
      font-weight: 600;
    }
    .chat-contact-meta {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 6px;
      flex-shrink: 0;
    }
    .chat-contact .time {
      font-size: 11px;
      color: var(--chat-text-secondary) !important;
      white-space: nowrap;
    }
    .chat-contact .unread-count {
      background: var(--chat-accent, #00a884) !important;
      color: #ffffff !important;
      font-size: 10px;
      min-width: 18px;
      height: 18px;
      padding: 0 5px;
      border-radius: 9px;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* Chat Header */
    .chat-body-header {
      background: var(--chat-header-bg);
      border-bottom: 1px solid var(--chat-border);
      padding: 12px 20px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .chat-body-header .left {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .chat-body-header .show-contact-sidebar {
      background: transparent;
      border: none;
      color: var(--chat-text);
      padding: 4px;
      display: none;
      cursor: pointer;
    }
    @media (max-width: 767.98px) {
      .chat-body-header .show-contact-sidebar {
        display: block;
      }
    }
    .chat-body-header .chat-user-info p,
    .chat-body-header #contact-name {
      font-weight: 600;
      color: var(--chat-text) !important;
      font-size: 15px;
      margin: 0;
    }
    .chat-body-header .chat-user-info span,
    .chat-body-header #contact-number {
      font-size: 12px;
      color: var(--chat-text-secondary) !important;
    }
    .name-number h5 {
      font-weight: 600;
      color: var(--chat-text) !important;
      font-size: 15px;
      margin: 0 0 2px 0;
    }
    .name-number span {
      font-size: 12px;
      color: var(--chat-text-secondary) !important;
    }

    /* Message Bubbles */
    .message {
      margin-bottom: 4px;
      padding: 2px 0;
      display: flex;
    }
    .message .message-wrapper {
      max-width: 70%;
      min-width: 90px;
      display: inline-flex;
      flex-direction: column;
    }
    .message .message-body {
      padding: 8px 14px;
      border-radius: 12px;
      font-size: 14px;
      line-height: 1.45;
      position: relative;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
      display: inline-block;
      min-width: fit-content;
    }
    .message .message-body p {
      margin: 0;
      word-wrap: break-word;
      word-break: break-word;
      white-space: pre-wrap;
    }
    .message.left {
      justify-content: flex-start;
    }
    .message.left .message-body {
      background: var(--chat-bubble-in);
      color: var(--chat-bubble-in-text);
      border: 1px solid var(--chat-bubble-in-border);
      border-top-left-radius: 2px;
    }
    .message.left .message-body p {
      color: var(--chat-bubble-in-text);
    }
    .message.right {
      justify-content: flex-end;
    }
    .message.right .message-wrapper {
      align-items: flex-end;
    }
    .message.right .message-body {
      background: var(--chat-bubble-out);
      color: var(--chat-bubble-out-text);
      border-top-right-radius: 2px;
    }
    .message.right .message-body p {
      color: var(--chat-bubble-out-text);
    }
    .message .message-footer {
      display: inline-flex;
      align-items: center;
      justify-content: flex-end;
      gap: 4px;
      float: right;
      margin-left: 10px;
      margin-top: 4px;
      position: relative;
      bottom: -2px;
    }
    .message.left .message-time {
      font-size: 11px;
      color: var(--chat-text-secondary);
      white-space: nowrap;
    }
    .message.right .message-time {
      font-size: 11px;
      color: rgba(255, 255, 255, 0.75);
      white-space: nowrap;
    }
    [data-bs-theme="light"] .message.right .message-time {
      color: rgba(0, 0, 0, 0.55);
    }
    .message.failed .message-body {
      background: rgba(217, 45, 32, 0.15) !important;
      border: 1px solid rgba(217, 45, 32, 0.4) !important;
      color: var(--chat-text) !important;
    }

    /* Status Icons */
    .message .ri-check-line,
    .message .ri-check-double-line {
      font-size: 15px;
      color: rgba(255, 255, 255, 0.6);
    }
    [data-bs-theme="light"] .message.right .ri-check-line,
    [data-bs-theme="light"] .message.right .ri-check-double-line {
      color: rgba(0, 0, 0, 0.45);
    }
    .message .ri-check-double-line[title="Read"] {
      color: #53bdeb !important;
    }
    .message .ri-time-line {
      font-size: 14px;
      color: rgba(255, 255, 255, 0.6);
    }
    .message .ri-error-warning-line {
      color: #ef4444;
      font-size: 14px;
    }

    /* Download Button */
    .message .download-btn {
      padding: 4px 8px;
      font-size: 11px;
      border-radius: 4px;
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.15);
      color: var(--chat-text-secondary);
      cursor: pointer;
      margin-top: 4px;
    }
    [data-bs-theme="light"] .message .download-btn {
      background: rgba(0, 0, 0, 0.05);
      border-color: rgba(0, 0, 0, 0.1);
    }
    .message .download-btn:hover {
      background: rgba(255, 255, 255, 0.2);
      color: var(--chat-text);
    }

    /* Media Preview */
    .media-preview-wrapper {
      display: none;
      padding: 8px 12px;
      background: var(--chat-input-bg);
      border: 1px solid var(--chat-border);
      border-radius: 8px;
      margin-bottom: 8px;
    }
    .media-preview-wrapper.active {
      display: block;
    }
    .media-preview {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 6px 10px;
      background: var(--chat-sidebar-bg);
      border-radius: 6px;
    }
    .media-preview-thumb {
      width: 44px;
      height: 44px;
      border-radius: 6px;
      background: var(--chat-input-bg);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .media-preview-thumb i {
      font-size: 20px;
      color: var(--chat-text-secondary);
    }
    .media-preview-info {
      flex: 1;
      min-width: 0;
    }
    .media-preview-name {
      font-size: 13px;
      font-weight: 500;
      color: var(--chat-text);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .media-preview-size {
      font-size: 11px;
      color: var(--chat-text-secondary);
    }
    .media-preview-remove {
      background: transparent;
      border: none;
      color: #ef4444;
      cursor: pointer;
      padding: 6px;
      font-size: 18px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .media-preview-remove:hover {
      background: rgba(239, 68, 68, 0.15);
    }

    /* Media Dropdown */
    .media-dropdown {
      position: relative;
    }
    .media-dropdown-menu {
      position: absolute;
      bottom: 100%;
      left: 0;
      background: var(--chat-dropdown-bg);
      border: 1px solid var(--chat-border);
      border-radius: 12px;
      box-shadow: var(--chat-dropdown-shadow);
      padding: 6px;
      min-width: 160px;
      display: none;
      z-index: 100;
      margin-bottom: 8px;
    }
    .media-dropdown-menu.show {
      display: block;
    }
    .media-dropdown-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 12px;
      cursor: pointer;
      transition: background 0.15s;
      border: none;
      background: none;
      width: 100%;
      text-align: left;
      font-size: 13px;
      font-weight: 500;
      color: var(--chat-text);
      border-radius: 6px;
    }
    .media-dropdown-item:hover {
      background: var(--chat-item-hover);
      color: var(--chat-primary);
    }
    .media-dropdown-item i {
      font-size: 18px;
      width: 20px;
      text-align: center;
    }
    .media-dropdown-item.image i { color: #22c55e; }
    .media-dropdown-item.video i { color: #f43f5e; }
    .media-dropdown-item.audio i { color: #a855f7; }
    .media-dropdown-item.document i { color: #3b82f6; }

    /* Chat Form */
    .chat-form {
      background: var(--chat-input-bg);
      border: 1px solid var(--chat-input-border);
      border-radius: 24px;
      display: flex;
      align-items: center;
      flex: 1;
      padding: 2px 4px 2px 14px;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .chat-form:focus-within {
      border-color: var(--chat-primary);
      box-shadow: 0 0 0 2px rgba(127, 86, 217, 0.2);
    }
    .chat-form input[type="text"],
    .chat-form input[name="body"],
    #message-input {
      flex: 1;
      border: none;
      background: transparent;
      padding: 8px 6px;
      font-size: 14px;
      color: var(--chat-input-text) !important;
      outline: none;
    }
    .chat-form input::placeholder,
    #message-input::placeholder {
      color: var(--chat-text-secondary);
      opacity: 0.8;
    }

    /* Send Button */
    .chat-submit-btn,
    .chat-body-footer button[type="submit"] {
      background: var(--chat-accent, #00a884) !important;
      border: none !important;
      border-radius: 50% !important;
      width: 36px !important;
      height: 36px !important;
      min-width: 36px !important;
      color: #ffffff !important;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex !important;
      align-items: center;
      justify-content: center;
      padding: 0 !important;
      margin: 2px;
    }
    .chat-submit-btn:hover,
    .chat-body-footer button[type="submit"]:hover {
      filter: brightness(1.15);
      transform: scale(1.05);
    }

    /* Sidebar Header */
    .chat-sidebar-header {
      padding: 14px 16px;
      background: var(--chat-sidebar-bg);
      border-bottom: 1px solid var(--chat-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }
    .chat-sidebar-header h5 {
      font-weight: 600;
      color: var(--chat-text) !important;
      font-size: 16px;
      margin: 0;
    }

    /* Chat Media Elements */
    .chat-media-image {
      max-width: 250px;
      max-height: 250px;
      border-radius: 8px;
      margin-top: 4px;
      display: block;
    }
    .chat-media-video {
      max-width: 250px;
      border-radius: 8px;
      margin-top: 4px;
    }
    .chat-media-audio {
      width: 240px;
      margin-top: 4px;
    }
    .chat-media-document {
      border-radius: 8px;
      margin-top: 4px;
    }

    /* No Conversation State */
    #no-conversation-state {
      background: var(--chat-body-bg);
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: var(--chat-text-secondary);
    }
    #no-conversation-state i.empty-chat-icon {
      font-size: 64px;
      color: var(--chat-text-secondary);
      opacity: 0.35;
      margin-bottom: 16px;
    }
    #no-conversation-state h4 {
      margin: 0 0 8px;
      color: var(--chat-text);
      font-weight: 600;
    }
    #no-conversation-state p {
      color: var(--chat-text-secondary);
    }

    /* Search Dropdown */
    .dropdown-search .dropdown-menu {
      background: var(--chat-dropdown-bg);
      border: 1px solid var(--chat-border);
      box-shadow: var(--chat-dropdown-shadow);
      border-radius: 10px;
    }
    .dropdown-search .dropdown-form {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 4px 8px;
      background: var(--chat-input-bg);
      border-radius: 6px;
      border: 1px solid var(--chat-input-border);
    }
    .dropdown-search .dropdown-form i {
      color: var(--chat-text-secondary);
    }
    .dropdown-search .dropdown-form input {
      background: transparent;
      border: none;
      color: var(--chat-text) !important;
      font-size: 13px;
      outline: none;
      box-shadow: none;
    }

    /* Template Modal */
    .template-modal .template-item {
      padding: 12px;
      border: 1px solid var(--chat-border);
      border-radius: 8px;
      margin-bottom: 8px;
      cursor: pointer;
      transition: all 0.15s;
      background: var(--chat-input-bg);
    }
    .template-modal .template-item:hover {
      background: var(--chat-item-hover);
      border-color: var(--chat-primary);
    }
    .template-modal .template-item h6 {
      margin-bottom: 4px;
      color: var(--chat-text);
      font-weight: 600;
      font-size: 13px;
    }
    .template-modal .template-item p {
      margin-bottom: 0;
      color: var(--chat-text-secondary);
      font-size: 12px;
    }
    #template-preview {
      background: var(--chat-input-bg) !important;
      border: 1px solid var(--chat-border) !important;
      color: var(--chat-text) !important;
    }

    /* Load More Button */
    .load-more-btn {
      display: block;
      width: calc(100% - 24px);
      margin: 8px 12px;
      padding: 8px;
      background: var(--chat-input-bg);
      border: 1px solid var(--chat-input-border);
      border-radius: 6px;
      color: var(--chat-text-secondary);
      font-size: 12px;
      font-weight: 500;
      cursor: pointer;
      text-align: center;
      transition: all 0.2s;
    }
    .load-more-btn:hover {
      background: var(--chat-item-hover);
      color: var(--chat-primary);
      border-color: var(--chat-primary);
    }

    /* Loader */
    .chat-loader {
      display: flex;
      justify-content: center;
      padding: 20px;
    }
    .chat-loader .spinner {
      width: 24px;
      height: 24px;
      border: 2px solid var(--chat-border);
      border-top-color: var(--chat-primary);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Modals */
    .modal-content {
      background: var(--chat-sidebar-bg);
      border: 1px solid var(--chat-border);
      color: var(--chat-text);
    }
    .modal-header, .modal-footer {
      border-color: var(--chat-border);
    }
    .modal-title {
      color: var(--chat-text);
    }

    /* Tab Content */
    .tab-content {
      flex: 1;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .tab-pane {
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    .tab-pane.show {
      display: flex !important;
    }

    /* Delete Button */
    .chat-contact .delete-conversation {
      opacity: 0.35;
      transition: opacity 0.2s, color 0.2s;
      color: var(--chat-text-secondary);
    }
    .chat-contact:hover .delete-conversation {
      opacity: 1;
      color: var(--color-danger, #ef4444) !important;
    }
  </style>
@endpush
@extends('admin.layouts.app')
@section('panel')

 <main class="main-body p-0">
    <div class="container-fluid px-0 main-content">
      <div class="chat-wrapper">
        <!-- ==========Sidebar Left========== -->
        <div class="chat-left">
          <div class="chat-left-sidebar">
            <div class="nav chat-menus" role="tablist" aria-orientation="vertical">
              <div class="chat-menu">
                <div class="nav-item" role="presentation" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="All Chats">
                  <a class="nav-link active" data-bs-toggle="tab" href="#chats" role="tab" aria-selected="true" data-type="all">
                    <i class="ri-question-answer-line"></i>
                  </a>
                </div>
                <div class="nav-item" role="presentation" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Pending">
                  <a class="nav-link" data-bs-toggle="tab" href="#pending" role="tab" aria-selected="false" data-type="pending">
                    <i class="ri-hourglass-line"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>
          <div class="chat-sidebar-wrapper">
            {{-- Device Selector --}}
            @if($devices->count() > 0)
            <div class="device-selector-wrapper">
              <div class="device-selector">
                <label for="device-filter"><i class="ri-smartphone-line me-1"></i>{{ translate("Device") }}:</label>
                <select id="device-filter" class="form-select form-select-sm">
                  <option value="all">{{ translate("All Devices") }} ({{ $devices->count() }})</option>
                  @foreach($devices as $device)
                    <option value="{{ $device['id'] }}">{{ $device['name'] }} {{ $device['address'] ? '('.$device['address'].')' : '' }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            @endif
            <div class="tab-content h-100">
              <div class="tab-pane fade show active" id="chats" role="tabpanel" aria-labelledby="chats-tab" tabindex="0">
                <div class="chat-sidebar-header">
                  <h5>{{ translate("All Chats") }}</h5>
                  <div class="d-flex align-items-center gap-lg-3 gap-2">
                    <div class="dropdown dropdown-search">
                      <button class="icon-btn bg-transparent fs-20 text-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-offset="10,10">
                        <i class="ri-search-line"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end p-2">
                        <form action="#" class="dropdown-form">
                          <i class="ri-search-line"></i>
                          <input type="search" class="form-control" id="conversation-search">
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="chat-sidebar-content">
                  <div class="chat-loader" id="conversations-loader" style="display: none;">
                    <div class="spinner"></div>
                  </div>
                  <ul class="chat-contacts" id="conversations-list"></ul>
                  <button class="load-more-btn" id="load-more-conversations" style="display: none;">{{ translate("Load More") }}</button>
                </div>
              </div>
              <!-- Pending tab -->
              <div class="tab-pane fade" id="pending" role="tabpanel" aria-labelledby="pending-tab" tabindex="0">
                <div class="chat-sidebar-header">
                  <h5>{{ translate("Pending") }}</h5>
                  <div class="d-flex align-items-center gap-lg-3 gap-2">
                    <div class="dropdown dropdown-search">
                      <button class="icon-btn bg-transparent fs-20 text-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-offset="10,10">
                        <i class="ri-search-line"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end p-2">
                        <form action="#" class="dropdown-form">
                          <i class="ri-search-line"></i>
                          <input type="search" class="form-control" id="pending-search">
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="chat-sidebar-content">
                  <div class="chat-loader" id="pending-loader" style="display: none;">
                    <div class="spinner"></div>
                  </div>
                  <ul class="chat-contacts" id="pending-list"></ul>
                  <button class="load-more-btn" id="load-more-pending" style="display: none;">{{ translate("Load More") }}</button>
                </div>
              </div>
              <!-- Profile tab (preserved from old) -->
              <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab" tabindex="0">
                <div class="chat-sidebar-content">
                  <ul class="chat-contacts" id="profile-list"></ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ==========Chat Content========== -->
        <div class="chat-body">
          <div class="no-conversation-selected" id="no-conversation-state" style="display: flex;">
            <i class="ri-chat-3-line empty-chat-icon"></i>
            <h4>{{ translate("Select a conversation") }}</h4>
            <p class="text-muted">{{ translate("Choose a conversation from the sidebar to start messaging") }}</p>

            <button class="show-contact-sidebar i-btn btn--primary btn--sm mt-4 d-lg-none d-block">
                  {{ translate("Show Chat List") }}
                </button>
          </div>
          <div class="chat-interface" id="chat-interface" style="display: none;">
            <div class="chat-body-header">
              <div class="left">
                <button class="show-contact-sidebar">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="m22 11h-17.586l5.293-5.293a1 1 0 1 0 -1.414-1.414l-7 7a1 1 0 0 0 0 1.414l7 7a1 1 0 0 0 1.414-1.414l-5.293-5.293h17.586a1 1 0 0 0 0-2z" />
                  </svg>
                </button>
                <div class="chat-user-wrapper show-user-btn" role="button">
                  <div class="chat-user-info">
                    <p id="contact-name"></p>
                    <span id="contact-number"></span>
                  </div>
                </div>
              </div>
              <div class="right">
                <div class="d-flex align-items-center gap-lg-3 gap-2">
                  <div class="dropdown dropdown-search">
                    <button class="icon-btn bg-transparent fs-20 text-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-offset="10,10">
                      <i class="ri-search-line"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2">
                      <form action="#" class="dropdown-form">
                        <i class="ri-search-line"></i>
                        <input type="search" class="form-control" id="message-search">
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="chatting-body">
              <div class="chat-loader" id="messages-loader" style="display: none;">
                <div class="spinner"></div>
              </div>
              <ul class="chatting" id="messages-list"></ul>
              <button class="load-more-btn" id="load-more-messages" style="display: none;">{{ translate("Load More") }}</button>
            </div>
            <div class="chat-body-footer">
              <div class="nav-main-chat">
                <ul>
                  <li>
                    <button class="active">{{ translate("Reply") }}</button>
                  </li>
                </ul>
              </div>
              {{-- Media Preview Area --}}
              <div class="media-preview-wrapper" id="media-preview-wrapper">
                <div class="media-preview">
                  <div class="media-preview-thumb" id="media-preview-thumb">
                    <i class="ri-file-line"></i>
                  </div>
                  <div class="media-preview-info">
                    <div class="media-preview-name" id="media-preview-name">filename.jpg</div>
                    <div class="media-preview-size" id="media-preview-size">2.5 MB</div>
                  </div>
                  <button type="button" class="media-preview-remove" id="media-remove-btn" title="{{ translate("Remove") }}">
                    <i class="ri-close-line"></i>
                  </button>
                </div>
              </div>
              <div class="message-box-wrapper">
                {{-- Media Upload Button --}}
                <div class="media-dropdown">
                  <button type="button" class="media-btn" id="media-dropdown-btn" title="{{ translate("Attach Media") }}">
                    <i class="ri-attachment-2"></i>
                  </button>
                  <div class="media-dropdown-menu" id="media-dropdown-menu">
                    <button type="button" class="media-dropdown-item image" data-type="image" data-accept="image/jpeg,image/png,image/gif,image/webp">
                      <i class="ri-image-line"></i>
                      <span>{{ translate("Photo") }}</span>
                    </button>
                    <button type="button" class="media-dropdown-item video" data-type="video" data-accept="video/mp4,video/3gpp">
                      <i class="ri-video-line"></i>
                      <span>{{ translate("Video") }}</span>
                    </button>
                    <button type="button" class="media-dropdown-item audio" data-type="audio" data-accept="audio/mpeg,audio/ogg,audio/amr">
                      <i class="ri-music-line"></i>
                      <span>{{ translate("Audio") }}</span>
                    </button>
                    <button type="button" class="media-dropdown-item document" data-type="document" data-accept=".pdf,.doc,.docx,.xls,.xlsx">
                      <i class="ri-file-text-line"></i>
                      <span>{{ translate("Document") }}</span>
                    </button>
                  </div>
                </div>
                {{-- Template Button --}}
                <button type="button" class="template-btn" id="chat-template-btn" title="{{ translate("Use Template") }}">
                  <i class="ri-layout-fill"></i>
                </button>
                <form action="{{ route('admin.communication.whatsapp.chats.send') }}" class="chat-form" method="POST" id="message-form" enctype="multipart/form-data">
                  @csrf
                  <input type="hidden" name="conversation_id" id="conversation_id" value="">
                  <input type="hidden" name="media_type" id="media_type" value="">
                  <input type="file" name="media" id="media-input" style="display: none;" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx">
                  <input type="text" name="body" placeholder="{{ translate("Type a message") }}" id="message-input" />
                  <button type="submit" class="chat-submit-btn">
                    <i class="ri-send-plane-fill"></i>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
{{-- Delete Conversation Modal --}}
<div class="modal fade actionModal" id="deleteConversationModal" tabindex="-1" aria-labelledby="deleteConversationModal" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-start">
        <span class="action-icon danger">
          <i class="bi bi-exclamation-circle"></i>
        </span>
      </div>
      <div class="modal-body">
        <div class="action-message">
          <h5>{{ translate("Are you sure you want to delete this conversation?") }}</h5>
          <p class="text-muted">{{ translate("All messages in this conversation will be permanently deleted. This action cannot be undone.") }}</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="i-btn btn--dark outline btn--lg" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
        <button type="button" class="i-btn btn--danger btn--lg" id="confirmDeleteConversation">{{ translate("Delete") }}</button>
      </div>
    </div>
  </div>
</div>

{{-- Template Selection Modal --}}
<div class="modal fade" id="chatTemplateModal" tabindex="-1" aria-labelledby="chatTemplateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="chatTemplateModalLabel">{{ translate("Select WhatsApp Template") }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="chat-template-select" class="form-label">{{ translate("Choose a template") }}</label>
          <select class="form-select" id="chat-template-select">
            <option value="">{{ translate("Select template...") }}</option>
            @foreach($templates ?? [] as $template)
              <option value="{{ $template->id }}" data-message="{{ Arr::get($template->template_data, 'message', '') }}">
                {{ $template->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div id="template-preview" class="border rounded p-3 bg-light" style="display:none;">
          <label class="form-label fw-bold">{{ translate("Preview") }}:</label>
          <p id="template-preview-text" class="mb-0 white-space-pre-line"></p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="i-btn btn--dark outline btn--md" data-bs-dismiss="modal">{{ translate("Cancel") }}</button>
        <button type="button" class="i-btn btn--primary btn--md" id="insert-template-btn" disabled>{{ translate("Insert") }}</button>
      </div>
    </div>
  </div>
</div>

@endsection
@push("script-include")
  <script src="{{asset('assets/theme/global/js/chat.js')}}"></script>
  <script src="{{asset('assets/theme/global/js/select2.min.js')}}"></script>
@endpush
@push('script-push')
  @include('admin.communication.whatsapp.chats.js.enhanced-script', [
    'getConversationsRoute' => route('admin.communication.whatsapp.chats.conversations'),
    'showRoute'             => route('admin.communication.whatsapp.chats.show', ['conversation' => ':id']),
    'searchMessagesRoute'   => route('admin.communication.whatsapp.chats.search-messages', ['conversation' => ':id']),
    'loadMoreMessagesRoute' => route('admin.communication.whatsapp.chats.load-more-messages', ['conversation' => ':id'])
  ])

  <script>
  $(document).ready(function() {
      let selectedTemplateMessage = '';

      // Open template modal
      $('#chat-template-btn').on('click', function() {
          $('#chatTemplateModal').modal('show');
      });

      // Handle template selection
      $('#chat-template-select').on('change', function() {
          const selectedOption = $(this).find('option:selected');
          selectedTemplateMessage = selectedOption.data('message') || '';

          if (selectedTemplateMessage) {
              $('#template-preview-text').text(selectedTemplateMessage);
              $('#template-preview').show();
              $('#insert-template-btn').prop('disabled', false);
          } else {
              $('#template-preview').hide();
              $('#insert-template-btn').prop('disabled', true);
          }
      });

      // Insert template into message input
      $('#insert-template-btn').on('click', function() {
          if (selectedTemplateMessage) {
              $('#message-input').val(selectedTemplateMessage);
              $('#chatTemplateModal').modal('hide');

              // Reset modal
              $('#chat-template-select').val('');
              $('#template-preview').hide();
              $('#insert-template-btn').prop('disabled', true);
              selectedTemplateMessage = '';

              // Focus on message input
              $('#message-input').focus();
          }
      });

      // Reset modal when closed
      $('#chatTemplateModal').on('hidden.bs.modal', function() {
          $('#chat-template-select').val('');
          $('#template-preview').hide();
          $('#insert-template-btn').prop('disabled', true);
          selectedTemplateMessage = '';
      });
  });
  </script>
@endpush  