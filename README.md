# AI moderation help

## Installation

Copy the extension to phpBB/ext/userbase/aimodhelp

Go to "ACP" > "Customise" > "Extensions" and enable the "AI moderation help" extension.

## Configuration

The extension's configuration is stored in the database table `phpbb_aimodhelp_config`. This allows dynamic updates without modifying code.

### Initial Setup

1. Run the migration SQL scripts located in `ext/userbase/aimodhelp/migrations/`:
   - `aimodhelp_create_config_table.sql` to create the config table.
   - `aimodhelp_insert_config_values.sql` to insert default configuration values (edit with your config values before uploading).

2. The configuration parameters include:
   - `AI_PROVIDER`: The AI service provider (e.g., 'openrouter' or 'gemini').
   - `AI_API_KEY`: The API key for the AI provider.
   - `AI_MODEL`: The AI model to use.
   - `AI_BASEURL`: The base URL for the AI API.
   - `MAX_TOPIC_POSTS`: Maximum number of topic posts non-moderator/admin users can request.

### Changing Configuration

- To update configuration values, modify the entries in the `phpbb_aimodhelp_config` table directly via SQL or through an admin UI (if implemented).

## Examples
<img src="doc/images/Screenshot 2025-06-09 at 10.24.57.png" />

### Moderation example
<img src="doc/images/Screenshot 2025-06-08 at 21.27.15.png" width="400" />

### Summary example
<img src="doc/images/Screenshot 2025-06-09 at 06.26.11.png" width="400" />

## Basic Functionality

- Provides AI-powered moderation assistance for phpBB forums.
- Supports evaluating posts or topics against general or 'te koop' forum rules.
- Allows summarizing content.
- Enforces permission-based access control: non-moderators/admins can only summarize and have limits on topic post counts.
- Includes a frontend test page (`ai_evaluator.html`) for interacting with the AI evaluation API.
- Logs execution details for debugging and traceability.

## License

[GNU General Public License v2](userbase/aimodhelp/license.txt)
