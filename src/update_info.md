# Version 4.1 Release Notes

## Bug Fixes

- Fixed WhatsApp QR code issue
- Fixed duplicate incoming WhatsApp messages in chat interface
- Fixed WhatsApp "old version" warning popup for recipients
- Fixed template buttons not working (converted to formatted text)
- Fixed 500 errors after system update due to cached views/functions
- Fixed cache clearing issues on servers without terminal access
- Fixed migration/seeder silent failures during system updates
- Fixed double message sending when multiple automation methods configured
- Fixed PHP 8.4 deprecation warnings (nullable parameter types)
- Fixed "Attempt to read property on null" errors in topbar

## What's New

### Smart Automation Mode System
Prevents double message sending when multiple automation methods are configured:
- **Auto Detect**: System automatically detects supervisor workers
- **Cron URL Mode**: For cPanel/shared hosting - single URL handles everything
- **Scheduler Mode**: For VPS with `artisan schedule:run`
- **Supervisor Mode**: For enterprise with continuous queue workers
- Select your mode in **Admin > Settings > Automation**

### Enterprise Anti-Ban Protection for WhatsApp
- Random delays between messages (configurable in gateway settings)
- Batch pauses after specified message count
- Typing indicator simulation
- Hourly/daily message limits tracking

### Improved API Documentation
- Modern card-tabs layout for Email, SMS, WhatsApp
- Multi-language code examples (PHP, JavaScript, Python, Node.js)
- Full endpoint URLs with example parameters
- Success and error response examples

### Emergency Cache Clear Route
- Access: `/clear-cache?key=YOUR_PURCHASE_KEY`
- Works even when admin panel throws 500 errors
- Clears views, cache, bootstrap, OPcache
- No terminal access required

### System Update Improvements
- Comprehensive cache clearing with 8 fallback methods
- Multiple fallback methods for all server types
- Manual file deletion for shared hosting
- OPcache and APCu support
- Better error handling and logging

### PHP 8.4 Compatibility
- All helper functions updated with explicit nullable types
- Null-safe operators in Blade templates
- Backward compatible with PHP 8.2

## Upgrade Instructions

1. Backup your database and files
2. Upload the update package
3. Run the update from Admin > System > Update
4. Go to **Admin > Settings > Automation** and select your automation mode:
   - **cPanel/Shared hosting**: Select "Cron URL Only"
   - **VPS with scheduler**: Select "Laravel Scheduler"
   - **Enterprise with supervisor**: Select "Supervisor Workers"
   - **Unsure**: Keep "Auto Detect" (default)

## Notes

- No database migrations required for this update
- The SettingsSeeder will add the new `automation_mode` setting automatically
- Emergency cache clear URL available if you encounter 500 errors after update
