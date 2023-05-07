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

use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockStateSerializeException;
use pocketmine\data\bedrock\block\BlockStateSerializer;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\data\bedrock\block\downgrade\BlockStateDowngrader;
use pocketmine\data\bedrock\block\downgrade\BlockStateDowngradeSchemaUtils;
use pocketmine\data\bedrock\block\upgrade\BlockStateUpgrader;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Filesystem;
use pocketmine\utils\ProtocolSingletonTrait;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use Symfony\Component\Filesystem\Path;
use function str_replace;
use const pocketmine\BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH;

/**
 * @internal
 */
final class BlockTranslator{
	use ProtocolSingletonTrait;

	public const CANONICAL_BLOCK_STATES_PATH = 0;
	public const BLOCK_STATE_META_MAP_PATH = 1;

	public const PATHS = [
		ProtocolInfo::CURRENT_PROTOCOL => [
			self::CANONICAL_BLOCK_STATES_PATH => '',
			self::BLOCK_STATE_META_MAP_PATH => '',
		],
		ProtocolInfo::PROTOCOL_1_19_70 => [
			self::CANONICAL_BLOCK_STATES_PATH => '-1.19.70',
			self::BLOCK_STATE_META_MAP_PATH => '-1.19.70',
		],
		ProtocolInfo::PROTOCOL_1_19_63 => [
			self::CANONICAL_BLOCK_STATES_PATH => '-1.19.63',
			self::BLOCK_STATE_META_MAP_PATH => '-1.19.63',
		],
		ProtocolInfo::PROTOCOL_1_19_50 => [
			self::CANONICAL_BLOCK_STATES_PATH => '-1.19.50',
			self::BLOCK_STATE_META_MAP_PATH => '-1.19.50',
		],
		ProtocolInfo::PROTOCOL_1_19_40 => [
			self::CANONICAL_BLOCK_STATES_PATH => '-1.19.40',
			self::BLOCK_STATE_META_MAP_PATH => '-1.19.40',
		],
		ProtocolInfo::PROTOCOL_1_19_10 => [
			self::CANONICAL_BLOCK_STATES_PATH => '-1.19.10',
			self::BLOCK_STATE_META_MAP_PATH => '-1.19.10',
		],
		ProtocolInfo::PROTOCOL_1_19_0 => [
			self::CANONICAL_BLOCK_STATES_PATH => '-1.19.10',
			self::BLOCK_STATE_META_MAP_PATH => '-1.19.10',
		],
		ProtocolInfo::PROTOCOL_1_18_30 => [
			self::CANONICAL_BLOCK_STATES_PATH => '-1.18.30',
			self::BLOCK_STATE_META_MAP_PATH => '-1.19.10',
		],
		ProtocolInfo::PROTOCOL_1_18_10 => [
			self::CANONICAL_BLOCK_STATES_PATH => '-1.18.10',
			self::BLOCK_STATE_META_MAP_PATH => '-1.19.10',
		],
	];

	/**
	 * @var int[]
	 * @phpstan-var array<int, int>
	 */
	private array $networkIdCache = [];

	/** Used when a blockstate can't be correctly serialized (e.g. because it's unknown) */
	private BlockStateData $fallbackStateData;
	private int $fallbackStateId;

	private static function make(int $protocolId) : self{
		$dictionaryProtocol = self::getDictionaryProtocol($protocolId);
		$canonicalBlockStatesRaw = Filesystem::fileGetContents(str_replace(".nbt", self::PATHS[$dictionaryProtocol][self::CANONICAL_BLOCK_STATES_PATH] . ".nbt", BedrockDataFiles::CANONICAL_BLOCK_STATES_NBT));
		$metaMappingRaw = Filesystem::fileGetContents(str_replace(".json", self::PATHS[$dictionaryProtocol][self::BLOCK_STATE_META_MAP_PATH] . ".json", BedrockDataFiles::BLOCK_STATE_META_MAP_JSON));

		if(($blockStateSchemaId = self::getBlockStateSchemaId($protocolId)) !== null){
			$blockStateDowngrader = new BlockStateDowngrader(BlockStateDowngradeSchemaUtils::loadSchemas(
				Path::join(BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH, 'nbt_upgrade_schema'),
				$blockStateSchemaId
			));
		}

		return new self(
			BlockStateDictionary::loadFromString($canonicalBlockStatesRaw, $metaMappingRaw),
			GlobalBlockStateHandlers::getSerializer(),
			$blockStateDowngrader ?? null
		);
	}

