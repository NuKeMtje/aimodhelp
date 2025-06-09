<?php
namespace userbase\aimodhelp\config;

use phpbb\db\driver\driver_interface;

class ConfigProvider
{
    /** @var driver_interface */
    protected $db;

    /** @var array */
    protected $configCache = [];

    /**
     * Constructor.
     *
     * @param driver_interface $db The database connection.
     */
    public function __construct(driver_interface $db)
    {
        $this->db = $db;
        $this->loadConfig();
    }

    /**
     * Load all config values from the database into cache.
     */
    protected function loadConfig(): void
    {
        $sql = 'SELECT config_name, config_value FROM phpbb_aimodhelp_config';
        $result = $this->db->sql_query($sql);

        while ($row = $this->db->sql_fetchrow($result)) {
            $this->configCache[$row['config_name']] = $row['config_value'];
        }
        $this->db->sql_freeresult($result);
    }

    /**
     * Get a config value by name.
     *
     * @param string $name The config name.
     * @param mixed $default Default value if config not found.
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        return $this->configCache[$name] ?? $default;
    }

    /**
     * Get AI provider.
     *
     * @return string
     */
    public function getAiProvider(): string
    {
        return $this->get('AI_PROVIDER', 'openrouter');
    }

    /**
     * Get AI API key.
     *
     * @return string
     */
    public function getAiApiKey(): string
    {
        return $this->get('AI_API_KEY', '');
    }

    /**
     * Get AI model.
     *
     * @return string
     */
    public function getAiModel(): string
    {
        return $this->get('AI_MODEL', '');
    }

    /**
     * Get AI base URL.
     *
     * @return string
     */
    public function getAiBaseUrl(): string
    {
        return $this->get('AI_BASEURL', '');
    }

    /**
     * Get max topic posts count for non-moderator/admin users.
     *
     * @return int
     */
    public function getMaxTopicPosts(): int
    {
        return (int) $this->get('MAX_TOPIC_POSTS', 20);
    }
}
