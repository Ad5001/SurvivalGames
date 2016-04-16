<?php

/*
 * SurvivalGames plugin for PocketMine-MP & forks
 *
 * @Author: Driesboy & Svile
 * @E-mail: gamecraftpe@mail.com.com
 */

namespace svile\sw;


use pocketmine\event\Listener;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\BlockPlaceEvent;

use pocketmine\level\Position;
use pocketmine\level\Location;

use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;


class SGlistener implements Listener
{
    /** @var SGmain */
    private $pg;

    public function __construct(SGmain $plugin)
    {
        $this->pg = $plugin;
    }

    public function onSignChange(SignChangeEvent $ev)
    {
        if ($ev->getLine(0) != 'sg' or $ev->getPlayer()->isOp() == false)
            return;

        //Checks if the arena exists
        $SGname = TextFormat::clean(trim($ev->getLine(1)));
        if (!array_key_exists($SGname, $this->pg->arenas)) {
            $ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'This arena doesn\'t exist, try ' . TextFormat::WHITE . '/sg create');
            return;
        }

        //Checks if a sign already exists for the arena
        if (in_array($SGname, $this->pg->signs)) {
            $ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'A sign for this arena already exist, try ' . TextFormat::WHITE . '/sg signdelete');
            return;
        }

        //Checks if the sign is placed in a different world from the arena one
        $world = $ev->getPlayer()->getLevel()->getName();
        if ($world == $this->pg->arenas[$SGname]->getWorld()) {
            $ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'You can\'t place the join sign in the arena');
            return;
        }

        //Checks arena spawns
        if (!$this->pg->arenas[$SGname]->setSpawn(true, '')) {
            $ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'Not all the spawns are set in this arena, try ' . TextFormat::WHITE . ' /sg setspawn');
            return;
        }

        //Saves the sign
        if (!$this->pg->setSign($SGname, ($ev->getBlock()->getX() + 0), ($ev->getBlock()->getY() + 0), ($ev->getBlock()->getZ() + 0), $world))
            $ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'An error occured, please contact the developer');
        else
            $ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'SG join sign created !');

        //TODO: rewrite this and let the owner decide the sign style
        //Sets format
        $ev->setLine(0, TextFormat::BOLD . TextFormat::RED . '[' . TextFormat::AQUA . 'SG' . TextFormat::RED . ']');
        $ev->setLine(1, TextFormat::BOLD . TextFormat::YELLOW . $SGname);
        $ev->setLine(2, TextFormat::GREEN . '0' . TextFormat::BOLD . TextFormat::DARK_GRAY . '/' . TextFormat::RESET . TextFormat::GREEN . $this->pg->arenas[$SGname]->getSlot());
        $ev->setLine(3, TextFormat::WHITE . 'Tap to join');
        $this->pg->refreshSigns(true);
        unset($SGname, $world);
    }

    public function onInteract(PlayerInteractEvent $ev)
    {
        if ($ev->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK)
            return;

        //In-arena Tap
        foreach ($this->pg->arenas as $a) {
            if ($a->inArena($ev->getPlayer()->getName())) {
                if ($a->GAME_STATE == 0)
                    $ev->setCancelled();
                return;
            }
        }

        //Join sign Tap check
        $key = $ev->getBlock()->x . ':' . $ev->getBlock()->y . ':' . $ev->getBlock()->z . ':' . $ev->getBlock()->getLevel()->getName();
        if (array_key_exists($key, $this->pg->signs))
            $this->pg->arenas[$this->pg->signs[$key]]->join($ev->getPlayer());
        unset($key);
    }

    public function onMove(PlayerMoveEvent $ev)
    {
        foreach ($this->pg->arenas as $a) {
            if ($a->inArena($ev->getPlayer()->getName())) {
                if ($a->GAME_STATE == 0) {
                    $spawn = $a->getWorld(true, $ev->getPlayer()->getName());
                    if ($ev->getPlayer()->getPosition()->distanceSquared(new Position($spawn['x'], $spawn['y'], $spawn['z'])) > 2)
                        $ev->setTo(new Location($spawn['x'], $spawn['y'], $spawn['z']));
                    break;
                }
                if ($a->void >= $ev->getPlayer()->getFloorY() and $ev->getPlayer()->isAlive()) {
                    $event = new EntityDamageEvent($ev->getPlayer(), EntityDamageEvent::CAUSE_VOID, 10);
                    $ev->getPlayer()->attack($event->getFinalDamage(), $event);
                    unset($event);
                }
                break;
            }
        }
        //Checks if knockBack is enabled
        if ($this->pg->configs['sign_knockBack']) {
            foreach ($this->pg->signs as $key => $val) {
                $ex = explode(':', $key);
                if ($ev->getPlayer()->getLevel()->getName() == $ex[3]) {
                    $x = $ev->getPlayer()->getFloorX();
                    $z = $ev->getPlayer()->getFloorZ();
                    $radius = $this->pg->configs['knockBack_radius_from_sign'];
                    //If is inside the sign radius, knockBack
                    if (($x >= ($ex[0] - $radius) and $x <= ($ex[0] + $radius)) and ($z >= ($ex[2] - $radius) and $z <= ($ex[2] + $radius))) {
                        //If the block is not a sign, break
                        $block = $ev->getPlayer()->getLevel()->getBlock(new Vector3($ex[0], $ex[1], $ex[2]));
                        if ($block->getId() != 63 and $block->getId() != 68)
                            break;
                        //Finds sign yaw
                        switch ($block->getId()):
                            case 68:
                                switch ($block->getDamage()) {
                                    case 3:
                                        $yaw = 0;
                                        break;
                                    case 4:
                                        $yaw = 0x5a;
                                        break;
                                    case 2:
                                        $yaw = 0xb4;
                                        break;
                                    case 5:
                                        $yaw = 0x10e;
                                        break;
                                    default:
                                        $yaw = 0;
                                        break;
                                }
                                break;
                            case 63:
                                switch ($block->getDamage()) {
                                    case 0:
                                        $yaw = 0;
                                        break;
                                    case 1:
                                        $yaw = 22.5;
                                        break;
                                    case 2:
                                        $yaw = 0x2d;
                                        break;
                                    case 3:
                                        $yaw = 67.5;
                                        break;
                                    case 4:
                                        $yaw = 0x5a;
                                        break;
                                    case 5:
                                        $yaw = 112.5;
                                        break;
                                    case 6:
                                        $yaw = 0x87;
                                        break;
                                    case 7:
                                        $yaw = 157.5;
                                        break;
                                    case 8:
                                        $yaw = 0xb4;
                                        break;
                                    case 9:
                                        $yaw = 202.5;
                                        break;
                                    case 10:
                                        $yaw = 0xe1;
                                        break;
                                    case 11:
                                        $yaw = 247.5;
                                        break;
                                    case 12:
                                        $yaw = 0x10e;
                                        break;
                                    case 13:
                                        $yaw = 292.5;
                                        break;
                                    case 14:
                                        $yaw = 0x13b;
                                        break;
                                    case 15:
                                        $yaw = 337.5;
                                        break;
                                    default:
                                        $yaw = 0;
                                        break;
                                }
                                break;
                            default:
                                $yaw = 0;
                        endswitch;
                        //knockBack
                        $vector = (new Vector3((-(cos(deg2rad(90))) * sin(deg2rad($yaw))), (-sin(deg2rad(0))), ((cos(deg2rad(90))) * cos(deg2rad($yaw)))))->normalize();
                        $ev->getPlayer()->knockBack($ev->getPlayer(), 0, $vector->getX(), $vector->getZ(), ($this->pg->configs['knockBack_intensity'] / 0xa));
                        break;
                    }
                    unset($ex, $block, $x, $z, $radius, $yaw, $vector);
                }
            }
        }
    }

    public function onQuit(PlayerQuitEvent $ev)
    {
        foreach ($this->pg->arenas as $a) {
            if ($a->quit($ev->getPlayer()->getName(), true))
                break;
        }
    }

    public function onDeath(PlayerDeathEvent $ev)
    {
        foreach ($this->pg->arenas as $a) {
            if ($a->quit($ev->getEntity()->getName())) {
                $ev->setDeathMessage('');
                if (($ev->getEntity()->getLastDamageCause() instanceof EntityDamageByEntityEvent) and $ev->getEntity()->getLastDamageCause()->getDamager() instanceof \pocketmine\Player) {
                    foreach ($this->pg->getServer()->getLevelByName($a->getWorld())->getPlayers() as $p) {
                        $p->sendMessage(str_replace('{COUNT}', '[' . $a->getSlot(true) . '/' . $a->getSlot() . ']', str_replace('{KILLER}', $ev->getEntity()->getLastDamageCause()->getDamager()->getName(), str_replace('{PLAYER}', $ev->getEntity()->getName(), $this->pg->lang['player.kill']))));
                    }
                } elseif ($ev->getEntity()->getLastDamageCause()->getCause() == EntityDamageEvent::CAUSE_VOID) {
                    foreach ($this->pg->getServer()->getLevelByName($a->getWorld())->getPlayers() as $p) {
                        $p->sendMessage(str_replace('{COUNT}', '[' . $a->getSlot(true) . '/' . $a->getSlot() . ']', str_replace('{PLAYER}', $ev->getEntity()->getName(), $this->pg->lang['void.kill'])));
                    }
                } else {
                    foreach ($this->pg->getServer()->getLevelByName($a->getWorld())->getPlayers() as $p) {
                        $p->sendMessage(str_replace('{COUNT}', '[' . $a->getSlot(true) . '/' . $a->getSlot() . ']', str_replace('{PLAYER}', $ev->getEntity()->getName(), $this->pg->lang['game.left'])));
                    }
                }
                if (!$this->pg->configs['drops_in_arena'])
                    $ev->setDrops(array());
                break;
            }
        }
    }

    public function onDamage(EntityDamageEvent $ev)
    {
        if ($ev->getCause() == 0b100 or $ev->getCause() == 0b1100 or $ev->getCause() == 0b11) {
            $ev->setCancelled();
            return;
        }
        if ($ev->getEntity() instanceof Player) {
        if ($this->pg->arenas->inArena($ev->getEntity()->getName())) {
        if ($this->GAME_STATE == 1 and ($this->time % $this->pg->configs['NOPVP']) <= 0) {
            $ev->setCancelled();
            foreach ($this->pg->getServer()->getLevelByName($this->world)->getPlayers() as $p) {
                $p->sendTip("Invisible");
            }
            return;
        }
        }
        }
        foreach ($this->pg->arenas as $a) {
            if ($ev->getEntity() instanceof Player) {
                if ($a->inArena($ev->getEntity()->getName())) {
                    if ($ev->getCause() == 0b1111 and $this->pg->configs['starvation_can_damage_inArena_players'] == false)
                        $ev->setCancelled();
                    if ($a->GAME_STATE == 0)
                        $ev->setCancelled();
                    break;
                }
            }
        }
    }

    public function onRespawn(PlayerRespawnEvent $ev)
    {
        if ($this->pg->configs['always_spawn_in_defaultLevel'])
            $ev->setRespawnPosition($this->pg->getServer()->getDefaultLevel()->getSpawnLocation());
        //Removes player things
        if ($this->pg->configs['clear_inventory_on_respawn&join'])
            $ev->getPlayer()->getInventory()->clearAll();
        if ($this->pg->configs['clear_effects_on_respawn&join'])
            $ev->getPlayer()->removeAllEffects();
    }

    public function onBreak(BlockBreakEvent $ev)
    {
        foreach ($this->pg->arenas as $a) {
            if ($a->inArena($ev->getPlayer()->getName())) {
                if ($a->GAME_STATE == 0)
                    $ev->setCancelled();
                break;
            }
        }
        if (!$ev->getPlayer()->isOp())
            return;
        $key = (($ev->getBlock()->getX() + 0) . ':' . ($ev->getBlock()->getY() + 0) . ':' . ($ev->getBlock()->getZ() + 0) . ':' . $ev->getPlayer()->getLevel()->getName());
        if (array_key_exists($key, $this->pg->signs)) {
            $this->pg->arenas[$this->pg->signs[$key]]->stop();
            $ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'Arena reloaded !');
            if ($this->pg->setSign($this->pg->signs[$key], 0, 0, 0, 'world', true, false)) {
                $ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::GREEN . 'SW join sign deleted !');
            } else {
                $ev->getPlayer()->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'An error occured, please contact the developer');
            }
        }
        unset($key);
    }

    public function onPlace(BlockPlaceEvent $ev)
    {
        foreach ($this->pg->arenas as $a) {
            if ($a->inArena($ev->getPlayer()->getName())) {
                if ($a->GAME_STATE == 0)
                    $ev->setCancelled();
                break;
            }
        }
    }

    public function onCommand(PlayerCommandPreprocessEvent $ev)
    {
        $command = strtolower($ev->getMessage());
        if ($command{0} == '/') {
            $command = explode(' ', $command)[0];
            foreach ($this->pg->arenas as $a) {
                if ($a->inArena($ev->getPlayer()->getName())) {
                    if (in_array($command, $this->pg->configs['banned_commands_while_in_game'])) {
                        $ev->getPlayer()->sendMessage(str_replace('@', '§', $this->pg->configs['banned_command_message']));
                        $ev->setCancelled();
                    }
                    break;
                }
            }
        }
        unset($command);
    }
}
