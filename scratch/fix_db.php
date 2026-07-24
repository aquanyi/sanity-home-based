<?php
require '../db_connect.php';
try {
    $pdo->exec('ALTER TABLE students ADD COLUMN admission_no VARCHAR(50) UNIQUE NULL');
    echo "Added admission_no to students.\n";
} catch(Exception $e){
    echo $e->getMessage()."\n";
}
try {
    $pdo->exec('ALTER TABLE parents ADD COLUMN nationality VARCHAR(100) NULL');
    echo "Added nationality to parents.\n";
} catch(Exception $e){
    echo $e->getMessage()."\n";
}
