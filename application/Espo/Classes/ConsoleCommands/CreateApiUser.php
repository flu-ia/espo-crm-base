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

namespace Espo\Classes\ConsoleCommands;

use Espo\Core\Acl\Table;
use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Core\DataManager;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Role;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/**
 * Console command to create or update an API User and assign a Role.
 */
class CreateApiUser implements Command
{
    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
        private DataManager $dataManager,
    ) {}

    public function run(Params $params, IO $io): void
    {
        $userName = $params->getArgument(0) ?? $params->getOption('userName');
        $apiKey = $params->getOption('apiKey') ?? $params->getArgument(1);
        $roleName = $params->getOption('role') ?? 'API Full Access';
        $authMethod = $params->getOption('authMethod') ?? 'ApiKey';

        if (!$userName) {
            $io->writeLine("Error: A username must be specified (e.g. `bin/command create-api-user <username> --api-key=<key>`).");
            $io->setExitStatus(1);

            return;
        }

        if (!$apiKey) {
            $io->writeLine("Error: An API key must be specified via `--api-key=<key>` or as second argument.");
            $io->setExitStatus(1);

            return;
        }

        $userRepository = $this->entityManager->getRDBRepositoryByClass(User::class);

        /** @var ?User $user */
        $user = $userRepository
            ->where([User::FIELD_USER_NAME => $userName])
            ->findOne();

        if ($user) {
            if (!$user->isApi()) {
                $io->writeLine("Error: User '{$userName}' already exists but is not an API user.");
                $io->setExitStatus(1);

                return;
            }

            $currentKey = $user->get(User::FIELD_API_KEY);
            if ($currentKey === $apiKey && $user->isActive()) {
                $io->writeLine("API User '{$userName}' already exists and is active with the specified key.");
            } else {
                $user->set(User::FIELD_API_KEY, $apiKey);
                $user->set(User::FIELD_AUTH_METHOD, $authMethod);
                $user->set(User::FIELD_IS_ACTIVE, true);
                $userRepository->save($user);

                $io->writeLine("API User '{$userName}' updated with the specified API key.");
            }
        } else {
            $user = $userRepository->getNew();
            $user->setUserName($userName);
            $user->setType(User::TYPE_API);
            $user->setLastName($userName);
            $user->set(User::FIELD_AUTH_METHOD, $authMethod);
            $user->set(User::FIELD_API_KEY, $apiKey);
            $user->set(User::FIELD_IS_ACTIVE, true);

            $userRepository->save($user);
            $io->writeLine("API User '{$userName}' created successfully.");
        }

        if ($roleName) {
            $roleRepository = $this->entityManager->getRDBRepositoryByClass(Role::class);
            /** @var ?Role $role */
            $role = $roleRepository->where(['name' => $roleName])->findOne();

            if (!$role) {
                $role = $roleRepository->getNew();
                $role->set('name', $roleName);
            }

            // Always ensure full access data permissions on the role
            $scopes = $this->metadata->get(['scopes']) ?? [];
            $data = [];
            foreach ($scopes as $scopeName => $scopeDef) {
                if (!empty($scopeDef['acl'])) {
                    $data[$scopeName] = [
                        Table::ACTION_CREATE => Table::LEVEL_YES,
                        Table::ACTION_READ => Table::LEVEL_ALL,
                        Table::ACTION_EDIT => Table::LEVEL_ALL,
                        Table::ACTION_DELETE => Table::LEVEL_ALL,
                        Table::ACTION_STREAM => Table::LEVEL_ALL,
                    ];
                }
            }
            $role->setRawData($data);
            $role->set('assignmentPermission', 'all');
            $role->set('userPermission', 'all');
            $role->set('portalPermission', 'yes');
            $role->set('exportPermission', 'yes');
            $role->set('massUpdatePermission', 'yes');
            $roleRepository->save($role);

            $rolesRelation = $this->entityManager->getRelation($user, User::LINK_ROLES);
            $alreadyLinked = false;
            foreach ($rolesRelation->find() as $existingRole) {
                if ($existingRole->getId() === $role->getId()) {
                    $alreadyLinked = true;
                    break;
                }
            }

            if (!$alreadyLinked) {
                $rolesRelation->relate($role);
                $io->writeLine("Role '{$roleName}' assigned to user '{$userName}'.");
            }

            $this->dataManager->clearCache();
        }
    }
}
