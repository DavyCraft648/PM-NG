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

namespace pocketmine\network\mcpe\convert;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\player\Player;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use Symfony\Component\Filesystem\Path;

final class GlobalItemTypeDictionary{
	use SingletonTrait;

	private static function make() : self{
		$protocolPaths = [
			ProtocolInfo::CURRENT_PROTOCOL => "",
			ProtocolInfo::PROTOCOL_1_19_50 => "-1.19.50",
			ProtocolInfo::PROTOCOL_1_19_10 => "-1.19.10",
			ProtocolInfo::PROTOCOL_1_19_0 => "-1.19.0"
		];

		$dictionaries = [];

		foreach ($protocolPaths as $protocolId => $path){
			$data = Filesystem::fileGetContents(Path::join(\pocketmine\BEDROCK_DATA_PATH, 'required_item_list' . $path . '.json'));
			$dictionaries[$protocolId] = ItemTypeDictionaryFromDataHelper::loadFromString($data);
		}

		return new self($dictionaries);
	}

	/**
	 * @param ItemTypeDictionary[] $dictionaries
	 */
	public function __construct(private array $dictionaries){}

	public static function getDictionaryProtocol(int $protocolId) : int{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_19_10 && $protocolId <= ProtocolInfo::PROTOCOL_1_19_40){
			return ProtocolInfo::PROTOCOL_1_19_10;
		}
		return $protocolId;
	}

	/**
	 * @param Player[] $players
	 *
	 * @return Player[][]
	 */
	public static function sortByProtocol(array $players) : array{
		$sortPlayers = [];

		foreach($players as $player){
			$dictionaryProtocol = self::getDictionaryProtocol($player->getNetworkSession()->getProtocolId());

			if(isset($sortPlayers[$dictionaryProtocol])){
				$sortPlayers[$dictionaryProtocol][] = $player;
			}else{
				$sortPlayers[$dictionaryProtocol] = [$player];
			}
		}

		return $sortPlayers;
	}

	/**
	 * @return  ItemTypeDictionary[] $dictionaries
	 */
	public function getDictionaries() : array{ return $this->dictionaries; }

	public function getDictionary(int $dictionaryId) : ItemTypeDictionary{ return $this->dictionaries[$dictionaryId] ?? $this->dictionaries[ProtocolInfo::CURRENT_PROTOCOL]; }
}
