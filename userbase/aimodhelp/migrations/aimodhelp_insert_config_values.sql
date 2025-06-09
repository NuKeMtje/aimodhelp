-- Insert default configuration values for AI moderation help
INSERT INTO `phpbb_aimodhelp_config` (`config_name`, `config_value`) VALUES
('AI_PROVIDER', 'openrouter'),
('AI_API_KEY', '***'),
('AI_MODEL', 'microsoft/mai-ds-r1:free'),
('AI_BASEURL', 'https://openrouter.ai/api/v1'),
('MAX_TOPIC_POSTS', '20')
ON DUPLICATE KEY UPDATE
  `config_value` = VALUES(`config_value`);
