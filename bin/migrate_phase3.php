<?php declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;

$db = Connection::getInstance();

echo "Running Phase 3 DB Migrations & Seeds...\n";

// 1. Add teaching_mode column if not present
try {
    $db->exec("ALTER TABLE tutor_profiles ADD COLUMN teaching_mode ENUM('ONLINE', 'IN_PERSON', 'BOTH') NOT NULL DEFAULT 'BOTH' AFTER approval_status");
    echo "[OK] Added teaching_mode column to tutor_profiles\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column') || str_contains($e->getMessage(), 'already exists')) {
        echo "[INFO] teaching_mode column already exists\n";
    } else {
        echo "[ERROR] " . $e->getMessage() . "\n";
    }
}

// 2. Ensure Curricula exist
$curricula = [
    [1, 'Primary (KS1 & KS2)', 'PRIMARY_KS1_KS2', 'Key Stage 1 and 2 curriculum for ages 5-11'],
    [2, '11+ & Entrance Exams', 'ENTRANCE_11_PLUS', 'Preparation for grammar and independent school exams'],
    [3, 'Secondary (KS3)', 'KS3', 'Key Stage 3 curriculum for ages 11-14'],
    [4, 'GCSE & IGCSE', 'GCSE', 'General Certificate of Secondary Education preparation'],
    [5, 'A-Level & AS', 'A_LEVEL', 'Advanced Level qualification preparation'],
    [6, 'International Baccalaureate (IB)', 'IB', 'IB Diploma and Middle Years Programme'],
];

$cStmt = $db->prepare("INSERT INTO curricula (id, name, code, description) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)");
foreach ($curricula as $c) {
    $cStmt->execute($c);
}
echo "[OK] Curricula verified\n";

// 3. Ensure standard subjects exist
$subjects = [
    // Primary
    [1, 'Primary Mathematics', 'primary-mathematics'],
    [1, 'Primary English (Reading & Writing)', 'primary-english'],
    [1, 'Primary Science', 'primary-science'],
    [1, 'Phonics & Early Reading', 'phonics-early-reading'],
    // 11+
    [2, '11+ Verbal Reasoning', '11-plus-verbal-reasoning'],
    [2, '11+ Non-Verbal Reasoning', '11-plus-non-verbal-reasoning'],
    [2, '11+ Mathematics', '11-plus-mathematics'],
    [2, '11+ English Comprehension', '11-plus-english-comprehension'],
    // KS3
    [3, 'KS3 Mathematics', 'ks3-mathematics'],
    [3, 'KS3 English', 'ks3-english'],
    [3, 'KS3 Science', 'ks3-science'],
    [3, 'KS3 French', 'ks3-french'],
    [3, 'KS3 Spanish', 'ks3-spanish'],
    [3, 'KS3 History', 'ks3-history'],
    [3, 'KS3 Geography', 'ks3-geography'],
    // GCSE
    [4, 'GCSE Mathematics', 'gcse-mathematics'],
    [4, 'GCSE English Language', 'gcse-english-language'],
    [4, 'GCSE English Literature', 'gcse-english-literature'],
    [4, 'GCSE Biology', 'gcse-biology'],
    [4, 'GCSE Chemistry', 'gcse-chemistry'],
    [4, 'GCSE Physics', 'gcse-physics'],
    [4, 'GCSE Combined Science', 'gcse-combined-science'],
    [4, 'GCSE Computer Science', 'gcse-computer-science'],
    [4, 'GCSE French', 'gcse-french'],
    [4, 'GCSE Spanish', 'gcse-spanish'],
    [4, 'GCSE History', 'gcse-history'],
    [4, 'GCSE Geography', 'gcse-geography'],
    [4, 'GCSE Business Studies', 'gcse-business-studies'],
    [4, 'GCSE Economics', 'gcse-economics'],
    // A-Level
    [5, 'A-Level Mathematics', 'a-level-mathematics'],
    [5, 'A-Level Further Mathematics', 'a-level-further-mathematics'],
    [5, 'A-Level Physics', 'a-level-physics'],
    [5, 'A-Level Chemistry', 'a-level-chemistry'],
    [5, 'A-Level Biology', 'a-level-biology'],
    [5, 'A-Level English Literature', 'a-level-english-literature'],
    [5, 'A-Level Economics', 'a-level-economics'],
    [5, 'A-Level Computer Science', 'a-level-computer-science'],
    [5, 'A-Level Psychology', 'a-level-psychology'],
    [5, 'A-Level History', 'a-level-history'],
    // IB
    [6, 'IB Mathematics (HL/SL)', 'ib-mathematics'],
    [6, 'IB Physics (HL/SL)', 'ib-physics'],
    [6, 'IB Chemistry (HL/SL)', 'ib-chemistry'],
    [6, 'IB Biology (HL/SL)', 'ib-biology'],
    [6, 'IB Economics (HL/SL)', 'ib-economics'],
    [6, 'IB English Literature (HL/SL)', 'ib-english-literature'],
];

$sStmt = $db->prepare("INSERT INTO subjects (curriculum_id, name, slug) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE slug = VALUES(slug)");
foreach ($subjects as $s) {
    $sStmt->execute($s);
}
echo "[OK] Subjects seeded successfully\n";
echo "Phase 3 DB Setup Complete!\n";
