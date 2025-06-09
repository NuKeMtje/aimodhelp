<?php

namespace userbase\aimodhelp\service;

use phpbb\user;
use phpbb\auth\auth;
use phpbb\db\driver\driver_interface;
use phpbb\di\service_collection;
use phpbb\request\request_interface;
use userbase\aimodhelp\config\ConfigProvider;

class ServiceHandler
{
    /** @var user */
    protected $user;

    /** @var auth */
    protected $auth;

    /** @var \phpbb_cache_container */
    protected $phpbb_container;

    /** @var driver_interface */
    protected $db;

    /** @var string */
    protected $phpbb_root_path;

    /** @var string */
    protected $php_ext;

    /** @var LogService */
    protected $logService;

    /** @var ForumContentProvider */
    protected $contentProviderService;

    /** @var LlmService */
    protected $llmService;

    /** @var request_interface */
    protected $request;

    /** @var ConfigProvider */
    protected $configProvider;

    /**
     * ServiceHandler constructor.
     *
     * @param user $user The phpBB user object.
     * @param auth $auth The phpBB auth object.
     * @param \phpbb_cache_container $phpbb_container The phpBB service container.
     * @param driver_interface $db The database connection.
     * @param string $phpbb_root_path The root path of the phpBB installation.
     * @param string $php_ext The PHP file extension.
     * @param LogService $logService The logging service.
     * @param ForumContentProvider $contentProviderService The content provider service.
     * @param LlmService $llmService The LLM service.
     * @param request_interface $request The phpBB request object.
     * @param ConfigProvider $configProvider The configuration provider.
     */
    public function __construct(
        user $user,
        auth $auth,
        \phpbb_cache_container $phpbb_container,
        driver_interface $db,
        string $phpbb_root_path,
        string $php_ext,
        LogService $logService,
        ForumContentProvider $contentProviderService,
        LlmService $llmService,
        request_interface $request,
        ConfigProvider $configProvider
    ) {
        $this->user = $user;
        $this->auth = $auth;
        $this->phpbb_container = $phpbb_container;
        $this->db = $db;
        $this->phpbb_root_path = $phpbb_root_path;
        $this->php_ext = $php_ext;
        $this->logService = $logService;
        $this->contentProviderService = $contentProviderService;
        $this->llmService = $llmService;
        $this->request = $request;
        $this->configProvider = $configProvider;

        // Custom exception handler to display exceptions to caller
        set_exception_handler([$this, 'handleException']);
    }

