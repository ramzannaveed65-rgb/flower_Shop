<?php
require __DIR__ . '/_init.php';

$_SESSION = [];
session_destroy();
redirect('login.php');