	public function __construct(
		private BlockStateDictionary $blockStateDictionary,
		private BlockStateSerializer $blockStateSerializer,
		private ?BlockStateDowngrader $blockStateDowngrader
	){
		$this->fallbackStateData = BlockStateData::current(BlockTypeNames::INFO_UPDATE, []);
		$this->fallbackStateId = $this->blockStateDictionary->lookupStateIdFromData($this->fallbackStateData) ??
			throw new AssumptionFailedError(BlockTypeNames::INFO_UPDATE . " should always exist");
	}

	public function internalIdToNetworkId(int $internalStateId) : int{
		if(isset($this->networkIdCache[$internalStateId])){
			return $this->networkIdCache[$internalStateId];
		}

		try{
			$blockStateData = $this->blockStateSerializer->serialize($internalStateId);
			if($this->blockStateDowngrader !== null){
				$blockStateData = $this->blockStateDowngrader->downgrade($blockStateData);
			}

			$networkId = $this->blockStateDictionary->lookupStateIdFromData($blockStateData);
			if($networkId === null){
				throw new AssumptionFailedError("Unmapped blockstate returned by blockstate serializer: " . $blockStateData->toNbt());
			}
		}catch(BlockStateSerializeException){
			//TODO: this will swallow any error caused by invalid block properties; this is not ideal, but it should be
			//covered by unit tests, so this is probably a safe assumption.
			$networkId = $this->fallbackStateId;
		}

		return $this->networkIdCache[$internalStateId] = $networkId;
	}

	/**
	 * Looks up the network state data associated with the given internal state ID.
	 */
	public function internalIdToNetworkStateData(int $internalStateId) : BlockStateData{
		//we don't directly use the blockstate serializer here - we can't assume that the network blockstate NBT is the
		//same as the disk blockstate NBT, in case we decide to have different world version than network version (or in
		//case someone wants to implement multi version).
		$networkRuntimeId = $this->internalIdToNetworkId($internalStateId);

		return $this->blockStateDictionary->generateDataFromStateId($networkRuntimeId) ?? throw new AssumptionFailedError("We just looked up this state ID, so it must exist");
	}

	public function getBlockStateDictionary() : BlockStateDictionary{ return $this->blockStateDictionary; }

	public function getFallbackStateData() : BlockStateData{ return $this->fallbackStateData; }

	public function getBlockStateDowngrader() : ?BlockStateDowngrader{ return $this->blockStateDowngrader; }

	public function getBlockStateUpgrader() : ?BlockStateUpgrader{ return GlobalBlockStateHandlers::getUpgrader()->getBlockStateUpgrader(); }

	public static function getDictionaryProtocol(int $protocolId) : int{
		return match ($protocolId) {
			ProtocolInfo::PROTOCOL_1_19_60 => ProtocolInfo::PROTOCOL_1_19_63,

			ProtocolInfo::PROTOCOL_1_19_30,
			ProtocolInfo::PROTOCOL_1_19_21,
			ProtocolInfo::PROTOCOL_1_19_20 => ProtocolInfo::PROTOCOL_1_19_40,

			default => $protocolId
		};
	}

	public static function convertProtocol(int $protocolId) : int{
		$dictionaryProtocol = self::getDictionaryProtocol($protocolId);
		$schemaId = self::getBlockStateSchemaId($dictionaryProtocol);

		return $schemaId === self::getBlockStateSchemaId($dictionaryProtocol) ? $dictionaryProtocol : $protocolId;
	}

	private static function getBlockStateSchemaId(int $protocolId) : ?int{
		return match($protocolId){
			ProtocolInfo::PROTOCOL_1_19_80 => null,

			ProtocolInfo::PROTOCOL_1_19_70 => 181,

			ProtocolInfo::PROTOCOL_1_19_63,
			ProtocolInfo::PROTOCOL_1_19_60 => 171,

			ProtocolInfo::PROTOCOL_1_19_50,
			ProtocolInfo::PROTOCOL_1_19_40,
			ProtocolInfo::PROTOCOL_1_19_10 => 161,

			ProtocolInfo::PROTOCOL_1_19_0 => 151,
			ProtocolInfo::PROTOCOL_1_18_30 => 141,
			ProtocolInfo::PROTOCOL_1_18_10 => 121,
			default => throw new AssumptionFailedError("Unknown protocol ID $protocolId"),
		};
	}
}
