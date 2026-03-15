module.exports = {
  apps: [
    // =========================================
    // DEFAULT QUEUE
    // =========================================
    {
      name: 'xsender-queue-default',
      script: 'artisan',
      args: 'queue:work database --queue=default --sleep=3 --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '256M',
      env: {
        APP_ENV: 'production',
      },
    },

    // =========================================
    // REGULAR QUEUES (Single Messages)
    // =========================================
    {
      name: 'xsender-queue-regular-sms',
      script: 'artisan',
      args: 'queue:work database --queue=regular-sms --sleep=3 --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 2,
      autorestart: true,
      watch: false,
      max_memory_restart: '256M',
      env: {
        APP_ENV: 'production',
      },
    },
    {
      name: 'xsender-queue-regular-email',
      script: 'artisan',
      args: 'queue:work database --queue=regular-email --sleep=3 --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 2,
      autorestart: true,
      watch: false,
      max_memory_restart: '256M',
      env: {
        APP_ENV: 'production',
      },
    },
    {
      name: 'xsender-queue-regular-whatsapp',
      script: 'artisan',
      args: 'queue:work database --queue=regular-whatsapp --sleep=3 --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 2,
      autorestart: true,
      watch: false,
      max_memory_restart: '256M',
      env: {
        APP_ENV: 'production',
      },
    },

    // =========================================
    // CAMPAIGN QUEUES (Bulk Messages)
    // =========================================
    {
      name: 'xsender-queue-campaign-sms',
      script: 'artisan',
      args: 'queue:work database --queue=campaign-sms --sleep=3 --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 3,
      autorestart: true,
      watch: false,
      max_memory_restart: '512M',
      env: {
        APP_ENV: 'production',
      },
    },
    {
      name: 'xsender-queue-campaign-email',
      script: 'artisan',
      args: 'queue:work database --queue=campaign-email --sleep=3 --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 3,
      autorestart: true,
      watch: false,
      max_memory_restart: '512M',
      env: {
        APP_ENV: 'production',
      },
    },
    {
      name: 'xsender-queue-campaign-whatsapp',
      script: 'artisan',
      args: 'queue:work database --queue=campaign-whatsapp --sleep=3 --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 3,
      autorestart: true,
      watch: false,
      max_memory_restart: '512M',
      env: {
        APP_ENV: 'production',
      },
    },

    // =========================================
    // CHAT QUEUE (WhatsApp Conversations)
    // =========================================
    {
      name: 'xsender-queue-chat-whatsapp',
      script: 'artisan',
      args: 'queue:work database --queue=chat-whatsapp --sleep=1 --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 2,
      autorestart: true,
      watch: false,
      max_memory_restart: '256M',
      env: {
        APP_ENV: 'production',
      },
    },

    // =========================================
    // UTILITY QUEUES
    // =========================================
    {
      name: 'xsender-queue-import-contacts',
      script: 'artisan',
      args: 'queue:work database --queue=import-contacts --sleep=3 --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '512M',
      env: {
        APP_ENV: 'production',
      },
    },
    {
      name: 'xsender-queue-verify-email',
      script: 'artisan',
      args: 'queue:work database --queue=verify-email --sleep=3 --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '256M',
      env: {
        APP_ENV: 'production',
      },
    },
    {
      name: 'xsender-queue-dispatch-logs',
      script: 'artisan',
      args: 'queue:work database --queue=dispatch-logs --sleep=5 --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '256M',
      env: {
        APP_ENV: 'production',
      },
    },

    // =========================================
    // WORKER-TRIGGER QUEUE (Meta-Queue for HTTP Triggers)
    // =========================================
    {
      name: 'xsender-queue-worker-trigger',
      script: 'artisan',
      args: 'queue:work database --queue=worker-trigger --sleep=1 --tries=3 --max-time=3600',
      interpreter: 'php',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '256M',
      env: {
        APP_ENV: 'production',
      },
    },

    // =========================================
    // LARAVEL SCHEDULER (Replaces Cron)
    // =========================================
    {
      name: 'xsender-laravel-scheduler',
      script: 'artisan',
      args: 'schedule:work',
      interpreter: 'php',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '128M',
      env: {
        APP_ENV: 'production',
      },
    }
  ],
};
