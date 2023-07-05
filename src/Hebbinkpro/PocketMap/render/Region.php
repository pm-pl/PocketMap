<?php

namespace Hebbinkpro\PocketMap\render;

use Generator;
use Hebbinkpro\PocketMap\utils\ResourcePack;

class Region
{
    private string $worldName;
    private int $zoom;
    private int $regionX;
    private int $regionZ;
    private ResourcePack $rp;


    public function __construct(string $worldName, int $zoom, int $regionX, int $regionZ, ResourcePack $rp)
    {
        $this->worldName = $worldName;
        $this->zoom = $zoom;
        $this->regionX = $regionX;
        $this->regionZ = $regionZ;
        $this->rp = $rp;
    }

    /**
     * Yields all chunk coordinates that are inside the region
     * @return int[]|Generator
     */
    public function getChunks(): Generator|array
    {
        $minX = $this->regionX * $this->getTotalChunks();
        $minZ = $this->regionZ * $this->getTotalChunks();

        for ($x = $minX; $x < $minX + $this->getTotalChunks(); $x++) {
            for ($z = $minZ; $z < $minZ + $this->getTotalChunks(); $z++) {
                yield [$x, $z];
            }
        }
    }

    public function getTotalChunks(): int
    {
        return WorldRenderer::ZOOM_LEVELS[$this->zoom];
    }

    /**
     * @return ResourcePack
     */
    public function getResourcePack(): ResourcePack
    {
        return $this->rp;
    }

    /**
     * Get the coordinates of a chunk inside the region.
     * @param int $worldChunkX the x coordinate of the chunk inside the world
     * @param int $worldChunkZ the z coordinate of the chunk inside the world
     * @return int[]
     */
    public function getRegionChunkCoords(int $worldChunkX, int $worldChunkZ): array
    {
        return [
            $worldChunkX - ($this->regionX * $this->getTotalChunks()),
            $worldChunkZ - ($this->regionZ * $this->getTotalChunks())
        ];
    }

    /**
     * Get the coordinates of a chunk inside the world
     * @param int $regionChunkX the x coordinate of the chunk inside the region
     * @param int $regionChunkZ the z coordinate of the chunk inside the region
     * @return float[]|int[]
     */
    public function getWorldChunkCoords(int $regionChunkX, int $regionChunkZ): array
    {
        return [
            $this->regionX * $this->getTotalChunks() + $regionChunkX,
            $this->regionZ * $this->getTotalChunks() + $regionChunkZ
        ];
    }

    public function getPixelsPerBlock(): int
    {
        return WorldRenderer::getPixelsPerBlock($this->zoom, $this->rp);
    }

    public function getChunkPixelSize(): int
    {
        return floor(WorldRenderer::RENDER_SIZE / $this->getTotalChunks());
    }

    /**
     * Compares the region with a given region.
     * @param Region $region the region to compare with
     * @return bool true if the region is the same, false otherwise
     */
    public function equals(Region $region): bool
    {
        return $region->getWorldName() === $this->worldName &&
            $region->getZoom() == $this->zoom &&
            $region->getRegionX() == $this->regionX &&
            $region->getRegionZ() == $this->regionZ;
    }

    /**
     * @return string
     */
    public function getWorldName(): string
    {
        return $this->worldName;
    }

    /**
     * @return int
     */
    public function getZoom(): int
    {
        return $this->zoom;
    }

    /**
     * @return int
     */
    public function getRegionX(): int
    {
        return $this->regionX;
    }

    /**
     * @return int
     */
    public function getRegionZ(): int
    {
        return $this->regionZ;
    }
}