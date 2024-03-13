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
use InvalidArgumentException;
use pocketmine\resourcepacks\ResourcePack as IResourcePack;
use pocketmine\resourcepacks\ResourcePackException;
use pocketmine\Server;
use Ramsey\Uuid\Uuid;
use RuntimeException;

use function file_exists;
use function file_get_contents;
use function hash;
use function implode;
use function is_file;
use function json_decode;
use function json_encode;
use function md5;
use function rtrim;
use function str_ends_with;
use function str_replace;
use function strlen;
use function substr;
use function unlink;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

abstract class ResourcePack implements IResourcePack{
	public const AUTOFILL_UUID = "00000000-0000-0000-0000-000000000000";

	protected string $name;
	protected string $id;
	protected string $version;
	protected string $sha256;

	protected string $contents;

	/**
	 * @param string[]                      $files innerPath => realPath
	 *
	 * @phpstan-param array<string, string> $files innerPath => realPath
	 *
	 * @throws ResourcePackException
	 */
	protected function __construct(array $files){
		if(!isset($files["manifest.json"]) || !file_exists($manifestPath = $files["manifest.json"])){
			throw new ResourcePackException("manifest.json not found in the pack");
		}

		if(($manifestData = file_get_contents($manifestPath)) === false){
			throw new ResourcePackException("Failed to open manifest.json file.");
		}

		try{
			$manifest = (new CommentedJsonDecoder())->decode($manifestData, true);
		}catch(RuntimeException $e){
			throw new ResourcePackException("Failed to parse manifest.json: " . $e->getMessage(), $e->getCode(), $e);
		}finally{
			unset($files["manifest.json"]);
		}

		if(!isset(
			$manifest["header"]["name"],
			$manifest["header"]["uuid"],
			$manifest["header"]["version"],
			$manifest["modules"]
		)){
			throw new ResourcePackException("manifest.json is missing required fields");
		}

		$header = $manifest["header"];
		$this->name = $header["name"];
		$this->version = implode(".", $header["version"]);
		$this->id = $header["uuid"];

		$tmp = self::cleanDirName(Server::getInstance()->getDataPath()) . "\$TEMP_" . md5($manifestPath) . ".zip";
		$fullContents = "";

		$archive = new \ZipArchive();
		$archive->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		foreach($files as $innerPath => $realPath){
			if(is_file($realPath)){
				$contents = file_get_contents($realPath);
				if(str_ends_with($realPath, ".json")){
					$json = json_decode($contents, true);
					$contents = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				}
				$archive->addFromString($innerPath, $contents);

				$fullContents .= $contents;
			}
		}

		if($this->id === self::AUTOFILL_UUID){
			$this->id = Uuid::fromString(md5($fullContents))->toString();
			$manifest["header"]["uuid"] = $this->id;
		}
		foreach((array) $manifest["modules"] as $key => $module){
			if($manifest["modules"][$key]["uuid"] === self::AUTOFILL_UUID){
				$manifest["modules"][$key]["uuid"] = UUID::fromString(md5($fullContents . $key))->toString();
			}
		}
		$archive->addFromString("manifest.json",
			json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		$archive->close();

		$this->contents = file_get_contents($tmp);
		$this->sha256 = hash("sha256", $this->contents, true);
		unlink($tmp);
	}

	/** Returns the human-readable name of the resource pack */
	public function getPackName() : string{
		return $this->name;
	}

	/** Returns the pack's UUID as a human-readable string */
	public function getPackId() : string{
		return $this->id;
	}

	/** Returns the size of the pack on disk in bytes. */
	public function getPackSize() : int{
		return strlen($this->contents) + 1;
	}

	/** Returns a version number for the pack in the format major.minor.patch */
	public function getPackVersion() : string{
		return $this->version;
	}

	/**
	 * Returns the raw SHA256 sum of the compressed resource pack zip. This is used by clients to validate pack
	 * downloads.
	 *
	 * @return string byte-array length 32 bytes
	 */
	public function getSha256() : string{
		return $this->sha256;
	}

	/**
	 * Returns a chunk of the resource pack zip as a byte-array for sending to clients.
	 *
	 * Note that resource packs must **always** be in zip archive format for sending.
	 * A folder resource loader may need to perform on-the-fly compression for this purpose.
	 *
	 * @param int $start  Offset to start reading the chunk from
	 * @param int $length Maximum length of data to return.
	 *
	 * @return string byte-array
	 * @throws InvalidArgumentException if the chunk does not exist
	 */
	public function getPackChunk(int $start, int $length) : string{
		return substr($this->contents, $start, $length);
	}

	protected static function cleanDirName(string $path) : string{
		return rtrim(str_replace("\\", "/", $path), "/") . "/";
	}
}
