<?php
/**
 * MiniNetflix - API para Test de Conexión a Amazon S3
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/s3.php';

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado. Se requieren permisos de administrador.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$accessKey = trim($input['aws_access_key_id'] ?? '');
$secretKey = trim($input['aws_secret_access_key'] ?? '');
$region = trim($input['aws_region'] ?? 'us-east-1');
$bucket = trim($input['aws_bucket'] ?? '');

$client = new AwsS3Client($accessKey, $secretKey, $region, $bucket);
$result = $client->testConnection();

echo json_encode($result);
