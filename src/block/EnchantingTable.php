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

namespace pocketmine\block;

use pocketmine\block\inventory\EnchantInventory;
use pocketmine\block\utils\HorizontalFacing8;
use pocketmine\block\utils\SupportType;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function abs;

class EnchantingTable extends Transparent{
	private int $bookshelfCount = 0;

	/**
	 * @return AxisAlignedBB[]
	 */
	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->trim(Facing::UP, 0.25)];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE();
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player instanceof Player){
			$this->refreshBookshelfCount();
			//TODO lock

			$player->setCurrentWindow(new EnchantInventory($this->position, $this->bookshelfCount));
		}

		return true;
	}

	private function refreshBookshelfCount() : void{
		$this->bookshelfCount = 0;
		$indexBookshelf = function(Vector3 $pos){
			foreach([$pos, $pos->getSide(Facing::UP)] as $checkBlock){
				if($this->position->getWorld()->getBlock($checkBlock) instanceof Bookshelf){
					$this->bookshelfCount++;
				}
			}
		};
		$air = VanillaBlocks::AIR();
		foreach(HorizontalFacing8::getAll() as $facing){
			$pos = $this->position->addVector($facing->toVector3());
			foreach([$pos, $pos->getSide(Facing::UP)] as $checkBlock){
				if(!$this->position->getWorld()->getBlock($checkBlock)->isSameState($air)){
					continue 2;
				}
			}

			$pos = $this->position->addVector($facing->toVector3(2));
			$indexBookshelf($pos);
			if($facing->isOrdinal()){
				foreach(Facing::HORIZONTAL as $i){
					$ordSide = $pos->getSide($i);
					if(abs($ordSide->x - $this->position->x) > 2 || abs($ordSide->z - $this->position->z) > 2){
						continue;
					}
					$indexBookshelf($ordSide);
				}
			}
		}
	}
}
