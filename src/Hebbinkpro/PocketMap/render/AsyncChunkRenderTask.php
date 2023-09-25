<?php

namespace Hebbinkpro\PocketMap\render;

use GdImage;
use Hebbinkpro\PocketMap\region\Region;
use Hebbinkpro\PocketMap\region\RegionChunks;
use Hebbinkpro\PocketMap\textures\TerrainTextures;
use Hebbinkpro\PocketMap\utils\block\BlockStateParser;
use Hebbinkpro\PocketMap\utils\ColorMapParser;
use Hebbinkpro\PocketMap\utils\TextureUtils;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Flowable;
use pocketmine\block\Opaque;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

class AsyncChunkRenderTask extends AsyncRenderTask
{
    private string $encodedChunks;

    public function __construct(RegionChunks $regionChunks, string $renderPath)
    {
        parent::__construct($regionChunks->getRegion(), $renderPath);

        $this->encodedChunks = $regionChunks->encode();
    }

    function render(Region $region, GdImage $image): string
    {
        $terrainTextures = $region->getTerrainTextures();

        $chunkSize = $region->getChunkPixelSize();

        // amount of visible full blocks inside the render
        $totalBlocks = TextureUtils::getTotalBlocks($chunkSize);
        // the size of pixels of each visible block
        $imgPixelsPerBlock = TextureUtils::getPixelsPerBlock($chunkSize, $totalBlocks);
        // the total size of the chunk image
        $imgChunkSize = $totalBlocks * $imgPixelsPerBlock;

        $chunks = [];

        $regionChunksGenerator = RegionChunks::yieldAllEncodedChunks($this->encodedChunks);
        unset($this->encodedChunks); // free up some space

        // yield all chunks
        foreach ($regionChunksGenerator as [$cx, $cz, $chunk]) {
            // save the chunk to the region data
            $chunks[] = [$cx, $cz];

            // create the chunk image
            $chunkImg = $this->createChunkTexture($chunk, $terrainTextures, $totalBlocks, $imgPixelsPerBlock);

            [$rcx, $rcz] = $region->getRegionChunkCoords($cx, $cz);

            // pixel coords the chunk starts
            $dx = floor($rcx * $chunkSize);
            $dz = floor($rcz * $chunkSize);

            // copy the chunk img onto the render
            // if imgPixelsPerBlock>pixelsPerBlock, this will resize it to the smaller variant
            imagecopyresized($image, $chunkImg, $dx, $dz, 0, 0, $chunkSize, $chunkSize, $imgChunkSize, $imgChunkSize);
            imagedestroy($chunkImg);
        }

        // clear the image cache
        $this->clearCache();

        return serialize($chunks);
    }

    /**
     * Create the texture of the given chunk
     * @param Chunk $chunk the chunk
     * @param TerrainTextures $terrainTextures the resource pack
     * @param int $totalBlocks the amount of blocks visible in the texture
     * @param int $pixelsPerBlock the amount of pixels of each block
     * @return GdImage the texture image of the chunk
     */
    private function createChunkTexture(Chunk $chunk, TerrainTextures $terrainTextures, int $totalBlocks, int $pixelsPerBlock): GdImage
    {
        $textureSize = $totalBlocks * $pixelsPerBlock;

        $texture = imagecreatetruecolor($textureSize, $textureSize);

        // amount of blocks between two blocks to render
        // this is to prevent rendering of only the upper left corner for rendering when <16 pixels are available for a chunk
        $diff = floor(16 / $totalBlocks);

        $color = $terrainTextures->getOptions()->getHeightOverlayColor();
        $alpha = $terrainTextures->getOptions()->getHeightOverlayAlpha();

        $r = ($color >> 16) & 0xff;
        $g = ($color >> 8) & 0xff;
        $b = $color & 0xff;
        $heightOverlay = $this->getHeightOverlay(imagecolorallocatealpha($texture, $r, $g, $b, 127 - $alpha), $pixelsPerBlock);

        // loop through all block indices that can be rendered
        for ($bdxI = 0; $bdxI < $totalBlocks; $bdxI++) {
            for ($bdzI = 0; $bdzI < $totalBlocks; $bdzI++) {
                // get the real x and z positions from the indices
                $bdx = $bdxI * $diff;
                $bdz = $bdzI * $diff;
                $highestY = $chunk->getHighestBlockAt($bdx, $bdz);

                $blockTexture = $this->getBlockTexture($bdx, $bdz, $chunk, $terrainTextures, $pixelsPerBlock);

                if ($highestY % 2 != 0) {

                    imagecopy($blockTexture, $heightOverlay, 0, 0, 0, 0, $pixelsPerBlock, $pixelsPerBlock);
                }

                $tx = $bdxI * $pixelsPerBlock;
                $ty = $bdzI * $pixelsPerBlock;

                imagecopy($texture, $blockTexture, $tx, $ty, 0, 0, $pixelsPerBlock, $pixelsPerBlock);
            }
        }

        imagedestroy($heightOverlay);

        return $texture;
    }

