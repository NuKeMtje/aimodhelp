<?php
/**
 * phpBB Post Service
 *
 * This standalone PHP service handles POST requests.
 * It supports two types of requests:
 * 1. POST with a post_id to fetch a single post 
 * 2. POST with topic_id (and optional count, days) to fetch topic posts
 */

define('IN_PHPBB', true);
// Enable error reporting and display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$phpbb_root_path = '../../../../'; // Adjusted path to phpBB root directory from service folder
$phpEx = substr(strrchr(__FILE__, '.'), 1);

// Include phpBB common file to initialize environment

include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx); // For display_forums, etc. if needed, but primarily for message_parser
include($phpbb_root_path . 'includes/message_parser.' . $phpEx);

use userbase\aimodhelp\service\ServiceHandler;
use userbase\aimodhelp\service\LogService;
use userbase\aimodhelp\service\ForumContentProvider;
use userbase\aimodhelp\service\LlmService;
use userbase\aimodhelp\config\ConfigProvider;

// Instantiate LogService
$logService = new LogService();

// Ensure $user, $auth, $phpbb_container, $db, $request are available from the global scope
global $user, $auth, $phpbb_container, $db, $request;

// Instantiate ConfigProvider
$configProvider = new ConfigProvider($db);

// Instantiate ForumContentProvider
$contentProviderService = new ForumContentProvider($db, $user, $auth, $phpbb_root_path, $phpEx, $logService);

// Instantiate LlmService
$llmService = new LlmService(
    $phpbb_root_path,
    $phpEx,
    $configProvider->getAiProvider(),
    $configProvider->getAiApiKey(),
    $configProvider->getAiModel(),
    $configProvider->getAiBaseUrl(),
    $logService
);

// Instantiate and run the ServiceHandler
$serviceHandler = new ServiceHandler(
    $user,
    $auth,
    $phpbb_container,
    $db,
    $phpbb_root_path,
    $phpEx,
    $logService,
    $contentProviderService,
    $llmService,
    $request,
    $configProvider
);

$serviceHandler->handleRequest();
?>
