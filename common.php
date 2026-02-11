<?php
/**
 * common.php
 * A single entry point to load all necessary system dependencies.
 */

// 1. Load Init (Database, Session, Constants like BASE_PATH)
require_once __DIR__ . '/init.php';

// 2. Load URLs (Configuration)
require_once __DIR__ . '/config/urls.php';

// 3. Load Global Functions
require_once __DIR__ . '/functions.php';