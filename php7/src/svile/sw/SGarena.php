<?php

/*
 * SurvivalGames plugin for PocketMine-MP & forks
 *
 * @Author: Driesboy & Svile
 * @E-mail: gamecraftpe@mail.com.com
 */

namespace svile\sw;
use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\level\Position;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\tile\Chest;
use pocketmine\item\Item;


class SGarena
{
    /** @var int */
    public $GAME_STATE = 0;//0 -> GAME_COUNTDOWN | 1 -> GAME_RUNNING
    /** @var SGmain */
    private $pg;
    /** @var string */
    private $SGname;
    /** @var int */
    private $slot;
    /** @var string */
    private $world;
    /** @var int */
    private $time = 0;//Seconds from the last reload
    /** @var int */
    private $maxtime = 10;//Max seconds after the countdown, if go over this, the game will finish
    /** @var int */
    private $countdown = 10;//Seconds to wait before the game starts
    /** @var array */
    private $spawns = [];
    /** @var array */
    private $players = [];
    /** @var int */
    public $void = 0;//This is used to check "fake void" to avoid fall (stunck in air) bug

    /**
     * @param SGmain $plugin
     * @param string $SWname
     * @param int $slot
     * @param string $world
     * @param int $countdown
     * @param int $maxtime
     * @param int $void
     */
    public function __construct(SWmain $plugin, $SGname = 'sw', $slot = 0, $world = 'world', $countdown = 0, $maxtime = 0, $void = 0)
    {
        $this->pg = $plugin;
        $this->SGname = $SGname;
        $this->slot = ($slot + 0);
        $this->world = $world;
        $this->countdown = ($countdown + 0);
        $this->maxtime = ($maxtime + 0);
        $this->void = $void;
        if (!$this->reload()) {
            $this->pg->getLogger()->info(TextFormat::RED . 'An error occured while reloading the arena: ' . TextFormat::WHITE . $this->SGname);
            $this->pg->getServer()->getPluginManager()->disablePlugin($this->pg);
        }
    }

    /**
     * @return bool
     */
    private function reload() : bool
    {
        //Map reset
        if ($this->pg->getServer()->isLevelLoaded($this->world))
            $this->pg->getServer()->unloadLevel($this->pg->getServer()->getLevelByName($this->world));
        if (!is_file($this->pg->getDataFolder() . 'arenas/' . $this->SGname . '/' . $this->world . '.zip'))
            return false;
        $zip = new \ZipArchive;
        $zip->open($this->pg->getDataFolder() . 'arenas/' . $this->SGname . '/' . $this->world . '.zip');
        $zip->extractTo($this->pg->getServer()->getDataPath() . 'worlds');
        $zip->close();
        unset($zip);
        $this->pg->getServer()->loadLevel($this->world);

        $config = new Config($this->pg->getDataFolder() . 'arenas/' . $this->SGname . '/settings.yml', CONFIG::YAML, array(//TODO: put descriptions
            'name' => $this->SGname,
            'slot' => $this->slot,
            'world' => $this->world,
            'countdown' => $this->countdown,
            'maxGameTime' => $this->maxtime,
            'void_Y' => $this->void,
            'spawns' => [],
        ));
        $this->SGname = $config->get('name');
        $this->slot = ($config->get('slot') + 0);
        $this->world = $config->get('world');
        $this->countdown = ($config->get('countdown') + 0);
        $this->maxtime = ($config->get('maxGameTime') + 0);
        $this->spawns = $config->get('spawns');
        $this->void = ($config->get('void_Y') + 0);
        unset($config);
        $this->players = [];
        $this->time = 0;
        $this->GAME_STATE = 0;

        //Reset Sign
        $this->pg->refreshSigns(false, $this->SGname, 0, $this->slot);
        return true;
    }

    /**
     * @return string
     */
    public function getState() : string
    {
        $state = TextFormat::WHITE . 'Tap to join';
        switch ($this->GAME_STATE) {
            case 1:
                $state = TextFormat::RED . TextFormat::BOLD . 'Running';
                break;
            case 0:
                if (count($this->players) >= $this->slot)
                    $state = TextFormat::RED . TextFormat::BOLD . 'Running';
                break;
        }
        return $state;
    }

    /**
     * @param bool $players
     * @return int
     */
    public function getSlot($players = false) : int
    {
        if ($players)
            return count($this->players);
        return $this->slot;
    }

    /**
     * @param bool|false $spawn
     * @param string $playerName
     * @return string|array
     */
    public function getWorld($spawn = false, $playerName = '')
    {
        if ($spawn and array_key_exists($playerName, $this->players))
            return $this->players[$playerName];
        else
            return $this->world;
    }

