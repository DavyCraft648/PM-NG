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

namespace pocketmine\network\mcpe\cache;

use pocketmine\crafting\CraftingManager;
use pocketmine\crafting\FurnaceType;
use pocketmine\crafting\ShapelessRecipeType;
use pocketmine\item\Item;
use pocketmine\network\mcpe\convert\GlobalItemTypeDictionary;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\recipe\CraftingRecipeBlockName;
use pocketmine\network\mcpe\protocol\types\recipe\FurnaceRecipe as ProtocolFurnaceRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\FurnaceRecipeBlockName;
use pocketmine\network\mcpe\protocol\types\recipe\PotionContainerChangeRecipe as ProtocolPotionContainerChangeRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\PotionTypeRecipe as ProtocolPotionTypeRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;
use pocketmine\network\mcpe\protocol\types\recipe\ShapedRecipe as ProtocolShapedRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\ShapelessRecipe as ProtocolShapelessRecipe;
use pocketmine\timings\Timings;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Binary;
use pocketmine\utils\SingletonTrait;
use Ramsey\Uuid\Uuid;
use function array_map;
use function spl_object_id;

final class CraftingDataCache{
	use SingletonTrait;

	/**
	 * @var CraftingDataPacket[]
	 * @phpstan-var array<int, array<int, CraftingDataPacket>>
	 */
	private array $caches = [];

	public function getCache(int $protocolId, CraftingManager $manager) : CraftingDataPacket{
		$id = spl_object_id($manager);
		if(!isset($this->caches[$id])){
			$manager->getDestructorCallbacks()->add(function() use ($id) : void{
				unset($this->caches[$id]);
			});
			$manager->getRecipeRegisteredCallbacks()->add(function() use ($id) : void{
				unset($this->caches[$id]);
			});
			$this->caches[$id] = $this->buildCraftingDataCache($manager);
		}
		return $this->caches[$id][match($protocolId){
			ProtocolInfo::PROTOCOL_1_19_20, ProtocolInfo::PROTOCOL_1_19_21, ProtocolInfo::PROTOCOL_1_19_30 => ProtocolInfo::PROTOCOL_1_19_40,
			default => $protocolId
		}];
	}

