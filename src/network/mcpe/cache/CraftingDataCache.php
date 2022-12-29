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
use pocketmine\crafting\MetaWildcardRecipeIngredient;
use pocketmine\crafting\RecipeIngredient;
use pocketmine\crafting\ShapelessRecipeType;
use pocketmine\data\bedrock\ItemTagToIdMap;
use pocketmine\item\Item;
use pocketmine\network\mcpe\convert\GlobalItemTypeDictionary;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\recipe\CraftingRecipeBlockName;
use pocketmine\network\mcpe\protocol\types\recipe\FurnaceRecipe as ProtocolFurnaceRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\FurnaceRecipeBlockName;
use pocketmine\network\mcpe\protocol\types\recipe\IntIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\PotionContainerChangeRecipe as ProtocolPotionContainerChangeRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\PotionTypeRecipe as ProtocolPotionTypeRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient as ProtocolRecipeIngredient;
use pocketmine\network\mcpe\protocol\types\recipe\ShapedRecipe as ProtocolShapedRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\ShapelessRecipe as ProtocolShapelessRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\TagItemDescriptor;
use pocketmine\timings\Timings;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Binary;
use pocketmine\utils\SingletonTrait;
use Ramsey\Uuid\Uuid;
use function array_map;
use function count;
use function spl_object_id;
use function var_dump;

final class CraftingDataCache{
	use SingletonTrait;

	/**
	 * @var CraftingDataPacket[]
	 * @phpstan-var array<int, array<int, CraftingDataPacket>>
	 */
	private array $caches = [];

