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

namespace pocketmine\item\enchantment;

use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\TieredTool;
use pocketmine\item\ToolTier;

final class EnchantabilityTable{
	private function __construct(){
		//NOOP
	}

	public static function getFor(Item $item) : int{
		if($item instanceof TieredTool){
			return match ($item->getTier()) {
				ToolTier::WOOD() => 15,
				ToolTier::STONE() => 5,
				ToolTier::IRON() => 14,
				ToolTier::GOLD() => 22,
				ToolTier::DIAMOND() => 10,

				// TODO: Netherite Items => 15
				default => 1
			};
		}elseif($item instanceof Armor){
			return match ($item->getTypeId()) {
				ItemTypeIds::LEATHER_CAP,
				ItemTypeIds::LEATHER_TUNIC,
				ItemTypeIds::LEATHER_PANTS,
				ItemTypeIds::LEATHER_BOOTS,
				ItemTypeIds::LEATHER_HORSE_ARMOR => 15,

				ItemTypeIds::CHAINMAIL_HELMET,
				ItemTypeIds::CHAINMAIL_CHESTPLATE,
				ItemTypeIds::CHAINMAIL_LEGGINGS,
				ItemTypeIds::CHAINMAIL_BOOTS => 12,

				ItemTypeIds::IRON_HELMET,
				ItemTypeIds::IRON_CHESTPLATE,
				ItemTypeIds::IRON_LEGGINGS,
				ItemTypeIds::IRON_BOOTS,
				ItemTypeIds::IRON_HORSE_ARMOR,
				ItemTypeIds::TURTLE_HELMET => 9,

				ItemTypeIds::GOLDEN_HELMET,
				ItemTypeIds::GOLDEN_CHESTPLATE,
				ItemTypeIds::GOLDEN_LEGGINGS,
				ItemTypeIds::GOLDEN_BOOTS,
				ItemTypeIds::GOLDEN_HORSE_ARMOR => 25,

				ItemTypeIds::DIAMOND_HELMET,
				ItemTypeIds::DIAMOND_CHESTPLATE,
				ItemTypeIds::DIAMOND_LEGGINGS,
				ItemTypeIds::DIAMOND_BOOTS,
				ItemTypeIds::DIAMOND_HORSE_ARMOR => 10,

				// TODO: Netherite Armor => 15
				default => 1
			};
		}
		return 1;
	}
}
