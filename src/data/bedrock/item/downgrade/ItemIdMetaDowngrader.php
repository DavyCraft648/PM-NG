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

namespace pocketmine\data\bedrock\item\downgrade;

use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use function is_array;

/**
 * Downgrades new item string IDs to older ones according to the given schemas.
 */
final class ItemIdMetaDowngrader{

	/**
	 * @var string[] $renamedIds
	 * @phpstan-var array<string, string> $renamedIds
	 */
	private array $renamedIds = [];
	/**
	 * @var string[][] $remappedMetas
	 * @phpstan-var array<string, array{string, int}> $remappedMetas
	 */
	private array $remappedMetas = [];

	public function __construct(ItemTypeDictionary $dictionary, int $schemaId){
		$upgrader = GlobalItemDataHandlers::getUpgrader()->getIdMetaUpgrader();

		$networkIds = [];
		foreach($upgrader->getSchemas() as $id => $schema){
			if($id <= $schemaId){
				continue;
			}

			foreach(Utils::stringifyKeys($schema->getRenamedIds()) as $oldId => $newStringId){
				if(isset($networkIds[$oldId])){
					$networkIds[$newStringId] = $networkIds[$oldId];
				}else{
					try{
						$dictionary->fromStringId($oldId);
						$networkIds[$newStringId] = $oldId;
					}catch(\InvalidArgumentException $e){
						//ignore
					}
				}
			}

			foreach(Utils::stringifyKeys($schema->getRemappedMetas()) as $oldId => $metaToNewId){
				if(isset($networkIds[$oldId])){
					foreach($metaToNewId as $oldMeta => $newStringId){
						if(is_array($networkIds[$oldId])){
							throw new AssumptionFailedError("Can't flatten IDs twice");
						}else{
							$networkIds[$newStringId] = [$networkIds[$oldId], $oldMeta];
						}
					}
				}else{
					try{
						$dictionary->fromStringId($oldId);
						foreach($metaToNewId as $oldMeta => $newStringId){
							$networkIds[$newStringId] = [$oldId, $oldMeta];
						}
					}catch(\InvalidArgumentException $e){
						//ignore
					}
				}
			}
		}

		foreach(Utils::stringifyKeys($networkIds) as $newStringId => $oldId){
			if(is_array($oldId)){
				$this->remappedMetas[$newStringId] = $oldId;
			}else{
				$this->renamedIds[$newStringId] = $oldId;
			}
		}
	}

	/**
	 * @phpstan-return array{string, int}
	 */
	public function downgrade(string $id, int $meta) : array{
		$newId = $id;
		$newMeta = $meta;

		if(isset($this->remappedMetas[$newId])){
			[$newId, $newMeta] = $this->remappedMetas[$newId];
		}elseif(isset($this->renamedIds[$newId])){
			$newId = $this->renamedIds[$newId];
		}

		return [$newId, $newMeta];
	}
}
