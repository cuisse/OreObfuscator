<?php /** @noinspection PhpUnused */
declare(strict_types = 1);


namespace OreObfuscator;


use pocketmine\event\block\BlockBreakEvent;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\PacketPool;


/**
 * @package OreObfuscator
 */
class Main extends PluginBase implements Listener
{



    /** @var string[] */
    protected $wl_worlds = [];

    /** @var string[] */
    protected $wl_players = [];



    public function onEnable()
    {
        $this->saveResource("config.yml");

        $this->wl_worlds  = $this->getConfig()->get("worlds", []);
        $this->wl_players = $this->getConfig()->get("players", []);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }



    /**
     * @param DataPacketSendEvent $event
     * @ignoreCancelled false
     */
    public function chunkPacket(DataPacketSendEvent $event)
    {
        /** @var $batch BatchPacket */
        if (($batch = $event->getPacket()) instanceof BatchPacket && !($batch instanceof ModifiedChunk))
        {
            if (isset($this->wl_worlds[$event->getPlayer()->getLevel()->getFolderName()]) || isset($this->wl_players[$event->getPlayer()->getName()]))
                return;

            $batch->decode();

            foreach ($batch->getPackets() as $packet)
            {
                $chunkPacket = PacketPool::getPacket($packet);
                if ($chunkPacket instanceof LevelChunkPacket)
                {
                    $chunkPacket->decode();
                    $this->getServer()->getAsyncPool()->submitTask(new ChunkModifierTask($event->getPlayer()->getLevel()->getChunk($chunkPacket->getChunkX(), $chunkPacket->getChunkZ()), $event->getPlayer()));
                    $event->setCancelled();
                }
            }

        }
    }



    /**
     * @param BlockBreakEvent $event
     */
    public function updateBlocks(BlockBreakEvent $event)
    {
        $blocks  = [$event->getBlock()->asVector3()];
        $players = $event->getBlock()->getLevel()->getChunkPlayers($event->getBlock()->getFloorX() >> 4, $event->getBlock()->getFloorZ() >> 4);

        foreach (ChunkModifierTask::BLOCK_SIDES as $side)
        {
            $side = $blocks[0]->getSide($side);

            foreach (ChunkModifierTask::BLOCK_SIDES as $side_2)
                $blocks[] = $side->getSide($side_2);

            $blocks[] = $side;
        }

        $event->getPlayer()->getLevel()->sendBlocks($players, $blocks, UpdateBlockPacket::FLAG_NEIGHBORS);
    }



}