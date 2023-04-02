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

use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\item\enchantment\Enchantment;

final class IncompatibleEnchantMap{
	public const MAP = [
		EnchantmentIds::PROTECTION => [EnchantmentIds::FIRE_PROTECTION => true, EnchantmentIds::BLAST_PROTECTION => true, EnchantmentIds::PROJECTILE_PROTECTION => true],
		EnchantmentIds::FIRE_PROTECTION => [EnchantmentIds::PROTECTION => true, EnchantmentIds::BLAST_PROTECTION => true, EnchantmentIds::PROJECTILE_PROTECTION => true],
		EnchantmentIds::BLAST_PROTECTION => [EnchantmentIds::PROTECTION => true, EnchantmentIds::FIRE_PROTECTION => true, EnchantmentIds::PROJECTILE_PROTECTION => true],
		EnchantmentIds::PROJECTILE_PROTECTION => [EnchantmentIds::PROTECTION => true, EnchantmentIds::FIRE_PROTECTION => true, EnchantmentIds::BLAST_PROTECTION => true],
		EnchantmentIds::DEPTH_STRIDER => [EnchantmentIds::FROST_WALKER => true],
		EnchantmentIds::SHARPNESS => [EnchantmentIds::SMITE => true, EnchantmentIds::BANE_OF_ARTHROPODS => true],
		EnchantmentIds::SMITE => [EnchantmentIds::SHARPNESS => true, EnchantmentIds::BANE_OF_ARTHROPODS => true],
		EnchantmentIds::BANE_OF_ARTHROPODS => [EnchantmentIds::SHARPNESS => true, EnchantmentIds::SMITE => true],
		EnchantmentIds::LOOTING => [EnchantmentIds::SILK_TOUCH => true],
		EnchantmentIds::SILK_TOUCH => [EnchantmentIds::FORTUNE => true, EnchantmentIds::LOOTING => true, EnchantmentIds::LUCK_OF_THE_SEA => true],
		EnchantmentIds::FORTUNE => [EnchantmentIds::SILK_TOUCH => true],
		EnchantmentIds::INFINITY => [EnchantmentIds::MENDING => true],
		EnchantmentIds::LUCK_OF_THE_SEA => [EnchantmentIds::SILK_TOUCH => true],
		EnchantmentIds::FROST_WALKER => [EnchantmentIds::DEPTH_STRIDER => true],
		EnchantmentIds::MENDING => [EnchantmentIds::INFINITY => true],
		EnchantmentIds::RIPTIDE => [EnchantmentIds::LOYALTY => true, EnchantmentIds::CHANNELING => true],
		EnchantmentIds::LOYALTY => [EnchantmentIds::RIPTIDE => true],
		EnchantmentIds::CHANNELING => [EnchantmentIds::RIPTIDE => true],
		EnchantmentIds::MULTISHOT => [EnchantmentIds::PIERCING => true],
		EnchantmentIds::PIERCING => [EnchantmentIds::MULTISHOT => true],
	];

	public static function isIncompatible(Enchantment $first, Enchantment $second) : bool{
		$map = EnchantmentIdMap::getInstance();
		$firstId = $map->toId($first);
		$secondId = $map->toId($second);
		return isset(self::MAP[$firstId][$secondId]) || isset(self::MAP[$secondId][$firstId]);
	}
}