    /**
     * Handles uncaught exceptions.
     *
     * @param \Throwable $exception The exception object.
     */
    public function handleException(\Throwable $exception): void
    {
        $errorMsg = "Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            echo "<pre style='color: red; background: #fdd; padding: 10px; border: 2px solid red;'>$errorMsg\nStack trace:\n" . $exception->getTraceAsString() . "</pre>";
        } else {
            echo "\n$errorMsg\nStack trace:\n" . $exception->getTraceAsString() . "\n";
        }
        exit(1);
    }

    /**
     * Main method to handle the request.
     */
    public function handleRequest(): void
    {
        // Start session management
        $this->user->session_begin();
        $this->auth->acl($this->user->data);
        $this->user->setup();
        $this->logService->log("Session management complete. User ID: " . $this->user->data['user_id']);

        // Set response header to JSON
        header('Content-Type: application/json');

        $this->logService->log("Script execution started.");

        $requestData = [];

        // Determine if request is GET or POST and extract parameters accordingly
        $method = $this->request->server('REQUEST_METHOD', 'GET');
        $contentType = $this->request->server('CONTENT_TYPE', '');
        $this->logService->log("Request method: $method, Content-Type: $contentType");

        if ($method === 'GET') {
            $action = $this->request->variable('action', '', request_interface::GET);
            if (!empty($action)) {
                $requestData['action'] = $action;
                $postId = $this->request->variable('p', '__NOT_SET__', request_interface::GET);
                if ($postId !== '__NOT_SET__') {
                    $requestData['post_id'] = (int) $postId;
                }

                $topicId = $this->request->variable('t', '__NOT_SET__', request_interface::GET);
                if ($topicId !== '__NOT_SET__') {
                    $requestData['topic_id'] = (int) $topicId;
                }

                $days = $this->request->variable('d', '__NOT_SET__', request_interface::GET);
                if ($days !== '__NOT_SET__') {
                    $requestData['days'] = (int) $days;
                }

                $count = $this->request->variable('c', '__NOT_SET__', request_interface::GET);
                if ($count !== '__NOT_SET__') {
                    $requestData['count'] = (int) $count;
                }

                $outputType = $this->request->variable('outputType', 'html', request_interface::GET);
                $requestData['outputType'] = $outputType;
            }
        } elseif ($method === 'POST' && strpos($contentType, 'application/json') !== false) {
            // Handle POST requests with JSON data
            $rawInput = file_get_contents('php://input');
            $requestData = json_decode($rawInput, true);
            if (!is_array($requestData)) {
                $requestData = [];
            }
        } else {
            // Handle POST requests with form-encoded data or other content types
            $requestData = [];
            $action = $this->request->variable('action', '', request_interface::POST);
            if (!empty($action)) {
                $requestData['action'] = $action;
                $postId = $this->request->variable('p', '__NOT_SET__', request_interface::POST);
                if ($postId !== '__NOT_SET__') {
                    $requestData['post_id'] = (int) $postId;
                }

                $topicId = $this->request->variable('t', '__NOT_SET__', request_interface::POST);
                if ($topicId !== '__NOT_SET__') {
                    $requestData['topic_id'] = (int) $topicId;
                }

                $days = $this->request->variable('d', '__NOT_SET__', request_interface::POST);
                if ($days !== '__NOT_SET__') {
                    $requestData['days'] = (int) $days;
                }

                $count = $this->request->variable('c', '__NOT_SET__', request_interface::POST);
                if ($count !== '__NOT_SET__') {
                    $requestData['count'] = (int) $count;
                }

                $outputType = $this->request->variable('outputType', 'html', request_interface::POST);
                $requestData['outputType'] = $outputType;
            }
        }

        /**
         * Check if content is being requested AND user is not registered, then show login error.
         * Refactored to use $requestData array instead of $this->request->variable()
         */
        if ((!empty($requestData['post_id']) || !empty($requestData['topic_id'])) && !$this->user->data['is_registered'])
        {
            $this->logService->log("User not registered and content requested. Access denied.");
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'You must be logged in to view this content.', 'logs' => $this->logService->getLogs()]);
            exit;
        }
        $this->logService->log("User is registered or no content requested, proceeding.");

        // Basic validation
        if (empty($requestData)) {
            $this->logService->log("No request data received. Exiting.");
            http_response_code(400);
            echo json_encode(['error' => 'No POST data received', 'logs' => $this->logService->getLogs()]);
            exit;
        }
        $this->logService->log("Request data received and validated.");

        // Check for action parameter
        if (!isset($requestData['action'])) {
            $this->logService->log("Missing action parameter. Exiting.");
            http_response_code(400);
            echo json_encode(['error' => 'Missing action parameter', 'logs' => $this->logService->getLogs()]);
            exit;
        }
        $this->logService->log("Action parameter found: " . ($requestData['action'] ?? 'N/A'));

        // Check user permissions for actions
        $action = $requestData['action'] ?? null;
        if (!$action) {
            $this->logService->log("Missing action parameter after initial check. Exiting.");
            http_response_code(400);
            echo json_encode(['error' => 'Missing action parameter', 'logs' => $this->logService->getLogs()]);
            exit;
        }

        // If user is not a moderator or admin, restrict actions to 'summarize' only
        if (!$this->auth->acl_get('m_') && !$this->auth->acl_get('a_')) {
            if ($action !== 'summarize') {
                $this->logService->log("User (ID: " . $this->user->data['user_id'] . ") lacks moderator/admin permissions for action: $action. Access denied.");
                http_response_code(403); // Forbidden
                echo json_encode(['error' => 'You do not have sufficient permissions to perform this action. Only "summarize" is allowed for non-moderators/admins.', 'logs' => $this->logService->getLogs()]);
                exit;
            }
            $this->logService->log("User (ID: " . $this->user->data['user_id'] . ") is not moderator/admin but action is 'summarize', proceeding.");
        } else {
            $this->logService->log("User (ID: " . $this->user->data['user_id'] . ") has moderator/admin permissions. All actions allowed.");
        }

        $contentToProcess = null;
        $contentType = ''; // e.g., 'single_post_html', 'topic_html', 'forum_post_bbcode'
        $responsePayload = [
            'status' => 'success',
            'action' => $action,
        ];

        // Determine request type and process accordingly
        if (isset($requestData['post_id']) && $requestData['post_id'] > 0) {
            $postId = (int)$requestData['post_id'];
            $this->logService->log("Attempting to fetch single post by ID: $postId");
            $postData = $this->contentProviderService->getPostById($postId);
            if (!$postData) {
                $this->logService->log("Failed to retrieve post content or no permission for post ID: $postId");
                http_response_code(422);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Het ophalen van de berichtinhoud is mislukt of er is geen toestemming.',
                    'logs' => $this->logService->getLogs(),
                ]);
                exit;
            }
            $this->logService->log("Successfully fetched post ID: $postId. Formatting for LLM.");
            $contentToProcess = $this->contentProviderService->formatPostForLlm($postData);
            $contentType = 'single_post_html';
            $responsePayload['type'] = 'single_post';
            $responsePayload['post_id'] = $postId;

        } elseif (isset($requestData['topic_id']) && $requestData['topic_id'] > 0) {
            $topicId = (int)$requestData['topic_id'];
            $count = isset($requestData['count']) ? (int)$requestData['count'] : 0;
            $days = isset($requestData['days']) ? (int)$requestData['days'] : 0;

            // Restrict max count for non-moderator/admin users
            if (!$this->auth->acl_get('m_') && !$this->auth->acl_get('a_')) {
                $maxCount = $this->configProvider->getMaxTopicPosts();
                if ($count <= 0 || $count > $maxCount) {
                    $count = $maxCount;
                    $this->logService->log("Non-moderator/admin user: count parameter adjusted to max allowed: $count");
                }
            }

            $this->logService->log("Attempting to fetch topic posts for topic ID: $topicId (count: $count, days: $days)");
            $topicPostsData = $this->contentProviderService->getTopicPosts($topicId, $count, $days);
            if (is_null($topicPostsData)) { // null means topic not found or no permission
                $this->logService->log("Failed to retrieve topic content or no permission for topic ID: $topicId");
                http_response_code(422);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Het ophalen van de onderwerpinhoud is mislukt of er is geen toestemming.',
                    'logs' => $this->logService->getLogs(),
                ]);
                exit;
            }

            if ($topicPostsData === []) {
                $this->logService->log("No posts found for topic ID: $topicId within specified constraints. Exiting.");
                http_response_code(400);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Er werden geen posts gevonden die voldeden aan de opgegeven voorwaarden.',
                    'logs' => $this->logService->getLogs(),
                ]);
                exit;
            }

            $this->logService->log("Successfully fetched topic posts for topic ID: $topicId. Formatting for LLM.");
            // If $topicPostsData is an empty array, it means topic exists but no posts found within constraints
            $contentToProcess = $this->contentProviderService->formatTopicPostsForLlm($topicPostsData, $topicPostsData[0]['topic_title'] ?? 'Unknown Topic');
            $contentType = 'topic_html';
            $responsePayload['type'] = 'topic_posts';
            $responsePayload['topic_id'] = $topicId;
            if ($count > 0) $responsePayload['count'] = $count;
            if ($days > 0) $responsePayload['days'] = $days;

        } else {
            $this->logService->log("Missing post_id or topic_id parameter. Exiting.");
            http_response_code(400);
            echo json_encode(['error' => 'Missing post_id or topic_id parameter', 'logs' => $this->logService->getLogs()]);
            exit;
        }
        $this->logService->log("Content to process determined. Content type: $contentType");

        $outputType = $requestData['outputType'] ?? 'html';
        $this->logService->log("Output type set to: $outputType");

        switch ($action) {
            case 'evaluate_general':
                $this->logService->log("Action: evaluate_general. Evaluating against general forum rules.");
                $evaluationResult = $this->llmService->evaluateAgainstGeneralForumRulesContent($contentToProcess, $contentType, $outputType);
                $responsePayload['evaluation'] = $evaluationResult;
                $this->logService->log("General evaluation complete.");
                break;
            case 'evaluate_te_koop':
                $this->logService->log("Action: evaluate_te_koop. Evaluating against 'te koop' forum rules.");
                $evaluationResult = $this->llmService->evaluateAgainstTeKoopForumRules($contentToProcess, $contentType, $outputType);
                $responsePayload['evaluation'] = $evaluationResult;
                $this->logService->log("'Te koop' evaluation complete.");
                break;
            case 'summarize':
                $this->logService->log("Action: summarize. Summarizing content.");
                $summaryResult = $this->llmService->summarizeContent($contentToProcess, $contentType);
                $responsePayload['summary'] = $summaryResult;
                $this->logService->log("Content summarization complete.");
                break;
            default:
                $this->logService->log("Unknown action: $action. Returning content preview.");
                // If action is not one of the above, but content was fetched/provided
                $responsePayload['content_preview'] = substr($contentToProcess, 0, 200) . (strlen($contentToProcess) > 200 ? '...' : ''); // Provide a preview
                $responsePayload['status'] = 'success_unknown_action';
                $responsePayload['message'] = 'Content retrieved/provided, but action not recognized for specific processing.';
                break;
        }

        // If user is not a moderator or admin, add the log to the response
        if ($this->auth->acl_get('m_') || $this->auth->acl_get('a_')) {
            $responsePayload['log'] = $this->logService->getLogs();
        }

        // Return the final response
        $this->logService->log("Attempting to send final JSON response.");
        $jsonResponse = json_encode($responsePayload);

        if ($jsonResponse === false) {
            // If json_encode fails, create an error payload
            $errorPayload = [
                'status' => 'error',
                'message' => 'Failed to encode JSON response. Possible non-UTF8 characters or unserializable data.',
                'original_payload' => $responsePayload, // Include the original payload for debugging
                'json_last_error' => json_last_error(),
                'json_last_error_msg' => json_last_error_msg(),
                'logs' => $this->logService->getLogs(),
            ];
            // Attempt to encode the error payload. If this also fails, fall back to plain text.
            $jsonErrorResponse = json_encode($errorPayload);
            if ($jsonErrorResponse === false) {
                // Fallback to plain text if even the error payload cannot be encoded
                header('Content-Type: text/plain; charset=utf-8');
                echo "Critical Error: Failed to encode JSON response and even the error payload.\n";
                echo "Original Payload (raw):\n" . print_r($responsePayload, true) . "\n";
                echo "JSON Error: " . json_last_error_msg() . "\n";
            } else {
                echo $jsonErrorResponse;
            }
        } else {
            echo $jsonResponse;
        }
        exit;
    }
}
?>
