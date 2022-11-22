<?php
/**
 * @copyright Copyright (c) 2022 Good Work
 */

namespace simplygoodwork\nag\services;

use simplygoodwork\nag\Nag;
use simplygoodwork\nag\helpers\LogToFile;

use Craft;
use craft\base\Component;
use craft\elements\User as UserElement;
use craft\helpers\App;
use DeviceDetector\DeviceDetector;

class NagService extends Component
{
    // Get plugin settings
    private $_settings;

    public function init(): void
    {
        parent::init();
        $this->_settings = Nag::$plugin->settings;
    }

    public function onAfterLoginHandler($event): void
    {
        $this->handleLogin($event->identity);
    }

    public function onAfterUserSaveHandler($event): void
    {
        // Ignore new users and check we should be sending alerts
        if ($event->isNew || !$this->_settings->alertOnProfileChange) {
            return;
        }

        // Retrieve old entry and get username + email address
        $oldProfile = UserElement::findOne(
            $event->sender->id
        );

        $sendAlert = false;
        // Compare old and new values
        if (
            !Craft::$app->getConfig()->getGeneral()->useEmailAsUsername
            && $event->sender->username !== $oldProfile->username
        ) {
            $sendAlert = true;

            $subject = sprintf('Username changed on %s website',
                Craft::$app->getSystemName()
            );

            $message = sprintf('Your username was updated on %s from %s to %s.',
                Craft::$app->getSystemName(),
                $oldProfile->username,
                $event->sender->username
            );
        }
        elseif ($event->sender->email !== $oldProfile->email) {
            $sendAlert = true;

            $subject = sprintf('Email address changed on %s website',
                Craft::$app->getSystemName()
            );

            $message = sprintf('Your email address was updated on %s from %s to %s.',
                Craft::$app->getSystemName(),
                $oldProfile->username,
                $event->sender->username
            );
        }

        // If the either have changed, send a message to old email with an alert
        if ($sendAlert) {
          $this->_sendAlert($oldProfile, $subject, $message);
        }
    }

    public function handleLogin($user): void
    {
        $subject = sprintf('New sign in on %s website',
            Craft::$app->getSystemName()
        );

        $message = sprintf('Someone signed-in to your %s website account.',
            Craft::$app->getSystemName()
        );

        $this->_sendAlert($user, $subject, $message);
    }

    private function _sendAlert($user, $subject, $message)
    {
        // Kicks off after a user logs in
        $sendAlert = false;

        // Are we restricting alerts?
        if (!$this->_settings->alertRestrictedGroups) {
            $sendAlert = true;
        }
        else {
            // Should we send alert to admins?
            if ($this->_settings->alertAdmins && $user->admin) {
                $sendAlert = true;
            }

            // Is the user in a specific group?
            foreach($this->_settings->alertUserGroups as $group ) {
                if ($user->isInGroup($group)) {
                    $sendAlert = true;
                }
            }
        }

        // Use anonymized IP address
        $ipAddress = $this->_anonymizeIp(Craft::$app->getRequest()->userIP);

        // Send an email notification to the User about login
        if ($sendAlert) {

            // Investigate the browser and device
            $dd = new DeviceDetector(Craft::$app->getRequest()->userAgent);
            $dd->parse();

            $meta = [
                'browser' => $dd->getClient('name') ?? 'browser',
                'os' => $dd->getOs('name') ?? 'web',
                'location' => $this->_ip2Location($ipAddress),
            ];

            Craft::$app->getView()->setTemplateMode('cp');
            $body = Craft::$app->getView()->renderTemplate('nag/email', [
                'user' => $user,
                'meta' => $meta,
                'message' => $message
            ]);

            Craft::$app->mailer->compose()
                ->setSubject($subject)
                ->setTextBody($body)
                ->setTo($user->email)
                ->send();
        }
    }


    // Anonymzie IP address by resetting the last octet to 0
    private function _anonymizeIp($ip4): string
    {
        $parts = explode('.', $ip4);

        if (count($parts) == 4) {
            array_pop($parts);
            $parts[] = '0';

            $ip4 = join('.', $parts);
        }
        return $ip4;
    }

    // Find a location for the IP address
    private function _ip2Location($ip4): string
    {
        $apiKey = App::parseEnv($this->_settings->geolocationApiKey);

        if ($apiKey) {
            $endpoint = 'https://api.ip2location.io/';

            $uri = sprintf('%s?key=%s&ip=%s',
                $endpoint,
                $apiKey,
                $ip4
            );

            try {
                $client = Craft::createGuzzleClient();
                $response = $client->request('GET',  $uri);
                $data = $response->getBody()->getContents() ?? null;

                $locationData = json_decode($data, false);
                if ($locationData) {
                    return $locationData->city_name . ', ' . $locationData->country_name;
                }
            }
            catch (\GuzzleHttp\Exception\GuzzleException $error) {
                $response = $error->getResponse();
                $body = $response->getBody()->getContents();
                $message = json_decode($body, false);

                // Log that the user has logged in
                LogToFile::info(sprintf('ip2location.io error. Response %s - %s',
                    $response->getStatusCode(),
                    $message->error->error_message,
                ), 'nag');
            }
            catch (\Exception $error) {
                // Log that the user has logged in
                LogToFile::info(sprintf('Error connecting to ip2location.io service. %s',
                    $error->getMessage()
                ), 'nag');
            }
        }

        return '';
    }
}
