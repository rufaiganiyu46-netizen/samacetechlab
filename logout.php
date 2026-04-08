<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

logout_user();
set_flash('success', 'You have been logged out successfully.');
redirect('index.php');