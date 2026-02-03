<?php
defined('SITEURL') || die('SITEURL not defined');

// Assets folder is at project root
defined('URL_ASSETS') || define('URL_ASSETS', SITEURL . '/assets');

// Auth pages (located in src/pages/)
defined('URL_LOGIN')      || define('URL_LOGIN', SITEURL . '/src/pages/login/login.php');
defined('URL_REGISTER')   || define('URL_REGISTER', SITEURL . '/src/pages/register/register.php');
defined('URL_FORGOT_PWD') || define('URL_FORGOT_PWD', SITEURL . '/src/pages/forgot-password/forgot-password.php');
defined('URL_RESET_PWD')  || define('URL_RESET_PWD', SITEURL . '/src/pages/forgot-password/reset-password.php');

// Home page
defined('URL_HOME')    || define('URL_HOME', SITEURL . '/src/pages/Home.php');
