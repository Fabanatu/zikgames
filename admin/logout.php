<?php
session_start();
session_destroy();
header('Location: /ZikGames/admin/login.php');
exit;

