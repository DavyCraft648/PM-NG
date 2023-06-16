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

use pocketmine\data\bedrock\item\downgrade\ItemIdMetaDowngrader;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\data\bedrock\item\ItemDeserializer;
use pocketmine\data\bedrock\item\ItemSerializer;
use pocketmine\data\bedrock\item\ItemTypeDeserializeException;
use pocketmine\data\bedrock\item\ItemTypeSerializeException;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\format\io\GlobalItemDataHandlers;

/**
 * This class handles translation between network item ID+metadata to PocketMine-MP internal ID+metadata and vice versa.
 */
final class ItemTranslator{
	public const PATHS = [
		ProtocolInfo::CURRENT_PROTOCOL => "",

		ProtocolInfo::PROTOCOL_1_19_80 => "-1.19.80",

		ProtocolInfo::PROTOCOL_1_19_70 => "-1.19.70",

		ProtocolInfo::PROTOCOL_1_19_63,
		ProtocolInfo::PROTOCOL_1_19_60 => "-1.19.63",

		ProtocolInfo::PROTOCOL_1_19_50 => "-1.19.50",

		ProtocolInfo::PROTOCOL_1_19_40,
		ProtocolInfo::PROTOCOL_1_19_30,
		ProtocolInfo::PROTOCOL_1_19_21,
		ProtocolInfo::PROTOCOL_1_19_20,
		ProtocolInfo::PROTOCOL_1_19_10 => "-1.19.40",

		ProtocolInfo::PROTOCOL_1_19_0 => "-1.19.0",

		ProtocolInfo::PROTOCOL_1_18_30 => "-1.18.30",

		ProtocolInfo::PROTOCOL_1_18_10 => "-1.18.10",
	];

	public const NO_BLOCK_RUNTIME_ID = 0; //this is technically a valid block runtime ID, but is used to represent "no block" (derp mojang)

	public function __construct(
		private ItemTypeDictionary $itemTypeDictionary,
		private BlockTranslator $blockTranslator,
		private ItemSerializer $itemSerializer,
		private ItemDeserializer $itemDeserializer,
		private BlockItemIdMap $blockItemIdMap,
		private ?ItemIdMetaDowngrader $itemDataDowngrader,
	){}

	/**
	 * @return int[]|null
	 * @phpstan-return array{int, int, ?int}|null
	 */
	public function toNetworkIdQuiet(Item $item) : ?array{
		try{
			return $this->toNetworkId($item);
		}catch(ItemTypeSerializeException){
			return null;
		}
	}

	/**
	 * @return int[]
	 * @phpstan-return array{int, int, ?int}
	 *
	 * @throws ItemTypeSerializeException
	 */
	public function toNetworkId(Item $item) : array{
		//TODO: we should probably come up with a cache for this

		$itemData = $this->itemSerializer->serializeType($item);

		if($this->itemDataDowngrader !== null){
			[$name, $meta] = $this->itemDataDowngrader->downgrade($itemData->getName(), $itemData->getMeta());

			try {
				$numericId = $this->itemTypeDictionary->fromStringId($name);
			} catch (\InvalidArgumentException $e) {
				$numericId = $this->itemTypeDictionary->fromStringId($itemData->getName());
				$meta = $itemData->getMeta();
			}
		} else {
			$numericId = $this->itemTypeDictionary->fromStringId($itemData->getName());
		}

		$blockStateData = $itemData->getBlock();

		if($blockStateData !== null){
			if(($blockStateDowngrader = $this->blockTranslator->getBlockStateDowngrader()) !== null){
				$blockStateData = $blockStateDowngrader->downgrade($blockStateData);
			}

			$blockRuntimeId = $this->blockTranslator->getBlockStateDictionary()->lookupStateIdFromData($blockStateData);
			if($blockRuntimeId === null){
				throw new AssumptionFailedError("Unmapped blockstate returned by blockstate serializer: " . $blockStateData->toNbt());
			}
		}else{
			$blockRuntimeId = null;
		}

		return [$numericId, $meta ?? $itemData->getMeta(), $blockRuntimeId];
	}

	/**
	 * @throws ItemTypeSerializeException
	 */
	public function toNetworkNbt(Item $item) : CompoundTag{
		//TODO: this relies on the assumption that network item NBT is the same as disk item NBT, which may not always
		//be true - if we stick on an older world version while updating network version, this could be a problem (and
		//may be a problem for multi version implementations)
		return $this->itemSerializer->serializeStack($item)->toNbt();
	}

	/**
	 * @throws TypeConversionException
	 */
	public function fromNetworkId(int $networkId, int $networkMeta, int $networkBlockRuntimeId) : Item{
		try{
			$stringId = $this->itemTypeDictionary->fromIntId($networkId);
		}catch(\InvalidArgumentException $e){
			//TODO: a quiet version of fromIntId() would be better than catching InvalidArgumentException
			throw TypeConversionException::wrap($e, "Invalid network itemstack ID $networkId");
		}

		$blockStateData = null;
		if($this->blockItemIdMap->lookupBlockId($stringId) !== null){
			$blockStateData = $this->blockTranslator->getBlockStateDictionary()->generateDataFromStateId($networkBlockRuntimeId);
			if($blockStateData === null){
				throw new TypeConversionException("Blockstate runtimeID $networkBlockRuntimeId does not correspond to any known blockstate");
			}

			if(($blockStateUpgrader = $this->blockTranslator->getBlockStateUpgrader()) !== null){
				$blockStateData = $blockStateUpgrader->upgrade($blockStateData);
			}
		}elseif($networkBlockRuntimeId !== self::NO_BLOCK_RUNTIME_ID){
			throw new TypeConversionException("Item $stringId is not a blockitem, but runtime ID $networkBlockRuntimeId was provided");
		}

		[$stringId, $networkMeta] = GlobalItemDataHandlers::getUpgrader()->getIdMetaUpgrader()->upgrade($stringId, $networkMeta);

		try{
			return $this->itemDeserializer->deserializeType(new SavedItemData($stringId, $networkMeta, $blockStateData));
		}catch(ItemTypeDeserializeException $e){
			throw TypeConversionException::wrap($e, "Invalid network itemstack data");
		}
	}

	public static function getItemSchemaId(int $protocolId) : ?int{
		return match($protocolId){
			ProtocolInfo::PROTOCOL_1_20_0 => null,

			ProtocolInfo::PROTOCOL_1_19_80 => 111,

			ProtocolInfo::PROTOCOL_1_19_70 => 101,

			ProtocolInfo::PROTOCOL_1_19_63,
			ProtocolInfo::PROTOCOL_1_19_60,
			ProtocolInfo::PROTOCOL_1_19_50,
			ProtocolInfo::PROTOCOL_1_19_40,
			ProtocolInfo::PROTOCOL_1_19_30 => 91,

			ProtocolInfo::PROTOCOL_1_19_21,
			ProtocolInfo::PROTOCOL_1_19_20,
			ProtocolInfo::PROTOCOL_1_19_10,
			ProtocolInfo::PROTOCOL_1_19_0,
			ProtocolInfo::PROTOCOL_1_18_30 => 81,

			ProtocolInfo::PROTOCOL_1_18_10 => 71,
			default => throw new AssumptionFailedError("Unknown protocol ID $protocolId"),
		};
	}
}
