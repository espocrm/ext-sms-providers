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

namespace Espo\Modules\SmsProviders\SerwerSms;

use Espo\Core\Exceptions\Error;
use Espo\Core\Sms\Sender;
use Espo\Core\Sms\Sms;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Integration;
use Espo\ORM\EntityManager;
use Throwable;

class SerwerSmsSender implements Sender
{
    private const BASE_URL = 'https://api2.serwersms.pl';

    private const TIMEOUT = 30;

    private $config;

    private $entityManager;

    private $log;

    private $metadata;

    public function __construct(
        Config $config,
        EntityManager $entityManager,
        Log $log,
        Metadata $metadata
    ) {
        $this->config = $config;
        $this->entityManager = $entityManager;
        $this->log = $log;
        $this->metadata = $metadata;
    }

    public function send(Sms $sms): void
    {
        $toNumberList = $sms->getToNumberList();

        if (!count($toNumberList)) {
            throw new Error('No recipient phone number.');
        }

        foreach ($toNumberList as $number) {
            $this->sendToNumber($sms, $number);
        }
    }

    private function sendToNumber(Sms $sms, string $toNumber): void
    {
        $integration = $this->getIntegrationEntity();

        $baseUrl = rtrim(
            $integration->get('serwerSmsBaseUrl') ??
            $this->config->get('serwerSmsBaseUrl') ??
            self::BASE_URL
        );

        $username = $integration->get('serwerSmsUsername');
        $password = $integration->get('serwerSmsPassword');

        $sender =
            $sms->getFromNumber() ??
            $integration->get('serwerSmsSender') ?? '';

        $test = $integration->get('serwerSmsTest') ?? false;
        $timeout = $this->config->get('serwerSmsTimeout') ?? self::TIMEOUT;

        if (!$username) {
            throw new Error('Serwer SMS: No username.');
        }

        if (!$password) {
            throw new Error('Serwer SMS: No password.');
        }

        if (!$toNumber) {
            throw new Error('Serwer SMS: No recipient phone number.');
        }

        $url = $baseUrl . '/messages/send_sms';

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $data = [
            'system' => 'client_php',
            'username' => $username,
            'password' => $password,
            'sender' => $sender,
            'test' => $test,
            'text' => $sms->getBody(),
            'phone' => self::formatNumber($toNumber),
        ];

        $ch = curl_init();

        curl_setopt($ch, \CURLOPT_URL, $url);
        curl_setopt($ch, \CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_HEADER, true);
        curl_setopt($ch, \CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, \CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, \CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, \CURLOPT_POST, true);
        curl_setopt($ch, \CURLOPT_POSTFIELDS, $this->buildQuery($data));

        $response = curl_exec($ch);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch);

        $headerSize = curl_getinfo($ch, \CURLINFO_HEADER_SIZE);

        $body = mb_substr($response, $headerSize);

        if ($code !== 200) {
            throw new Error('Serwer SMS: Unexpected HTTP code ' . $code);
        }

        try {
            $body = Json::decode($body);
        } catch (Throwable $e) {
            $body = (object) [];
        }

        if (isset($body->error)) {
            $this->processError(
                (int) $body->error->code,
                $body->error->type ?? '',
                $body->error->message
            );
        }
    }

    private function getIntegrationEntity(): Integration
    {
        $entity = $this->entityManager
            ->getEntity(Integration::ENTITY_TYPE, 'SerwerSms');

        if (!$entity || !$entity->get('enabled')) {
            throw new Error('Serwer SMS integration is not enabled.');
        }

        return $entity;
    }

    private static function formatNumber(string $number): string
    {
        return '+' . preg_replace('/[^0-9]/', '', $number);
    }

    private function buildQuery(array $data): string
    {
        return Json::encode($data);
    }

    private function processError(int $code, string $type, string $message): void
    {
        $message = $this->metadata->get(['integrations', 'SerwerSms', 'errorCodes', $code], $message);

        if ($message) {
            $this->log->error('Serwer SMS (' . $type . '): [' . $code . '] ' . $message);
        }

        throw new Error('Serwer SMS sending error. Code: ' . $code);
    }
}
