<?php
/**
 *
 * AI moderation help. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2025, Userbase
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace userbase\aimodhelp\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * AI moderation help Event listener.
 */
class main_listener implements EventSubscriberInterface
{
			
	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language_on_setup',
			'core.viewtopic_modify_post_row' => 'add_custom_button_data',
			];
	}

	/* @var \phpbb\language\language */
	protected $language;
	protected $template;
    protected $auth;

	/**
	 * Constructor
	 *
	 */
	
    public function __construct(
        \phpbb\language\language $language,
        \phpbb\template\template $template,
        \phpbb\auth\auth $auth
    ) {
        $this->language = $language;
        $this->template = $template;
        $this->auth = $auth;
    }


	/**
	 * Load common language files during user setup
	 *
	 * @param \phpbb\event\data	$event	Event object
	 */
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'userbase/aimodhelp',
			'lang_set' => 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

		
    public function add_custom_button_data($event)
    {
        // Post-gegevens ophalen
        $postrow = $event['post_row'];
        $row = $event['row'];
		
		$post_id = (int) $row['post_id'];
        $topic_id = (int) $row['topic_id'];
		
        // Initialize actions flag
        $has_actions = false;
		
        // Show to moderators and admins
        $admin = $this->auth->acl_get('m_') || $this->auth->acl_get('a_');
        if ($admin) {            
            // Action buttons 1,2,5 for mods/admins
            $postrow['CUSTOM_BUTTON_ACTION1_DATA'] =
                'data-post-id="' . $post_id . '" ' .
                'data-topic-id="' . $topic_id . '" ' .
                'data-action="evaluate_general" ' .
                'data-admin="' . ($admin ? '1' : '0') . '"';
            //$postrow['CUSTOM_BUTTON_ACTION1_URL'] = '/forum/ext/userbase/aimodhelp/service/ai_evaluator_form.html?p=' . $post_id . '&t=' . $topic_id . '&c=20&action=evaluate_general' . '&a=' . $admin;
            $postrow['CUSTOM_BUTTON_ACTION1_TOOLTIP'] = $this->language->lang('AIMODHELP_ACTION1_TOOLTIP');
            $postrow['CUSTOM_BUTTON_ACTION1'] = $this->language->lang('AIMODHELP_ACTION1');
            
            $has_actions = true;
        }
		
        // Action buttons 3,4 for all users
        $postrow['CUSTOM_BUTTON_ACTION2_DATA'] =
            'data-post-id="' . $post_id . '" ' .
            'data-topic-id="' . $topic_id . '" ' .
            'data-action="summarize" ' .
            'data-admin="' . ($admin ? '1' : '0') . '"' .
            'data-days=3';

        //$postrow['CUSTOM_BUTTON_ACTION2_URL'] = '/forum/ext/userbase/aimodhelp/service/ai_evaluator_form.html?p=' . $post_id . '&t=' . $topic_id . '&c=20&action=summarize' . '&a=' . $admin;
        $postrow['CUSTOM_BUTTON_ACTION2_TOOLTIP'] = $this->language->lang('AIMODHELP_ACTION2_TOOLTIP');
        $postrow['CUSTOM_BUTTON_ACTION2'] = $this->language->lang('AIMODHELP_ACTION2');

        $has_actions = true;
        
        // Set flag indicating actions exist
        $postrow['AIMODHELP_HAS_ACTIONS'] = $has_actions;
        
        $event['post_row'] = $postrow;
    }
}
