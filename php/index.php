<?php
session_start();
if (!empty($_SESSION['logged_in'])) {
    header('Location: home.php');
    exit;
}
header('Location: login.php');
exit;
