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

use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\item\Book;
use pocketmine\item\Item;
use pocketmine\item\utils\ItemRepairUtils;
use function array_values;

final class EnchantingLevelTable{
	private const MIN_ENCHANTMENT_LEVEL = [
		// https://minecraft.fandom.com/wiki/Enchanting/Levels
		// I didn't bother to reverse-engineer the code for generating this table, all values are sourced from the wiki

		EnchantmentIds::PROTECTION => [[1, 12], [12, 23], [23, 34], [34, 45]],
		EnchantmentIds::FIRE_PROTECTION => [[10, 18], [18, 26], [26, 34], [34, 42]],
		EnchantmentIds::FEATHER_FALLING => [[5, 11], [11, 17], [17, 23], [23, 29]],
		EnchantmentIds::BLAST_PROTECTION => [[5, 13], [13, 21], [21, 29], [29, 37]],
		EnchantmentIds::PROJECTILE_PROTECTION => [[3, 9], [9, 15], [15, 21], [21, 27]],
		EnchantmentIds::RESPIRATION => [[10, 40], [20, 50], [30, 60]],
		EnchantmentIds::AQUA_AFFINITY => [[1, 41]],
		EnchantmentIds::THORNS => [[10, 60], [30, 70], [50, 80]],
		EnchantmentIds::DEPTH_STRIDER => [[10, 25], [20, 35], [30, 45]],
		EnchantmentIds::FROST_WALKER => [[10, 25], [20, 35]],
		EnchantmentIds::BINDING => [[25, 50]],
		EnchantmentIds::SOUL_SPEED => [[10, 25], [20, 35], [30, 45]],
		// todo: swift sneak?

		EnchantmentIds::SHARPNESS => [[1, 21], [12, 32], [23, 43], [34, 54], [45, 65]],
		EnchantmentIds::SMITE => [[5, 25], [13, 33], [21, 41], [29, 49], [37, 57]],
		EnchantmentIds::BANE_OF_ARTHROPODS => [[5, 25], [13, 33], [21, 41], [29, 49], [37, 57]],
		EnchantmentIds::KNOCKBACK => [[5, 55], [25, 75]],
		EnchantmentIds::FIRE_ASPECT => [[10, 60], [30, 80]],
		EnchantmentIds::LOOTING => [[15, 65], [24, 74], [33, 83]],
		//EnchantmentIds::SWEEPING_EDGE => [[5, 20], [14, 29], [23, 38]],

		EnchantmentIds::POWER => [[1, 16], [11, 26], [21, 36], [31, 46], [41, 56]],
		EnchantmentIds::PUNCH => [[12, 37], [32, 57]],
		EnchantmentIds::FLAME => [[20, 50]],
		EnchantmentIds::INFINITY => [[20, 50]],

		EnchantmentIds::EFFICIENCY => [[1, 51], [11, 61], [21, 71], [31, 81], [41, 91]],
		EnchantmentIds::SILK_TOUCH => [[15, 65]],
		EnchantmentIds::FORTUNE => [[15, 65], [24, 74], [33, 83]],

		EnchantmentIds::LUCK_OF_THE_SEA => [[15, 65], [24, 74], [33, 83]],
		EnchantmentIds::LURE => [[15, 65], [24, 74], [33, 83]],

		EnchantmentIds::UNBREAKING => [[5, 55], [13, 63], [21, 71]],
		EnchantmentIds::MENDING => [[25, 75]],
		EnchantmentIds::VANISHING => [[25, 50]],

		EnchantmentIds::CHANNELING => [[25, 50]],
		EnchantmentIds::IMPALING => [[1, 21], [9, 29], [17, 37], [25, 45], [33, 53]],
		EnchantmentIds::LOYALTY => [[12, 50], [19, 50], [26, 50]],
		EnchantmentIds::RIPTIDE => [[17, 50], [24, 50], [31, 50]],

		EnchantmentIds::MULTISHOT => [[20, 50]],
		EnchantmentIds::PIERCING => [[1, 50], [11, 50], [21, 50], [31, 50]],
		EnchantmentIds::QUICK_CHARGE => [[12, 50], [32, 50], [52, 50]],
	];

	private const TREASURE_ENCHANTS = [
		EnchantmentIds::FROST_WALKER => true,
		EnchantmentIds::SWIFT_SNEAK => true,
		EnchantmentIds::BINDING => true,
		EnchantmentIds::SOUL_SPEED => true,
		EnchantmentIds::MENDING => true,
		EnchantmentIds::VANISHING => true
	];

	private function __construct(){
		//NOOP
	}

	/**
	 * @return EnchantmentInstance[]
	 */
	public static function getEnchants(Item $item, int $enchantingLevel) : array{
		$itemFlag = ItemRepairUtils::getItemFlagFor($item);
		$map = EnchantmentIdMap::getInstance();

		$candidates = [];
		foreach(self::MIN_ENCHANTMENT_LEVEL as $enchantmentId => $rangeList){
			if(isset(self::TREASURE_ENCHANTS[$enchantmentId])){
				continue;
			}

			$enchantment = $map->fromId($enchantmentId);
			if($enchantment === null){
				continue;
			}
			if(
				!$enchantment->hasPrimaryItemType($itemFlag) &&
				!$enchantment->hasSecondaryItemType($itemFlag) &&
				!$item instanceof Book
			){
				continue;
			}

			foreach($rangeList as $i => [$min, $max]){
				if($enchantingLevel < $min || $enchantingLevel > $max){
					continue;
				}
				// we don't need to check previous entry because PHP arrays are naturally ordered,
				// and our data is pre-sorted already.
				$candidates[$enchantmentId] = new EnchantmentInstance($enchantment, $i + 1);
			}
		}
		return array_values($candidates);
	}
}
