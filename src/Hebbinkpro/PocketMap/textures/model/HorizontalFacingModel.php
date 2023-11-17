<?php
/*
 *   _____           _        _   __  __
 *  |  __ \         | |      | | |  \/  |
 *  | |__) |__   ___| | _____| |_| \  / | __ _ _ __
 *  |  ___/ _ \ / __| |/ / _ \ __| |\/| |/ _` | '_ \
 *  | |  | (_) | (__|   <  __/ |_| |  | | (_| | |_) |
 *  |_|   \___/ \___|_|\_\___|\__|_|  |_|\__,_| .__/
 *                                            | |
 *                                            |_|
 *
 * Copyright (C) 2023 Hebbinkpro
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace Hebbinkpro\PocketMap\textures\model;

use GdImage;
use Hebbinkpro\PocketMap\utils\block\BlockUtils;
use pocketmine\block\Block;
use pocketmine\block\FenceGate;
use pocketmine\math\Facing;
use pocketmine\world\format\Chunk;

abstract class HorizontalFacingModel extends BlockModel
{

    public function getModelTexture(Block $block, Chunk $chunk, GdImage $texture): GdImage
    {
        $model = parent::getModelTexture($block, $chunk, $texture);

        $rotation = $this->getRotation($block);
        if ($rotation != 0) {
            // rotate the model
            $rotated = imagerotate($model, $rotation, imagecolorallocatealpha($model, 0, 0, 0, 127));
            imagedestroy($model);
            $model = $rotated;
        }

        return $model;
    }

    public function getRotation(Block $block): int
    {
        if (!BlockUtils::hasHorizontalFacing($block)) return 0;
        /** @type FenceGate $block */

        return match ($block->getFacing()) {
            Facing::NORTH => 0,
            Facing::EAST => 90,
            Facing::SOUTH => 180,
            Facing::WEST => 270,
            default => 0
        };
    }
}