    private function getHeightOverlay(int $color, int $pixelsPerBlock): GdImage
    {


        $heightOverlay = imagecreatetruecolor($pixelsPerBlock, $pixelsPerBlock);
        imagefill($heightOverlay, 0, 0, $color);

        return $heightOverlay;
    }

    private function getBlockTexture(int $x, int $z, Chunk $chunk, TerrainTextures $terrainTextures, int $pixelsPerBlock): ?GdImage
    {
        $y = $chunk->getHighestBlockAt($x, $z);

        // there is no block on this position
        if ($y === null) return null;

        $blocks = [];
        $blockIds = [];
        $waterDepth = 0;
        $height = 0;
        while ($y > World::Y_MIN) {
            $blockStateId = $chunk->getBlockStateId($x, $y, $z);
            $block = BlockStateParser::getBlockFromStateId($blockStateId);

            // it's a solid block
            if ($block instanceof Opaque) {
                $blocks[] = $block;
                break;
            }

            // it's water
            if ($block->getTypeId() === BlockTypeIds::WATER) {
                if ($waterDepth == 0) $blocks[] = $block;
                $waterDepth++;
            } // it's air
            else if ($block->getTypeId() === BlockTypeIds::AIR || in_array($block->getTypeId(), $blockIds) || $block instanceof Flowable) {
                $height++;
            } // it's another transparent block
            else {
                $blockIds[] = $block->getTypeId();
                $blocks[] = $block;
            }

            $y--;
        }

        $biomeId = $chunk->getBiomeId($x, $y, $z);
        $biome = BiomeRegistry::getInstance()->getBiome($biomeId);

        // loop from the bottom to the top in the blocks list
        // the latest added block has to be rendered under the previous block
        $texture = imagecreatetruecolor($pixelsPerBlock, $pixelsPerBlock);

        for ($i = count($blocks) - 1; $i >= 0; $i--) {
            $block = $blocks[$i];

            $blockTexture = TextureUtils::createCompressedBlockTexture($block, $biome, $terrainTextures, $pixelsPerBlock);
            $blockTexture = TextureUtils::rotateToFacing($blockTexture, BlockStateParser::getBlockFace($block));

            if ($block->getTypeId() === BlockTypeIds::WATER) {
                $waterAlpha = floor(32 - (4 * ColorMapParser::getWaterTransparency($biome, $terrainTextures) * $waterDepth));
                TextureUtils::applyAlpha($blockTexture, $waterAlpha, $pixelsPerBlock);
            }

            // it's the latest texture and there is a height difference
            if ($i == 0 && $height > 0) {
                // apply the height overlay to the full texture excluding the top block

                $heightAlpha = 96 - 8 * $height;
                if ($heightAlpha < 0) $heightAlpha = 0;
                $color = imagecolorallocatealpha($texture, 0, 0, 0, $heightAlpha);
                $heightOverlay = $this->getHeightOverlay($color, $pixelsPerBlock);

                imagealphablending($texture, true);
                imagecopy($texture, $heightOverlay, 0, 0, 0, 0, $pixelsPerBlock, $pixelsPerBlock);
            }

            imagealphablending($blockTexture, true);
            imagecopy($texture, $blockTexture, 0, 0, 0, 0, $pixelsPerBlock, $pixelsPerBlock);

            imagedestroy($blockTexture);
        }

        return $texture;
    }
}