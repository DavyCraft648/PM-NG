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
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
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
		ItemIds::FISHING_ROD => ItemFlags::FISHING_ROD,
		ItemIds::BOW => ItemFlags::BOW,
		ItemIds::CROSSBOW => 0x10000, // according to features/Enchants
		ItemIds::ELYTRA => ItemFlags::ELYTRA,
		ItemIds::TRIDENT => ItemFlags::TRIDENT,
		ItemIds::CARROT_ON_A_STICK => ItemFlags::CARROT_STICK,
	];

	private function __construct(){
		//NOOP
	}

	public static function getItemFlagFor(Item $item) : int{
		if($item instanceof Armor){
			return self::ARMOR_SLOT_TO_ITEM_FLAG[$item->getArmorSlot()] ?? ItemFlags::NONE;
		}

		$flag = self::ITEM_ID_TO_ITEM_FLAG[$item->getId()] ?? ItemFlags::NONE;
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
		return match ($target->getId()) {
			ItemIds::LEATHER_CAP,
			ItemIds::LEATHER_TUNIC,
			ItemIds::LEATHER_PANTS,
			ItemIds::LEATHER_BOOTS => [VanillaItems::LEATHER()],

			ItemIds::IRON_HELMET,
			ItemIds::IRON_CHESTPLATE,
			ItemIds::IRON_LEGGINGS,
			ItemIds::IRON_BOOTS,
			ItemIds::CHAIN_HELMET,
			ItemIds::CHAIN_CHESTPLATE,
			ItemIds::CHAIN_LEGGINGS,
			ItemIds::CHAIN_BOOTS => [VanillaItems::IRON_INGOT()],

			ItemIds::GOLD_HELMET,
			ItemIds::GOLD_CHESTPLATE,
			ItemIds::GOLD_LEGGINGS,
			ItemIds::GOLD_BOOTS => [VanillaItems::GOLD_INGOT()],

			ItemIds::DIAMOND_HELMET,
			ItemIds::DIAMOND_CHESTPLATE,
			ItemIds::DIAMOND_LEGGINGS,
			ItemIds::DIAMOND_BOOTS => [VanillaItems::DIAMOND()],

			ItemIds::ELYTRA => [ItemFactory::getInstance()->get(ItemIds::PHANTOM_MEMBRANE)],
			ItemIds::TURTLE_HELMET => [VanillaItems::SCUTE()],
			ItemIds::SHIELD => [
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
