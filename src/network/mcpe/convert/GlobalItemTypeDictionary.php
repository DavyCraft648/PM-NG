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
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use Webmozart\PathUtil\Path;
use function file_get_contents;

final class GlobalItemTypeDictionary{
	use SingletonTrait;

	private static function make() : self{
		$protocolPaths = [
			ProtocolInfo::CURRENT_PROTOCOL => "",
			ProtocolInfo::PROTOCOL_1_19_0 => "-1.19.0",
			ProtocolInfo::PROTOCOL_1_18_30 => "-1.18.30",
			ProtocolInfo::PROTOCOL_1_18_10 => "-1.18.10",
			ProtocolInfo::PROTOCOL_1_18_0 => "-1.18.0",
			ProtocolInfo::PROTOCOL_1_17_40 => "-1.17.40",
			ProtocolInfo::PROTOCOL_1_17_30 => "-1.17.30",
			ProtocolInfo::PROTOCOL_1_17_10 => "-1.17.10",
			ProtocolInfo::PROTOCOL_1_17_0 => "-1.17.0",
		];

		$dictionaries = [];

		foreach ($protocolPaths as $protocolId => $path){
			if($protocolId < ProtocolInfo::PROTOCOL_1_19_0){
				continue;
			}
			$data = Utils::assumeNotFalse(file_get_contents(Path::join(\pocketmine\BEDROCK_DATA_PATH, 'required_item_list' . $path . '.json')), "Missing required resource file");
			$dictionaries[$protocolId] = ItemTypeDictionaryFromDataHelper::loadFromString($data);
		}

		return new self($dictionaries);
	}

	/**
	 * @param ItemTypeDictionary[] $dictionaries
	 */
	public function __construct(private array $dictionaries){}

	public static function getDictionaryProtocol(int $protocolId) : int{
		if($protocolId === ProtocolInfo::PROTOCOL_1_19_10 || $protocolId === ProtocolInfo::PROTOCOL_1_19_20){
			return ProtocolInfo::PROTOCOL_1_19_21;
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
