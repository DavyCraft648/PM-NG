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
use pocketmine\item\Item;
use pocketmine\utils\Random;
use pocketmine\utils\WeightedRandom;
use function count;
use function floor;
use function max;
use function min;
use function round;
use function rtrim;
use function strlen;

final class EnchantingMechanics{

	private const RUNES = [
		"the", "elder", "scrolls", "klaatu", "berata", "niktu", "xyzzy", "bless", "curse", "light", "darkness", "fire",
		"air", "earth", "water", "hot", "dry", "cold", "wet", "ignite", "snuff", "embiggen", "twist", "shorten",
		"stretch", "fiddle", "destroy", "imbue", "galvanize", "enchant", "free", "limited", "range", "of", "towards",
		"inside", "sphere", "cube", "self", "other", "ball", "mental", "physical", "grow", "shrink", "demon",
		"elemental", "spirit", "animal", "creature", "beast", "humanoid", "undead", "fresh", "stale"
	];

	private function __construct(){
		//NOOP
	}

	public static function generateName(Random $random) : string{
		$name = "";
		$numName = $random->nextRange(3, 5);
		for($j = 0; $j < $numName && strlen($name) < 10; $j++){
			$name .= self::RUNES[$random->nextRange(end: (int) floor(count(self::RUNES) / 2) - 1) + $random->nextRange(end: (int) floor(count(self::RUNES) / 2) - 1)] . " ";
		}
		return rtrim($name);
	}

	/**
	 * @return array<EnchantmentEntry>
	 */
	public static function generateEnchantOptions(Random $random, int $bookshelfCount, Item $item) : array{
		if($item->isNull() || count($item->getEnchantments()) > 0){
			return [];
		}
		$options = [];
		$enchantMap = EnchantmentIdMap::getInstance();

		// https://minecraft.fandom.com/wiki/Enchanting_mechanics#Basic_mechanics
		$bookshelfCount = min(15, $bookshelfCount);
		$baseEnchantmentLevel = (int) ($random->nextRange(1, 8) + floor($bookshelfCount / 2) + $random->nextRange(0, $bookshelfCount));

		foreach([
			(int) floor(max($baseEnchantmentLevel / 3, 1)), // top
			(int) floor(($baseEnchantmentLevel * 2) / 3 + 1), // middle
			(int) floor(max($baseEnchantmentLevel, $bookshelfCount * 2)) // bottom
		] as $i => $enchantmentLevel){
			// https://minecraft.fandom.com/wiki/Enchanting_mechanics#How_enchantments_are_chosen
			$enchantability = EnchantabilityTable::getFor($item);
			$randEnchantability = 1 + $random->nextRange(end: (int) floor($enchantability / 4)) + $random->nextRange(end: (int) floor($enchantability / 4));
			$k = $enchantmentLevel + $randEnchantability;
			$randBonusPercent = 1 + ($random->nextFloat() + $random->nextFloat() - 1) * 0.15;
			$finalLevel = max(1, (int) round($k * $randBonusPercent));

			$weightedRandom = new WeightedRandom($random);
			foreach(EnchantingLevelTable::getEnchants($item, $finalLevel) as $enchant){
				$weightedRandom->insert($enchant, $enchant->getType()->getRarity());
			}
			/** @var EnchantmentInstance $picked */
			$picked = $weightedRandom->next();
			if($picked === null){
				continue;
			}
			$entryEnchants = [$picked];

			for(; ((int) floor(($finalLevel + 1) / 50)) > 0; $finalLevel = (int) floor($finalLevel / 2)){
				/** @var EnchantmentInstance $picked */
				$picked = $weightedRandom->next();
				if($picked === null){
					break;
				}
				foreach($entryEnchants as $enchantmentInstance){
					if($enchantMap->toId($enchantmentInstance->getType()) == $enchantMap->toId($picked->getType())){
						continue 2;
					}
					if(IncompatibleEnchantMap::isIncompatible($picked->getType(), $enchantmentInstance->getType())){
						continue 2;
					}
				}
				$entryEnchants[] = $picked;
			}

			$options[] = new EnchantmentEntry($i, $enchantmentLevel, self::generateName($random), $entryEnchants);
		}
		return $options;
	}
}
