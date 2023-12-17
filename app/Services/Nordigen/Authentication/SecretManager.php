<?php

/*
 * SecretManager.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\Nordigen\Authentication;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class SecretManager
 */
class SecretManager
{
    public const string NORDIGEN_ID  = 'nordigen_id';
    public const string NORDIGEN_KEY = 'nordigen_key';

    /**
     * Will return the Nordigen ID. From a cookie if its there, otherwise from configuration.
     *
     * @return string
     */
    public static function getId(): string
    {
        if (!self::hasId()) {
            app('log')->debug('No Nordigen ID in hasId() session, will return config variable.');

            return (string)config('nordigen.id');
        }
        try {
            $id = (string)session()->get(self::NORDIGEN_ID);
        } catch (ContainerExceptionInterface | NotFoundExceptionInterface $e) {
            $id = '(super invalid)';
        }
        return $id;
    }

    /**
     * Will return the Nordigen ID. From a cookie if its there, otherwise from configuration.
     *
     * @return string
     */
    public static function getKey(): string
    {
        if (!self::hasKey()) {
            app('log')->debug('No Nordigen key in hasKey() session, will return config variable.');

            return (string)config('nordigen.key');
        }

        try {
            $key = (string)session()->get(self::NORDIGEN_KEY);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            $key = '(super invalid key)';
        }
        return $key;
    }

    /**
     * Store access token in a cookie.
     *
     * @param string $identifier
     *
     * @return void
     */
    public static function saveId(string $identifier): void
    {
        session()->put(self::NORDIGEN_ID, $identifier);
    }

    /**
     * Store access token in a cookie.
     *
     * @param string $key
     *
     * @return void
     */
    public static function saveKey(string $key): void
    {
        session()->put(self::NORDIGEN_KEY, $key);
    }

    /**
     * Will verify if the user has a Nordigen ID (in a cookie)
     *
     * @return bool
     */
    private static function hasId(): bool
    {
        try {
            $id = (string)session()->get(self::NORDIGEN_ID);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            $id = '';
        }
        return '' !== $id;
    }

    /**
     * Will verify if the user has a Nordigen Key (in a cookie)
     *
     * @return bool
     */
    private static function hasKey(): bool
    {
        try {
            $key = (string)session()->get(self::NORDIGEN_KEY);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            $key = '';
        }
        return '' !== $key;
    }
}
