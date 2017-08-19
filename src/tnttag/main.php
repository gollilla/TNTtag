<?php

namespace tnttag;

use pocketmine\Server;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\level\particle\LavaParticle;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerHungerChangeEvent;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\event\Listener;
use pocketmine\scheduler\PluginTask;
use pocketmine\item\Item;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\utils\UUID;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\protocol\InteractPacket;

class main extends PluginBase implements Listener
{
    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        if (!file_exists($this->getDataFolder())) {
            mkdir($this->getDataFolder(), 0744, true);
        }
        $this->con = new Config($this->getDataFolder().'config.yml', Config::YAML, array('x' => '0', 'y' => '4', 'z' => '0', 'level' => 'world', 'w2' => 'world', 'lx' => '0', 'ly' => '4', 'lz' => '0', 'mx' => '0', 'my' => '4', 'mz' => '0'));
        $this->con->save();
        $this->point = new Config($this->getDataFolder().'point.yml', Config::YAML, array());
        $this->point->save();
    }
    public function load()
    {
        $this->game['status'] = 'prepare';
        $this->game['players'] = [];
        $this->game['count'] = 0;
        $this->game['round'] = 1;
    }
    public function onDisable()
    {
        $this->con->save();
    }
    public function onJoin(PlayerJoinEvent $ev)
    {
        $player = $ev->getPlayer();
        $name = $player->getName();
        $player->getInventory()->clearAll();
        if (!$this->point->exists($name)) {
            $this->point->set($name, '0');
            $this->point->save();
        }
        $eid1 = '123456789';
        $pk = new AddPlayerPacket();
        $pk->eid = $eid1;
        $pk->uuid = UUID::fromRandom();
        $pk->x = $this->con->get('mx');
        $pk->y = $this->con->get('my');
        $pk->z = $this->con->get('mz');
        $pk->speedX = 0;
        $pk->speedY = 0;
        $pk->speedZ = 0;
        $pk->yaw = 0;
        $pk->pitch = 0;
        $pk->item = Item::get(46, 0, 1);
        $flags = [Entity::DATA_FLAG_CAN_SHOW_NAMETAG, Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG, Entity::DATA_FLAG_IMMOBILE];
        $pk->metadata = [Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags], Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING,'§l§cJoin to §eTNTtag!!'], Entity::DATA_LEAD_HOLDER_EID => [Entity::DATA_TYPE_LONG, -1]];
        Server::getInstance()->broadcastPacket(Server::getInstance()->getOnlinePlayers(), $pk);
    }
    public function onHunger(PlayerHungerChangeEvent $ev)
    {
        $ev->setCancelled();
    }
    public function onReceive(DataPacketReceiveEvent $ev)
    {
        $pk = $ev->getPacket();
        $player = $ev->getPlayer();
        $name = $player->getName();
        $eid = '123456789';
        if ($pk instanceof InteractPacket) {
            if ($pk->target == $eid) {
                if ($this->game['status'] == 'prepare') {
                    array_push($this->game['players'], $name);
                    $player->sendMessage('§bInfo§f >§6ゲームに参加しました');
                    $level = Server::getInstance()->getLevelByName($this->con->get('level'));
                    $pos = new Position($this->con->get('x'), $this->con->get('y'), $this->con->get('z'), $level);
                    $player->teleport($pos);
                    if (count($this->game['players']) >= 1) {
                        if ($this->game['count'] == 0) {
                            $task = new count($this, 30);
                            $this->getServer()->getScheduler()->scheduleRepeatingTask($task, 20);
                            $this->game['count'] = 1;
                        }
                    }
                }
                if ($this->game['status'] == 'now') {
                    $player->sendMessage('§a只今ゲーム中です。お待ちください');
                }
            }
        }
    }
    public function start()
    {
        $this->game['status'] = 'now';
        foreach ($this->game['players'] as $name) {
            $player = $this->getServer()->getPlayer($name);
            $level = Server::getInstance()->getLevelByName($this->con->get('w2'));
            $pos = new Position($this->con->get('lx'), $this->con->get('ly'), $this->con->get('lz'), $level);
            if ($player instanceof Player) {
                $player->teleport($pos);
            }
        }
        $tnt = Item::get(46, 0, 1);
        $onic = floor(count($this->game['players']) * 0.4);
        $keys = array_rand($this->game['players'], $onic);
        if (count($keys) > 1) {
            foreach ($keys as $key) {
                array_push($this->game['onis'], $this->game['players'][$key]);
            }
        } elseif (count($keys) == 1) {
            array_push($this->game['onis'], $this->game['players'][$keys]);
        }
        foreach ($this->game['onis'] as $name) {
            $oni = $this->getServer()->getPlayer($name);
            if ($oni instanceof Player) {
                $oni->sendMessage('§c§lYou Became Bomber!!');
                $oni->setNameTag('§cBomber §f| §a'.$oni->getName());
                $oni->setDisplayName('§cBomber §f| §a'.$oni->getName());
                $oni->getInventory()->setItemInHand($tnt);
            }
        }
        $task2 = new BombTimingTask($this, 20);
        $this->getServer()->getScheduler()->scheduleRepeatingTask($task2, 20);
    }
    public function broadcastPopup($message)
    {
        foreach ($this->game['living'] as $name) {
            $player = $this->getServer()->getPlayer($name);
            if ($player instanceof Player) {
                $player->sendPopup($message);
            } else {
                echo "Warnig : no player such as $player on null";
            }
        }
    }
    public function broadcastTip($message)
    {
        foreach ($this->game['living'] as $name) {
            $player = $this->getServer()->getPlayer($name);
            if ($player instanceof Player) {
                $player->sendTip($message);
            } else {
                echo "Warnig : no player such as $player on null";
            }
        }
    }
    public function addOni()
    {
        $tnt = Item::get(46, 0, 1);
        ++$this->game['round'];
        if (count($this->game['living']) > 2) {
            $onic = floor(count($this->game['living']) * 0.4);
            $keys = array_rand($this->game['living'], $onic);
            if (count($keys) == 1) {
                array_push($this->game['onis'], $this->game['living'][$keys]);
                $oni = $this->getServer()->getPlayer($this->game['living'][$keys]);
                if ($oni instanceof Player) {
                    $oni->sendMessage('§c§lYou became Bomber');
                    $oni->setNameTag('§cBomber §f| §a'.$oni->getName());
                    $oni->setDisplayName('§cBomber §f| §a'.$oni->getName());
                    $oni->getInventory()->setItemInHand($tnt);
                    $oni->getInventory()->setItemInHand($tnt);
                }
            } else {
                foreach ($keys as $key) {
                    array_push($this->game['onis'], $this->game['living'][$key]);
                    $oni = $this->getServer()->getPlayer($this->game['living'][$key]);
                    if ($oni instanceof Player) {
                        $oni->sendMessage('§c§lYou became Bomber');
                        $oni->setNameTag('§cBomber §f| §a'.$oni->getName());
                        $oni->setDisplayName('§cBomber §f| §a'.$oni->getName());
                        $oni->getInventory()->setItemInHand($tnt);
                        $oni->getInventory()->setItemInHand($tnt);
                    }
                }
            }
        }
        if ($this->getSc() <= 2) {
            $key = array_rand($this->game['living'], 1);
            array_push($this->game['onis'], $this->game['living'][$key]);
            $oni = $this->getServer()->getPlayer($this->game['living'][$key]);
            if ($oni instanceof Player) {
                $oni->sendMessage('§c§lYou became Bomber');
                $oni->setNameTag('§cBomber §f| §a'.$oni->getName());
                $oni->setDisplayName('§cBomber §f| §a'.$oni->getName());
                $oni->getInventory()->setItemInHand($tnt);
                $oni->getInventory()->setItemInHand($tnt);
            }
        }
        $task = new BombTimingTask($this, 20);
        $this->getServer()->getScheduler()->scheduleRepeatingTask($task, 20);
    }
    public function addPoint($name, $point)
    {
        if ($this->point->exists($name)) {
            $addp = $point + $this->point->get($name);
            $this->point->set($name, $addp);
            $this->point->save();

            return true;
        } else {
            return false;
        }
    }
    public function check()
    {
        if (count($this->game['players']) >= 4) {
            $this->start();
        } else {
            foreach ($this->game['players'] as $name) {
                $player = $this->getServer()->getPlayer($name);
                if ($player instanceof Player) {
                    $player->sendMessage('§eNotice §f| §aプレイヤーが少ないので戻ります');
                    $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                }
            }
            $this->load();
        }
    }
    public function end($reason = '')
    {
        if ($reason !== '') {
            switch ($reason) { case 'onlyone': foreach ($this->game['living'] as $name) {
     if ($name !== null) {
         $this->getServer()->broadcastMessage('Info§c > §b'.$name.'§6 が生き残りました');
         $livi = $this->getServer()->getPlayer($name);
         if ($livi instanceof Player) {
             $livi->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
             $livi->sendMessage('§bInfo§f > §620Point §a手に入れました');
             $this->addPoint($name, 20);
         }
     }
 } break; case 'no player': $this->getServer()->broadcastMessage('§cWarnig §f| §eプレイヤーが居なくなったのでゲームを終了します'); break; }
            $this->load();
        } else {
            $this->load();
        }
    }
    public function getStatus()
    {
        return $this->game['status'];
    }
    public function getPc()
    {
        if (!empty($this->game['players'])) {
            return count($this->game['players']);
        } else {
            return false;
        }
    }
    public function getSc()
    {
        if (!empty($this->game['living'])) {
            return count($this->game['living']);
        } else {
            return false;
        }
    }
    public function getRound()
    {
        return $this->game['round'];
    }
    public function isGamer($name)
    {
        if (in_array($name, $this->game['players'])) {
            return true;
        } else {
            return false;
        }
    }
    public function isLiving($name)
    {
        if ($this->isGamer($name)) {
            if (in_array($name, $this->game['living'])) {
                return true;
            } else {
                return false;
            }
        }
    }
    public function isOni($name)
    {
        if (in_array($name, $this->game['onis'])) {
            return true;
        } else {
            return false;
        }
    }
    public function onDamage(EntityDamageEvent $ev)
    {
        $tnt = Item::get(46, 0, 1);
        $entity = $ev->getEntity();
        if ($ev instanceof EntityDamageByEntityEvent) {
            $damager = $ev->getDamager();
            if ($entity instanceof Player and $damager instanceof Player) {
                $ename = $entity->getName();
                $dname = $damager->getName();
                if ($this->isOni($dname)) {
                    if (!$this->isOni($ename)) {
                        if ($this->isGamer($ename)) {
                            $key = array_search($dname, $this->game['onis']);
                            unset($this->game['onis'][$key]);
                            array_values($this->game['onis']);
                            $damager->setNameTag($dname);
                            $damager->setDisplayName($dname);
                            $damager->getInventory()->clearAll();
                            array_push($this->game['onis'], $ename);
                            $entity->setNameTag('§cBomber §f| §a'.$ename);
                            $entity->setDisplayName('§cBomber §f| §a'.$ename);
                            $entity->sendMessage('§c§lYou bcame Bomber!!');
                            $entity->getInventory()->setItemInHand($tnt);
                            $entity->getInventory()->setItemInHand($tnt);
                        } else {
                            $damager->sendMessage('§eその人はゲームに参加していないようです');
                        }
                    } else {
                        $damager->sendMessage('§cその人は既にBomberです');
                    }
                }
            }
            $ev->setCancelled();
        }
    }
    public function Bomb()
    {
        foreach ($this->game['onis'] as $name) {
            $oni = $this->getServer()->getPlayer($name);
            if ($oni instanceof Player) {
                $pos = new Vector3($oni->x, $oni->y + 1, $oni->z);
                $particle = new LavaParticle($pos);
                $count = 90;
                for ($i = 0;$i < $count;++$i) {
                    $oni->getLevel()->addParticle($particle);
                }
                $oni->getInventory()->clearAll();
                $oni->setNameTag($oni->getName());
                $oni->setDisplayName($oni->getName());
                $oni->sendMessage('§eNotice §f| §c§lYou are dead!!');
                $oni->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                $oni->sendMessage('§bInfo§f > §610Point §a手に入れました');
                $this->addPoint($name, 10);
            }
            $key = array_search($name, $this->game['living']);
            unset($this->game['living'][$key]);
            array_values($this->game['living']);
        }
        if (count($this->game['living']) > 1) {
            $task = new NextRound($this, 10);
            $this->getServer()->getScheduler()->scheduleRepeatingTask($task, 20);
            $this->game['onis'] = [];
        }
        if (count($this->game['living']) == 1) {
            $this->end('onlyone');
        }
    }
    public function onQuit(PlayerQuitEvent $ev)
    {
        $player = $ev->getPlayer();
        $name = $player->getName();
        if ($this->isGamer($name)) {
            $key1 = array_search($name, $this->game['players']);
            unset($this->game['players'][$key1]);
            array_values($this->game['players']);
            if ($this->isLiving($name)) {
                $key2 = array_search($name, $this->game['living']);
                unset($this->game['living'][$key2]);
                array_values($this->game['living']);
            }
        }
        if ($this->isOni($name)) {
            $key3 = array_search($name, $this->game['onis']);
            unset($this->game['onis'][$key3]);
            array_values($this->game['onis']);
        }
        if ($this->game['status'] == 'now') {
            if (count($this->game['players']) <= 0) {
                $this->end('no player');
            }
        }
    }
    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args)
    {
        $com = $cmd->getName();
        switch ($com) { case 'sp': if ($sender->isOp()) {
     $x = $sender->x;
     $y = $sender->y;
     $z = $sender->z;
     $this->con->set('mx', $x);
     $this->con->save();
     $this->con->set('my', $y);
     $this->con->save();
     $this->con->set('mz', $z);
     $this->con->save();
     $sender->sendMessage('§eMobの位置を変更しました');
 } break; case 'cg': if ($sender->isOp()) {
     if (isset($this->mode[$sender->getName()])) {
         if ($this->mode[$sender->getName()] == 'off') {
             $this->mode[$sender->getName()] = 'on';
             $sender->setGamemode(1);
             $sender->sendMessage('§bInfo§f > §e建築モードをonにしました');
         } else {
             $this->mode[$sender->getName()] = 'off';
             $sender->setGamemode(0);
             $sender->sendMessage('§bInfo§f > §a建築モードをoffにしました');
         }
     }
 } break; case 'mp': $name = $sender->getName(); $point = $this->point->get($name); $sender->sendMessage('§eInfo§f > §bあなたのPointは§6 '.$point.'　§bです'); break; case 'prank': $rank = $this->point->getAll(); arsort($rank); $sender->sendMessage('§b------ §6Pointランキング §b------'); $i = 0; foreach ($rank as $key => $value) {
     ++$i;
     if ($i <= 5) {
         $sender->sendMessage('§a'.$i.'§6位 §e'.$key.'さん  : §6'.$value.' §ePoint');
     }
 } break; }
    }
} class count extends PluginTask
{
    public function __construct(PluginBase $owner, $count)
    {
        parent::__construct($owner);
        $this->count = $count;
    }
    public function onRun($currentTick)
    {
        $count = --$this->count;
        $pcount = $this->getOwner()->getPc();
        if ($count > 10) {
            $this->getOwner()->getServer()->broadcastPopup('§b§l'.$count.' §6§ls  §a'.$pcount.'§6nin§r');
        }
        if ($count <= 10) {
            if ($count >= 0) {
                $this->getOwner()->getServer()->broadcastPopup('§c§l'.$count.' §6§ls  §a'.$pcount.'§6nin§r');
            }
        }
        if ($count == 0) {
            $this->getOwner()->check();
        }
    }
} class BombTimingTask extends PluginTask
{
    public $stat;
    public function __construct(PluginBase $owner, $count)
    {
        parent::__construct($owner);
        $this->count = $count;
        $this->stat = 0;
    }
    public function onRun($currentTick)
    {
        $count = --$this->count;
        if ($this->getOwner()->getStatus() == 'prepare') {
            $this->count = 0;
        }
        $round = $this->getOwner()->getRound();
        $lcount = $this->getOwner()->getSc();
        $pcount = $this->getOwner()->getPc();
        if ($count > 0) {
            $this->getOwner()->broadcastPopup('                                             §6Bomb in... §c'.$count."\n                                             §eRound : §b".$round."\n                                             §6".$lcount.' / '.$pcount."\n\n\n\n");
        }
        if ($this->stat == 0) {
            if ($count == 0) {
                $this->getOwner()->broadcastPopup('                                             §6Bomb in... §c'.$count."\n                                             §eRound : §b".$round."\n                                             §6".$lcount.' / '.$pcount."\n\n\n\n");
                $this->getOwner()->Bomb();
                $this->stat = 1;
            }
        }
    }
} class NextRound extends PluginTask
{
    public $stat;
    public function __construct(PluginBase $owner, $count)
    {
        parent::__construct($owner);
        $this->stat = 0;
        $this->count = $count;
    }
    public function onRun($currentTick)
    {
        $count = --$this->count;
        $lcount = $this->getOwner()->getSc();
        $pcount = $this->getOwner()->getPc();
        if ($this->getOwner()->getStatus() == 'prepare') {
            $this->count = 0;
        }
        if ($count > 0) {
            $this->getOwner()->broadcastPopup('                                        §6NextRound... §c'.$count."\n\n                                             §6".$lcount.' / '.$pcount."\n\n\n\n");
        }
        if ($this->stat == 0) {
            if ($count == 0) {
                $this->getOwner()->broadcastPopup('                                        §6NextRound... §c'.$count."\n\n                                             §6".$lcount.' / '.$pcount."\n\n\n\n");
                $this->getOwner()->addOni();
                $this->stat = 1;
            }
        }
    }
}
