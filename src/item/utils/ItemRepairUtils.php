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

namespace pocketmine\item\utils;

use pocketmine\block\BlockToolType;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\ItemFlags;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\TieredTool;
use pocketmine\item\ToolTier;
use pocketmine\item\VanillaItems;

final class ItemRepairUtils{
	private const ARMOR_SLOT_TO_ITEM_FLAG = [
		ArmorInventory::SLOT_HEAD => ItemFlags::HEAD,
		ArmorInventory::SLOT_CHEST => ItemFlags::TORSO,
		ArmorInventory::SLOT_LEGS => ItemFlags::LEGS,
		ArmorInventory::SLOT_FEET => ItemFlags::FEET,
	];
	private const TOOL_TYPE_TO_ITEM_FLAG = [
		BlockToolType::SHOVEL => ItemFlags::SHOVEL,
		BlockToolType::PICKAXE => ItemFlags::PICKAXE,
		BlockToolType::AXE => ItemFlags::AXE,
		BlockToolType::SHEARS => ItemFlags::SHEARS,
		BlockToolType::HOE => ItemFlags::HOE,
		BlockToolType::SWORD => ItemFlags::SWORD,
	];
	private const ITEM_ID_TO_ITEM_FLAG = [
		ItemTypeIds::FISHING_ROD => ItemFlags::FISHING_ROD,
		ItemTypeIds::BOW => ItemFlags::BOW,
		ItemTypeIds::CROSSBOW => 0x10000, // according to features/Enchants
		ItemTypeIds::ELYTRA => ItemFlags::ELYTRA,
		ItemTypeIds::TRIDENT => ItemFlags::TRIDENT,
	];

	private function __construct(){
		//NOOP
	}

	public static function getItemFlagFor(Item $item) : int{
		if($item instanceof Armor){
			return self::ARMOR_SLOT_TO_ITEM_FLAG[$item->getArmorSlot()] ?? ItemFlags::NONE;
		}

		$flag = self::ITEM_ID_TO_ITEM_FLAG[$item->getTypeId()] ?? ItemFlags::NONE;
		if($flag === ItemFlags::NONE){
			return self::TOOL_TYPE_TO_ITEM_FLAG[$item->getBlockToolType()] ?? ItemFlags::NONE;
		}

		return $flag;
	}

	public static function isRepairableWith(Durable $target, Item $sacrifice) : bool{
		$validRepairMaterials = self::getRepairMaterial($target);
		if($validRepairMaterials === null){
			return false;
		}
		foreach($validRepairMaterials as $material){
			if($material->equals($sacrifice, false, false)){
				return true;
			}
		}
		return false;
	}

	/**
	 * This method determines what item should be used to repair tools, armor, etc.
	 *
	 * @return Item[]|null
	 */
	public static function getRepairMaterial(Durable $target) : ?array{
		if($target instanceof TieredTool){
			return match ($target->getTier()->id()) {
				ToolTier::WOOD()->id() => [VanillaBlocks::OAK_PLANKS()->asItem()],
				ToolTier::STONE()->id() => [VanillaBlocks::COBBLESTONE()->asItem()],
				ToolTier::GOLD()->id() => [VanillaItems::GOLD_INGOT()],
				ToolTier::IRON()->id() => [VanillaItems::IRON_INGOT()],
				ToolTier::DIAMOND()->id() => [VanillaItems::DIAMOND()],
				default => null
				// TODO: Netherite tools
			};
		}
		return match ($target->getTypeId()) {
			ItemTypeIds::LEATHER_CAP,
			ItemTypeIds::LEATHER_TUNIC,
			ItemTypeIds::LEATHER_PANTS,
			ItemTypeIds::LEATHER_BOOTS => [VanillaItems::LEATHER()],

			ItemTypeIds::IRON_HELMET,
			ItemTypeIds::IRON_CHESTPLATE,
			ItemTypeIds::IRON_LEGGINGS,
			ItemTypeIds::IRON_BOOTS,
			ItemTypeIds::CHAINMAIL_HELMET,
			ItemTypeIds::CHAINMAIL_CHESTPLATE,
			ItemTypeIds::CHAINMAIL_LEGGINGS,
			ItemTypeIds::CHAINMAIL_BOOTS => [VanillaItems::IRON_INGOT()],

			ItemTypeIds::GOLDEN_HELMET,
			ItemTypeIds::GOLDEN_CHESTPLATE,
			ItemTypeIds::GOLDEN_LEGGINGS,
			ItemTypeIds::GOLDEN_BOOTS => [VanillaItems::GOLD_INGOT()],

			ItemTypeIds::DIAMOND_HELMET,
			ItemTypeIds::DIAMOND_CHESTPLATE,
			ItemTypeIds::DIAMOND_LEGGINGS,
			ItemTypeIds::DIAMOND_BOOTS => [VanillaItems::DIAMOND()],

			ItemTypeIds::ELYTRA => [VanillaItems::PHANTOM_MEMBRANE()],
			ItemTypeIds::TURTLE_HELMET => [VanillaItems::SCUTE()],
			ItemTypeIds::SHIELD => [
				VanillaBlocks::OAK_PLANKS()->asItem(),
				VanillaBlocks::BIRCH_PLANKS()->asItem(),
				VanillaBlocks::ACACIA_PLANKS()->asItem(),
				VanillaBlocks::DARK_OAK_PLANKS()->asItem(),
				VanillaBlocks::JUNGLE_PLANKS()->asItem(),
				VanillaBlocks::SPRUCE_PLANKS()->asItem(),
			],
			default => null
			//TODO: Netherite armor
		};
	}
}
