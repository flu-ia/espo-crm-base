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

namespace tests\unit\Espo\Commands;

use Espo\Classes\ConsoleCommands\CreateApiUser;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Core\DataManager;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Role;
use Espo\Entities\User;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRelation;
use Espo\ORM\Repository\RDBRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CreateApiUserTest extends TestCase
{
    private EntityManager&MockObject $entityManager;
    private Metadata&MockObject $metadata;
    private DataManager&MockObject $dataManager;
    private IO&MockObject $io;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManager::class);
        $this->metadata = $this->createMock(Metadata::class);
        $this->dataManager = $this->createMock(DataManager::class);
        $this->io = $this->createMock(IO::class);
    }

    public function testMissingUserName(): void
    {
        $params = new Params(
            options: [],
            flagList: [],
            argumentList: [],
        );

        $this->io->expects($this->once())
            ->method('setExitStatus')
            ->with(1);

        $command = new CreateApiUser(
            $this->entityManager,
            $this->metadata,
            $this->dataManager,
        );

        $command->run($params, $this->io);
    }

    public function testMissingApiKey(): void
    {
        $params = new Params(
            options: [],
            flagList: [],
            argumentList: ['test_user'],
        );

        $this->io->expects($this->once())
            ->method('setExitStatus')
            ->with(1);

        $command = new CreateApiUser(
            $this->entityManager,
            $this->metadata,
            $this->dataManager,
        );

        $command->run($params, $this->io);
    }

    public function testUserExistsAndIsNotApiUser(): void
    {
        $params = new Params(
            options: ['apiKey' => 'some_secret_key'],
            flagList: [],
            argumentList: ['regular_user'],
        );

        $user = $this->createMock(User::class);
        $user->method('isApi')->willReturn(false);

        $userRepository = $this->createMock(RDBRepository::class);
        $userRepository->method('where')->willReturnSelf();
        $userRepository->method('findOne')->willReturn($user);

        $this->entityManager->method('getRDBRepositoryByClass')
            ->with(User::class)
            ->willReturn($userRepository);

        $this->io->expects($this->once())
            ->method('setExitStatus')
            ->with(1);

        $command = new CreateApiUser(
            $this->entityManager,
            $this->metadata,
            $this->dataManager,
        );

        $command->run($params, $this->io);
    }

    public function testCreateNewApiUserAndAssignRole(): void
    {
        $params = new Params(
            options: ['apiKey' => 'fresh_api_key', 'role' => 'API Full Access'],
            flagList: [],
            argumentList: ['new_api_user'],
        );

        $newUser = $this->createMock(User::class);
        $newUser->method('isApi')->willReturn(true);
        $newUser->method('isActive')->willReturn(true);

        $newRole = $this->createMock(Role::class);
        $newRole->method('getId')->willReturn('role-uuid-123');

        $userRepository = $this->createMock(RDBRepository::class);
        $userRepository->method('where')->willReturnSelf();
        $userRepository->method('findOne')->willReturn(null);
        $userRepository->method('getNew')->willReturn($newUser);
        $userRepository->expects($this->once())->method('save')->with($newUser);

        $roleRepository = $this->createMock(RDBRepository::class);
        $roleRepository->method('where')->willReturnSelf();
        $roleRepository->method('findOne')->willReturn(null);
        $roleRepository->method('getNew')->willReturn($newRole);
        $roleRepository->expects($this->once())->method('save')->with($newRole);

        $this->entityManager->method('getRDBRepositoryByClass')
            ->willReturnCallback(function (string $class) use ($userRepository, $roleRepository) {
                if ($class === User::class) {
                    return $userRepository;
                }
                if ($class === Role::class) {
                    return $roleRepository;
                }
                return null;
            });

        $this->metadata->method('get')
            ->with(['scopes'])
            ->willReturn([
                'Lead' => ['acl' => true],
                'Account' => ['acl' => true],
            ]);

        $rolesRelation = $this->createMock(RDBRelation::class);
        $rolesRelation->method('find')->willReturn(new EntityCollection([]));
        $rolesRelation->expects($this->once())->method('relate')->with($newRole);

        $this->entityManager->method('getRelation')
            ->with($newUser, User::LINK_ROLES)
            ->willReturn($rolesRelation);

        $this->dataManager->expects($this->once())->method('clearCache');
        $this->io->expects($this->never())->method('setExitStatus');

        $command = new CreateApiUser(
            $this->entityManager,
            $this->metadata,
            $this->dataManager,
        );

        $command->run($params, $this->io);
    }

    public function testIdempotentWhenAlreadyExistingAndLinked(): void
    {
        $params = new Params(
            options: ['apiKey' => 'same_key', 'role' => 'API Full Access'],
            flagList: [],
            argumentList: ['existing_api_user'],
        );

        $existingUser = $this->createMock(User::class);
        $existingUser->method('isApi')->willReturn(true);
        $existingUser->method('isActive')->willReturn(true);
        $existingUser->method('get')->with(User::FIELD_API_KEY)->willReturn('same_key');

        $existingRole = $this->createMock(Role::class);
        $existingRole->method('getId')->willReturn('role-uuid-123');

        $userRepository = $this->createMock(RDBRepository::class);
        $userRepository->method('where')->willReturnSelf();
        $userRepository->method('findOne')->willReturn($existingUser);
        $userRepository->expects($this->never())->method('save');

        $roleRepository = $this->createMock(RDBRepository::class);
        $roleRepository->method('where')->willReturnSelf();
        $roleRepository->method('findOne')->willReturn($existingRole);
        $roleRepository->expects($this->once())->method('save')->with($existingRole);

        $this->entityManager->method('getRDBRepositoryByClass')
            ->willReturnCallback(function (string $class) use ($userRepository, $roleRepository) {
                if ($class === User::class) {
                    return $userRepository;
                }
                if ($class === Role::class) {
                    return $roleRepository;
                }
                return null;
            });

        $this->metadata->method('get')
            ->with(['scopes'])
            ->willReturn(['Lead' => ['acl' => true]]);

        $rolesRelation = $this->createMock(RDBRelation::class);
        $rolesRelation->method('find')->willReturn(new EntityCollection([$existingRole]));
        // Since already linked, relate should never be called
        $rolesRelation->expects($this->never())->method('relate');

        $this->entityManager->method('getRelation')
            ->with($existingUser, User::LINK_ROLES)
            ->willReturn($rolesRelation);

        $this->dataManager->expects($this->once())->method('clearCache');
        $this->io->expects($this->never())->method('setExitStatus');

        $command = new CreateApiUser(
            $this->entityManager,
            $this->metadata,
            $this->dataManager,
        );

        $command->run($params, $this->io);
    }
}
