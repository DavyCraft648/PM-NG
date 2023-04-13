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
use pocketmine\network\mcpe\protocol\types\Enchant;
use pocketmine\network\mcpe\protocol\types\EnchantOption;
use function array_map;

final class EnchantmentEntry{
	/**
	 * @param EnchantmentInstance[] $enchantments
	 */
	public function __construct(
		private int $index,
		private int $level,
		private string $displayName,
		private array $enchantments
	){
	}

	public function getIndex() : int{
		return $this->index;
	}

	public function getLevel() : int{
		return $this->level;
	}

	/**
	 * @return EnchantmentInstance[]
	 */
	public function getEnchantments() : array{
		return $this->enchantments;
	}

	public function toEnchantOption() : EnchantOption{
		$map = EnchantmentIdMap::getInstance();
		return new EnchantOption(
			$this->getLevel(),
			$this->enchantments[0]->getType()->getPrimaryItemFlags(),
			[], [], array_map(fn(EnchantmentInstance $e) => new Enchant($map->toId($e->getType()), $e->getLevel()), $this->enchantments),
			$this->displayName,
			$this->getIndex()
		);
	}
}
