<?php

declare(strict_types=1);

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

include_once('compare-table.php');

global $ruigehond014;
$ruigehond014->uninstall();
