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

final class EnchantmentTransfer{

	private const RARITY_TO_MULTIPLIER = [
		Rarity::COMMON => 1,
		Rarity::UNCOMMON => 1,
		Rarity::RARE => 2,
		Rarity::MYTHIC => 4,
	];
	private const SOURCE_RARITY_TO_MULTIPLIER = [
		Rarity::COMMON => 1,
		Rarity::UNCOMMON => 2,
		Rarity::RARE => 2,
		Rarity::MYTHIC => 2,
	];

	private function __construct(){
		//NOOP
	}

	public static function getCost(Enchantment $type, int $levelDifference, bool $transferFromItem) : int{
		$rarity = $type->getRarity();
		$cost = self::RARITY_TO_MULTIPLIER[$rarity] * $levelDifference;
		if($transferFromItem){
			$cost *= self::SOURCE_RARITY_TO_MULTIPLIER[$rarity];
		}
		return $cost;
	}
}
