# RAG Chunk B — Required Headers

```php
function klaviyo_headers(string $apiKey): array {
    return [
        "Authorization: Klaviyo-API-Key {$apiKey}",
        "Content-Type: application/vnd.api+json",
        "Accept: application/vnd.api+json",
        "revision: 2025-04-15"
    ];
}
```
