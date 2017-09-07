<?php
namespace Autobahn\Installer;

/**
 * Class WordPress
 *
 * @package Autobahn\Installer
 */
class WordPress
{
    /**
     * @var WordPressConfiguration Configuration object
     */
    protected $config;

    /**
     * Installer constructor.
     *
     * @param WordPressConfiguration $config The installer configuration
     */
    public function __construct(WordPressConfiguration $config)
    {
        $this->config = $config;
    }

    /**
     * Registers the installer with the WordPress install hook
     * and disables install defaults if required.
     */
    public function register()
    {
        // Maybe disable WordPress defaults
        if (!$this->config->defaults()) {
            $this->disableDefaults();
        }

        add_action('wp_install', [$this, 'install'], PHP_INT_MAX);
    }

    /**
     * Run the install scripts.
     */
    public function install()
    {
        // 1. Set options
        $this->setOptions($this->config->options());

        // 2. Activate plugins
        $this->activatePlugins($this->config->plugins());

        // 3. Maybe set the theme (and settings)
        if ($theme = $this->config->theme()) {
            $this->setTheme($theme->name, $theme->mods, $theme->options, $theme->menus);
        }

        // 4. Maybe set the permalink structure
        global $wp_rewrite;
        if ($wp_rewrite && $permalinks = $this->config->permalinks()) {
            $this->setPermalinkStructure($wp_rewrite, $permalinks);
        }

        // 5. Maybe set the timezone
        if ($timezone = $this->config->timezone()) {
            $this->setTimezone($timezone);
        }
    }

    /**
     * Disables the WordPress install defaults.
     */
    public function disableDefaults()
    {
        // Import the helper file in the global namespace and
        // overwrite wp_install_defaults functions if it doesn't exist yet.
        include('wp_install_defaults.php');
    }

    /**
     * Set or expand a list if WordPress options.
     *
     * @param array $options The list of options to be set
     */
    public function setOptions($options)
    {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
    }

    /**
     * Activated all given plugins.
     *
     * @param array $plugins List of plugins to be activated.
     */
    public function activatePlugins($plugins)
    {
        foreach ($plugins as $plugin) {
            activate_plugin($plugin);
        }
    }

    /**
     * Set a theme and possible mods, related options and menus.
     *
     * @param string $theme
     * @param array  $mods
     * @param array  $options
     * @param array  $menus
     */
    public function setTheme($theme, array $mods = [], array $options = [], array $menus = [])
    {
        $this->setOptions($options);
        switch_theme($theme);
        $this->setThemeMods($mods);
        $this->createMenus($menus);
    }

    /**
     * Set and flush the permalink structure.
     *
     * @param WP_Rewrite $wp_rewrite The WordPress rewrite engine.
     * @param string     $structure  The permalink structure to be used.
     */
    public function setPermalinkStructure($wp_rewrite, $structure)
    {
        // Set the new permalinks structure
        $wp_rewrite->set_permalink_structure($structure);
        // Flush the rules and tell it to write htaccess
        $wp_rewrite->flush_rules(true);

        // Clear the rewrite rules options cache
        update_option("rewrite_rules", false);
    }

    /**
     * Set the WordPress timezone string setting and ensures gmt offset is not used.
     *
     * @param string $timezone The timezone string to be set.
     */
    public function setTimezone($timezone)
    {
        delete_option('gmt_offset');
        update_option('timezone_string', $timezone);
    }

    /**
     * Set or expand a single WordPress option.
     * If the given $value is an array, extend the data by reading it first if $extend flag is set.
     *
     * @param string $option The name of the option to be set.
     * @param mixed  $value  The new value for the option.
     * @param bool   $extend Enables extending for sub options.
     */
    protected function setOption($option, $value, $extend = true)
    {
        // for arrays as value, extend the value
        if ($extend && is_array($value)) {
            $current = get_option($option, []);
            foreach ($value as $sub_option => $sub_value) {
                $current[$sub_option] = $sub_value;
            }
            $value = $current;
        }

        // store the option
        update_option($option, $value);
    }

    /**
     * Set theme mods.
     *
     * @param array $themeMods
     */
    protected function setThemeMods($themeMods)
    {
        foreach ($themeMods as $option => $value) {
            set_theme_mod($option, $value);
        }
    }

    /**
     * Create, fill and place WordPress menus.
     *
     * @param array $menus
     */
    protected function createMenus($menus)
    {
        $locations = [];

        foreach ($menus as $menu) {
            $menu_id = $this->createMenu($menu['name']);

            if ($menu_id) {
                $locations[$menu['location']] = $menu_id;
                $this->populateMenu($menu_id, $menu['items']);
            }
        }

        set_theme_mod('nav_menu_locations', $locations);
    }

    /**
     * Create a new WordPress menu.
     *
     * @param string $menu_name
     *
     * @return bool|int|WP_Error
     */
    protected function createMenu($menu_name)
    {
        $menu_id = wp_create_nav_menu($menu_name);

        if (!is_object($menu_id) && intval($menu_id) > 0) {
            return $menu_id;
        }

        return false;
    }

    /**
     * Add menu items to menu.
     *
     * @param integer $menu_id    The menu ID
     * @param array   $menu_items List of menu items
     */
    protected function populateMenu($menu_id, $menu_items)
    {
        foreach ($menu_items as $menu_item) {
            if (isset($menu_item['title']) && isset($menu_item['type'])) {
                wp_update_nav_menu_item($menu_id, 0, $this->getMenuItemData($menu_item['type'], $menu_item));
            }
        }
    }

    /**
     * Converts data for a menu item.
     *
     * @param string $type
     * @param array  $data
     *
     * @return array
     */
    private function getMenuItemData($type, $data)
    {
        if ('page' == $type) {
            return $this->getPageMenuItem($data);
        }
        if ('post' == $type) {
            return $this->getPostMenuItem($data);
        }

        return $this->getCustomMenuItem($data);
    }

    /**
     * Gets data for a new custom menu item.
     *
     * @param $menu_item
     *
     * @return array
     */
    private function getCustomMenuItem($menu_item)
    {
        return [
            'menu-item-title'  => $menu_item['title'],
            'menu-item-url'    => isset($menu_item['url']) ? $menu_item['url'] : '',
            'menu-item-status' => 'publish',
            'menu-item-type'   => 'custom'
        ];
    }

    /**
     * Gets data for a new page menu item.
     *
     * @param $menu_item
     *
     * @return array
     */
    private function getPageMenuItem($menu_item)
    {
        return [
            'menu-item-title'     => $menu_item['title'],
            'menu-item-object-id' => isset($menu_item['id']) ? $menu_item['id'] : 0,
            'menu-item-object'    => 'page',
            'menu-item-status'    => 'publish',
            'menu-item-type'      => 'post_type'
        ];
    }

    /**
     * Gets data for a new post menu item.
     *
     * @param $menu_item
     *
     * @return array
     */
    private function getPostMenuItem($menu_item)
    {
        return [
            'menu-item-title'     => $menu_item['title'],
            'menu-item-object-id' => isset($menu_item['id']) ? $menu_item['id'] : 0,
            'menu-item-object'    => 'post',
            'menu-item-status'    => 'publish',
            'menu-item-type'      => 'post_type'
        ];
    }
}
