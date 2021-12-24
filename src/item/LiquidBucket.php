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
use pocketmine\block\Lava;
use pocketmine\block\Liquid;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class LiquidBucket extends Item{

	/** @var Liquid */
	private $liquid;

	public function __construct(ItemIdentifier $identifier, string $name, Liquid $liquid){
		parent::__construct($identifier, $name);
		$this->liquid = $liquid;
	}

	public function getMaxStackSize() : int{
		return 1;
	}

	public function getFuelTime() : int{
		if($this->liquid instanceof Lava){
			return 20000;
		}

		return 0;
	}

	public function getFuelResidue() : Item{
		return VanillaItems::BUCKET();
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector) : ItemUseResult{
		//TODO: move this to generic placement logic
		$resultBlock = clone $this->liquid;

		$layer2C = $blockClicked->getBlockLayer(1);
		$layer2R = $blockReplace->getBlockLayer(1);
		$toReplace = $blockReplace->canBeReplaced() ? $blockReplace : ($layer2R->getId() === 0 ? $layer2R : null);
		$toReplace = $blockClicked->canWaterlogged($resultBlock) ? ($layer2C->getId() === 0 ? $layer2C : null) : $toReplace;
		if($toReplace === null){
			return ItemUseResult::NONE();
		}

		if(!in_array(1, $resultBlock->getSupportedLayers())){
			$toReplace = $blockReplace;
		}

		$ev = new PlayerBucketEmptyEvent($player, $toReplace, $face, $this, VanillaItems::BUCKET());
		$ev->call();
		if(!$ev->isCancelled()){
			$player->getWorld()->setBlockLayer($toReplace->getPosition(), $resultBlock->getFlowingForm(), $toReplace->getLayer());
			$player->getWorld()->addSound($toReplace->getPosition()->add(0.5, 0.5, 0.5), $resultBlock->getBucketEmptySound());

			if($player->hasFiniteResources()){
				$player->getInventory()->setItemInHand($ev->getItem());
			}
			return ItemUseResult::SUCCESS();
		}

		return ItemUseResult::FAIL();
	}

	public function getLiquid() : Liquid{
		return $this->liquid;
	}
}
