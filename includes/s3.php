<?php
/**
 * AwsS3Client - Cliente ligero para Amazon Simple Storage Service (S3)
 * Implementa autenticación completa AWS Signature Version 4 (SigV4)
 * sin requerir dependencias externas de Composer.
 */

class AwsS3Client {
    private string $accessKeyId;
    private string $secretAccessKey;
    private string $region;
    private string $bucket;
    private int $defaultExpiry;

    public function __construct(
        string $accessKeyId = '',
        string $secretAccessKey = '',
        string $region = 'us-east-1',
        string $bucket = '',
        int $defaultExpiry = 3600
    ) {
        $this->accessKeyId = trim($accessKeyId);
        $this->secretAccessKey = trim($secretAccessKey);
        $this->region = trim($region) ?: 'us-east-1';
        $this->bucket = trim($bucket);
        $this->defaultExpiry = $defaultExpiry > 0 ? $defaultExpiry : 3600;
    }

    /**
     * Verifica si el cliente tiene configuradas las credenciales de AWS S3
     */
    public function isConfigured(): bool {
        return !empty($this->accessKeyId) && 
               !empty($this->secretAccessKey) && 
               !empty($this->bucket);
    }

    public function getBucket(): string {
        return $this->bucket;
    }

    public function getRegion(): string {
        return $this->region;
    }

    /**
     * Obtiene el endpoint de host para el bucket
     */
    private function getHost(): string {
        if ($this->region === 'us-east-1') {
            return "{$this->bucket}.s3.amazonaws.com";
        }
        return "{$this->bucket}.s3.{$this->region}.amazonaws.com";
    }

