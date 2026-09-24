<?php declare(strict_types=1);

/**
 * Public API: Search Approved Tutors
 * Method: GET
 * Query Params:
 *  - subject_id (int)
 *  - delivery_mode (ONLINE, IN_PERSON, BOTH)
 *  - min_rate (float)
 *  - max_rate (float)
 *  - search (string keyword in headline/bio/name)
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Database\Connection;
use App\Services\ResponseService;

header('Content-Type: application/json; charset=utf-8');

$db = Connection::getInstance();

// Parse filter parameters
$subjectId = filter_var($_GET['subject_id'] ?? null, FILTER_VALIDATE_INT);
$deliveryMode = strtoupper(trim((string)($_GET['delivery_mode'] ?? '')));
$minRate = filter_var($_GET['min_rate'] ?? null, FILTER_VALIDATE_FLOAT);
$maxRate = filter_var($_GET['max_rate'] ?? null, FILTER_VALIDATE_FLOAT);
$search = trim((string)($_GET['search'] ?? ''));

// Base query: ONLY approved tutors with active user status
$whereClauses = [
    'tp.approval_status = "APPROVED"',
    'u.status = "ACTIVE"'
];
$params = [];

if ($subjectId && $subjectId > 0) {
    $whereClauses[] = 'EXISTS (SELECT 1 FROM tutor_subjects ts WHERE ts.tutor_profile_id = tp.id AND ts.subject_id = :subject_id)';
    $params[':subject_id'] = $subjectId;
}

if (!empty($deliveryMode) && in_array($deliveryMode, ['ONLINE', 'IN_PERSON'], true)) {
    $whereClauses[] = '(tp.teaching_mode = :delivery_mode OR tp.teaching_mode = "BOTH")';
    $params[':delivery_mode'] = $deliveryMode;
}

if ($minRate !== false && $minRate !== null && $minRate >= 0) {
    $whereClauses[] = 'tp.hourly_rate >= :min_rate';
    $params[':min_rate'] = $minRate;
}

if ($maxRate !== false && $maxRate !== null && $maxRate > 0) {
    $whereClauses[] = 'tp.hourly_rate <= :max_rate';
    $params[':max_rate'] = $maxRate;
}

if (!empty($search)) {
    $whereClauses[] = '(u.first_name LIKE :search_q OR u.last_name LIKE :search_q OR tp.headline LIKE :search_q OR tp.bio LIKE :search_q)';
    $params[':search_q'] = '%' . $search . '%';
}

$whereSql = implode(' AND ', $whereClauses);

// Query approved tutors (NEVER select email, firebase_uid, dbs_certificate_path, approved_by, or private notes)
$sql = "
    SELECT 
        tp.id AS tutor_profile_id,
        u.first_name,
        u.last_name,
        u.avatar_url,
        tp.headline,
        tp.bio,
        tp.hourly_rate,
        tp.experience_years,
        tp.qualifications,
        tp.teaching_mode,
        tp.is_featured,
        (
            SELECT COUNT(*) 
            FROM availability_slots av 
            WHERE av.tutor_profile_id = tp.id 
              AND av.is_blocked = 0 
              AND av.start_time > NOW() 
              AND av.booked_count < av.max_capacity
        ) AS active_slots_count
    FROM tutor_profiles tp
    JOIN users u ON tp.user_id = u.id
    WHERE {$whereSql}
    ORDER BY tp.is_featured DESC, tp.updated_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tutors = $stmt->fetchAll();

// Eager load subjects for the returned tutors in a single batch (Avoid N+1)
if (!empty($tutors)) {
    $tutorIds = array_map(fn($t) => (int)$t['tutor_profile_id'], $tutors);
    $placeholders = implode(',', array_fill(0, count($tutorIds), '?'));
    
    $subjectStmt = $db->prepare("
        SELECT 
            ts.tutor_profile_id,
            s.id AS subject_id,
            s.name AS subject_name,
            s.slug AS subject_slug,
            c.name AS curriculum_name
        FROM tutor_subjects ts
        JOIN subjects s ON ts.subject_id = s.id
        JOIN curricula c ON s.curriculum_id = c.id
        WHERE ts.tutor_profile_id IN ($placeholders)
        ORDER BY c.id ASC, s.name ASC
    ");
    $subjectStmt->execute($tutorIds);
    $subjectRows = $subjectStmt->fetchAll();

    // Group subjects by tutor_profile_id
    $subjectsByTutor = [];
    foreach ($subjectRows as $row) {
        $tId = (int)$row['tutor_profile_id'];
        if (!isset($subjectsByTutor[$tId])) {
            $subjectsByTutor[$tId] = [];
        }
        $subjectsByTutor[$tId][] = [
            'id' => (int)$row['subject_id'],
            'name' => $row['subject_name'],
            'slug' => $row['subject_slug'],
            'curriculum' => $row['curriculum_name']
        ];
    }

    // Attach to tutors
    foreach ($tutors as &$tutor) {
        $tId = (int)$tutor['tutor_profile_id'];
        $tutor['subjects'] = $subjectsByTutor[$tId] ?? [];
        $tutor['hourly_rate'] = (float)$tutor['hourly_rate'];
        $tutor['experience_years'] = (int)$tutor['experience_years'];
        $tutor['active_slots_count'] = (int)$tutor['active_slots_count'];
        $tutor['is_featured'] = (bool)$tutor['is_featured'];
    }
    unset($tutor);
}

// Fetch all available subjects for filter dropdown
$filterSubjectsStmt = $db->query('
    SELECT s.id, s.name, c.name AS curriculum_name 
    FROM subjects s 
    JOIN curricula c ON s.curriculum_id = c.id 
    ORDER BY c.id ASC, s.name ASC
');
$availableFilterSubjects = $filterSubjectsStmt->fetchAll();

ResponseService::json([
    'tutors' => $tutors,
    'total' => count($tutors),
    'filter_subjects' => $availableFilterSubjects
], 'Approved tutors retrieved successfully');
