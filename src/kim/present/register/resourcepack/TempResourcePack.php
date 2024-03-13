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
use pocketmine\resourcepacks\json\Manifest;
use pocketmine\resourcepacks\ResourcePack as IResourcePack;
use pocketmine\resourcepacks\ResourcePackException;

use function file_exists;
use function file_get_contents;
use function filesize;
use function gettype;
use function hash;
use function implode;
use function strlen;
use function substr;

class TempResourcePack implements IResourcePack{
	protected string $name;
	protected string $id;
	protected string $version;
	protected string $contents;
	protected string $sha256;

	/** @throws ResourcePackException */
	public function __construct(string $zipPath){
		if(!file_exists($zipPath)){
			throw new ResourcePackException("File not found");
		}

		$size = filesize($zipPath);
		if($size === false){
			throw new ResourcePackException("Unable to determine size of file");
		}

		if($size === 0){
			throw new ResourcePackException("Empty file, probably corrupted");
		}

		$archive = new \ZipArchive();
		if(($openResult = $archive->open($zipPath)) !== true){
			throw new ResourcePackException("Encountered ZipArchive error code $openResult while trying to open $zipPath");
		}

		$manifestData = $archive->getFromName("manifest.json");
		if($manifestData === false){
			throw new ResourcePackException("manifest.json not found in the resource pack");
		}
		$archive->close();

		try{
			$manifest = (new CommentedJsonDecoder())->decode($manifestData);
		}catch(\RuntimeException $e){
			throw new ResourcePackException("Failed to parse manifest.json: " . $e->getMessage(), $e->getCode(), $e);
		}
		if(!($manifest instanceof \stdClass)){
			throw new ResourcePackException("manifest.json should contain a JSON object, not " . gettype($manifest));
		}

		try{
			/** @var Manifest $manifest */
			$mapper = new \JsonMapper();
			$mapper->bExceptionOnMissingData = true;
			$mapper->bStrictObjectTypeChecking = true;
			$manifest = $mapper->map($manifest, new Manifest());
		}catch(\JsonMapper_Exception $e){
			throw new ResourcePackException("Invalid manifest.json contents: " . $e->getMessage(), 0, $e);
		}

		$this->name = $manifest->header->name;
		$this->id = $manifest->header->uuid;
		$this->version = implode(".", $manifest->header->version);
		$this->contents = file_get_contents($zipPath);
		$this->sha256 = hash("sha256", $this->contents, true);
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
	 * @throws \InvalidArgumentException if the chunk does not exist
	 */
	public function getPackChunk(int $start, int $length) : string{
		return substr($this->contents, $start, $length);
	}
}
