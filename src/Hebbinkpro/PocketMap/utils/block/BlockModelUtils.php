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

namespace Hebbinkpro\PocketMap\utils\block;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\block\utils\SignLikeRotationTrait;


class BlockModelUtils
{
    public static function isHidden(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::AIR, BlockTypeIds::BARRIER => true,
            default => false
        };
    }

    public static function hasModel(Block $block): bool
    {
        return self::hasCrossModel($block) || self::hasConnections($block) || self::hasStickModel($block)
            || self::isNotFullBlock($block) || self::hasHorizontalFacing($block) || self::hasSignLikeRotation($block)
            || self::hasAnyFacing($block);
    }

    public static function hasCrossModel(Block $block): bool
    {
        if (self::isGrass($block) || self::isSapling($block) || self::isCrops($block) || self::isFlower($block)
            || self::isCoral($block) || self::isFungi($block) || self::isVines($block) || self::isFire($block)) return true;

        return match ($block->getTypeId()) {
            BlockTypeIds::CHAIN, BlockTypeIds::BIG_DRIPLEAF_STEM, BlockTypeIds::BREWING_STAND, BlockTypeIds::COBWEB,
            BlockTypeIds::SWEET_BERRY_BUSH, BlockTypeIds::DEAD_BUSH, BlockTypeIds::HANGING_ROOTS => true,
            default => false
        };
    }

    public static function isGrass(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::TALL_GRASS, BlockTypeIds::DOUBLE_TALLGRASS, BlockTypeIds::FERN, BlockTypeIds::LARGE_FERN => true,
            default => false
        };

    }

    public static function isSapling(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::OAK_SAPLING, BlockTypeIds::BIRCH_SAPLING, BlockTypeIds::SPRUCE_SAPLING, BlockTypeIds::ACACIA_SAPLING,
            BlockTypeIds::DARK_OAK_SAPLING, BlockTypeIds::JUNGLE_SAPLING, BlockTypeIds::CHERRY_SAPLING => true,
            default => false
        };
    }

    public static function isCrops(Block $block): bool
    {

        return match ($block->getTypeId()) {
            BlockTypeIds::WHEAT, BlockTypeIds::BEETROOTS, BlockTypeIds::CARROTS, BlockTypeIds::POTATOES,
            BlockTypeIds::MELON_STEM, BlockTypeIds::PUMPKIN_STEM => true,
            default => false
        };
    }

    public static function isFlower(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::DANDELION, BlockTypeIds::POPPY, BlockTypeIds::BLUE_ORCHID, BlockTypeIds::ALLIUM,
            BlockTypeIds::AZURE_BLUET, BlockTypeIds::RED_TULIP, BlockTypeIds::ORANGE_TULIP, BlockTypeIds::WHITE_TULIP,
            BlockTypeIds::PINK_TULIP, BlockTypeIds::OXEYE_DAISY, BlockTypeIds::CORNFLOWER, BlockTypeIds::LILY_OF_THE_VALLEY,
            BlockTypeIds::WITHER_ROSE, BlockTypeIds::SUNFLOWER, BlockTypeIds::LILAC, BlockTypeIds::ROSE_BUSH,
            BlockTypeIds::PEONY => true,
            default => false
        };
    }

    public static function isCoral(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::CORAL, BlockTypeIds::CORAL_FAN, BlockTypeIds::WALL_CORAL_FAN => true,
            default => false
        };
    }

    public static function isFungi(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::RED_MUSHROOM, BlockTypeIds::BROWN_MUSHROOM => true,
            default => false
        };
    }

    public static function isVines(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::CAVE_VINES, BlockTypeIds::TWISTING_VINES, BlockTypeIds::WEEPING_VINES => true,
            default => false
        };
    }

    public static function isFire(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::FIRE, BlockTypeIds::SOUL_FIRE => true,
            default => false
        };
    }

    public static function hasConnections(Block $block): bool
    {
        if (self::isFence($block) || self::isFenceGate($block) || self::isGlassPane($block) || self::isWall($block)) return true;

        return match ($block->getTypeId()) {
            BlockTypeIds::IRON_BARS => true,
            default => false
        };
    }

    public static function isFence(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::OAK_FENCE, BlockTypeIds::SPRUCE_FENCE, BlockTypeIds::BIRCH_FENCE, BlockTypeIds::JUNGLE_FENCE,
            BlockTypeIds::ACACIA_FENCE, BlockTypeIds::DARK_OAK_FENCE, BlockTypeIds::MANGROVE_FENCE, BlockTypeIds::CHERRY_FENCE,
            BlockTypeIds::CRIMSON_FENCE, BlockTypeIds::WARPED_FENCE, BlockTypeIds::NETHER_BRICK_FENCE => true,
            default => false
        };
    }

    public static function isFenceGate(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::OAK_FENCE_GATE, BlockTypeIds::SPRUCE_FENCE_GATE, BlockTypeIds::BIRCH_FENCE_GATE, BlockTypeIds::JUNGLE_FENCE_GATE,
            BlockTypeIds::ACACIA_FENCE_GATE, BlockTypeIds::DARK_OAK_FENCE_GATE, BlockTypeIds::MANGROVE_FENCE_GATE, BlockTypeIds::CHERRY_FENCE_GATE,
            BlockTypeIds::CRIMSON_FENCE_GATE, BlockTypeIds::WARPED_FENCE_GATE => true,
            default => false
        };
    }

    public static function isGlassPane(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::GLASS_PANE, BlockTypeIds::HARDENED_GLASS_PANE, BlockTypeIds::STAINED_HARDENED_GLASS_PANE, BlockTypeIds::STAINED_GLASS_PANE => true,
            default => false
        };
    }

    public static function isWall(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::COBBLESTONE_WALL, BlockTypeIds::MOSSY_COBBLESTONE_WALL, BlockTypeIds::STONE_BRICK_WALL, BlockTypeIds::MOSSY_STONE_BRICK_WALL,
            BlockTypeIds::ANDESITE_WALL, BlockTypeIds::DIORITE_WALL, BlockTypeIds::GRANITE_WALL, BlockTypeIds::SANDSTONE_WALL,
            BlockTypeIds::RED_SANDSTONE_WALL, BlockTypeIds::BRICK_WALL, BlockTypeIds::PRISMARINE_WALL, BlockTypeIds::NETHER_BRICK_WALL,
            BlockTypeIds::RED_NETHER_BRICK_WALL, BlockTypeIds::END_STONE_BRICK_WALL, BlockTypeIds::BLACKSTONE_WALL, BlockTypeIds::POLISHED_BLACKSTONE_WALL,
            BlockTypeIds::POLISHED_BLACKSTONE_BRICK_WALL, BlockTypeIds::COBBLED_DEEPSLATE_WALL, BlockTypeIds::POLISHED_DEEPSLATE_WALL,
            BlockTypeIds::DEEPSLATE_BRICK_WALL, BlockTypeIds::DEEPSLATE_TILE_WALL, BlockTypeIds::MUD_BRICK_WALL => true,
            default => false
        };
    }

    public static function hasStickModel(Block $block): bool
    {
        if (self::isTorch($block) || self::isCandle($block) || self::isBamboo($block)) return true;

        return match ($block->getTypeId()) {
            BlockTypeIds::LIGHTNING_ROD, BlockTypeIds::END_ROD => true,
            default => false
        };
    }

    public static function isTorch(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::TORCH, BlockTypeIds::SOUL_TORCH, BlockTypeIds::REDSTONE_TORCH, BlockTypeIds::UNDERWATER_TORCH,
            BlockTypeIds::BLUE_TORCH, BlockTypeIds::RED_TORCH, BlockTypeIds::PURPLE_TORCH, BlockTypeIds::GREEN_TORCH => true,
            default => false
        };
    }

    public static function isCandle(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::CANDLE, BlockTypeIds::DYED_CANDLE => true,
            default => false
        };
    }

    public static function isBamboo(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::BAMBOO, BlockTypeIds::BAMBOO_SAPLING => true,
            default => false
        };
    }

    public static function isNotFullBlock(Block $block): bool
    {
        if (self::isChest($block) || self::isPressurePlate($block) || self::isCake($block)) return true;

        return false;
    }

    public static function isChest(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::CHEST, BlockTypeIds::TRAPPED_CHEST, BlockTypeIds::ENDER_CHEST => true,
            default => false
        };
    }

    public static function isPressurePlate(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::WEIGHTED_PRESSURE_PLATE_LIGHT, BlockTypeIds::WEIGHTED_PRESSURE_PLATE_HEAVY, BlockTypeIds::STONE_PRESSURE_PLATE,
            BlockTypeIds::POLISHED_BLACKSTONE_PRESSURE_PLATE, BlockTypeIds::OAK_PRESSURE_PLATE, BlockTypeIds::SPRUCE_PRESSURE_PLATE,
            BlockTypeIds::BIRCH_PRESSURE_PLATE, BlockTypeIds::JUNGLE_PRESSURE_PLATE, BlockTypeIds::ACACIA_PRESSURE_PLATE,
            BlockTypeIds::DARK_OAK_PRESSURE_PLATE, BlockTypeIds::MANGROVE_PRESSURE_PLATE, BlockTypeIds::CHERRY_PRESSURE_PLATE,
            BlockTypeIds::CRIMSON_PRESSURE_PLATE, BlockTypeIds::WARPED_PRESSURE_PLATE => true,
            default => false
        };
    }

    public static function isCake(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::CAKE, BlockTypeIds::CAKE_WITH_CANDLE, BlockTypeIds::CAKE_WITH_DYED_CANDLE => true,
            default => false
        };
    }

    public static function hasHorizontalFacing(Block $block): bool
    {
        return in_array(HorizontalFacingTrait::class, class_uses($block::class));
    }

    public static function hasSignLikeRotation(Block $block): bool
    {
        return in_array(SignLikeRotationTrait::class, class_uses($block::class));
    }

    public static function hasAnyFacing(Block $block): bool
    {
        if (self::isStairs($block) || self::isShulker($block)) return false;

        if (in_array(AnyFacingTrait::class, class_uses($block::class))) return true;

        return match ($block->getTypeId()) {
            BlockTypeIds::LEVER => true,
            default => false
        };
    }

    public static function isStairs(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::OAK_STAIRS, BlockTypeIds::SPRUCE_STAIRS, BlockTypeIds::BIRCH_STAIRS, BlockTypeIds::JUNGLE_STAIRS,
            BlockTypeIds::ACACIA_STAIRS, BlockTypeIds::DARK_OAK_STAIRS, BlockTypeIds::MANGROVE_STAIRS, BlockTypeIds::CHERRY_STAIRS,
            BlockTypeIds::CRIMSON_STAIRS, BlockTypeIds::WARPED_STAIRS, BlockTypeIds::STONE_STAIRS, BlockTypeIds::GRANITE_STAIRS,
            BlockTypeIds::POLISHED_GRANITE_STAIRS, BlockTypeIds::DIORITE_STAIRS, BlockTypeIds::POLISHED_DIORITE_STAIRS,
            BlockTypeIds::ANDESITE_STAIRS, BlockTypeIds::POLISHED_ANDESITE_STAIRS, BlockTypeIds::COBBLESTONE_STAIRS,
            BlockTypeIds::MOSSY_COBBLESTONE_STAIRS, BlockTypeIds::STONE_BRICK_STAIRS, BlockTypeIds::MOSSY_STONE_BRICK_STAIRS,
            BlockTypeIds::BRICK_STAIRS, BlockTypeIds::END_STONE_BRICK_STAIRS, BlockTypeIds::NETHER_BRICK_STAIRS,
            BlockTypeIds::RED_NETHER_BRICK_STAIRS, BlockTypeIds::SANDSTONE_STAIRS, BlockTypeIds::SMOOTH_SANDSTONE_STAIRS,
            BlockTypeIds::SMOOTH_RED_SANDSTONE_STAIRS, BlockTypeIds::RED_SANDSTONE_STAIRS, BlockTypeIds::QUARTZ_STAIRS,
            BlockTypeIds::SMOOTH_QUARTZ_STAIRS, BlockTypeIds::PURPUR_STAIRS, BlockTypeIds::PRISMARINE_STAIRS,
            BlockTypeIds::PRISMARINE_BRICKS_STAIRS, BlockTypeIds::BLACKSTONE_STAIRS, BlockTypeIds::POLISHED_BLACKSTONE_STAIRS,
            BlockTypeIds::POLISHED_BLACKSTONE_BRICK_STAIRS, BlockTypeIds::CUT_COPPER_STAIRS, BlockTypeIds::COBBLED_DEEPSLATE_STAIRS,
            BlockTypeIds::POLISHED_DEEPSLATE_STAIRS, BlockTypeIds::DEEPSLATE_BRICK_STAIRS, BlockTypeIds::DEEPSLATE_TILE_STAIRS,
            BlockTypeIds::MUD_BRICK_STAIRS => true,
            default => false
        };
    }

    public static function isShulker(Block $block): bool
    {
        return match ($block->getTypeId()) {
            BlockTypeIds::SHULKER_BOX, BlockTypeIds::DYED_SHULKER_BOX => true,
            default => false
        };
    }
}