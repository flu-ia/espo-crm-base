<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM – Open Source CRM application.
 * Copyright (C) 2014-2026 EspoCRM, Inc.
 * Website: https://www.espocrm.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace tests\unit\Espo\Core\Utils\Security;

use Espo\Core\Utils\Security\HostCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HostCheckTest extends TestCase
{
    public function testIsHostAndNotInternal(): void
    {
        $hostCheck = new HostCheck();

        $this->assertTrue(
            $hostCheck->isHostAndNotInternal('200.1.1.1')
        );

        $this->assertFalse(
            $hostCheck->isHostAndNotInternal('172.20.0.1')
        );

        $this->assertFalse(
            $hostCheck->isHostAndNotInternal('0177.0.0.1')
        );

        $this->assertFalse(
            $hostCheck->isHostAndNotInternal('127.1')
        );

        $this->assertFalse(
            $hostCheck->isHostAndNotInternal('127.0.1')
        );

        $this->assertFalse(
            $hostCheck->isHostAndNotInternal('2130706433')
        );

        $this->assertFalse(
            $hostCheck->isHostAndNotInternal('0x7f000001')
        );

        $this->assertFalse(
            $hostCheck->isHostAndNotInternal('0x7f.1')
        );
    }

    public static function ipAddressTestProvider(): array
    {
        return [
            ['200.1.1.1', true],
            ['172.20.0.1', false],
            ['2606:2800:220:1:248:1893:25c8:1946', true],
            ['::ffff:127.0.0.1', false],
            ['64:ff9b::a00:1', false],
            ['2002:0a00:0001::', false],
            ['2001:0000:4136:e378:8000:0000:f5ff:fffe', false],
            ['2001:4860:4860::8888', true],
            ['2606:4700:4700::1111', true],
            ['2620:fe::fe', true],
            ['2404:6800:4004:80c::200e', true],
            ['2a09:bac1:1234::1', true],
            ['fe80::1', false],
            ['fc00::1', false],
            ['::1', false],
        ];
    }

    #[DataProvider('ipAddressTestProvider')]
    public function testIpAddress(string $ipAddress, bool $expected)
    {
        $hostCheck = new HostCheck();

        $this->assertEquals($expected, $hostCheck->ipAddressIsNotInternal($ipAddress));
    }
}
