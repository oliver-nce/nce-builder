# RAG Chunk D — Marketing Opt-Out

Endpoint:
POST /api/profile-subscription-bulk-delete-jobs

```php
function bulk_unsubscribe_profiles(string $apiKey, array $profiles): array {
    $payload = [
        "data" => [
            "type" => "profile-subscription-bulk-delete-job",
            "attributes" => [
                "profiles" => ["data" => $profiles]
            ]
        ]
    ];
    $ch = curl_init("https://a.klaviyo.com/api/profile-subscription-bulk-delete-jobs");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => klaviyo_headers($apiKey),
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true
    ]);
    $out = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ["status"=>$code, "body"=>$out];
}
```
