# Queue Setup Guide for Background Jobs

## Overview

Currently, Excel exports and ERP web scraping run **synchronously**, blocking the user request. Moving these to queues will:
- Improve user experience (no waiting for large exports)
- Free up server resources during request handling
- Enable retry logic and job monitoring
- Allow scheduling heavy operations during off-peak hours

## Current Synchronous Operations

### 1. Excel Export (`AgenticChatbotController@exportExcel`)
- **Current behavior**: User waits 10s-60s+ for large datasets
- **Impact**: Ties up PHP worker, blocks other requests
- **Data size**: Can exceed 10,000+ rows

### 2. ERP Web Scraping (`ERPService@fetchErpGuidanceFromWeb`)
- **Current behavior**: Scrapes external website during chatbot request
- **Impact**: 5-30s delay per request, external API dependency
- **Frequency**: Called when user asks for ERP guidance

### 3. User Import (`AdminController@usersImport`)
- **Current behavior**: Validates and imports users synchronously
- **Impact**: Slow for large CSV files (1000+ rows)

## Queue Architecture

### Job Classes to Create

```
app/Jobs/
├── ExportChatDataToExcel.php          # Async export for chat tables
├── ExportUsersToExcel.php             # Async export for user management
├── FetchErpGuidanceFromWeb.php        # Async ERP web scraping
├── ImportUsersFromCsv.php             # Async user import
└── NotifyExportComplete.php           # Notification when export finishes
```

### Recommended Queue Flow

#### For Excel Exports:
1. User clicks "Export to Excel"
2. Backend creates job and returns job ID immediately
3. Frontend polls `/api/export-status/{jobId}` every 2s
4. When job completes, frontend shows download link
5. User clicks link to download file from `storage/app/exports/`

#### For ERP Scraping:
1. Background job runs on schedule (e.g., every 6 hours)
2. Scraped data cached in database/Redis
3. Chatbot uses cached data instead of live scraping

## Implementation Example

### 1. Create Export Job

```php
// app/Jobs/ExportChatDataToExcel.php
namespace App\Jobs;

use App\Exports\ChatTableExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExportChatDataToExcel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 3;

    public function __construct(
        public array $headers,
        public array $rows,
        public string $filename,
        public int $userId,
        public ?array $chartInfo = null
    ) {}

    public function handle()
    {
        $export = new ChatTableExport(
            $this->headers,
            $this->rows,
            $this->chartInfo ? 'Chart Data' : 'Data Export',
            $this->chartInfo
        );

        // Store file instead of downloading
        $path = "exports/{$this->filename}";
        Excel::store($export, $path, 'local');

        // Update export status in database
        \DB::table('exports')->where('job_id', $this->job->uuid())->update([
            'status' => 'completed',
            'file_path' => $path,
            'completed_at' => now(),
        ]);

        // Notify user (via SSE, WebSocket, or email)
        ExportNotificationService::notify($this->userId, $this->filename, $path);
    }

    public function failed(\Throwable $exception)
    {
        \Log::error("Export job failed: {$exception->getMessage()}");
        
        \DB::table('exports')->where('job_id', $this->job->uuid())->update([
            'status' => 'failed',
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### 2. Create Exports Tracking Table

```php
// database/migrations/YYYY_MM_DD_create_exports_table.php
Schema::create('exports', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained();
    $table->string('job_id')->unique();
    $table->string('filename');
    $table->string('file_path')->nullable();
    $table->enum('status', ['pending', 'processing', 'completed', 'failed']);
    $table->text('error')->nullable();
    $table->timestamps();
    $table->timestamp('completed_at')->nullable();
    
    $table->index(['user_id', 'status']);
});
```

### 3. Update Controller to Dispatch Job

```php
// In AgenticChatbotController@exportExcel
public function exportExcel(Request $request)
{
    $request->validate([
        'headers' => 'required|array',
        'rows' => 'required|array',
        'filename' => 'nullable|string|max:255',
    ]);

    $rowsCount = count($request->input('rows'));
    $filename = $request->input('filename', 'export-' . date('Y-m-d_His') . '.xlsx');

    // For small exports, keep synchronous (fast response)
    if ($rowsCount < 500) {
        $export = new ChatTableExport(/* ... */);
        return Excel::download($export, $filename);
    }

    // For large exports, queue it
    $exportRecord = \DB::table('exports')->insertGetId([
        'id' => \Illuminate\Support\Str::uuid(),
        'user_id' => Auth::id(),
        'job_id' => \Illuminate\Support\Str::uuid(),
        'filename' => $filename,
        'status' => 'pending',
        'created_at' => now(),
    ]);

    ExportChatDataToExcel::dispatch(
        $request->input('headers'),
        $request->input('rows'),
        $filename,
        Auth::id()
    )->onQueue('exports');

    return response()->json([
        'queued' => true,
        'export_id' => $exportRecord['id'],
        'message' => 'Export sedang diproses. Anda akan diberi tahu saat selesai.',
    ]);
}
```

### 4. Add Status Endpoint

```php
public function getExportStatus($exportId)
{
    $export = \DB::table('exports')
        ->where('id', $exportId)
        ->where('user_id', Auth::id())
        ->first();

    if (!$export) {
        return response()->json(['error' => 'Export not found'], 404);
    }

    return response()->json([
        'status' => $export->status,
        'filename' => $export->filename,
        'download_url' => $export->status === 'completed' 
            ? route('export.download', $export->id) 
            : null,
        'error' => $export->error,
    ]);
}
```

## Queue Configuration

### 1. Update `.env`

```env
# Switch to Redis queue for better performance
QUEUE_CONNECTION=redis

