<?php
/**
 * @copyright Copyright (c) 2022 Good Work
 */

namespace simplygoodwork\nag;

use Craft;
use craft\base\Plugin;
use craft\web\twig\variables\CraftVariable;
use craft\web\User;

use simplygoodwork\nag\models\SettingsModel;
use simplygoodwork\nag\services\NagService;
use simplygoodwork\nag\variables\NagVariable;

use yii\base\Event;

class Nag extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Nag
     */
    public static Nag $plugin;

    public static function config(): array
    {
        return [
            'components' => [
                'nag' => ['class' => NagService::class],
            ],
        ];
    }

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * Set to `true` if the plugin should have a settings view in the control panel.
     *
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * Set to `true` if the plugin should have its own section (main nav item) in the control panel.
     *
     * @var bool
     */
    public bool $hasCpSection = false;

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('nag', NagVariable::class);
            }
        );

        // After user login
        Event::on(User::class, User::EVENT_AFTER_LOGIN,
            [self::$plugin->nagService, 'onAfterLoginHandler']
        );
    }

    // Protected Methods
    // =========================================================================
    protected function createSettingsModel(): SettingsModel
    {
        return new SettingsModel();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('nag/settings', [
            'settings' => $this->getSettings(),
        ]);
    }
}
