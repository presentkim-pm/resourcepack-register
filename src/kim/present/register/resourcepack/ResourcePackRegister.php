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
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\register\resourcepack;

use Ahc\Json\Comment as CommentedJsonDecoder;
use pocketmine\plugin\Plugin;
use pocketmine\resourcepacks\ResourcePack as IResourcePack;
use pocketmine\resourcepacks\ResourcePackException;
use pocketmine\resourcepacks\ResourcePackManager;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\Server;
use Symfony\Component\Filesystem\Path;

use function copy;
use function file_get_contents;
use function json_encode;
use function md5;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function sys_get_temp_dir;
use function tempnam;
use function time;
use function unlink;

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


	/**
	 * Register resource pack from the specified directory of plugin resources
	 *
	 * @param bool $isTemp Whether archive to temporary dir. if false, archive to resource_packs dir
	 */
	public static function registerFromResource(Plugin $plugin, string $resourcePath, bool $isTemp = true) : void{
		self::registerFromDirectory(
			$plugin->getResourcePath($resourcePath),
			$isTemp,
			$plugin->getName() . "." . str_replace("/", ".", $resourcePath)
		);
	}


	/**
	 * Register resource pack from the specified directory
	 *
	 * @param bool   $isTemp     Whether archive to temporary dir. if false, archive to resource_packs dir
	 * @param string $outputName Using when $isTemp is false, if empty, use md5 hash of $addonDir
	 */
	public static function registerFromDirectory(string $addonDir, bool $isTemp = true, string $outputName = "") : void{
		$tmp = tempnam(sys_get_temp_dir(), "pm$");
		self::archiveDirectory($addonDir, $tmp);
		if($isTemp){
			$pack = new TempResourcePack($tmp);
		}else{
			$output = Path::join(
				Server::getInstance()->getResourcePackManager()->getPath(),
				"_resourcepack-register." . ($outputName ?: md5($addonDir)) . ".zip"
			);
			copy($tmp, $output);
			$pack = new ZippedResourcePack($output);
		}
		unlink($tmp);
		self::registerPack($pack);
	}

	private static function archiveDirectory(string $sourceDir, string $outputPath) : void{
		$archive = new \ZipArchive();
		$archive->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

		/** @var \SplFileInfo $fileInfo */
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir)) as $fileInfo){
			if(!$fileInfo->isFile()){
				continue;
			}

			$realPath = $fileInfo->getPathname();
			$innerPath = Path::makeRelative($realPath, $sourceDir);
			if(str_starts_with($innerPath, ".")){
				continue;
			}

			$contents = file_get_contents($realPath);
			if($contents === false){
				throw new ResourcePackException("Failed to open $realPath file");
			}

			if(str_ends_with($innerPath, ".json")){
				try{
					$contents = json_encode((new CommentedJsonDecoder())->decode($contents));
				}catch(\RuntimeException){
				}
			}
			$archive->addFromString($innerPath, $contents);
			$archive->setCompressionName($innerPath, \ZipArchive::CM_DEFLATE64);
			$archive->setMtimeName($innerPath, time());
		}
		$archive->close();
	}
}
