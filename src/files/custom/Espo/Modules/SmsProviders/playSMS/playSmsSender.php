<?php
/************************************************************************
 * This file is part of SMS Providers extension for EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Modules\SmsProviders\playSMS;

use Espo\Core\Sms\Sender;
use Espo\Core\Sms\Sms;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Json;
use Espo\Core\Exceptions\Error;

use Espo\ORM\EntityManager;

use Espo\Entities\Integration;

use Throwable;

class PlaySmsSender implements Sender
{
    private const TIMEOUT = 10;

    private $config;

    private $entityManager;

    private $log;

    public function __construct(Config $config, EntityManager $entityManager, Log $log)
    {
        $this->config = $config;
        $this->entityManager = $entityManager;
        $this->log = $log;
    }

    public function send(Sms $sms): void
    {
        $toNumberList = $sms->getToNumberList();

        if (!count($toNumberList)) {
            throw new Error("No recipient phone number.");
        }

        foreach ($toNumberList as $number) {
            $this->sendToNumber($sms, $number);
        }
    }

    private function sendToNumber(Sms $sms, string $toNumber): void
    {
        $integration = $this->getIntegrationEntity();

        $username = $integration->get('playSmsUsername');
        $webservicesToken = $integration->get('playSmsWebservicesToken');
        $baseUrl = rtrim($integration->get('playSmsBaseUrl'));
        $timeout = $this->config->get('playSmsSendTimeout') ?? self::TIMEOUT;

        $fromNumber = $sms->getFromNumber();

        if (!$username) {
            throw new Error("No playSMS username.");
        }
        if (!$webservicesToken) {
            throw new Error("No playSMS Webservices Token.");
        }

        if (!$fromNumber) {
            throw new Error("No sender phone number.");
        }

        if (!$toNumber) {
            throw new Error("No recipient phone number.");
        }

        $numberPrefix = $integration->get('playSmsNumberPrefix');
        if ($numberPrefix) {
            $toNumber = $numberPrefix . self::formatNumber($toNumber);
        }

        $url = $baseUrl . '/index.php?app=ws&u=' . $username . '&h=' . $webservicesToken . '&op=pv&to=' . urlencode($toNumber) . '&from=' . urlencode(self::formatNumber($fromNumber)) . '&msg=' . urlencode($sms->getBody());

        $ch = curl_init();
        if ($ch) {

            curl_setopt($ch, \CURLOPT_URL, $url);
            curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, \CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, \CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, 'GET');

            $response = curl_exec($ch);

            $code = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
            $error = curl_errno($ch);

            $headerSize = curl_getinfo($ch, \CURLINFO_HEADER_SIZE);

            $body = mb_substr($response, $headerSize);

            if ($code && !($code >= 200 && $code < 300)) {
                $this->processError($code, $body);

            }

            if ($error) {
                if (in_array($error, [\CURLE_OPERATION_TIMEDOUT, \CURLE_OPERATION_TIMEOUTED])) {
                    throw new Error("playSMS SMS sending timeout.");
                }
            }

            curl_close($ch);
        }
    }

    private function buildQuery(array $data): string
    {
        $itemList = [];

        foreach ($data as $key => $value) {
            $itemList[] = urlencode($key) . '=' . urlencode($value);
        }

        return implode('&', $itemList);
    }

    private static function formatNumber(string $number): string
    {
        return preg_replace('/[^0-9]/', '', $number);
    }

    private function processError(int $code, string $body): void
    {
        try {
            $data = Json::decode($body);

            $message = $data->message ?? null;
        }
        catch (Throwable $e) {
            $message = null;
        }

        if ($message) {
            $this->log->error("playSMS SMS sending error. Message: " . $message);
        }

        throw new Error("playSMS SMS sending error. Code: {$code}.");
    }

    private function getIntegrationEntity(): Integration
    {
        $entity = $this->entityManager
            ->getEntity(Integration::ENTITY_TYPE, 'playSMS');

        if (!$entity || !$entity->get('enabled')) {
            throw new Error("playSMS integration is not enabled");
        }

        return $entity;
    }
}
