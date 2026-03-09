<?php
session_start();
unset($_SESSION['super_admin_logged_in'], $_SESSION['super_admin_id'], $_SESSION['super_admin_email'], $_SESSION['super_admin_name']);
header('Location: login.php');
exit;
