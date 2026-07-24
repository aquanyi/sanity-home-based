<?php
$_GET['action'] = 'get_pending_teachers';
$_REQUEST['action'] = 'get_pending_teachers';
$_POST['action'] = 'get_pending_teachers';

// Fake session
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['user_role'] = 'admin';

include 'api/api_approve_teacher.php';
