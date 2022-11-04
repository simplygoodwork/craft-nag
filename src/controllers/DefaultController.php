<?php

/**
 * @copyright Copyright (c) 2022 Good Work
 */

namespace simplygoodwork\nag\controllers;


use Craft;
use craft\web\Controller;
use simplygoodwork\nag\Nag;
use yii\web\Response;

class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    protected int|bool|array $allowAnonymous = [
        'test'
    ];

    // Public Methods
    // =========================================================================

    public function actionTest()
    {
      Nag::$plugin->nagService->handleLogin(Craft::$app->user->getIdentity());

      return true;
    }

}
