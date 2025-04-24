<?php
require_once __DIR__ . '/includes/auth.php';
start_session_if_not_started();

logout();
redirect('login.php');
?>