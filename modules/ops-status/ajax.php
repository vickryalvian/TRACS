<?php

require_once __DIR__.'/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_OFF);

$action = $_GET['action'] ?? '';

function ops_json($success, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

if($action === 'save'){

    $id = intval($_POST['id'] ?? 0);

    $message = trim($_POST['message'] ?? '');

    $severity = trim($_POST['severity'] ?? 'info');

    if($message === ''){
        ops_json(false, 'Message is required', 400);
    }

    if(!in_array($severity, ['info','warning','critical','solved'], true)){
        $severity = 'info';
    }

    if($id > 0){

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE ops_status
             SET message=?, severity=?
             WHERE id=?"
        );

        if(!$stmt){
            ops_json(false, 'ops_status table is missing or invalid', 500);
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ssi",
            $message,
            $severity,
            $id
        );

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO ops_status
             (message,severity)
             VALUES (?,?)"
        );

        if(!$stmt){
            ops_json(false, 'ops_status table is missing or invalid', 500);
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ss",
            $message,
            $severity
        );
    }

    if(!mysqli_stmt_execute($stmt)){
        ops_json(false, 'Failed saving ops status', 500);
    }

    ops_json(true, 'Saved');
}

if ($_GET['action'] === 'archive') {

    $id = (int)($_POST['id'] ?? 0);

    if(!$id){
        ops_json(false, 'ID required', 400);
    }

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE ops_status
         SET is_active = 0
         WHERE id = ?"
    );

    if(!$stmt){
        ops_json(false, 'ops_status table is missing or invalid', 500);
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);

    if(!mysqli_stmt_execute($stmt)){
        ops_json(false, 'Failed archiving ops status', 500);
    }

    ops_json(true, 'Archived');
}

ops_json(false, 'Invalid action', 404);