    /**
     * Sube un archivo a Amazon S3 usando PUT directo con firma AWS SigV4
     */
    public function putObject(string $key, string $filePath, string $contentType = 'application/octet-stream'): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Credenciales de AWS S3 no configuradas.'];
        }

        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'El archivo local no existe.'];
        }

        $payload = file_get_contents($filePath);
        $contentHash = hash('sha256', $payload);
        $contentLength = strlen($payload);

        $key = ltrim($key, '/');
        $host = $this->getHost();
        $service = 's3';
        $method = 'PUT';

        $now = time();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $dateStamp = gmdate('Ymd', $now);

        // Cabeceras canónicas
        $canonicalHeaders = "content-length:{$contentLength}\n" .
                            "content-type:{$contentType}\n" .
                            "host:{$host}\n" .
                            "x-amz-content-sha256:{$contentHash}\n" .
                            "x-amz-date:{$amzDate}\n";
        $signedHeaders = "content-length;content-type;host;x-amz-content-sha256;x-amz-date";

        $canonicalUri = '/' . $this->encodeKey($key);
        $canonicalQuery = '';

        $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\n{$contentHash}";

        // Cadena para firmar
        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        // Claves derivadas
        $signingKey = $this->getSigningKey($this->secretAccessKey, $dateStamp, $this->region, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKeyId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $url = "https://{$host}/{$this->encodeKey($key)}";

        $headers = [
            "Host: {$host}",
            "x-amz-date: {$amzDate}",
            "x-amz-content-sha256: {$contentHash}",
            "Content-Type: {$contentType}",
            "Content-Length: {$contentLength}",
            "Authorization: {$authorization}"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => "cURL Error: {$curlError}"];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'key' => $key,
                'http_code' => $httpCode,
                'url' => $url
            ];
        }

        return [
            'success' => false,
            'http_code' => $httpCode,
            'error' => "AWS S3 respondió con código {$httpCode}: " . strip_tags($response)
        ];
    }

    /**
     * Genera una URL prefirmada (Presigned URL) de Amazon S3 con tiempo de expiración
     * Esto asegura que los archivos no estén expuestos públicamente de forma permanente.
     */
    public function getPresignedUrl(string $key, ?int $expiresInSeconds = null): string {
        if (!$this->isConfigured()) {
            return '';
        }

        $key = ltrim($key, '/');
        $expires = $expiresInSeconds ?? $this->defaultExpiry;
        $host = $this->getHost();
        $service = 's3';
        $method = 'GET';

        $now = time();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $dateStamp = gmdate('Ymd', $now);

        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $credential = "{$this->accessKeyId}/{$credentialScope}";

        // Parámetros de consulta en orden canónico
        $queryParams = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $credential,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string)$expires,
            'X-Amz-SignedHeaders' => 'host'
        ];
        ksort($queryParams);

        $canonicalQueryParts = [];
        foreach ($queryParams as $k => $v) {
            $canonicalQueryParts[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $canonicalQuery = implode('&', $canonicalQueryParts);

        $canonicalHeaders = "host:{$host}\n";
        $signedHeaders = "host";
        $payloadHash = 'UNSIGNED-PAYLOAD';

        $canonicalUri = '/' . $this->encodeKey($key);
        $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        $signingKey = $this->getSigningKey($this->secretAccessKey, $dateStamp, $this->region, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $presignedUrl = "https://{$host}{$canonicalUri}?{$canonicalQuery}&X-Amz-Signature={$signature}";

        return $presignedUrl;
    }

    /**
     * Elimina un archivo de Amazon S3
     */
    public function deleteObject(string $key): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'AWS S3 no configurado'];
        }

        $key = ltrim($key, '/');
        $host = $this->getHost();
        $service = 's3';
        $method = 'DELETE';

        $now = time();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $dateStamp = gmdate('Ymd', $now);
        $contentHash = hash('sha256', '');

        $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$contentHash}\nx-amz-date:{$amzDate}\n";
        $signedHeaders = "host;x-amz-content-sha256;x-amz-date";
        $canonicalUri = '/' . $this->encodeKey($key);
        $canonicalQuery = '';

        $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\n{$contentHash}";
        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $signingKey = $this->getSigningKey($this->secretAccessKey, $dateStamp, $this->region, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKeyId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $url = "https://{$host}{$canonicalUri}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: {$host}",
            "x-amz-date: {$amzDate}",
            "x-amz-content-sha256: {$contentHash}",
            "Authorization: {$authorization}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['success' => ($httpCode === 204 || $httpCode === 200), 'http_code' => $httpCode];
    }

    /**
     * Realiza un test de conexión al bucket S3
     */
    public function testConnection(): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Faltan credenciales requeridas (Access Key, Secret Key o Bucket).'
            ];
        }

        // Realizamos una petición HEAD al bucket para verificar existencia y permisos
        $host = $this->getHost();
        $service = 's3';
        $method = 'HEAD';

        $now = time();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $dateStamp = gmdate('Ymd', $now);
        $contentHash = hash('sha256', '');

        $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$contentHash}\nx-amz-date:{$amzDate}\n";
        $signedHeaders = "host;x-amz-content-sha256;x-amz-date";
        $canonicalUri = '/';
        $canonicalQuery = '';

        $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\n{$contentHash}";
        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $signingKey = $this->getSigningKey($this->secretAccessKey, $dateStamp, $this->region, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKeyId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $url = "https://{$host}/";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: {$host}",
            "x-amz-date: {$amzDate}",
            "x-amz-content-sha256: {$contentHash}",
            "Authorization: {$authorization}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'message' => "Error de conexión de red/SSL: {$curlError}"];
        }

        if ($httpCode === 200) {
            return ['success' => true, 'message' => "Conexión exitosa con el bucket '{$this->bucket}' en la región '{$this->region}'."];
        } elseif ($httpCode === 403) {
            return ['success' => false, 'message' => "Acceso Denegado (403): Las credenciales no tienen permisos para el bucket '{$this->bucket}'."];
        } elseif ($httpCode === 404) {
            return ['success' => false, 'message' => "El bucket '{$this->bucket}' no existe en la región '{$this->region}'."];
        } else {
            return ['success' => false, 'message' => "Código de respuesta HTTP inesperado: {$httpCode}."];
        }
    }

    /**
     * Deriva la clave de firma SigV4
     */
    private function getSigningKey(string $key, string $dateStamp, string $regionName, string $serviceName): string {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    /**
     * Codificación URI para S3 según estándares RFC 3986
     */
    private function encodeKey(string $key): string {
        $segments = explode('/', $key);
        $encoded = array_map('rawurlencode', $segments);
        return implode('/', $encoded);
    }
}
