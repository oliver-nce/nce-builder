# Task 2 - Reserved

This folder is reserved for future task implementation.

## To activate this task:

1. Create your task file (e.g., `my_task.php`)
2. Define your task function (e.g., `function my_task_name(array $params): array { ... }`)
3. Update `/src/nce-runner_task_manager.php` registry:
   - Change `'file' => null` to `'file' => 'my_task.php'`
   - Change `'function' => null` to `'function' => 'my_task_name'`
   - Update `'description'` to describe your task

## Usage:

```bash
curl -X POST https://your-site.com/wp-json/nce/v1/run?task=2
```

Your function will receive all URL parameters in the `$params` array.

