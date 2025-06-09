-- Create table for AI moderation help configuration
CREATE TABLE IF NOT EXISTS `phpbb_aimodhelp_config` (
  `config_name` VARCHAR(255) NOT NULL,
  `config_value` MEDIUMTEXT,
  PRIMARY KEY (`config_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
