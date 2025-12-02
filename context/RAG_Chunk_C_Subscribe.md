# RAG Chunk C — Grant / Restore Marketing Consent

Endpoint:
POST /api/profile-subscription-bulk-create-jobs

```php
function bulk_subscribe_profiles(string $apiKey, array $profiles): array {
    $payload = [
        "data" => [
            "type" => "profile-subscription-bulk-create-job",
            "attributes" => [
                "profiles" => ["data" => $profiles],
                "historical_import" => true
            ]
        ]
    ];
    $ch = curl_init("https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs");
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
