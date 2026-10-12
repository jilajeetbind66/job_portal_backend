<?php
$ca = 'C:\\Program Files\\Git\\mingw64\\etc\\ssl\\certs\\ca-bundle.crt';
$options = [
    PDO::MYSQL_ATTR_SSL_CA => $ca,
];
try {
    $pdo = new PDO('mysql:host=gateway01.ap-northeast-1.prod.aws.tidbcloud.com;port=4000;dbname=test', '25e5X3aEVRXftqf.root', 'Zq1NB1MMMj8Bbab3', $options);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS vcreate_portal;");
    echo "Database vcreate_portal created successfully!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

