# Support Request: Data Source Records Not Appearing After Successful API Submissions

**Date:** November 1, 2025  
**Data Source ID:** `01K8Y5K8M7E0Q689TCVTMFR6TB`  
**Data Source Name:** `default_ds_20251031_221818`  
**Issue:** Successfully uploading records via API (HTTP 204 responses) but records not appearing in Klaviyo UI

---

## Summary

We have implemented a WordPress-based integration that successfully submits data source records to Klaviyo via the Data Source Record Bulk Create Jobs API endpoint. Our system has successfully uploaded over 16,000 records across 180+ batches, all returning HTTP 204 (success) responses. However, the records are not appearing in the Klaviyo data source when viewed in the UI.

---

## Script Architecture

### 1. **Overview**
- **Platform:** WordPress plugin with WP-Cron background processing
- **Language:** PHP 8.x
- **Database:** MySQL (WordPress)
- **Execution:** Asynchronous batch processing via REST API trigger

### 2. **Workflow**

```
┌─────────────────┐
│  External API   │ (Zoho/Deluge)
│  Trigger Call   │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────┐
│  WordPress REST API Endpoint    │
│  /wp-json/nce/v1/run            │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│  WP-Cron Job Queued             │
│  (nce_runner_do_work)           │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│  klaviyo_write_objects()        │
│  - Read config from DB          │
│  - Query 90 records at a time   │
│  - Submit to Klaviyo API        │
│  - Wait 3 seconds between calls │
│  - Repeat until batch_limit     │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│  Klaviyo API                    │
│  POST /api/data-source-record-  │
│       bulk-create-jobs          │
│  Returns: HTTP 204 (No Content) │
└─────────────────────────────────┘
```

### 3. **Configuration**
- **Batch Size:** 90 records per API call
- **Delay Between Batches:** 3 seconds (increases to 4s if rate limited)
- **Batch Limit:** Configurable (current: 180 batches)
- **Total Records Uploaded:** 16,200 records
- **Execution Time:** ~12 minutes for 180 batches
- **API Version:** `2025-10-15`

### 4. **Error Handling**
- Retry logic with exponential backoff for HTTP 429 (rate limiting)
- Automatic delay increase when rate limited
- Progress checkpointing every 50 batches
- Comprehensive logging to temp_log.log

---

## API Request Details

### Endpoint
```
POST https://a.klaviyo.com/api/data-source-record-bulk-create-jobs
```

### Headers
```
Authorization: Klaviyo-API-Key pk_************************************
Content-Type: application/json
Accept: application/json
revision: 2025-10-15
```

### Payload Structure

```json
{
  "data": {
    "type": "data-source-record-bulk-create-job",
    "attributes": {
      "data-source-records": {
        "data": [
          {
            "type": "data-source-record",
            "attributes": {
              "record": {
                "p_key": "SKU123|||wp_id_456",
                "order_year": "2024",
                "order_month": "10",
                "event_year": "2024",
                "event_month": "11",
                "SKU": "PRODUCT-ABC",
                "family_email": "family@example.com",
                "price": "99.99",
                "person_type": "Player",
                "first_name": "John",
                "last_name": "Doe",
                "YOB": "2010",
                "rating": "5",
                "gender": "M",
                "preferred_position": "Forward"
              }
            }
          }
          // ... 89 more records (90 total per batch)
        ]
      }
    },
    "relationships": {
      "data-source": {
        "data": {
          "type": "data-source",
          "id": "01K8Y5K8M7E0Q689TCVTMFR6TB"
        }
      }
    }
  }
}
```

### Sample Record Data
Each record contains:
- `p_key`: Composite primary key (SKU|||wp_id)
- `order_year`, `order_month`: Order date components
- `event_year`, `event_month`: Event date components
- `SKU`: Product identifier
- `family_email`: Customer email
- `price`: Transaction amount
- `person_type`: Customer type
- `first_name`, `last_name`: Customer name
- `YOB`: Year of birth
- `rating`: Product rating
- `gender`: M/F
- `preferred_position`: Custom field

---

## API Response

### Typical Response
```
HTTP/1.1 204 No Content
```

**Body:** (empty)

### Our Observation
- All 180+ batches returned HTTP 204
- No error messages received
- No rate limiting issues (one batch hit 429, successfully retried)
- No HTTP 400/500 errors

---

## Log Evidence

### Sample Log Output

