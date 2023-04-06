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

namespace pocketmine\block\utils;

use pocketmine\math\Vector3;
use pocketmine\utils\EnumTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see build/generate-registry-annotations.php
 * @generate-registry-docblock
 *
 * @method static HorizontalFacing8 EAST()
 * @method static HorizontalFacing8 NORTH()
 * @method static HorizontalFacing8 NORTHEAST()
 * @method static HorizontalFacing8 NORTHWEST()
 * @method static HorizontalFacing8 SOUTH()
 * @method static HorizontalFacing8 SOUTHEAST()
 * @method static HorizontalFacing8 SOUTHWEST()
 * @method static HorizontalFacing8 WEST()
 */
final class HorizontalFacing8{
	use EnumTrait {
		__construct as private Enum__construct;
	}

	public function __construct(
		string $name,
		private int $xOffset, private int $zOffset,
		private bool $isCardinal, private bool $isOrdinal
	){
		$this->Enum__construct($name);
	}

	protected static function setup() : void{
		self::registerAll(
			new self("north", 0, -1, true, false),
			new self("east", 1, 0, true, false),
			new self("west", -1, 0, true, false),
			new self("south", 0, 1, true, false),
			new self("northeast", 1, -1, false, true),
			new self("northwest", -1, -1, false, true),
			new self("southeast", 1, 1, false, true),
			new self("southwest", -1, 1, false, true)
		);
	}

	public function toVector3(int $steps = 1) : Vector3{
		return new Vector3($this->xOffset * $steps, 0, $this->zOffset * $steps);
	}

	public function isCardinal() : bool{
		return $this->isCardinal;
	}

	public function isOrdinal() : bool{
		return $this->isOrdinal;
	}
}
