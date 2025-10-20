<?php
session_start();

// Clear all CBT session variables
unset($_SESSION['cbt_student_id']);
unset($_SESSION['cbt_user_id']);
unset($_SESSION['cbt_username']);
unset($_SESSION['cbt_full_name']);
unset($_SESSION['cbt_class_id']);
unset($_SESSION['cbt_class_name']);
unset($_SESSION['cbt_logged_in']);
unset($_SESSION['exam_started']);
unset($_SESSION['exam_start_time']);
unset($_SESSION['exam_answers']);
unset($_SESSION['current_question']);
unset($_SESSION['result_id']);

// Redirect to login page
header("Location: login.php");
exit();
?>