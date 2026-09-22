<?php
// register.php
// Receives team registration data (JSON POST from register.html) and
// stores it in MySQL: one row per team in `registrations` (team name +
// leader name), one row per additional member in `team_members`.
//
// No contact info, no accounts/passwords — this data is for admin viewing
// only (see admin.php).

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Some hosts don't enable the mbstring extension by default — fall back to
// strlen() rather than crash the whole script with a fatal error.
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int { return strlen($s); }
}

header('Content-Type: application/json');

function respond(bool $success, string $message, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', 405);
}

// Accept JSON body (from the fetch() call) or a standard form POST as a fallback.
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

// --- Collect + trim fields -------------------------------------------------
$team_name     = trim((string)($input['team_name'] ?? ''));
$leader_name   = trim((string)($input['leader_name'] ?? ''));
$problem_statement_id = trim((string)($input['problem_statement_id'] ?? ''));
$members_input = $input['members'] ?? [];

if (!is_array($members_input)) {
    $members_input = [];
}

// --- Server-side validation (never trust the client) -----------------------
$errors = [];

if ($team_name === '' || mb_strlen($team_name) > 150) {
    $errors[] = 'Team name is required.';
}
if ($leader_name === '' || mb_strlen($leader_name) > 150) {
    $errors[] = 'Team leader name is required.';
}
if ($problem_statement_id === '' || mb_strlen($problem_statement_id) > 100) {
    $errors[] = 'Problem statement ID is required.';
}

$members = [];
foreach ($members_input as $m) {
    if (!is_array($m)) {
        $errors[] = 'One of the member entries is malformed.';
        continue;
    }
    $name  = trim((string)($m['name'] ?? ''));
    $class = trim((string)($m['class_name'] ?? ''));

    // A fully blank row (never filled in) is just ignored, not an error.
    if ($name === '' && $class === '') {
        continue;
    }
    if ($name === '' || $class === '') {
        $errors[] = 'Each member needs both a name and a class.';
        continue;
    }
    if (mb_strlen($name) > 150) {
        $errors[] = "Member name \"$name\" is too long.";
        continue;
    }
    if (mb_strlen($class) > 100) {
        $errors[] = "Class value for \"$name\" is too long.";
        continue;
    }
    $members[] = ['name' => $name, 'class_name' => $class];
}

if (count($members) < 1) {
    $errors[] = 'Add at least one team member.';
} elseif (count($members) > 5) {
    $errors[] = 'A team can have at most 5 members plus the leader.';
}

if (!empty($errors)) {
    respond(false, implode(' ', $errors), 422);
}

// --- Insert (registration + members, as a single transaction) --------------
$pdo = null;
try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // Reject duplicate team names.
    $check = $pdo->prepare('SELECT id FROM registrations WHERE team_name = :team_name LIMIT 1');
    $check->execute(['team_name' => $team_name]);
    if ($check->fetch()) {
        $pdo->rollBack();
        respond(false, 'That team name is already registered.', 409);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO registrations (team_name, leader_name, problem_statement_id) VALUES (:team_name, :leader_name, :problem_statement_id)'
    );
    $stmt->execute([
        'team_name'   => $team_name,
        'leader_name' => $leader_name,
        'problem_statement_id' => $problem_statement_id,
    ]);
    $registration_id = (int)$pdo->lastInsertId();

    $memberStmt = $pdo->prepare(
        'INSERT INTO team_members (registration_id, member_name, member_class, position)
         VALUES (:registration_id, :member_name, :member_class, :position)'
    );
    foreach ($members as $position => $m) {
        $memberStmt->execute([
            'registration_id' => $registration_id,
            'member_name'     => $m['name'],
            'member_class'    => $m['class_name'],
            'position'        => $position,
        ]);
    }

    $pdo->commit();
    respond(true, "Team registered — see you at HACKTHON.");

} catch (PDOException $e) {
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Never leak DB details to the client; log server-side instead.
    error_log('Registration DB error: ' . $e->getMessage());
    if ($e->getCode() === '2002') {
        respond(false, 'Registration database is unavailable. Start MySQL in the XAMPP Control Panel, then try again.', 503);
    }
    if ($e->getCode() === '1049') {
        respond(false, 'Registration database is not set up. Create the fracture_point database and run schema.sql, then try again.', 503);
    }
    respond(false, 'Server error — please try again later.', 500);
}