# Redis settings (already configured)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 2. Run Queue Worker

```bash
# Development
php artisan queue:work --tries=3

# Production (supervised)
php artisan queue:work --sleep=3 --tries=3 --max-time=3600

# Multiple workers for different priorities
php artisan queue:work --queue=exports,erp-scraping,default --sleep=3
```

### 3. Setup Supervisor (Production)

Create `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasuser=false
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

## ERP Scraping Schedule

Instead of scraping on-demand, run it on a schedule:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Refresh ERP guidance every 6 hours
    $schedule->job(new \App\Jobs\FetchErpGuidanceFromWeb)
             ->everySixHours()
             ->onQueue('erp-scraping');
}
```

Then enable scheduler in cron:
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Monitoring

### 1. Horizon (Redis Queue Dashboard)

```bash
composer require laravel/horizon
php artisan horizon:install
```

Access at: `http://yourapp/horizon`

Features:
- Real-time job monitoring
- Failed job retries
- Queue throughput metrics
- Worker management

### 2. Failed Job Handling

```bash
# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

## Performance Benefits

| Operation | Before (Sync) | After (Async) |
|-----------|---------------|---------------|
| Large Export (10k rows) | 30-60s blocking | Immediate response, download when ready |
| ERP Scraping | 5-30s per request | 0s (uses cached data) |
| User Import (1000 rows) | 10-20s blocking | Background processing |
| Server Load | High during peak | Distributed over time |
| User Experience | Poor (waiting) | Excellent (instant feedback) |

## Migration Path

### Phase 1: Infrastructure Setup
1. Install and configure Redis queue ✅ (already done in Priority #3)
2. Create `exports` tracking table
3. Set up queue workers
4. Install Horizon for monitoring

### Phase 2: Implement Jobs
1. Create `ExportChatDataToExcel` job
2. Create `FetchErpGuidanceFromWeb` job
3. Create `ImportUsersFromCsv` job
4. Add status tracking endpoints

### Phase 3: Frontend Integration
1. Add polling/notification for export status
2. Show progress bar during processing
3. Add download button when complete
4. Add notification system (SSE/WebSocket)

### Phase 4: Scheduled Tasks
1. Convert ERP scraping to scheduled job
2. Cache results for instant access
3. Monitor and optimize job throughput

## Rollback Plan

If queue system fails:
1. Jobs automatically retry (configured `$tries = 3`)
2. Failed jobs logged to `failed_jobs` table
3. Can temporarily revert to synchronous exports
4. Horizon dashboard alerts on failures

## Next Steps

1. **Immediate**: Run queue worker: `php artisan queue:work`
2. **Short-term**: Implement `ExportChatDataToExcel` job
3. **Medium-term**: Set up Horizon dashboard
4. **Long-term**: Full async architecture with notifications
