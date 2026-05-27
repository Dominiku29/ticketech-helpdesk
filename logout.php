<?php
session_start();
session_destroy();
header("Location: tech_login.php");
exit;
?>