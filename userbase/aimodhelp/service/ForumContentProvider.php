<?php
/**
 *
 * AI moderation help. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2025, Userbase
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace userbase\aimodhelp\service;

use phpbb\db\driver\driver_interface;
use phpbb\user;
use phpbb\auth\auth;
use userbase\aimodhelp\service\LogService; // Add LogService

class ForumContentProvider
{
    /** @var driver_interface */
    protected $db;

    /** @var user */
    protected $user;

    /** @var auth */
    protected $auth;

    /** @var string */
    protected $phpbb_root_path;

    /** @var string */
    protected $php_ext;

    /** @var LogService */
    protected $logService;

    /**
     * ContentFetcherService constructor.
     *
     * @param driver_interface $db The database connection.
     * @param user $user The user object.
     * @param auth $auth The auth object.
     * @param string $phpbb_root_path The root path of the phpBB installation.
     * @param string $php_ext The PHP file extension.
     * @param LogService $logService The logging service.
     */
    public function __construct(
        driver_interface $db,
        user $user,
        auth $auth,
        string $phpbb_root_path,
        string $php_ext,
        LogService $logService
    ) {
        $this->db = $db;
        $this->user = $user;
        $this->auth = $auth;
        $this->phpbb_root_path = $phpbb_root_path;
        $this->php_ext = $php_ext;
        $this->logService = $logService;
        
        // Ensure the message parser class is available for BBCode parsing
        if (!class_exists('parse_message')) {
            include_once($phpbb_root_path . 'includes/message_parser.' . $php_ext);
        }
    }

    /**
     * Fetch a post from the database by post ID.
     *
     * Retrieves a single post, parses its BBCode, and returns structured data.
     * Returns null if the post does not exist, the ID is invalid, or the user lacks permission.
     *
     * @param int $post_id The post ID to fetch.
     * @return array|null Structured post data, or null on failure/not found/no permission.
     */
    public function getPostById(int $post_id): ?array
    {
        if ($post_id <= 0) {
            $this->logService->log("Invalid post ID provided: $post_id");
            return null; // Indicate invalid ID
        }

        try {
            $sql = 'SELECT p.post_id, p.topic_id, p.forum_id, p.post_text, p.bbcode_uid, p.bbcode_bitfield, p.post_subject, p.post_time, p.poster_id, u.username, u.user_colour, t.topic_title
                    FROM ' . POSTS_TABLE . ' p
                    LEFT JOIN ' . USERS_TABLE . ' u ON (p.poster_id = u.user_id)
                    LEFT JOIN ' . TOPICS_TABLE . ' t ON (p.topic_id = t.topic_id)
                    WHERE p.post_id = ' . $post_id;
            $result = $this->db->sql_query($sql);
            $post_data = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);
        } catch (\Exception $e) {
            $this->logService->log("Database error fetching post ID $post_id: " . $e->getMessage());
            return null;
        }

        if (!$post_data) {
            $this->logService->log("Post not found for ID: $post_id");
            return null; // Post not found
        }

        $forum_id = (int)$post_data['forum_id'];
        if (empty($forum_id) || !$this->auth->acl_get('f_read', $forum_id)) {
            $this->logService->log("No read permission for forum ID $forum_id for post ID $post_id.");
            return null; // Forum not found or no permission
        }

        // Parse the post text with BBCode and smilies
        $message_parser = new \parse_message();
        $message_parser->message = $post_data['post_text'];
        $message_parser->bbcode_uid = $post_data['bbcode_uid'];
        $message_parser->bbcode_bitfield = $post_data['bbcode_bitfield'];
        // Flags for parsing: allow_bbcode, allow_urls, allow_smilies, allow_post_links, allow_quote_alert, allow_font_change, allow_flash
        $message_parser->parse(true, true, true, true, false, true, true);

        // Return structured data
        return [
            'post_id'           => (int) $post_data['post_id'],
            'topic_id'          => (int) $post_data['topic_id'],
            'forum_id'          => (int) $post_data['forum_id'],
            'post_subject'      => $post_data['post_subject'],
            'post_time'         => (int) $post_data['post_time'],
            'post_time_formatted' => $this->user->format_date($post_data['post_time']),
            'poster_id'         => (int) $post_data['poster_id'],
            'username'          => $post_data['username'],
            'user_colour'       => $post_data['user_colour'],
            'topic_title'       => $post_data['topic_title'],
            'post_text_raw'     => $post_data['post_text'], // Original BBCode/text
            'post_text_parsed'  => $message_parser->message, // Parsed HTML
            // Add other relevant fields if needed
        ];
    }

    /**
     * Fetches posts in a topic.
     *
     * Retrieves all posts in a topic, optionally limited by count and/or age in days.
     * Returns null if the topic does not exist or the user lacks permission.
     * Returns an empty array if the topic exists but has no posts.
     *
     * @param int $topic_id The topic ID to fetch posts from.
     * @param int $max_count Optional. Maximum number of posts to return (0 for no limit).
     * @param int $max_days Optional. Maximum age of posts in days (0 for no limit).
     * @return array|null An array of structured post data, or null on failure/not found/no permission.
     */
    public function getTopicPosts(int $topic_id, int $max_count = 0, int $max_days = 0): ?array
    {
        if ($topic_id <= 0) {
            $this->logService->log("Invalid topic ID provided: $topic_id");
            return null; // Indicate invalid ID
        }

        // Fetch forum_id from topic_id to check permissions
        try {
            $sql_forum_check = 'SELECT forum_id FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . (int) $topic_id;
            $result_forum_check = $this->db->sql_query($sql_forum_check);
            $topic_data_for_forum_check = $this->db->sql_fetchrow($result_forum_check);
            $this->db->sql_freeresult($result_forum_check);
        } catch (\Exception $e) {
            $this->logService->log("Database error fetching forum ID for topic $topic_id: " . $e->getMessage());
            return null;
        }

        if (!$topic_data_for_forum_check || empty($topic_data_for_forum_check['forum_id'])) {
            $this->logService->log("Topic not found or no associated forum for topic ID: $topic_id");
            return null; // Topic not found or no associated forum
        }
        $forum_id = (int) $topic_data_for_forum_check['forum_id'];
        if (empty($forum_id) || !$this->auth->acl_get('f_read', $forum_id)) {
            $this->logService->log("No read permission for forum ID $forum_id for topic ID $topic_id.");
            return null; // Forum not found or no permission
        }
        
        //$this->logService->log("Supplied arguments: topic_id = $topic_id, max_count = $max_count, max_days = $max_days");

        $where_conditions = [
            'p.topic_id'    => (int) $topic_id,
            // Add approval check if necessary: 'p.post_approved' => 1
        ];

        $sql_where_extra = ' AND t.topic_status <> ' . ITEM_MOVED . ' AND t.forum_id = ' . $forum_id;

        if ($max_days > 0) {
            $time_limit = time() - ($max_days * 86400);
            $sql_where_extra .= ' AND p.post_time >= ' . (int) $time_limit;
        }

        $order_by_clause = 'p.post_time ASC';
        if ($max_count > 0) {
            $order_by_clause = 'p.post_time DESC'; // Fetch latest N first if count is limited
        }

        $sql_array = [
            'SELECT'    => 'p.post_id, p.topic_id, p.post_subject, p.post_text, p.bbcode_uid, p.bbcode_bitfield, p.post_time, p.poster_id, u.username, u.user_colour, t.topic_title',
            'FROM'      => [
                POSTS_TABLE     => 'p',
            ],
            'LEFT_JOIN' => [
                [
                    'FROM'  => [USERS_TABLE => 'u'],
                    'ON'    => 'p.poster_id = u.user_id',
                ],
                [
                    'FROM'  => [TOPICS_TABLE => 't'],
                    'ON'    => 'p.topic_id = t.topic_id',
                ]
            ],
            'WHERE'     => $this->db->sql_build_array('SELECT', $where_conditions) . 
                           ' AND t.topic_status <> ' . ITEM_MOVED . 
                           ' AND t.forum_id = ' . $forum_id . 
                           ($max_days > 0 ? ' AND p.post_time >= ' . (int) $time_limit : ''),
            'ORDER_BY'  => $order_by_clause,
        ];

        $sql = $this->db->sql_build_query('SELECT', $sql_array);

        // Log the built SQL and WHERE clause for debugging
        //$this->logService->log("Built SQL query for topic posts: " . $sql);

        try {
            if ($max_count > 0) {
                $result = $this->db->sql_query_limit($sql, $max_count);
            } else {
                $result = $this->db->sql_query($sql);
            }

            $posts_data = [];
            while ($row = $this->db->sql_fetchrow($result)) {
                $posts_data[] = $row;
            }
            $this->db->sql_freeresult($result);
        } catch (\Exception $e) {
            $this->logService->log("Database error fetching topic posts for topic ID $topic_id: " . $e->getMessage());
            return null;
        }

        if (empty($posts_data)) {
            // Check if topic itself exists but has no posts or is moved/invalid
            $sql_topic_check = 'SELECT topic_id, forum_id, topic_status FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . (int)$topic_id;
            $result_topic_check = $this->db->sql_query($sql_topic_check);
            $topic_info = $this->db->sql_fetchrow($result_topic_check);
            $this->db->sql_freeresult($result_topic_check);

            if (!$topic_info) {
                $this->logService->log("Topic does not exist for ID: $topic_id");
                return null; // Topic does not exist
            } elseif ($topic_info['topic_status'] == ITEM_MOVED) {
                $this->logService->log("Topic ID $topic_id is moved.");
                 // Indicate topic moved - maybe return a specific status or message?
                 // For now, returning empty array might be sufficient, or null with a specific error code
                 // Let's return an empty array but maybe add a status key later if needed by controller
                 return []; // Topic exists but no posts found (or moved)
            }

            $this->logService->log("No posts found for topic ID $topic_id within specified constraints.");
            return []; // No posts found (within constraints or awaiting approval)
        }

        // If we ordered by DESC to get the latest N posts, reverse the array for chronological display
        if ($max_count > 0) {
            $posts_data = array_reverse($posts_data, false);
        }

        $parsed_posts = [];
        $message_parser = new \parse_message();

        foreach ($posts_data as $post_data) {
            $message_parser->message = $post_data['post_text'];
            $message_parser->bbcode_uid = $post_data['bbcode_uid'];
            $message_parser->bbcode_bitfield = $post_data['bbcode_bitfield'];
            // Flags for parsing: allow_bbcode, allow_urls, allow_smilies, allow_post_links, allow_quote_alert, allow_font_change, allow_flash
            $message_parser->parse(true, true, true, true, false, true, true);

            $parsed_posts[] = [
                'post_id'           => (int) $post_data['post_id'],
                'topic_id'          => (int) $post_data['topic_id'],
                'forum_id'          => $forum_id, // Add forum_id to the data structure
                'post_subject'      => $post_data['post_subject'],
                'post_time'         => (int) $post_data['post_time'],
                'post_time_formatted' => $this->user->format_date($post_data['post_time']),
                'poster_id'         => (int) $post_data['poster_id'],
                'username'          => $post_data['username'],
                'user_colour'       => $post_data['user_colour'],
                'topic_title'       => $post_data['topic_title'],
                'post_text_raw'     => $post_data['post_text'], // Original BBCode/text
                'post_text_parsed'  => $message_parser->message, // Parsed HTML
                // Add other relevant fields if needed
            ];
        }

        return $parsed_posts;
    }

    /**
     * Format topic posts into a single HTML string for LLM context.
     *
     * This helper combines all posts in a topic into a minimal HTML structure,
     * suitable for LLM input. Each post includes subject, author, time, and parsed content.
     *
     * @param array $posts_data Array of structured post data from fetchTopicPosts.
     * @param string $topic_title The topic title.
     * @return string Formatted HTML string.
     */
    public function formatTopicPostsForLlm(array $posts_data, string $topic_title): string
    {
        if (empty($posts_data)) {
            $this->logService->log("No posts data provided for formatting topic posts for LLM.");
            return ''; // Return empty string if no posts
        }

        $html = '';
        // Optional: Display topic title - decided against for cleaner LLM context
        // $html .= '<h2>' . htmlspecialchars($topic_title) . '</h2>';

        foreach ($posts_data as $post_data) {
            // Use post_subject from the post itself, or fallback to topic_title for subsequent posts if desired
            $current_post_subject = !empty($post_data['post_subject']) ? $post_data['post_subject'] : $topic_title;

            // Minimal HTML structure for LLM context
            $html .= '<div class="post" id="p' . $post_data['post_id'] . '">';
            $html .=  '  <div class="post-header">';
            $html .=  '    <div class="post-subject">' . htmlspecialchars($current_post_subject) . '</div>';
            $html .=  '    <div class="post-meta">Posted by ' . htmlspecialchars($post_data['username']) . ' on ' . $post_data['post_time_formatted'] . '</div>'; // Use formatted time
            $html .=  '  </div>';
            $html .=  '  <div class="post-content">';
            $html .=  $post_data['post_text_parsed']; // Use parsed HTML content
            $html .=  '  </div>';
            $html .=  '</div>';
            $html .=  '<hr />'; // Simple separator between posts
        }

        $this->logService->log("Formatted " . count($posts_data) . " topic posts for LLM.");
        return $html;
    }

    /**
     * Format a single post into a single HTML string for LLM context.
     *
     * This helper creates a minimal HTML structure for a single post,
     * suitable for LLM input. Includes subject, author, time, and parsed content.
     *
     * @param array $post_data Structured post data from fetchPostById.
     * @return string Formatted HTML string.
     */
    public function formatPostForLlm(array $post_data): string
    {
         if (empty($post_data)) {
            $this->logService->log("No post data provided for formatting single post for LLM.");
            return ''; // Return empty string if no post data
        }

        // Minimal HTML structure for LLM context
        $html = '<div class="post" id="p' . $post_data['post_id'] . '">';
        $html .=  '  <div class="post-header">';
        $html .=  '    <div class="post-subject">' . htmlspecialchars($post_data['post_subject']) . '</div>';
        $html .=  '    <div class="post-meta">Posted by ' . htmlspecialchars($post_data['username']) . ' on ' . $post_data['post_time_formatted'] . '</div>'; // Use formatted time
        $html .=  '  </div>';
        $html .=  '  <div class="post-content">';
        $html .=  $post_data['post_text_parsed']; // Use parsed HTML content
        $html .=  '  </div>';
        $html .=  '</div>';

        $this->logService->log("Formatted single post ID " . $post_data['post_id'] . " for LLM.");
        return $html;
    }
}
