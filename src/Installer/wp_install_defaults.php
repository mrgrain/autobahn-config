<?php

namespace {

    /**
     * Overwrite wp_install_defaults in the global namespace to prevent WordPress defaults
     */
    if (!function_exists('wp_install_defaults')) {
        function wp_install_defaults($user_id)
        {
        }
    }
}

