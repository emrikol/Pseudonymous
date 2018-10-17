<?php
/**
 * Plugin Name: Pseudonymous
 * Description: Anonymizes user data as much as possible on the site frontend.
 * Version: 1.0.0
 * Author: Derrick Tennant
 * Author URI: https://emrikol.com/
 *
 * @package WordPress
 */

require __DIR__ . '/inc/class-pseudonymous-admin.php';
require __DIR__ . '/inc/class-pseudonymous.php';

Pseudonymous::get_instance()->init_hooks();
