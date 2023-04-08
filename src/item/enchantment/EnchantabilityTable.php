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
use pocketmine\item\ItemIds;
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
			return match ($item->getId()) {
				ItemIds::LEATHER_HELMET,
				ItemIds::LEATHER_CHESTPLATE,
				ItemIds::LEATHER_LEGGINGS,
				ItemIds::LEATHER_BOOTS,
				ItemIds::LEATHER_HORSE_ARMOR => 15,

				ItemIds::CHAIN_HELMET,
				ItemIds::CHAIN_CHESTPLATE,
				ItemIds::CHAIN_LEGGINGS,
				ItemIds::CHAIN_BOOTS => 12,

				ItemIds::IRON_HELMET,
				ItemIds::IRON_CHESTPLATE,
				ItemIds::IRON_LEGGINGS,
				ItemIds::IRON_BOOTS,
				ItemIds::IRON_HORSE_ARMOR,
				ItemIds::TURTLE_HELMET => 9,

				ItemIds::GOLD_HELMET,
				ItemIds::GOLD_CHESTPLATE,
				ItemIds::GOLD_LEGGINGS,
				ItemIds::GOLD_BOOTS,
				ItemIds::GOLD_HORSE_ARMOR => 25,

				ItemIds::DIAMOND_HELMET,
				ItemIds::DIAMOND_CHESTPLATE,
				ItemIds::DIAMOND_LEGGINGS,
				ItemIds::DIAMOND_BOOTS,
				ItemIds::DIAMOND_HORSE_ARMOR => 10,

				// TODO: Netherite Armor => 15
				default => 1
			};
		}
		return 1;
	}
}
