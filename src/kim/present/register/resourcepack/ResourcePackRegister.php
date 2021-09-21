<?php

/**
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 * @noinspection SpellCheckingInspection
 * @noinspection PhpPureAttributeCanBeAddedInspection
 */

declare(strict_types=1);

namespace kim\present\register\resourcepack;

use pocketmine\resourcepacks\ResourcePack;
use pocketmine\resourcepacks\ResourcePackManager;
use pocketmine\Server;
use ReflectionClass;
use ReflectionProperty;

use function strtolower;

final class ResourcePackRegister{
    private function __construct(){ }

    public static function registerPack(ResourcePack $resourcePack) : void{
        /**
         * @var ReflectionProperty $resourcePacksAccessor
         * @var ReflectionProperty $uuidListAccessor
         */
        static $resourcePacksAccessor, $uuidListAccessor;
        if(!isset($resourcePacksAccessor) || !isset($uuidListAccessor)){
            $ref = new ReflectionClass(ResourcePackManager::class);
            $resourcePacksAccessor = $ref->getProperty("resourcePacks");
            $uuidListAccessor = $ref->getProperty("uuidList");

            $resourcePacksAccessor->setAccessible(true);
            $uuidListAccessor->setAccessible(true);
        }

        $origin = Server::getInstance()->getResourcePackManager();

        $resourcePacks = $resourcePacksAccessor->getValue($origin);
        $uuidList = $uuidListAccessor->getValue($origin);

        $resourcePacks[] = $resourcePack;
        $uuidList[strtolower($resourcePack->getPackId())] = $resourcePack;

        $resourcePacksAccessor->setValue($origin, $resourcePacks);
        $uuidListAccessor->setValue($origin, $uuidList);
    }
}