    /**
     * @param $playerName
     * @return bool
     */
    public function inArena($playerName) : bool
    {
        if (array_key_exists($playerName, $this->players))
            return true;
        return false;
    }

    /**
     * @param bool|false $check
     * @param Player|string $player
     * @param int $slot
     * @return bool
     */
    public function setSpawn($check = false, $player, $slot = 1) : bool
    {
        if ($check) {
            if (empty($this->spawns))
                return false;
            foreach ($this->spawns as $key => $val) {
                if (!is_array($val) or count($val) != 5 or $this->slot != count($this->spawns) or in_array('n.a', $val, true))
                    return false;
            }
            return true;
        } else {
            if ($slot > $this->slot) {
                $player->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'This arena have only got ' . TextFormat::WHITE . $this->slot . TextFormat::RED . ' slots');
                return false;
            }
            $config = new Config($this->pg->getDataFolder() . 'arenas/' . $this->SGname . '/settings.yml', CONFIG::YAML);

            if (empty($config->get('spawns', []))) {
                $keys = [];
                for ($i = $this->slot; $i >= 1; $i--) {
                    $keys[] = $i;
                }
                unset($i);
                $config->set('spawns', array_fill_keys(array_reverse($keys), [
                    'x' => 'n.a',
                    'y' => 'n.a',
                    'z' => 'n.a',
                    'yaw' => 'n.a',
                    'pitch' => 'n.a'
                ]));
                unset($keys);
            }
            $s = $config->get('spawns');
            $s[$slot] = [
                'x' => floor($player->x),
                'y' => floor($player->y),
                'z' => floor($player->z),
                'yaw' => $player->yaw,
                'pitch' => $player->pitch
            ];
            $config->set('spawns', $s);
            $this->spawns = $s;
            $config->save();
            unset($config, $s);
            if (count($this->spawns) != $this->slot) {
                $player->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'An error occured setting the spawn, pls contact the developer');
                return false;
            } else
                return true;
        }
    }

    /** VOID */
    private function refillChests()
    {
        $contents = $this->pg->getChestContents();
        foreach ($this->pg->getServer()->getLevelByName($this->world)->getTiles() as $tile) {
            if ($tile instanceof Chest) {
                //CLEARS CHESTS
                for ($i = 0; $i < $tile->getSize(); $i++) {
                    $tile->getInventory()->setItem($i, Item::get(0));
                }
                //SET CONTENTS
                if (empty($contents))
                    $contents = $this->pg->getChestContents();
                foreach (array_shift($contents) as $key => $val) {
                    $tile->getInventory()->setItem($key, Item::get($val[0], 0, $val[1]));
                }
            }
        }
        unset($contents, $tile);
    }

    /** VOID */
    public function tick()
    {
        if ($this->GAME_STATE == 0 and count($this->players) < ($this->pg->configs['needed_players_to_run_countdown'] + 0))
            return;
        $this->time++;

        //START and STOP
        if ($this->GAME_STATE == 0 and $this->pg->configs['start.when_full'] and $this->slot <= count($this->players)) {
            $this->start();
            return;
        }
        if ($this->GAME_STATE == 1 and 2 > count($this->players)) {
            $this->stop();
            return;
        }
        if ($this->GAME_STATE == 0 and $this->time >= $this->countdown) {
            $this->start();
            return;
        }
        if ($this->GAME_STATE == 1 and $this->time >= $this->maxtime) {
            $this->stop();
            return;
        }

        if ($this->GAME_STATE == 1 and $this->pg->configs['chest.refill'] and ($this->time % $this->pg->configs['chest.refill_rate']) == 0) {
            $this->refillChests();
            foreach ($this->pg->getServer()->getLevelByName($this->world)->getPlayers() as $p) {
                $p->sendMessage($this->pg->lang['game.chest_refill']);
            }
            return;
        }

        //Chat and Popup messanges
        if ($this->GAME_STATE == 0 and $this->time % 30 == 0) {
            foreach ($this->pg->getServer()->getLevelByName($this->world)->getPlayers() as $p) {
                $p->sendMessage(str_replace('{N}', date('i:s', ($this->countdown - $this->time)), $this->pg->lang['chat.countdown']));
            }
        }
        if ($this->GAME_STATE == 0) {
            foreach ($this->pg->getServer()->getLevelByName($this->world)->getPlayers() as $p) {
                $p->sendPopup(str_replace('{N}', date('i:s', ($this->countdown - $this->time)), $this->pg->lang['popup.countdown']));
                if (($this->countdown - $this->time) <= 10)
                    $p->getLevel()->addSound((new \pocketmine\level\sound\ButtonClickSound($p)), [$p]);
            }
        }
    }

    /**
     * @param Player $player
     */
    public function join(Player $player)
    {
        if ($this->GAME_STATE == 1) {
            $player->sendMessage($this->pg->lang['sign.game_running']);
            return;
        }
        if (count($this->players) >= $this->slot or empty($this->spawns)) {
            $player->sendMessage($this->pg->lang['sign.game_full']);
            return;
        }
        //Sound
        $player->getLevel()->addSound((new \pocketmine\level\sound\EndermanTeleportSound($player)), [$player]);

        //Removes player things
        if ($this->pg->configs['clear_inventory_on_arena_join'])
            $player->getInventory()->clearAll();
        if ($this->pg->configs['clear_effects_on_arena_join'])
            $player->removeAllEffects();

        $this->pg->getServer()->loadLevel($this->world);
        $level = $this->pg->getServer()->getLevelByName($this->world);
        $this->players[$player->getName()] = array_shift($this->spawns);
        $player->teleport(new Position($this->players[$player->getName()]['x'], $this->players[$player->getName()]['y'], $this->players[$player->getName()]['z'], $level), $this->players[$player->getName()]['yaw'], $this->players[$player->getName()]['pitch']);
        foreach ($level->getPlayers() as $p) {
            $p->sendMessage(str_replace('{COUNT}', '[' . $this->getSlot(true) . '/' . $this->slot . ']', str_replace('{PLAYER}', $player->getName(), $this->pg->lang['game.join'])));
        }
        $this->pg->refreshSigns(false, $this->SGname, $this->getSlot(true), $this->slot, $this->getState());
    }

    /**
     * @param string $playerName
     * @param bool|false $left
     * @return bool
     */
    public function quit($playerName, $left = false) : bool
    {
        if (!array_key_exists($playerName, $this->players))
            return false;
        if ($this->GAME_STATE == 0)
            $this->spawns[] = $this->players[$playerName];
        unset($this->players[$playerName]);
        $this->pg->refreshSigns(false, $this->SGname, $this->getSlot(true), $this->slot, $this->getState());
        if ($left) {
            foreach ($this->pg->getServer()->getLevelByName($this->world)->getPlayers() as $p) {
                $p->sendMessage(str_replace('{COUNT}', '[' . $this->getSlot(true) . '/' . $this->slot . ']', str_replace('{PLAYER}', $playerName, $this->pg->lang['game.left'])));
            }
        }
        $this->pg->getServer()->getPlayer($playerName)->getInventory()->clearAll();
        $this->pg->getServer()->getPlayer($playerName)->removeAllEffects();
        return true;
    }

    /** VOID */
    private function start()
    {
        if ($this->pg->configs['chest.refill'])
            $this->refillChests();
        foreach ($this->pg->getServer()->getLevelByName($this->world)->getPlayers() as $p) {
            $p->sendMessage($this->pg->lang['game.start']);
            if ($p->getLevel()->getBlock($p->floor()->subtract(0, 2))->getId() == 20)
                $p->getLevel()->setBlock($p->floor()->subtract(0, 2), Block::get(0), true, false);
            if ($p->getLevel()->getBlock($p->floor()->subtract(0, 1))->getId() == 20)
                $p->getLevel()->setBlock($p->floor()->subtract(0, 1), Block::get(0), true, false);
        }
        $this->time = 0;
        $this->GAME_STATE = 1;
        $this->pg->refreshSigns(false, $this->SGname, $this->getSlot(true), $this->slot, (TextFormat::RED . TextFormat::BOLD . 'Running'));
    }

    /**
     * @return bool
     */
    public function stop() : bool
    {
        foreach ($this->players as $name => $spawn) {
            $p = $this->pg->getServer()->getPlayer($name);
            if ($p instanceof Player) {

                //Removes player things
                $p->getInventory()->clearAll();
                $p->removeAllEffects();
                $p->teleport($p->getServer()->getDefaultLevel()->getSpawnLocation());
                foreach ($this->pg->getServer()->getDefaultLevel()->getPlayers() as $pl) {
                    $pl->sendMessage(str_replace('{SGNAME}', $this->SGname, str_replace('{PLAYER}', $p->getName(), $this->pg->lang['server.broadcast_winner'])));
                }

            }
        }
        $this->pg->getServer()->loadLevel($this->world);
        foreach ($this->pg->getServer()->getLevelByName($this->world)->getPlayers() as $p)
            $p->teleport($p->getServer()->getDefaultLevel()->getSpawnLocation());
        $this->reload();
        return true;
    }
}
