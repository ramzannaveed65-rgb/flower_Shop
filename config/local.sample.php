<?php
// =====================================================
// config/local.sample.php - Database details for paid hosting (cPanel)
//
// HOW TO USE (only on the hosting server, not on your PC):
//  1. Make a copy of this file and name the copy:  local.php
//  2. Put in the database name, user and password you created in cPanel.
//  3. Save. That is all - db.php reads local.php by itself.
//
// local.php is never uploaded to GitHub (it is in .gitignore),
// so your password stays private.
// =====================================================

return [
    'host' => 'localhost',            // almost always "localhost" on cPanel
    'name' => 'cpaneluser_flower',    // database name, e.g. flowersf_flower
    'user' => 'cpaneluser_floweruser', // database user
    'pass' => 'PUT-THE-DATABASE-PASSWORD-HERE',
];
