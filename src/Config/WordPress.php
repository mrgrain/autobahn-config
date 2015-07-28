<?php
namespace Autobahn\Config;

use Dotenv\Dotenv;

/**
 * Configuration class for WordPress sites
 * @package Autobahn\Config
 */
class WordPress
{
    /** @var string Root path of the local installation (where autobahn.json is). */
    protected $path;

    /** @var array The local configuration (contents from autobahn.json) */
    protected $config = [];

    /** @var array Required env variables */
    protected $required = ['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'WP_HOME'];

    /** @var array List of WordPress configuration constants */
    protected $minimal = [
        'WP_ENV', 'WP_HOME',
        'PUBLIC_DIR', 'CONTENT_DIR', 'WORDPRESS_DIR',
        'WP_SITEURL', 'WP_CONTENT_DIR', 'WP_CONTENT_URL',
        'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET', 'DB_COLLATE', 'DB_PREFIX',
        'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
        'ABSPATH',
    ];

    /**
     * Default values for some configuration entries
     * @var array
     */
    protected $defaults = [
        'PUBLIC_DIR' => '/public',
        'CONTENT_DIR' => '/app',
        'WORDPRESS_DIR' => '/wp',
        'DB_HOST' => 'localhost',
        'DB_CHARSET' => 'utf8',
        'DB_COLLATE' => '',
    ];

    /** @var Dotenv $dotenv instance */
    protected $dotenv;

    /**
     * WordPress constructor.
     * @param string $path Root path of the local installation (where autobahn.json is).
     * @param string $config The local configuration file.
     */
    public function __construct($path, $config = 'autobahn.json')
    {
        $this->path = $path;
        $this->config = $this->getJsonConfig($config);
        $this->dotenv = new Dotenv($path);
    }

    /**
     * Set the local WordPress configuration
     */
    public function set()
    {
        try {
            $this->loadEnv();
            $this->setPhpIni(getenv('WP_ENV') ?: 'development');
            $this->setConfig(getenv('WP_ENV') ?: 'development');
        } catch (\RuntimeException $e) {
            die('<h1>Configuration could not be loaded.</h1>');
        }
        return $this;
    }

    /**
     * Get a config value
     * @param $config
     * @return mixed
     */
    public function getConfig($config)
    {
        return getenv($config) ?: $this->getDefault($config, $this->getEnvConfig("config.{$config}", WP_ENV));
    }

    /**
     * Get a default options
     * @return mixed|null
     */
    public function getOptions()
    {
        return $this->getEnvConfig("option", WP_ENV);
    }

    /**
     * Get a default option value
     * @param $option
     * @return mixed|null
     */
    public function getOption($option)
    {
        return $this->getEnvConfig("option.{$option}", WP_ENV);
    }


    /**
     * Use Dotenv to set required environment variables from .env file in root
     */
    protected function loadEnv()
    {
        try {
            $this->dotenv->load();
            $this->dotenv->required($this->required);
        } catch (\InvalidArgumentException $e) {
            // Assuming env data is set by server
        }
    }

    /**
     * Set custom PHP ini settings
     * @param string $env Current environment
     */
    protected function setPhpIni($env = 'development')
    {
        foreach ($this->getPhpIni($env) as $varname => $newvalue) {
            ini_set($varname, $newvalue);
        }
    }

    /**
     * Get the
     * @param string $env Current environment
     * @return array
     */
    protected function getPhpIni($env = 'development')
    {
        $config = $this->getEnvConfig('php', $env);
        return is_array($config) ? $config : [];
    }


    /**
     * Set the WordPress configuration constants
     * @param string $env Current environment
     */
    protected function setConfig($env = 'development')
    {
        // env
        define('WP_ENV', $env);

        // other configs
        $configs = array_merge(
            $this->minimal,
            array_keys($this->getArrayDot($this->config, "config", [])),
            array_keys($this->getArrayDot($this->config, "env.{$env}.config", []))
        );
        foreach ($configs as $config) {
            if (!defined($config)) {
                define($config, $this->getConfig($config));
            }
        }
    }

    /**
     * Get the contents of the local configuration file
     * @param $file
     * @return array
     */
    protected function getJsonConfig($file)
    {
        if (file_exists($this->path . DIRECTORY_SEPARATOR . $file)) {
            return json_decode(file_get_contents($this->path . DIRECTORY_SEPARATOR . $file), true);
        }
        return [];
    }


    /**
     * Get default config value, if real one does not exist
     * @param $name
     * @param $real
     * @return mixed
     */
    protected function getDefault($name, $real)
    {
        // local value exists
        if (!is_null($real)) {
            return $real;
        }

        // default from array
        if (isset($this->defaults[$name])) {
            return $this->defaults[$name];
        }

        switch ($name) {
            case 'WP_CONTENT_DIR':
                return $this->path . PUBLIC_DIR . CONTENT_DIR;
            case 'WP_SITEURL':
                return WP_HOME . WORDPRESS_DIR;
            case 'WP_CONTENT_URL':
                return WP_HOME . CONTENT_DIR;
            case 'ABSPATH':
                return $this->path . PUBLIC_DIR . WORDPRESS_DIR . DIRECTORY_SEPARATOR;
            default:
                return $real;
        }
    }

    /**
     * Get the environment depend config value in dot syntax
     * @param $name
     * @param string $env
     * @return null|mixed
     */
    protected function getEnvConfig($name, $env = 'development')
    {
        $default =  $this->getArrayDot($this->config, $name, null);
        $env = $this->getArrayDot($this->config, "env.{$env}.{$name}", null);

        // get merged subtree
        if (is_array($default) && is_array($env)) {
            return array_replace_recursive($default, $env);
        }

        // no default => get env value
        if (!is_null($env)) {
            return $env;
        }

        return $default;
    }

    /**
     * Retrieve a value from an array in dot syntax
     * @param $context
     * @param $name
     * @param null $default
     * @return null
     */
    protected function getArrayDot(&$context, $name, $default = null) {
        $pieces = explode('.', $name);
        foreach ($pieces as $piece) {
            if (!is_array($context) || !array_key_exists($piece, $context)) {
                // error occurred
                return $default;
            }
            $context = &$context[$piece];
        }
        return $context;
    }
}