	public function getCache(int $dictionaryProtocol, CraftingManager $manager) : CraftingDataPacket{
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
		return $this->caches[$id][match($dictionaryProtocol){
			ProtocolInfo::PROTOCOL_1_19_21, ProtocolInfo::PROTOCOL_1_19_30 => ProtocolInfo::PROTOCOL_1_19_20,
			default => $dictionaryProtocol
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
			ProtocolInfo::PROTOCOL_1_19_0,
			ProtocolInfo::PROTOCOL_1_19_10,
			ProtocolInfo::PROTOCOL_1_19_20,
			ProtocolInfo::PROTOCOL_1_19_40,
			ProtocolInfo::PROTOCOL_1_19_50
		];
		foreach($protocolIds as $protocolId){
			$counter = 0;
			$recipesWithTypeIds = [];
			foreach($manager->getShapelessRecipes() as $list){
				foreach($list as $recipe){
					$typeTag = match($recipe->getType()->id()){
						ShapelessRecipeType::CRAFTING()->id() => CraftingRecipeBlockName::CRAFTING_TABLE,
						ShapelessRecipeType::STONECUTTER()->id() => CraftingRecipeBlockName::STONECUTTER,
						ShapelessRecipeType::CARTOGRAPHY()->id() => CraftingRecipeBlockName::CARTOGRAPHY_TABLE,
						ShapelessRecipeType::SMITHING()->id() => CraftingRecipeBlockName::SMITHING_TABLE,
						default => throw new AssumptionFailedError("Unreachable"),
					};
					$recipesWithTypeIds[] = new ProtocolShapelessRecipe(
						CraftingDataPacket::ENTRY_SHAPELESS,
						Binary::writeInt(++$counter),
						array_map(function(RecipeIngredient $item) use ($protocolId, $converter) : ProtocolRecipeIngredient{
							return $converter->coreRecipeIngredientToNet($item, $protocolId);
						}, $recipe->getIngredientList()),
						array_map(function(Item $item) use ($protocolId, $converter) : ItemStack{
							return $converter->coreItemStackToNet($item, $protocolId);
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
					$tagItems = [];

					for($row = 0, $height = $recipe->getHeight(); $row < $height; ++$row){
						for($column = 0, $width = $recipe->getWidth(); $column < $width; ++$column){
							try{
								$input = $converter->coreRecipeIngredientToNet($recipe->getIngredient($column, $row), $protocolId);
								if($input->getDescriptor() instanceof TagItemDescriptor && $protocolId < ProtocolInfo::PROTOCOL_1_19_40){
									foreach(ItemTagToIdMap::getInstance()->getIdsForTag($input->getDescriptor()->getTag()) as $name){
										$tagItems[$name][$row][$column] = $converter->coreRecipeIngredientToNet(new MetaWildcardRecipeIngredient($name), $protocolId);
									}
									continue;
								}
								$inputs[$row][$column] = $input;
							}catch(\InvalidArgumentException){
								continue 3;
							}
						}
					}
					if($protocolId < ProtocolInfo::PROTOCOL_1_19_40 && count($tagItems) > 0){
						$tagInputs = [];
						foreach($tagItems as $name => $items){
							for($row = 0, $height = $recipe->getHeight(); $row < $height; ++$row){
								for($column = 0, $width = $recipe->getWidth(); $column < $width; ++$column){
									$tagInputs[$name][$row][$column] = $items[$row][$column] ?? $inputs[$row][$column] ?? new ProtocolRecipeIngredient(null, 0);
								}
							}
						}
						foreach($tagInputs as $tagInput){
							$recipesWithTypeIds[] = new ProtocolShapedRecipe(
								CraftingDataPacket::ENTRY_SHAPED,
								Binary::writeInt(++$counter),
								$tagInput,
								array_map(function(Item $item) use ($protocolId, $converter) : ItemStack{
									return $converter->coreItemStackToNet($item, $protocolId);
								}, $recipe->getResults()),
								$nullUUID,
								CraftingRecipeBlockName::CRAFTING_TABLE,
								50,
								$counter
							);
						}
						continue;
					}
					$recipesWithTypeIds[] = new ProtocolShapedRecipe(
						CraftingDataPacket::ENTRY_SHAPED,
						Binary::writeInt(++$counter),
						$inputs,
						array_map(function(Item $item) use ($protocolId, $converter) : ItemStack{
							return $converter->coreItemStackToNet($item, $protocolId);
						}, $recipe->getResults()),
						$nullUUID,
						CraftingRecipeBlockName::CRAFTING_TABLE,
						50,
						$counter
					);
				}
			}

			foreach(FurnaceType::getAll() as $furnaceType){
				$typeTag = match($furnaceType->id()){
					FurnaceType::FURNACE()->id() => FurnaceRecipeBlockName::FURNACE,
					FurnaceType::BLAST_FURNACE()->id() => FurnaceRecipeBlockName::BLAST_FURNACE,
					FurnaceType::SMOKER()->id() => FurnaceRecipeBlockName::SMOKER,
					default => throw new AssumptionFailedError("Unreachable"),
				};
				foreach($manager->getFurnaceRecipeManager($furnaceType)->getAll() as $recipe){
					$input = $converter->coreRecipeIngredientToNet($recipe->getInput(), $protocolId)->getDescriptor();
					if(!$input instanceof IntIdMetaItemDescriptor){
						throw new AssumptionFailedError();
					}
					$recipesWithTypeIds[] = new ProtocolFurnaceRecipe(
						CraftingDataPacket::ENTRY_FURNACE_DATA,
						$input->getId(),
						$input->getMeta(),
						$converter->coreItemStackToNet($recipe->getResult(), $protocolId),
						$typeTag
					);
				}
			}

			$potionTypeRecipes = [];
			foreach($manager->getPotionTypeRecipes() as $recipe){
				$input = $converter->coreRecipeIngredientToNet($recipe->getInput(), $protocolId)->getDescriptor();
				$ingredient = $converter->coreRecipeIngredientToNet($recipe->getIngredient(), $protocolId)->getDescriptor();
				if(!$input instanceof IntIdMetaItemDescriptor || !$ingredient instanceof IntIdMetaItemDescriptor){
					throw new AssumptionFailedError();
				}
				$output = $converter->coreItemStackToNet($recipe->getOutput(), $protocolId);
				$potionTypeRecipes[] = new ProtocolPotionTypeRecipe(
					$input->getId(),
					$input->getMeta(),
					$ingredient->getId(),
					$ingredient->getMeta(),
					$output->getId(),
					$output->getMeta()
				);
			}

			$potionContainerChangeRecipes = [];
			$itemTypeDictionary = GlobalItemTypeDictionary::getInstance()->getDictionary($protocolId);
			foreach($manager->getPotionContainerChangeRecipes() as $recipe){
				$input = $itemTypeDictionary->fromStringId($recipe->getInputItemId());
				$ingredient = $converter->coreRecipeIngredientToNet($recipe->getIngredient(), $protocolId)->getDescriptor();
				if(!$ingredient instanceof IntIdMetaItemDescriptor){
					throw new AssumptionFailedError();
				}
				$output = $itemTypeDictionary->fromStringId($recipe->getOutputItemId());
				$potionContainerChangeRecipes[] = new ProtocolPotionContainerChangeRecipe(
					$input,
					$ingredient->getId(),
					$output
				);
			}

			$packets[$protocolId] = CraftingDataPacket::create($recipesWithTypeIds, $potionTypeRecipes, $potionContainerChangeRecipes, [], true);
		}

		Timings::$craftingDataCacheRebuild->stopTiming();
		return $packets;
	}
}
