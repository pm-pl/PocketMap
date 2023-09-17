<?php

namespace Hebbinkpro\PocketMap\scheduler;

use Generator;
use Hebbinkpro\PocketMap\PocketMap;
use Hebbinkpro\PocketMap\region\Region;
use Hebbinkpro\PocketMap\render\WorldRenderer;
use pocketmine\scheduler\Task;

class ChunkSchedulerTask extends Task
{
    public const CACHE_FILE = "tmp/regions/render.txt";


    private PocketMap $pocketMap;

    /** @var Region[] */
    private array $queuedRegions;

    /**
     * @var array{renderer: WorldRenderer, chunks: Generator}[]
     */
    private array $chunkGenerators;

    private bool $enableCache;

    private int $maxQueueSize;

    public function __construct(PocketMap $pocketMap)
    {
        $this->pocketMap = $pocketMap;
        $this->queuedRegions = [];
        $this->chunkGenerators = [];

        $this->enableCache = PocketMap::getConfigManger()->getBool("renderer.chunk-renderer.region-cache", true);
        $this->maxQueueSize = PocketMap::getConfigManger()->getInt("chunk-loader.queue-size", 256);

        if ($this->enableCache) $this->readFromCache();
    }

    /**
     * Read the contents of the cache file and restore the contents inside the updated regions list.
     * This allows us to start directly with rendering if there were chunks added just before the server closed.
     * @return void
     */
    private function readFromCache(): void
    {
        $cacheFile = PocketMap::getFolder() . self::CACHE_FILE;
        if (!file_exists($cacheFile)) return;

        $data = file_get_contents($cacheFile);
        $this->queuedRegions = unserialize($data);
        $this->pocketMap->getLogger()->debug("Restored " . count($this->queuedRegions) . " regions from the cache");
    }

    /**
     * Add a list of chunks to the render queue
     * @param WorldRenderer $renderer
     * @param Generator $chunks
     * @return void
     */
    public function addChunks(WorldRenderer $renderer, Generator $chunks): void
    {
        $this->pocketMap->getLogger()->debug("Adding chunks generator for world: {$renderer->getWorld()->getFolderName()}");
        $this->chunkGenerators[$renderer->getWorld()->getFolderName()] = [
            "renderer" => $renderer,
            "chunks" => $chunks
        ];
    }

    /**
     * Run the chunk render task
     * 1. Add chunks from the generators
     * 2. Update all region cool-downs
     * 3. Start region renders until the queue is filled
     * @return void
     */
    public function onRun(): void
    {
        // add the chunks from the added iterator lists
        $this->loadChunksFromGenerators();

        $started = [];

        // start render for all queued chunks
        foreach ($this->queuedRegions as $name => $region) {

            // get the world renderer
            $renderer = $this->pocketMap->getWorldRenderer($region->getWorldName());
            if (!$renderer) continue;

            $isStarted = $renderer->startRegionRender($region);

            // the render did not start
            if (!$isStarted) {
                // the queue is full, so no other has to try it
                break;
            }

            $this->pocketMap->getLogger()->debug("[Chunk Render] Added region to the scheduler: " . $region->getName());
            $started[] = $name;

        }

        // remove all the started regions
        foreach ($started as $name) {
            unset($this->queuedRegions[$name]);
        }

        if ($this->enableCache) {
            // update the cache
            $cacheFile = PocketMap::getFolder() . self::CACHE_FILE;
            file_put_contents($cacheFile, serialize($this->queuedRegions));
        }
    }

    /**
     * Yield some values from the generators and add the chunks.
     * We yield up to a max cap to prevent the server from lagging when a lot of chunks are inside the generators.
     * @return void
     */
    private function loadChunksFromGenerators(): void
    {
        $finished = [];

        $maxLoad = PocketMap::getConfigManger()->getInt("renderer.chunk-renderer.generator-yield", 10);
        $loaded = 0;

        foreach ($this->chunkGenerators as $worldName => $worldChunks) {
            /** @var WorldRenderer $renderer */
            $renderer = $worldChunks["renderer"];
            /** @var Generator $chunks */
            $chunks = $worldChunks["chunks"];

            while ($chunks->valid() && $loaded < $maxLoad && count($this->queuedRegions) < $this->maxQueueSize) {
                [$cx, $cz] = $chunks->key();
                $this->addChunk($renderer, $cx, $cz);
                $chunks->next();
                $loaded++;
            }

            // finished with loading
            if (!$chunks->valid()) {
                $this->pocketMap->getLogger()->debug("Chunks generator for world: {$renderer->getWorld()->getFolderName()} is finished");
                $finished[] = $worldName;
            }

            // the max amount of chunks of this run is loaded
            if ($loaded >= $maxLoad || count($this->queuedRegions) >= $this->maxQueueSize) break;
        }

        foreach ($finished as $worldName) {
            unset($this->chunkGenerators[$worldName]);
        }
    }

    /**
     * Add a chunk to the render queue
     * @param WorldRenderer $renderer
     * @param int $chunkX
     * @param int $chunkZ
     * @return void
     */
    public function addChunk(WorldRenderer $renderer, int $chunkX, int $chunkZ): void
    {
        $world = $renderer->getWorld()->getFolderName();
        $textures = $renderer->getResourcePack();
        $region = new Region($world, WorldRenderer::MIN_ZOOM, $chunkX, $chunkZ, $textures);
        $this->queuedRegions[$region->getName()] = $region;
    }
}