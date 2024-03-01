<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe;

use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\serializer\ChunkSerializer;
use pocketmine\scheduler\AsyncTask;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\utils\Binary;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
/** @phpstan-ignore-next-line */
use function xxhash64;

class ChunkRequestTask extends AsyncTask{
	private const TLS_KEY_PROMISE = "promise";

	protected string $chunk;
	protected int $chunkX;
	protected int $chunkZ;
	/** @phpstan-var DimensionIds::* */
	private int $dimensionId;
	/** @phpstan-var NonThreadSafeValue<Compressor> */
	protected NonThreadSafeValue $compressor;
	protected int $mappingProtocol;
	private string $tiles;

	/**
	 * @phpstan-param DimensionIds::* $dimensionId
	 */
	public function __construct(int $chunkX, int $chunkZ, int $dimensionId, Chunk $chunk, TypeConverter $typeConverter, CachedChunkPromise $promise, Compressor $compressor){
		$this->compressor = new NonThreadSafeValue($compressor);
		$this->mappingProtocol = $typeConverter->getProtocolId();

		$this->chunk = FastChunkSerializer::serializeTerrain($chunk);
		$this->chunkX = $chunkX;
		$this->chunkZ = $chunkZ;
		$this->dimensionId = $dimensionId;
		$this->tiles = ChunkSerializer::serializeTiles($chunk, $typeConverter);

		$this->storeLocal(self::TLS_KEY_PROMISE, $promise);
	}

	public function onRun() : void{
		$chunk = FastChunkSerializer::deserializeTerrain($this->chunk);

		$cache = new CachedChunk();

		$converter = TypeConverter::getInstance($this->mappingProtocol);
		foreach(ChunkSerializer::serializeSubChunks($chunk, $this->dimensionId, $converter->getBlockTranslator(), $this->mappingProtocol) as $subChunk){
			/** @phpstan-ignore-next-line */
			$cache->addSubChunk(Binary::readLong(xxhash64($subChunk)), $subChunk);
		}

		$encoder = PacketSerializer::encoder($this->mappingProtocol);
		$biomeEncoder = clone $encoder;
		ChunkSerializer::serializeBiomes($chunk, $this->dimensionId, $biomeEncoder);
		/** @phpstan-ignore-next-line */
		$cache->setBiomes(Binary::readLong(xxhash64($chunkBuffer = $biomeEncoder->getBuffer())), $chunkBuffer);

		$chunkDataEncoder = clone $encoder;
		ChunkSerializer::serializeChunkData($chunk, $chunkDataEncoder, $converter, $this->tiles);

		$cache->compressPackets(
			$this->chunkX,
			$this->chunkZ,
			$this->dimensionId,
			$chunkDataEncoder->getBuffer(),
			$this->compressor->deserialize(),
			$this->mappingProtocol
		);

		$this->setResult($cache);
	}

	public function onCompletion() : void{
		/** @var CachedChunk $result */
		$result = $this->getResult();

		/** @var CachedChunkPromise $promise */
		$promise = $this->fetchLocal(self::TLS_KEY_PROMISE);
		$promise->resolve($result);
	}
}
