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

    /**
     * Handle a request going to our module's index action URL,
     * e.g.: actions/runway-module/default
     *
     * @return mixed
     */
    public function actionTest()
    {
      Nag::$plugin->nagService->handleLogin(Craft::$app->user->getIdentity());

      return true;
    }

}