	/**
	 * Rebuilds the cached CraftingDataPacket.
	 *
	 * @return CraftingDataPacket[]
	 */
	private function buildCraftingDataCache(CraftingManager $manager) : array{
		Timings::$craftingDataCacheRebuild->startTiming();
		$packets = [];

		$nullUUID = Uuid::fromString(Uuid::NIL);
		$converter = TypeConverter::getInstance();
		$protocolIds = [
			ProtocolInfo::PROTOCOL_1_19_50,
			ProtocolInfo::PROTOCOL_1_19_40,
			ProtocolInfo::PROTOCOL_1_19_10,
			ProtocolInfo::PROTOCOL_1_19_0,
			ProtocolInfo::PROTOCOL_1_18_30,
			ProtocolInfo::PROTOCOL_1_18_10,
			ProtocolInfo::PROTOCOL_1_18_0,
			ProtocolInfo::PROTOCOL_1_17_40,
			ProtocolInfo::PROTOCOL_1_17_30,
			ProtocolInfo::PROTOCOL_1_17_10,
			ProtocolInfo::PROTOCOL_1_17_0,
		];
		foreach($protocolIds as $protocolId){
			$dictionaryProtocol = GlobalItemTypeDictionary::getDictionaryProtocol($protocolId);
			$counter = 0;
			$recipesWithTypeIds = [];
			foreach($manager->getShapelessRecipes() as $list){
				foreach($list as $recipe){
					$typeTag = match($recipe->getType()->id()){
						ShapelessRecipeType::CRAFTING()->id() => CraftingRecipeBlockName::CRAFTING_TABLE,
						ShapelessRecipeType::STONECUTTER()->id() => CraftingRecipeBlockName::STONECUTTER,
						default => throw new AssumptionFailedError("Unreachable"),
					};
					$recipesWithTypeIds[] = new ProtocolShapelessRecipe(
						CraftingDataPacket::ENTRY_SHAPELESS,
						Binary::writeInt(++$counter),
						array_map(function(Item $item) use ($dictionaryProtocol, $converter) : RecipeIngredient{
							return $converter->coreItemStackToRecipeIngredient($dictionaryProtocol, $item);
						}, $recipe->getIngredientList()),
						array_map(function(Item $item) use ($protocolId, $converter) : ItemStack{
							return $converter->coreItemStackToNet($protocolId, $item);
						}, $recipe->getResults()),
						$nullUUID,
						$typeTag,
						50,
						$counter
					);
				}
			}
			foreach($manager->getShapedRecipes() as $list){
				foreach($list as $recipe){
					$inputs = [];

					for($row = 0, $height = $recipe->getHeight(); $row < $height; ++$row){
						for($column = 0, $width = $recipe->getWidth(); $column < $width; ++$column){
							$inputs[$row][$column] = $converter->coreItemStackToRecipeIngredient($dictionaryProtocol, $recipe->getIngredient($column, $row));
						}
					}
					$recipesWithTypeIds[] = $r = new ProtocolShapedRecipe(
						CraftingDataPacket::ENTRY_SHAPED,
						Binary::writeInt(++$counter),
						$inputs,
						array_map(function(Item $item) use ($protocolId, $converter) : ItemStack{
							return $converter->coreItemStackToNet($protocolId, $item);
						}, $recipe->getResults()),
						$nullUUID,
						CraftingRecipeBlockName::CRAFTING_TABLE,
						50,
						$counter
					);
				}
			}

			foreach(FurnaceType::getAll() as $furnaceType){
				$typeTag = match ($furnaceType->id()) {
					FurnaceType::FURNACE()->id() => FurnaceRecipeBlockName::FURNACE,
					FurnaceType::BLAST_FURNACE()->id() => FurnaceRecipeBlockName::BLAST_FURNACE,
					FurnaceType::SMOKER()->id() => FurnaceRecipeBlockName::SMOKER,
					default => throw new AssumptionFailedError("Unreachable"),
				};
				foreach($manager->getFurnaceRecipeManager($furnaceType)->getAll() as $recipe){
					$input = $converter->coreItemStackToNet($protocolId, $recipe->getInput());
					$recipesWithTypeIds[] = new ProtocolFurnaceRecipe(
						CraftingDataPacket::ENTRY_FURNACE_DATA,
						$input->getId(),
						$input->getMeta(),
						$converter->coreItemStackToNet($protocolId, $recipe->getResult()),
						$typeTag
					);
				}
			}

			$potionTypeRecipes = [];
			foreach($manager->getPotionTypeRecipes() as $recipes){
				foreach($recipes as $recipe){
					$input = $converter->coreItemStackToNet($protocolId, $recipe->getInput());
					$ingredient = $converter->coreItemStackToNet($protocolId, $recipe->getIngredient());
					$output = $converter->coreItemStackToNet($protocolId, $recipe->getOutput());
					$potionTypeRecipes[] = new ProtocolPotionTypeRecipe(
						$input->getId(),
						$input->getMeta(),
						$ingredient->getId(),
						$ingredient->getMeta(),
						$output->getId(),
						$output->getMeta()
					);
				}
			}

			$potionContainerChangeRecipes = [];
			$itemTranslator = ItemTranslator::getInstance();
			foreach($manager->getPotionContainerChangeRecipes() as $recipes){
				foreach($recipes as $recipe){
					$input = $itemTranslator->toNetworkId($dictionaryProtocol, $recipe->getInputItemId(), 0);
					$ingredient = $itemTranslator->toNetworkId($dictionaryProtocol, $recipe->getIngredient()->getId(), 0);
					$output = $itemTranslator->toNetworkId($dictionaryProtocol, $recipe->getOutputItemId(), 0);
					$potionContainerChangeRecipes[] = new ProtocolPotionContainerChangeRecipe(
						$input[0],
						$ingredient[0],
						$output[0]
					);
				}
			}

			$packets[$protocolId] = CraftingDataPacket::create($recipesWithTypeIds, $potionTypeRecipes, $potionContainerChangeRecipes, [], true);
		}

		Timings::$craftingDataCacheRebuild->stopTiming();
		return $packets;
	}
}
