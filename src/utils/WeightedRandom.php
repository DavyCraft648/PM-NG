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

namespace pocketmine\utils;

use function count;

class WeightedRandom{
	/** @var array<array<int, mixed>> */
	private array $items = [];
	private int $totalWeight = 0;

	public function __construct(private Random $random){
	}

	/*	@phpstan-ignore-next-line */
	public function insert($item, int $weight) : void{
		$this->items[] = [$weight, $item];
		$this->totalWeight += $weight;
	}

	public function next(bool $remove = true) : mixed{
		if(count($this->items) < 1){
			return null;
		}
		$w = $this->random->nextRange(end: $this->totalWeight);
		foreach($this->items as $i => [$weight, $item]){
			$w -= $weight;
			if($w >= 0){
				continue;
			}
			if($remove){
				$this->totalWeight -= $weight;
				unset($this->items[$i]);
			}
			return $item;
		}
		return null;
	}
}
