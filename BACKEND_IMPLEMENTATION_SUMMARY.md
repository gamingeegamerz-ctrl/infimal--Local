# InfiMal Backend Implementation Summary

## Overview
Complete end-to-end email marketing backend flow has been implemented following the strict order:
**List → Subscribers → Campaign → Queue → Throttle → SMTP → Tracking**

---

## ✅ Completed Implementation

### 1. LIST → SUBSCRIBERS
**Files Modified:**
- `app/Models/Subscriber.php` - Enhanced with tags, duplicate prevention, bounce/unsubscribe methods
- `database/migrations/2025_01_15_000001_add_tags_to_subscribers_table.php` - Added tags column (JSON)
- `database/migrations/2025_01_15_000006_add_unique_email_per_list_constraint.php` - Unique constraint per list

**Features:**
- ✅ Duplicate email prevention per list (unique constraint on `list_id` + `email`)
- ✅ Tags support (JSON column)
- ✅ Status management: `active`, `unsubscribed`, `bounced`
- ✅ Helper methods: `markAsBounced()`, `markAsUnsubscribed()`, `addTag()`, `removeTag()`
- ✅ `existsInList()` static method for duplicate checking

---

### 2. SUBSCRIBERS → CAMPAIGN
**Files Modified:**
- `app/Models/Campaign.php` - Added `html_content` and `plain_text` fields
- `database/migrations/2025_01_15_000002_add_html_content_to_campaigns_table.php` - Added content fields

**Features:**
- ✅ Campaign belongs to ONE list (existing relationship)
- ✅ Campaign fields: `subject`, `html_content`, `plain_text`, `content` (fallback)
- ✅ Status: `draft`, `scheduled`, `sending`, `sent`, `completed`
- ✅ Campaign does NOT send emails directly (only through queue)

---

### 3. CAMPAIGN → QUEUE
**Files Modified:**
- `app/Http/Controllers/CampaignController.php` - Updated `send()` method
- `app/Models/EmailJob.php` - Created complete EmailJob model
- `database/migrations/2025_01_15_000003_update_email_jobs_table.php` - Full EmailJob table structure

**Features:**
- ✅ When campaign is started:
  - Fetches ONLY active subscribers from the campaign's list
  - Creates individual `EmailJob` records for each subscriber
  - Pushes jobs into Laravel queue with `SendCampaignEmailJob`
- ✅ Each queued job = ONE email
- ✅ Campaign status remains 'sending' until jobs complete

---

### 4. QUEUE → THROTTLE
**Files Created:**
- `app/Services/ThrottleService.php` - Complete throttle implementation
- `app/Models/ThrottleSetting.php` - Throttle settings model
- `database/migrations/2025_01_15_000005_create_throttle_settings_table.php` - Throttle settings table

**Files Modified:**
- `app/Jobs/SendCampaignEmailJob.php` - Integrated throttle checking

**Features:**
- ✅ `emails_per_minute` limit (default: 60)
- ✅ `sending_time_window` (start_time, end_time) - default: 09:00-17:00
- ✅ Per-user or global throttle settings
- ✅ If limit reached: pause sending until next minute
- ✅ If outside time window: delay jobs until next window
- ✅ Cache-based minute-by-minute counting

---

### 5. THROTTLE → SMTP
**Files Modified:**
- `app/Models/SMTPAccount.php` - Enhanced with priority, daily limits, fallback logic
- `database/migrations/2025_01_15_000004_add_priority_and_daily_limit_to_smtps_table.php` - New SMTP fields

**Files Modified:**
- `app/Jobs/SendCampaignEmailJob.php` - Integrated SMTP selection and fallback

**Features:**
- ✅ Multiple SMTP configurations supported
- ✅ SMTP selection based on:
  - `priority` (higher = higher priority)
  - `reputation_score` (higher = better)
  - `daily_limit` (max emails per day)
  - `sent_today` count
- ✅ If SMTP fails:
  - Auto-retry with fallback SMTP (excludes failed SMTP)
  - Mark failed SMTP as temporarily disabled (60-120 minutes)
- ✅ Daily limit enforcement
- ✅ Hourly warmup limits still respected

**New Methods:**
- `pickForSending($userId)` - Selects best SMTP
- `pickFallback($excludeSmtpId, $userId)` - Selects alternative SMTP
- `temporarilyDisable($minutes)` - Disables SMTP temporarily
- `enable()` - Re-enables SMTP

---

### 6. SMTP → TRACKING
**Files Created:**
- `app/Http/Controllers/TrackingController.php` - Complete tracking implementation
- `routes/web.php` - Added tracking routes

**Features:**
- ✅ Open tracking via 1x1 transparent pixel
- ✅ Click tracking via URL redirect wrapping
- ✅ Track for each email:
  - `sent_at` (logged in CampaignAnalytics)
  - `opened_at` (logged on pixel load)
  - `clicked_at` (logged on link click)
  - `bounced_at` (logged on bounce)
- ✅ Automatic campaign stats updates
- ✅ Duplicate event prevention (tracks unique events only)

**Routes:**
- `GET /track/open?c={campaign_id}&s={subscriber_id}` - Open tracking pixel
- `GET /track/click?c={campaign_id}&s={subscriber_id}&url={encoded_url}` - Click redirect
- `GET /track/unsubscribe?c={campaign_id}&s={subscriber_id}` - Unsubscribe
- `POST /track/bounce` - Bounce webhook

**Methods:**
- `TrackingController::processEmailContent()` - Adds tracking pixel and wraps links in email HTML

