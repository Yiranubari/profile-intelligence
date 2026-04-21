<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../config/app.php';

use App\Database\Database;
use App\Helpers\UuidHelper;

$jsonFilePath = __DIR__ . '/seed_profiles.json';

if (!file_exists($jsonFilePath)) {
    die("Error: Seed file not found at {$jsonFilePath}\n");
}

$jsonData = file_get_contents($jsonFilePath);
$data = json_decode($jsonData, true);
$profiles = $data['profiles'] ?? [];

if (!is_array($profiles)) {
    die("Error: Failed to decode JSON seed file.\n");
}

try {
    $pdo = Database::getInstance()->getConnection();
} catch (\Exception $e) {
    die("Error connecting to database: " . $e->getMessage() . "\n");
}

$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM profiles WHERE LOWER(name) = LOWER(:name)");

$insertStmt = $pdo->prepare("
    INSERT INTO profiles (
        id, name, gender, gender_probability, sample_size, 
        age, age_group, country_id, country_probability, country_name, created_at
    ) VALUES (
        :id, :name, :gender, :gender_probability, :sample_size, 
        :age, :age_group, :country_id, :country_probability, :country_name, :created_at
    )
");

$total = count($profiles);
$inserted = 0;
$skipped = 0;

echo "Starting database seed for {$total} profiles...\n";

foreach ($profiles as $profile) {
    if (!isset($profile['name'])) {
        echo "Skipping invalid profile (missing name).\n";
        continue;
    }

    $name = is_string($profile['name']) ? trim($profile['name']) : '';

    $checkStmt->execute([':name' => $name]);
    $count = (int) $checkStmt->fetchColumn();

    if ($count > 0) {
        // Skip it
        $skipped++;
        continue;
    }

    $id = UuidHelper::generate();
    $createdAt = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

    // Fallback missing data if necessary
    $sampleSize = isset($profile['sample_size']) ? $profile['sample_size'] : 0;

    // Assuming JSON has standard fields matching our database columns based on standard APIs 
    // gender, gender_probability, age, age_group, country_id, country_probability, and now country_name.
    $insertStmt->execute([
        ':id' => $id,
        ':name' => $name,
        ':gender' => $profile['gender'] ?? '',
        ':gender_probability' => $profile['gender_probability'] ?? 0.0,
        ':sample_size' => $sampleSize,
        ':age' => $profile['age'] ?? 0,
        ':age_group' => $profile['age_group'] ?? '',
        ':country_id' => $profile['country_id'] ?? '',
        ':country_name' => $profile['country_name'] ?? ($profile['country_id'] ?? ''),
        ':country_probability' => $profile['country_probability'] ?? 0.0,
        ':created_at' => $createdAt
    ]);

    $inserted++;
}

echo "Database seed complete.\n";
echo "Total processed: {$total}\n";
echo "Inserted: {$inserted}\n";
echo "Skipped: {$skipped}\n";
