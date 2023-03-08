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

namespace pocketmine\data\bedrock\block\downgrade;

use pocketmine\data\bedrock\block\upgrade\BlockStateUpgradeSchemaUtils;
use pocketmine\utils\Filesystem;
use Symfony\Component\Filesystem\Path;
use function krsort;
use const SORT_NUMERIC;

final class BlockStateDowngradeSchemaUtils{

	/**
	 * Returns a list of schemas ordered by schema ID. Newest schemas appear first.
	 *
	 * @return BlockStateDowngradeSchema[]
	 */
	public static function loadSchemas(string $path, int $minSchemaId) : array{
		$iterator = new \RegexIterator(
			new \FilesystemIterator(
				$path,
				\FilesystemIterator::KEY_AS_FILENAME | \FilesystemIterator::SKIP_DOTS
			),
			'/^(\d{4}).*\.json$/',
			\RegexIterator::GET_MATCH,
			\RegexIterator::USE_KEY
		);

		$result = [];

		/** @var string[] $matches */
		foreach($iterator as $matches){
			$filename = $matches[0];
			$schemaId = (int) $matches[1];

			if($schemaId < $minSchemaId){
				continue;
			}

			$fullPath = Path::join($path, $filename);

			$raw = Filesystem::fileGetContents($fullPath);

			try{
				$schema = BlockStateUpgradeSchemaUtils::loadSchemaFromString($raw, $schemaId)->reverse();
			}catch(\RuntimeException $e){
				throw new \RuntimeException("Loading schema file $fullPath: " . $e->getMessage(), 0, $e);
			}

			$result[$schemaId] = $schema;
		}

		krsort($result, SORT_NUMERIC);
		return $result;
	}
}