```
[2025-11-01 18:01:02] JOB STARTED

[18:01:02] First batch payload sample:
  Data Source ID: 01K8Y5K8M7E0Q689TCVTMFR6TB
  Endpoint: https://a.klaviyo.com/api/data-source-record-bulk-create-jobs
  Records in batch: 90
  Sample record keys: p_key, order_year, order_month, event_year, event_month, 
                      SKU, family_email, price, person_type, first_name, 
                      last_name, YOB, rating, gender, preferred_position

[18:01:02] Batch 001/180 | Records: 90 | HTTP: 204 | Status: SUCCESS | Uploaded: 90 | Memory: 193.6MB | Klaviyo: Job queued successfully

[18:01:02] API Response Details for Batch 1:
  HTTP Code: 204
  Raw Body: (empty)

[18:01:05] Batch 002/180 | Records: 90 | HTTP: 204 | Status: SUCCESS | Uploaded: 180 | Memory: 193.6MB | Klaviyo: Job queued successfully

[18:01:08] Batch 003/180 | Records: 90 | HTTP: 204 | Status: SUCCESS | Uploaded: 270 | Memory: 193.6MB | Klaviyo: Job queued successfully

...

[17:00:21] Batch 026/180 | Records: 90 | HTTP: 204 | Status: SUCCESS | Uploaded: 2340 | Memory: 196.3MB | Klaviyo: Job queued successfully
[17:00:21] RATE LIMITED - Delay increased to 4s

[17:00:25] Batch 027/180 | Records: 90 | HTTP: 204 | Status: SUCCESS | Uploaded: 2430 | Memory: 196.3MB | Klaviyo: Job queued successfully

...

[18:11:18] Batch 180/180 | Records: 90 | HTTP: 204 | Status: SUCCESS | Uploaded: 16200 | Memory: 196.4MB | Klaviyo: Job queued successfully

[18:11:18] JOB COMPLETED SUCCESSFULLY - Total: 180 batches, 16200 records
```

---

## Questions for Klaviyo Support

1. **Is HTTP 204 the correct success response for this endpoint?**
   - We're receiving 204 for all submissions but no response body

2. **Are there any background processing delays?**
   - Should we expect a delay before records appear in the UI?
   - How long should async processing typically take?

3. **How can we verify job status?**
   - Since 204 returns no job ID, how do we check if jobs are processing?
   - Is there an API endpoint to query data source record counts?

4. **Is there a rate limit we should be aware of?**
   - We're submitting 90 records per batch, 3-second delays
   - One 429 response occurred at batch 26 (2,340 records)
   - Should we reduce batch size or increase delays?

5. **Are we using the correct API endpoint?**
   - Endpoint: `/api/data-source-record-bulk-create-jobs`
   - Is this the recommended endpoint for bulk data source uploads?

6. **Is our payload structure correct?**
   - We're wrapping records in a job structure per API docs
   - Data source relationship is at the job level
   - Should records have any additional metadata?

7. **How can we debug this issue?**
   - Are there any diagnostic endpoints?
   - Can you see our API calls in your system logs?
   - Data Source ID: `01K8Y5K8M7E0Q689TCVTMFR6TB`
   - Approximate submission time: November 1, 2025, 18:00-18:15 UTC

---

## Additional Information

### Account Details
- **Data Source ID:** `01K8Y5K8M7E0Q689TCVTMFR6TB`
- **Data Source Name:** `default_ds_20251031_221818`
- **API Key:** pk_**** (using private key)
- **API Revision:** 2025-10-15

### Technical Environment
- **Server:** WP Engine hosted WordPress
- **PHP Version:** 8.x
- **HTTP Client:** WordPress wp_remote_request()
- **Execution Time Limit:** 30 minutes
- **Memory Limit:** 512MB

### What We've Verified
✅ API key is valid (no 401/403 errors)  
✅ Data source ID exists (no 404 errors)  
✅ Payload structure matches documentation  
✅ All API calls return success (HTTP 204)  
✅ No timeout or connection errors  
✅ Rate limiting is handled correctly  

### What's Not Working
❌ Records do not appear in Klaviyo data source UI  
❌ No way to verify if jobs are actually processing  
❌ No job ID returned to check status  

---

## Request

Please help us understand why our successfully submitted records (16,200+) are not appearing in the data source. We're happy to provide additional logs, payload samples, or any other diagnostic information needed.

Thank you for your assistance!

