<?php
/**
 * @copyright Copyright (c) 2022 Good Work
 */

namespace simplygoodwork\nag\services;

use simplygoodwork\nag\Nag;
use simplygoodwork\nag\helpers\LogToFile;

use Craft;
use craft\base\Component;
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

    public function handleLogin($user): bool
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
        // TODO: location based alerts $onlyAlertForNewIp

        // Use anonymized IP address
        $ipAddress = $this->_anonymizeIp(Craft::$app->getRequest()->userIP);

        // Log that the user has logged in
        LogToFile::info(sprintf('User %d - %s from %s. Alerted: %s', 
            $user->id, 
            $user->email,
            $ipAddress,
            $sendAlert ? 'Y' : 'N'
        ), 'nag');

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

            $subject = sprintf('New sign in on %s website', 
                Craft::$app->getSystemName()
            );

            Craft::$app->getView()->setTemplateMode('cp');
            $body = Craft::$app->getView()->renderTemplate('nag/email', [
                'user' => $user,
                'meta' => $meta
            ]);

            Craft::$app->mailer->compose()
                ->setSubject($subject)
                ->setTextBody($body)
                ->setTo($user->email)
                ->send();
        }

        return $sendAlert;
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

            $client = Craft::createGuzzleClient();
            $response= $client->request('GET',  $uri);
            $data = $response->getBody()->getContents() ?? null;

            $locationData = json_decode($data, false);
            if ($locationData) {
                return $locationData->city_name . ', ' . $locationData->country_name;
            }    
        }
  
        return '';
    }
}
