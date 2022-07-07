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

namespace pocketmine\network\mcpe\convert;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockStateSerializeException;
use pocketmine\data\bedrock\block\BlockStateSerializer;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use Webmozart\PathUtil\Path;
use function file_get_contents;

/**
 * @internal
 */
final class RuntimeBlockMapping{
	use SingletonTrait;

	/**
	 * @var int[]
	 * @phpstan-var array<int, int>
	 */
	private array $networkIdCache = [];

	/** Used when a blockstate can't be correctly serialized (e.g. because it's unknown) */
	private BlockStateData $fallbackStateData;
	/** @var int[] */
	private array $fallbackStateId;

	private const BLOCK_PALETTE_PATH = 0;
	private const META_MAP_PATH = 1;

	private static function make() : self{
		$protocolPaths = [
			ProtocolInfo::CURRENT_PROTOCOL => [
				self::BLOCK_PALETTE_PATH => '',
				self::META_MAP_PATH => '',
			],
			ProtocolInfo::PROTOCOL_1_18_30 => [
				self::BLOCK_PALETTE_PATH => '-1.18.30',
				self::META_MAP_PATH => '-1.18.30',
			],
			ProtocolInfo::PROTOCOL_1_18_10 => [
				self::BLOCK_PALETTE_PATH => '-1.18.10',
				self::META_MAP_PATH => '-1.18.10',
			],
			ProtocolInfo::PROTOCOL_1_18_0 => [
				self::BLOCK_PALETTE_PATH => '-1.18.0',
				self::META_MAP_PATH => '-1.18.0',
			],
			ProtocolInfo::PROTOCOL_1_17_40 => [ // 1.18.0 has negative chunk hacks
				self::BLOCK_PALETTE_PATH => '-1.18.0',
				self::META_MAP_PATH => '-1.18.0',
			],
			ProtocolInfo::PROTOCOL_1_17_30 => [
				self::BLOCK_PALETTE_PATH => '-1.17.30',
				self::META_MAP_PATH => '-1.18.0',
			],
			ProtocolInfo::PROTOCOL_1_17_10 => [
				self::BLOCK_PALETTE_PATH => '-1.17.10',
				self::META_MAP_PATH => '-1.17.10',
			],
			ProtocolInfo::PROTOCOL_1_17_0 => [
				self::BLOCK_PALETTE_PATH => '-1.17.0',
				self::META_MAP_PATH => '-1.17.10',
			]
		];

		$blockStateDictionaries = [];

		foreach($protocolPaths as $mappingProtocol => $paths){
			$canonicalBlockStatesFile = Path::join(\pocketmine\BEDROCK_DATA_PATH, "canonical_block_states-" . $paths[self::BLOCK_PALETTE_PATH] . ".nbt");
			$canonicalBlockStatesRaw = Utils::assumeNotFalse(file_get_contents($canonicalBlockStatesFile), "Missing required resource file");

			$metaMappingFile = Path::join(\pocketmine\BEDROCK_DATA_PATH, 'block_state_meta_map-' . $paths[self::META_MAP_PATH] . '.json');
			$metaMappingRaw = Utils::assumeNotFalse(file_get_contents($metaMappingFile), "Missing required resource file");

			$blockStateDictionaries[$mappingProtocol] = BlockStateDictionary::loadFromString($canonicalBlockStatesRaw, $metaMappingRaw);
		}

		return new self(
			$blockStateDictionaries,
			GlobalBlockStateHandlers::getSerializer()
		);
	}

	/**
	 * @param BlockStateDictionary[] $blockStateDictionaries
	 */
	public function __construct(
		private BlockStateDictionary $blockStateDictionaries,
		private BlockStateSerializer $blockStateSerializer
	){
		$this->fallbackStateData = new BlockStateData(BlockTypeNames::INFO_UPDATE, CompoundTag::create(), BlockStateData::CURRENT_VERSION);

		foreach($this->blockStateDictionaries as $mappingProtocol => $blockStateDictionary){
			$this->fallbackStateId[$mappingProtocol] = $blockStateDictionary->lookupStateIdFromData($this->fallbackStateData) ?? throw new AssumptionFailedError(BlockTypeNames::INFO_UPDATE . " should always exist");
		}
	}

	public function toRuntimeId(int $internalStateId, int $mappingProtocol) : int{
		if(isset($this->networkIdCache[$internalStateId])){
			return $this->networkIdCache[$internalStateId];
		}

		try{
			$blockStateData = $this->blockStateSerializer->serialize($internalStateId);

			$networkId = $this->getBlockStateDictionary($mappingProtocol)->lookupStateIdFromData($blockStateData);
			if($networkId === null){
				throw new AssumptionFailedError("Unmapped blockstate returned by blockstate serializer: " . $blockStateData->toNbt());
			}
		}catch(BlockStateSerializeException){
			//TODO: this will swallow any error caused by invalid block properties; this is not ideal, but it should be
			//covered by unit tests, so this is probably a safe assumption.
			$networkId = $this->fallbackStateId;
		}

		return $this->networkIdCache[$mappingProtocol][$internalStateId] = $networkId;
	}

	public function getBlockStateDictionary(int $mappingProtocol) : BlockStateDictionary{ return $this->blockStateDictionaries[$mappingProtocol] ?? throw new AssumptionFailedError("Missing block state dictionary for protocol $mappingProtocol"); }

	public function getFallbackStateData() : BlockStateData{ return $this->fallbackStateData; }

	public static function getMappingProtocol(int $protocolId) : int{ return $protocolId; }

	/**
	 * @param Player[] $players
	 *
	 * @return Player[][]
	 */
	public static function sortByProtocol(array $players) : array{
		$sortPlayers = [];

		foreach($players as $player){
			$mappingProtocol = self::getMappingProtocol($player->getNetworkSession()->getProtocolId());

			if(isset($sortPlayers[$mappingProtocol])){
				$sortPlayers[$mappingProtocol][] = $player;
			}else{
				$sortPlayers[$mappingProtocol] = [$player];
			}
		}

		return $sortPlayers;
	}
}
