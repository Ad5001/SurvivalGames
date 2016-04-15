<?php

/*
 * SurvivalGames plugin for PocketMine-MP & forks
 *
 * @Author: Driesboy & Svile
 * @E-mail: gamecraftpe@mail.com.com
 */

namespace svile\sw;


use pocketmine\scheduler\PluginTask;


class SGtimer extends PluginTask
{
    public function __construct(SGmain $plugin)
    {
        parent::__construct($plugin);
    }

    public function onRun($tick)
    {
        foreach ($this->getOwner()->arenas as $SGname => $SGarena) {
            $SGarena->tick();
        }
    }
}
