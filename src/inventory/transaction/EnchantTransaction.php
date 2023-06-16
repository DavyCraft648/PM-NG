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

namespace pocketmine\inventory\transaction;

use pocketmine\block\inventory\EnchantInventory;
use pocketmine\item\Book;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use function assert;
use function count;

class EnchantTransaction extends InventoryTransaction{
	private int $selectedRecipeId = -1;

	public function __construct(Player $source, private Item $item, array $actions = []){
		parent::__construct($source, $actions);
	}

	public function getResult(int $recipeId) : ?Item{
		$window = $this->source->getCurrentWindow();
		assert($window instanceof EnchantInventory);

		$entry = $window->getEnchantmentEntry($recipeId);
		if($entry === null){
			return null;
		}

		$this->selectedRecipeId = $recipeId;

		$item = $this->item;
		if($item instanceof Book){
			$item = VanillaItems::ENCHANTED_BOOK();
		}

		foreach($entry->getEnchantments() as $enchantment){
			$item->addEnchantment($enchantment);
		}
		return $item;
	}

	public function validate() : void{
		$this->squashDuplicateSlotChanges();
		if(count($this->actions) < 1){
			throw new TransactionValidationException("Transaction must have at least one action to be executable");
		}

		/** @var Item[] $createdItems */
		$createdItems = [];
		/** @var Item[] $deletedItems */
		$deletedItems = [];
		$this->matchItems($createdItems, $deletedItems);
		if(count($createdItems) < 1){
			throw new TransactionValidationException("No resulting items.");
		}
		if(count($deletedItems) < 1 + ((int) !$this->source->isCreative())){
			throw new TransactionValidationException("Not enough deleted items.");
		}
	}

	public function execute() : void{
		$window = $this->source->getCurrentWindow();
		assert($window instanceof EnchantInventory);

		$entry = $window->getEnchantmentEntry($this->selectedRecipeId);
		if($entry === null){
			throw new TransactionValidationException("No such option exists.");
		}

		$cost = $entry->getIndex() + 1;
		if(!$this->source->isCreative() && $this->source->getXpManager()->getXpLevel() < $cost){
			throw new TransactionValidationException("Not enough XP.");
		}
		try{
			parent::execute();
		}catch(TransactionValidationException) {
			$networkSession = $this->source->getNetworkSession();
			$networkSession->getEntityEventBroadcaster()->syncAttributes([$networkSession], $this->source, $this->source->getAttributeMap()->getAll());
			return;
		}

		if($this->source->isCreative()){
			return;
		}
		$this->source->getXpManager()->subtractXpLevels($cost);
	}
}
