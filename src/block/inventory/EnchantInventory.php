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

namespace pocketmine\block\inventory;

use pocketmine\event\player\PlayerEnchantOptionsRequestEvent;
use pocketmine\inventory\SimpleInventory;
use pocketmine\inventory\TemporaryInventory;
use pocketmine\item\enchantment\EnchantingMechanics;
use pocketmine\item\enchantment\EnchantmentEntry;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\PlayerEnchantOptionsPacket;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\Position;
use function array_map;
use function assert;
use function count;

class EnchantInventory extends SimpleInventory implements BlockInventory, TemporaryInventory{
	use BlockInventoryTrait;

	public const SLOT_INPUT = 0;
	public const SLOT_LAPIS = 1;

	/** @var EnchantmentEntry[] */
	private array $options = [];

	public function __construct(Position $holder, private int $bookshelfCount = 0){
		$this->holder = $holder;
		parent::__construct(2);
	}

	protected function onSlotChange(int $index, Item $before) : void{
		parent::onSlotChange($index, $before);
		$viewers = $this->getViewers();
		assert(count($viewers) === 1);
		if($index !== self::SLOT_INPUT){
			return;
		}
		foreach($viewers as $player){
			$this->refreshEnchantOptions($player, clone $this->getItem(self::SLOT_INPUT));
			$player->getNetworkSession()->sendDataPacket(PlayerEnchantOptionsPacket::create(array_map(
				fn(EnchantmentEntry $entry) => $entry->toEnchantOption(), $this->options
			)));
		}
	}

	private function refreshEnchantOptions(Player $player, Item $item) : void{
		($ev = new PlayerEnchantOptionsRequestEvent($player, $item, EnchantingMechanics::generateEnchantOptions(
			new Random($player->getXpSeed()), $this->bookshelfCount, $item
		)))->call();
		$this->options = $ev->getOptions();
	}

	public function getEnchantmentEntry(int $index) : ?EnchantmentEntry{
		foreach($this->options as $option){
			if($option->getIndex() === $index){
				return $option;
			}
		}
		return null;
	}
}
