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

namespace pocketmine\item;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\player\PlayerBucketFillEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Bucket extends Item{

	public function getMaxStackSize() : int{
		return 16;
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		//TODO: move this to generic placement logic
		$layer2 = $blockClicked->getBlockLayer(1);
		$liquid = $blockClicked instanceof Liquid ? $blockClicked : ($layer2 instanceof Liquid ? $layer2 : null);
		if($liquid instanceof Liquid && $liquid->isSource()){
			$stack = clone $this;
			$stack->pop();

			$resultItem = match($liquid->getTypeId()){
				BlockTypeIds::LAVA => VanillaItems::LAVA_BUCKET(),
				BlockTypeIds::WATER => VanillaItems::WATER_BUCKET(),
				default => null
			};
			if($resultItem === null){
				return ItemUseResult::FAIL;
			}

			$ev = new PlayerBucketFillEvent($player, $blockReplace, $face, $this, $resultItem);
			$ev->call();
			if(!$ev->isCancelled()){
				$player->getWorld()->setBlockLayer($liquid->getPosition(), VanillaBlocks::AIR(), $liquid->getLayer());
				$player->getWorld()->addSound($liquid->getPosition()->add(0.5, 0.5, 0.5), $liquid->getBucketFillSound());

				$this->pop();
				$returnedItems[] = $ev->getItem();
				return ItemUseResult::SUCCESS;
			}

			return ItemUseResult::FAIL;
		}

		return ItemUseResult::NONE;
	}
}
