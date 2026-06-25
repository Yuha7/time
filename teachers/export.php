<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/auth.php';
auth_required();

$teachers = $pdo->query("
    SELECT name, email, phone,
           CASE is_active WHEN 1 THEN 'Active' ELSE 'Inactive' END AS status,
           DATE_FORMAT(created_at, '%d/%m/%Y') AS date_added
    FROM users
    WHERE role = 'teacher'
    ORDER BY name
")->fetchAll(PDO::FETCH_NUM);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="teachers_' . date('Ymd_His') . '.csv"');

$f = fopen('php://output', 'w');
fputcsv($f, ['Name', 'Email', 'Phone', 'Status', 'Date Added']);
foreach ($teachers as $row) fputcsv($f, $row);
fclose($f);
