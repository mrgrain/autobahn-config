<?php
namespace Autobahn\Installer;

/**
 * Class WordPressConfiguration
 *
 * @package Autobahn\Installer
 */
class WordPressConfiguration
{
    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    protected $plugins = true;

    /**
     * @var boolean|object
     */
    protected $theme = false;

    /**
     * @var boolean|string
     */
    protected $permalinks = true;

    /**
     * @var boolean|string
     */
    protected $timezone = true;

    /**
     * @var boolean
     */
    protected $defaults = false;

    /**
     * InstallerConfiguration constructor.
     *
     * @param string $content A configuration string.
     */
    public function __construct($content)
    {
        // check if config is valid
        if (!static::isValidConfiguration($content)) {
            throw new \InvalidArgumentException('Install configuration file is invalid.');
        }

        // convert to object
        $config = json_decode($content, true);

        // set properties
        if (isset($config['options'])) {
            $this->options = $config['options'];
        }
        if (isset($config['plugins'])) {
            $this->plugins = $config['plugins'];
        }
        if (isset($config['theme'])) {
            $this->theme = (object)$config['theme'];
        }
        if (isset($config['permalinks'])) {
            $this->permalinks = $config['permalinks'];
        }
        if (isset($config['timezone'])) {
            $this->timezone = $config['timezone'];
        }
        if (isset($config['defaults'])) {
            $this->defaults = $config['defaults'];
        }
    }

    /**
     * Create a configuration from a file path.
     *
     * @param string $file Path to the configuration file.
     *
     * @return self
     */
    public static function fromFile($file)
    {
        $content = @file_get_contents($file);

        return new static($content);
    }

    /**
     * Check if the install configuration is valid.
     *
     * @todo Actually check parts of config for sanity.
     *
     * @param string $content
     *
     * @return bool
     */
    public static function isValidConfiguration($content)
    {
        json_decode($content);

        return (json_last_error() == JSON_ERROR_NONE);
    }


    /**
     * Get the options configuration.
     *
     * @return array
     */
    public function options()
    {
        return $this->options ?: [];
    }

    /**
     * Get a list of plugins to be activated.
     *
     * @return array
     */
    public function plugins()
    {
        // plugins is a list of arrays, activate on a per plugin base
        if (is_array($this->plugins)) {
            return $this->plugins;
        }

        // for anything the evaluates to true, activate all plugins
        if ($this->plugins) {
            $plugins = apply_filters('all_plugins', get_plugins());

            return array_keys($plugins);
        }

        return [];
    }

    /**
     * Get the theme object or false if not to be set.
     *
     * @return bool|object
     */
    public function theme()
    {
        // nothing set, skip theme
        if (!$this->theme || !isset($this->theme->name)) {
            return false;
        }

        // only theme name is set
        if (is_string($this->theme)) {
            return (object)[
                'name'     => $this->theme,
                'mods'     => [],
                'options'  => [],
                'menus'    => [],
                'sidebars' => []
            ];
        }

        // read theme setting
        return (object)[
            'name'     => $this->theme->name,
            'mods'     => isset($this->theme->mods) ? $this->theme->mods : [],
            'options'  => isset($this->theme->options) ? $this->theme->options : [],
            'menus'    => isset($this->theme->menus) ? $this->theme->menus : [],
            'sidebars' => isset($this->theme->sidebars) ? $this->theme->sidebars : []
        ];
    }

    /**
     * Get the permalinks structure or false if not to be set.
     *
     * @return string
     */
    public function permalinks()
    {
        if (true === $this->permalinks) {
            return $this->getDefaultPermalinks();
        }

        return $this->permalinks;
    }

    /**
     * Get the timezone string or false if not to be set.
     *
     * @return string|boolean
     */
    public function timezone()
    {
        if (true === $this->timezone) {
            return $this->getDefaultTimezone();
        }

        return $this->timezone;
    }

    /**
     * Get the setting if WordPress install defaults are to be used.
     *
     * @return boolean
     */
    public function defaults()
    {
        return $this->defaults;
    }

    /**
     * Get the default timezone value.
     *
     * @return string
     */
    protected function getDefaultTimezone()
    {
        return ini_get('date.timezone');
    }

    /**
     * Get the default permalinks value.
     *
     * @return string
     */
    protected function getDefaultPermalinks()
    {
        return "/%postname%/";
    }
}
