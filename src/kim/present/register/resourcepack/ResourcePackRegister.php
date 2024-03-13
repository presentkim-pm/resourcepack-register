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
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
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

use pocketmine\resourcepacks\ResourcePack as IResourcePack;
use pocketmine\resourcepacks\ResourcePackManager;
use pocketmine\Server;

use function strtolower;

final class ResourcePackRegister{
	private function __construct(){}

	public static function registerPack(IResourcePack $resourcePack) : void{
		\Closure::bind( //HACK: Closure bind hack to access inaccessible members
			closure: static function(ResourcePackManager $resourcePackManager) use ($resourcePack){
				$resourcePackManager->resourcePacks[] = $resourcePack;
				$resourcePackManager->uuidList[strtolower($resourcePack->getPackId())] = $resourcePack;
			},
			newThis: null,
			newScope: ResourcePackManager::class
		)(Server::getInstance()->getResourcePackManager());
	}
}
