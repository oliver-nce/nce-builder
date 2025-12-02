# RAG Chunk F — Remove Manual Suppression

Endpoint:
POST /api/profile-suppression-bulk-delete-jobs

```php
function bulk_unsuppress_profiles(string $apiKey, array $emails): array {
    $profiles = array_map(fn($e)=>[
        "type"=>"profile","attributes"=>["email"=>$e]
    ],$emails);

    $payload=["data"=>[
        "type"=>"profile-suppression-bulk-delete-job",
        "attributes"=>["profiles"=>["data"=>$profiles]]
    ]];

    $ch=curl_init("https://a.klaviyo.com/api/profile-suppression-bulk-delete-jobs");
    curl_setopt_array($ch,[
        CURLOPT_HTTPHEADER=>klaviyo_headers($apiKey),
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($payload),
        CURLOPT_RETURNTRANSFER=>true
    ]);
    $out=curl_exec($ch);
    $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    return["status"=>$code,"body"=>$out];
}
```