---

### 7. FAILURES & SAFETY
**Files Modified:**
- `app/Jobs/SendCampaignEmailJob.php` - Complete retry and bounce handling

**Features:**
- ✅ Auto-retry failed emails (max 3 attempts)
- ✅ Exponential backoff on retries
- ✅ Fallback SMTP selection on failure
- ✅ If permanently failed (after 3 retries):
  - Mark subscriber as `bounced`
  - Log bounce event in CampaignAnalytics
  - Update campaign bounce statistics
- ✅ Never blocks queue workers (safe error handling)
- ✅ SMTP reputation reduction on failures
- ✅ Temporary SMTP disabling on repeated failures

---

## 📋 Database Migrations

Run these migrations to set up the complete system:

```bash
php artisan migrate
```

**New Migrations:**
1. `2025_01_15_000001_add_tags_to_subscribers_table.php`
2. `2025_01_15_000002_add_html_content_to_campaigns_table.php`
3. `2025_01_15_000003_update_email_jobs_table.php`
4. `2025_01_15_000004_add_priority_and_daily_limit_to_smtps_table.php`
5. `2025_01_15_000005_create_throttle_settings_table.php`
6. `2025_01_15_000006_add_unique_email_per_list_constraint.php`

---

## 🔄 Complete Flow Example

1. **User creates Campaign** → Status: `draft`
2. **User clicks "Send Campaign"** → `CampaignController::send()`
3. **Campaign fetches active subscribers** → Only `status = 'active'` from campaign's list
4. **Creates EmailJob records** → One per subscriber
5. **Dispatches SendCampaignEmailJob** → Jobs queued
6. **Queue worker picks up job** → `SendCampaignEmailJob::handle()`
7. **Throttle check** → Verifies rate limit and time window
8. **Trust check** → Verifies user can send (if TrustManager available)
9. **SMTP selection** → Picks best SMTP based on priority/reputation
10. **Email content processing** → Adds tracking pixel and wraps links
11. **Email sent** → Via Laravel Mail
12. **Success handling** → Updates stats, logs analytics, records throttle
13. **If failure** → Retries with fallback SMTP (max 3 times)
14. **If permanent failure** → Marks subscriber as bounced

---

## 📝 Key Models

### EmailJob
- Represents a single email to be sent
- Links: `user_id`, `campaign_id`, `subscriber_id`, `smtp_id`
- Status: `queued`, `processing`, `sent`, `failed`, `bounced`
- Tracks retry count and error messages

### CampaignAnalytics
- Tracks all email events: `sent`, `opened`, `clicked`, `bounced`, `unsubscribed`
- Automatic campaign stats calculation
- Unique event tracking (prevents duplicates)

### ThrottleSetting
- Global or per-user throttle configuration
- Controls: `emails_per_minute`, `sending_start_time`, `sending_end_time`

---

## 🚀 Queue Configuration

Ensure your queue worker is running:

```bash
php artisan queue:work --queue=emails
```

Or use supervisor/systemd for production.

---

## ⚙️ Configuration

### Default Throttle Settings
Create a global throttle setting:

```php
ThrottleSetting::create([
    'user_id' => null, // null = global
    'emails_per_minute' => 60,
    'sending_start_time' => '09:00:00',
    'sending_end_time' => '17:00:00',
]);
```

### SMTP Priority Setup
Set priorities when creating SMTP accounts (higher number = higher priority):

```php
SMTPAccount::create([
    'priority' => 10, // High priority
    'daily_limit' => 5000,
    // ... other fields
]);
```

---

## 🔍 Testing the Flow

1. Create a mailing list
2. Add active subscribers (status: 'active')
3. Create a campaign for that list
4. Click "Send Campaign"
5. Monitor queue: `php artisan queue:work`
6. Check email_logs table for sent emails
7. Check campaign_analytics for tracking events
8. Open email to test tracking pixel
9. Click link to test click tracking

---

## 📊 Campaign Statistics

Campaign stats are automatically updated when:
- Email is sent (via CampaignAnalytics::logEvent)
- Email is opened (via tracking pixel)
- Link is clicked (via click redirect)
- Bounce occurs (via bounce handler or permanent failure)
- Unsubscribe occurs (via unsubscribe link)

Use: `$campaign->updateStatistics()` to recalculate from analytics table.

---

## ⚠️ Important Notes

1. **UI Code Not Touched** - All changes are backend only
2. **Existing Functionality Preserved** - All existing features continue to work
3. **Graceful Degradation** - TrustManager checks are wrapped in try-catch (optional)
4. **Production Ready** - Error handling, logging, and safety measures included
5. **Scalable** - Designed to handle large volumes with throttling

---

## 🐛 Troubleshooting

### Jobs not processing?
- Check queue worker is running: `php artisan queue:work`
- Check queue connection in `.env` (database/redis/beanstalkd)

### Emails not sending?
- Verify SMTP accounts are active and within daily limits
- Check throttle settings (time window, rate limit)
- Verify subscribers are active status

### Tracking not working?
- Verify routes are registered: `php artisan route:list | grep track`
- Check email HTML contains tracking pixel and wrapped links
- Verify campaign_id and subscriber_id in tracking URLs

---

## 📚 Next Steps

1. Run migrations: `php artisan migrate`
2. Set up throttle settings (optional, defaults work)
3. Configure SMTP accounts with priorities
4. Start queue worker
5. Test with a small campaign first
6. Monitor logs and analytics

---

**Implementation Date:** 2025-01-15
**Status:** ✅ Complete and Production-Ready
