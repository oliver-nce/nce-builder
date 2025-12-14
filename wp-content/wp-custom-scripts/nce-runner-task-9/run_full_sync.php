<?php
// LAST UPDATED: 2025-12-11
// v1.2.0 - Added 5th task (enrollment), default lookback now 14 hours
declare(strict_types=1);
  
/**
 * Run Full Sync - Task 9
 * ---
 * Orchestrates running multiple tasks in sequence:
 *   1. Task 3: Bulk upsert profiles from database
 *   2. Task 1: Upload data to Klaviyo (optimized) - family_members
 *   3. Task 4: Grant email consent for new profiles
 *   4. Task 5: Grant SMS consent for new profiles
 *   5. Task 1b: Upload data to Klaviyo (optimized) - enrollment
 * 
 * @param array $params Parameters from REST request:
 *                      - job_name (optional): defaults to 'default'
 *                      - profiles_job_name (optional): overrides Task 3 job (defaults to 'profiles')
 *                      - lookback_hours (optional): defaults to 14 hours (for tasks 3, 4, 5)
 *                      - skip_tasks (optional): comma-separated task numbers to skip (e.g., "1,5")
 * @return array Summary with results from all tasks
 */
if (!function_exists('nce_task_run_full_sync')) {
    function nce_task_run_full_sync(array $params = []): array {
        @ini_set('max_execution_time', '7200'); // 2 hours max
        @ini_set('memory_limit', '1024M');
        @set_time_limit(7200);
        
        $jobName = isset($params['job_name']) ? trim((string)$params['job_name']) : 'default';
        $lookbackHours = isset($params['lookback_hours']) ? (int)$params['lookback_hours'] : 14;
        
        // Parse skip_tasks parameter
        $skipTasks = [];
        if (!empty($params['skip_tasks'])) {
            $skipTasks = array_map('intval', explode(',', (string)$params['skip_tasks']));
        }
        
        error_log("nce_task_run_full_sync: Starting full sync (Job: {$jobName})");
        
        // Initialize temp log file
        $temp_log = ABSPATH . 'wp-content/wp-custom-scripts/temp_log.log';
        file_put_contents($temp_log, ""); // Clear the file
        file_put_contents($temp_log, "[" . date('Y-m-d H:i:s') . "] ========== FULL SYNC STARTED ==========\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Job: {$jobName}\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Profiles job: profiles\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Lookback hours: {$lookbackHours}\n", FILE_APPEND);
        if (!empty($skipTasks)) {
            file_put_contents($temp_log, "[" . date('H:i:s') . "] Skipping tasks: " . implode(', ', $skipTasks) . "\n", FILE_APPEND);
        }
        file_put_contents($temp_log, "\n", FILE_APPEND);
        
        $startTime = microtime(true);
        $results = [];
        $hasErrors = false;
        
        // Define task sequence
        // skip_log_clear prevents tasks from clearing temp_log when run from full sync
        $taskSequence = [
            3 => [
                'name' => 'Bulk Upsert Profiles',
                'file' => 'nce-runner-task-3/bulk_upsert_profiles.php',
                'function' => 'nce_task_upsert_klaviyo_profiles',
                'params' => ['job_name' => 'profiles', 'lookback_hours' => $lookbackHours, 'skip_log_clear' => true]
            ],
            1 => [
                'name' => 'Upload Data to Klaviyo',
                'file' => 'nce-runner-task-1/klaviyo_write_objects_optimized.php',
                'function' => 'klaviyo_write_objects_optimized',
                'params' => ['job_name' => 'family_members', 'skip_log_clear' => true]
            ],
            4 => [
                'name' => 'Grant Email Consent',
                'file' => 'nce-runner-task-4/grant_email_consent.php',
                'function' => 'nce_task_grant_email_consent',
                'params' => ['job_name' => $jobName, 'lookback_hours' => 168, 'skip_log_clear' => true] // 1 week lookback
            ],
            5 => [
                'name' => 'Grant SMS Consent',
                'file' => 'nce-runner-task-5/grant_sms_consent.php',
                'function' => 'nce_task_grant_sms_consent',
                'params' => ['job_name' => $jobName, 'lookback_hours' => $lookbackHours, 'skip_log_clear' => true]
            ],
            '1b' => [
                'name' => 'Upload Enrollment Data to Klaviyo',
                'file' => 'nce-runner-task-1/klaviyo_write_objects_optimized.php',
                'function' => 'klaviyo_write_objects_optimized',
                'params' => ['job_name' => 'enrollment', 'skip_log_clear' => true]
            ]
        ];
        
        $basePath = ABSPATH . 'wp-content/wp-custom-scripts/';
        
        foreach ($taskSequence as $taskNum => $taskConfig) {
            // Check if task should be skipped
            if (in_array($taskNum, $skipTasks)) {
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ⏭️  SKIPPING Task {$taskNum}: {$taskConfig['name']}\n", FILE_APPEND);
                $results["task_{$taskNum}"] = [
                    'skipped' => true,
                    'name' => $taskConfig['name']
                ];
                continue;
            }
            
            file_put_contents($temp_log, "[" . date('H:i:s') . "] ▶️  STARTING Task {$taskNum}: {$taskConfig['name']}\n", FILE_APPEND);
            error_log("nce_task_run_full_sync: Starting Task {$taskNum} - {$taskConfig['name']}");
            
            $taskFile = $basePath . $taskConfig['file'];
            
            // Check if file exists
            if (!file_exists($taskFile)) {
                $errorMsg = "Task file not found: {$taskFile}";
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ❌ ERROR: {$errorMsg}\n", FILE_APPEND);
                $results["task_{$taskNum}"] = [
                    'error' => $errorMsg,
                    'name' => $taskConfig['name']
                ];
                $hasErrors = true;
                continue;
            }
            
            // Load task file
            require_once $taskFile;
            
            // Check if function exists
            if (!function_exists($taskConfig['function'])) {
                $errorMsg = "Function not found: {$taskConfig['function']}";
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ❌ ERROR: {$errorMsg}\n", FILE_APPEND);
                $results["task_{$taskNum}"] = [
                    'error' => $errorMsg,
                    'name' => $taskConfig['name']
                ];
                $hasErrors = true;
                continue;
            }
            
            // Execute task
            $taskStart = microtime(true);
            try {
                $taskResult = call_user_func($taskConfig['function'], $taskConfig['params']);
                $taskDuration = round(microtime(true) - $taskStart, 2);
                
                // Check for errors in result
                if (isset($taskResult['error'])) {
                    file_put_contents($temp_log, "[" . date('H:i:s') . "] ❌ Task {$taskNum} FAILED: {$taskResult['error']}\n", FILE_APPEND);
                    $hasErrors = true;
                } else {
                    file_put_contents($temp_log, "[" . date('H:i:s') . "] ✅ Task {$taskNum} completed in {$taskDuration}s\n", FILE_APPEND);
                }
                
                // Store result
                $taskResult['name'] = $taskConfig['name'];
                $taskResult['duration_seconds'] = $taskDuration;
                $results["task_{$taskNum}"] = $taskResult;
                
            } catch (Throwable $e) {
                $taskDuration = round(microtime(true) - $taskStart, 2);
                $errorMsg = $e->getMessage();
                file_put_contents($temp_log, "[" . date('H:i:s') . "] ❌ Task {$taskNum} EXCEPTION: {$errorMsg}\n", FILE_APPEND);
                
                $results["task_{$taskNum}"] = [
                    'error' => $errorMsg,
                    'name' => $taskConfig['name'],
                    'exception' => get_class($e),
                    'duration_seconds' => $taskDuration
                ];
                $hasErrors = true;
            }
            
            file_put_contents($temp_log, "\n", FILE_APPEND);
            
            // Brief pause between tasks
            usleep(500000); // 0.5 seconds
        }
        
        // Summary
        $endTime = microtime(true);
        $totalDuration = round($endTime - $startTime, 2);
        
        file_put_contents($temp_log, "[" . date('H:i:s') . "] ========== FULL SYNC COMPLETE ==========\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Total duration: {$totalDuration}s\n", FILE_APPEND);
        file_put_contents($temp_log, "[" . date('H:i:s') . "] Status: " . ($hasErrors ? "COMPLETED WITH ERRORS" : "SUCCESS") . "\n", FILE_APPEND);
        
        error_log("nce_task_run_full_sync: Complete - Duration: {$totalDuration}s, Errors: " . ($hasErrors ? "YES" : "NO"));
        
        return [
            'success' => !$hasErrors,
            'message' => $hasErrors ? 'Full sync completed with errors' : 'Full sync completed successfully',
            'job_name' => $jobName,
            'lookback_hours' => $lookbackHours,
            'total_duration_seconds' => $totalDuration,
            'tasks' => $results
        ];
    }
}

