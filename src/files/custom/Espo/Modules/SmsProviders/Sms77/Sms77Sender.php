<?php
/************************************************************************
 * This file is part of SMS Providers extension for EspoCRM.
 *
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

namespace Espo\Modules\SmsProviders\Sms77;

use Espo\Core\Sms\Sender;
use Espo\Core\Sms\Sms;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Json;
use Espo\Core\Exceptions\Error;

use Espo\ORM\EntityManager;

use Espo\Entities\Integration;

use Throwable;

class Sms77Sender implements Sender
{
    private const BASE_URL = 'https://gateway.sms77.io/api';

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

        $apiKey = $integration->get('sms77ApiKey');
        $baseUrl = rtrim(
            $integration->get('apiBaseUrl') ??
            self::BASE_URL
        );

        $from = $integration->get('sms77From');

        $timeout = $this->config->get('sms77SmsSendTimeout') ?? self::TIMEOUT;

        if (!$apiKey) {
            throw new Error("No sms77 Auth Token.");
        }

        if (!$toNumber) {
            throw new Error("No recipient phone number.");
        }

        $url = $baseUrl . '/sms';

        $data = [
            'from' => $from,
            'text' => $sms->getBody(),
            'to' => self::formatNumber($toNumber),
        ];

        $headers = [
            'X-Api-Key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, \CURLOPT_URL, $url);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_HEADER, true);
        curl_setopt($ch, \CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, \CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, \CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, \CURLOPT_POSTFIELDS, $this->buildQuery($data));

        $GLOBALS['log']->warning(json_encode( $this->buildQuery($data)) );

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $error = curl_errno($ch);

        if ($code) {
            $headerSize = curl_getinfo($ch, \CURLINFO_HEADER_SIZE);
            $body = mb_substr($response, $headerSize);
            $obj = Json::decode($body);
            $success = (int)$obj->success;

            if (100 !== $success) {
                $this->processError($code, $success);
            }
        }

        if ($error) {
            if (in_array($error, [\CURLE_OPERATION_TIMEDOUT, \CURLE_OPERATION_TIMEOUTED])) {
                throw new Error("sms77 SMS sending timeout.");
            }
        }
    }

    private function buildQuery(array $data): string
    {
        return json_encode($data);
    }

    private static function formatNumber(string $number): string
    {
        return '+' . preg_replace('/[^0-9]/', '', $number);
    }

    private function processError(int $code, int $success): void
    {
        $message = null;

        try {
            switch($success) {
                case 201:
                    $message = 'The sender is invalid. A maximum of 11 alphanumeric or 16 numeric characters are allowed.';
                    break;
                case 202:
                    $message = 'The recipient number is invalid.';
                    break;
                case 301:
                    $message = 'The variable to is not set.';
                    break;
                case 305:
                    $message = 'The variable text is not set.';
                    break;
                case 401:
                    $message = 'The variable text is too long.';
                    break;
                case 402:
                    $message = 'The Reload Lock prevents sending this SMS as it has already been sent within the last 180 seconds.';
                    break;
                case 403:
                    $message = 'The maximum limit for this number per day has been reached.';
                    break;
                case 500:
                    $message = 'The account has too little credit available.';
                    break;
                case 600:
                    $message = 'The carrier delivery failed.';
                    break;
                case 700:
                    $message = 'An unknown error occurred.';
                    break;
                case 900:
                    $message = 'The authentication failed. Please check your API key.';
                    break;
                case 902:
                    $message = 'The API key has no access rights to this endpoint.';
                    break;
                case 903:
                    $message = 'The server IP is wrong.';
                    break;
            }
        }
        catch (Throwable $e) {}

        if ($message) {
            $this->log->error("sms77 SMS sending error. Message: " . $message);
        }

        throw new Error("sms77 SMS sending error. Code: {$code}.");
    }

    private function getIntegrationEntity(): Integration
    {
        $entity = $this->entityManager
            ->getEntity(Integration::ENTITY_TYPE, 'Sms77');

        if (!$entity || !$entity->get('enabled')) {
            throw new Error("Sms77 integration is not enabled");
        }

        return $entity;
    }
}
