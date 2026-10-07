<?php
// =====================================================
// logout.php - Log the customer out (the cart is kept)
// =====================================================

require __DIR__ . '/site/_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['customer_id']);
    session_regenerate_id(true);
    flash('You are logged out.', 'info');
}
redirect('index.php');
