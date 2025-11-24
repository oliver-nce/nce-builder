# NCE Klaviyo Integration

WordPress plugin that uploads customer data from database to Klaviyo's Data Source API in batches.

## Features
- **Synchronous execution** - Completes in 2-3 minutes for 17k records
- **Batch processing** - 450 records per batch (configurable)
- **Automatic rate limiting** - Handles HTTP 429 with exponential backoff
- **Real-time logging** - Progress tracking in database and log files

## Usage

### Trigger Upload
```bash
curl -X POST https://your-site.com/wp-json/nce/v1/run \
  -H "Content-Type: application/json" \
  -d '{"task": 3}'
```

### Response
```json
{
  "ok": true,
  "task": 3,
  "status": "completed",
  "duration_seconds": 125.4,
  "result": {
    "success": true,
    "message": "Job completed - see last_result field for details"
  }
}
```

## Tools
- PHPCS + WPCS
- PHPStan
- PHPUnit

## Development
```bash
composer install
composer run lint | fix | stan | test
```

## Documentation
See `PROJECT_HANDOFF.md` for detailed architecture and operations guide.
