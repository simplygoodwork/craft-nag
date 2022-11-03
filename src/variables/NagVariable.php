<?php
/**
 * @copyright Copyright (c) 2022 Good Work
 */

namespace simplygoodwork\nag\variables;

use Craft;
use simplygoodwork\nag\Nag;

class NagVariable
{
    // Public Methods
    public function alertFooter()
    {
        $settings = Nag::$plugin->settings;
        return $settings->alertFooter;
    }
}