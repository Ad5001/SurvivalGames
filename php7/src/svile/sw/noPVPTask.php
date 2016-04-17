<?php


namespace svile\sw;; 

use pocketmine\server;
use pocketmine\scheduler\PluginTask;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\ServerScheduler;
use pocketmine\event\Listener;
use pocketmine\entity\Effect;
use pocketmine\level\Level;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\block\Block;
use pocketmine\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\IPlayer;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;

   class noPVPTask extends PluginTask  {
	private $plugin;
    public function __construct($plugin){
        parent::__construct($plugin);
		$this->p = $plugin;
	}
	public function onRun($tick) {
	foreach($this->p->getServer()->getOnlinePlayers() as $player) {
    $this->getServer()->dispatchCommand(new ConsoleCommandSender(), "nopvp both ".$player->getName());
	}
	}
	}
