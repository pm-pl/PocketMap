<?php

namespace Hebbinkpro\PocketMap;

use Exception;
use Hebbinkpro\PocketMap\render\WorldRenderer;
use Hebbinkpro\PocketMap\task\ChunkRenderTask;
use Hebbinkpro\PocketMap\task\RenderSchedulerTask;
use Hebbinkpro\PocketMap\task\UpdateApiTask;
use Hebbinkpro\PocketMap\utils\ConfigManager;
use Hebbinkpro\PocketMap\utils\ResourcePack;
use Hebbinkpro\WebServer\exception\WebServerException;
use Hebbinkpro\WebServer\http\HttpRequest;
use Hebbinkpro\WebServer\http\HttpResponse;
use Hebbinkpro\WebServer\libs\Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;
use Hebbinkpro\WebServer\route\Router;
use Hebbinkpro\WebServer\WebServer;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\item\StringToItemParser;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ResourcePackException;
use pocketmine\resourcepacks\ResourcePackManager;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\utils\Config;
use pocketmine\utils\Filesystem;
use pocketmine\world\World;

class PocketMap extends PluginBase implements Listener
{
    public const CONFIG_VERSION = 1.4;

    public const RESOURCE_PACK_PATH = "resource_packs/";
    public const RESOURCE_PACK_NAME = "v1.20.10.1";
    public const TEXTURE_SIZE = 16;
    public const RENDER_PATH = "renders/";

    private static ConfigManager $configManager;
    private static string $tmpDataPath;

    private ResourcePack $resourcePack;
    private WebServer $webServer;

    private RenderSchedulerTask $renderScheduler;
    private ChunkRenderTask $chunkRenderer;

    /** @var WorldRenderer[] */
    private array $worldRenderers = [];

    public static function getConfigManger(): ConfigManager
    {
        return self::$configManager;
    }

    /**
     * Get the Tmp data path
     * @return string
     */
    public static function getTmpDataPath(): string
    {
        return self::$tmpDataPath;
    }

    /**
     * Get a world renderer by its world or the name of the world
     * @param World|string $world The world or the name of the world
     * @return WorldRenderer|null the WorldRenderer or null when it wasn't found
     */
    public function getWorldRenderer(World|string $world): ?WorldRenderer
    {
        if (is_string($world)) $worldName = $world;
        else $worldName = $world->getFolderName();

        return $this->worldRenderers[$worldName] ?? null;
    }

    /**
     * Create a world renderer for a given world
     * @param World $world
     * @return WorldRenderer
     */
    public function createWorldRenderer(World $world): WorldRenderer
    {
        $path = $this->getDataFolder() . PocketMap::RENDER_PATH . $world->getFolderName() . "/";
        $renderer = new WorldRenderer($world, $this->getResourcePack(), $path, $this->getRenderScheduler(), $this->chunkRenderer);
        $this->worldRenderers[$world->getFolderName()] = $renderer;
        return $renderer;
    }

    /**
     * Get the resource pack
     * @return ResourcePack
     */
    public function getResourcePack(): ResourcePack
    {
        return $this->resourcePack;
    }

    /**
     * Get the render scheduler
     * @return RenderSchedulerTask
     */
    public function getRenderScheduler(): RenderSchedulerTask
    {
        return $this->renderScheduler;
    }

    /**
     * Get the chunk renderer
     * @return ChunkRenderTask
     */
    public function getChunkRenderer(): ChunkRenderTask
    {
        return $this->chunkRenderer;
    }

    /**
     * Remove a world renderer
     * @param World $world
     * @return void
     */
    public function removeWorldRenderer(World $world): void
    {
        unset($this->worldRenderers[$world->getFolderName()]);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "reload":
                $this->getLogger()->info("Reloading all config files...");
                $this->loadResources(true);
                $this->getLogger()->info("All config files are reloaded");
                break;

            default:
                return false;
        }

        return true;
    }

    /**
     * Load all resources in the plugin data folder
     * @param bool $reloadWebFiles if the web files should be replaced by the files inside the resources folder
     * @return void
     */
    private function loadResources(bool $reloadWebFiles = false): void
    {
        // save the config file
        $this->saveDefaultConfig();

        $config = $this->getConfig();
        $version = $config->get("version", -1.0);
        if ($version != self::CONFIG_VERSION) {
            $this->getLogger()->notice("The current version of PocketMap is using another config version.");
            $this->getLogger()->info("You can find your old config in 'config_v$version.yml'");
            $this->getLogger()->warning("Replacing 'config.yml v$version' with 'config.yml v" . self::CONFIG_VERSION . "'");

            // clone all contents from config.yml inside the backup config
            file_put_contents($this->getDataFolder() . "config_v$version.yml",
                file_get_contents($this->getDataFolder() . "config.yml"));

            // save the new config
            $this->saveResource("config.yml", true);
            // update the config to use it in the config manager
            // don't use $this->getConfig(), because that will result in the OLD config
            $config = new Config($this->getDataFolder()."config.yml");
        }

        // construct the config manager
        self::$configManager = ConfigManager::fromConfig($config);

        // get startup settings
        $startupSettings = self::$configManager->getManager("startup", true, ["reload-web-files" => false]);

        $pluginResources = $this->getFile() . "resources/";
        $data = $this->getDataFolder();

        // load the resource pack files
        $resourcePacks = "resource_packs/";
        $defaultPack = $resourcePacks . self::RESOURCE_PACK_NAME;
        if (!is_dir($data . $resourcePacks) || !is_dir($data . $defaultPack)) {
            Filesystem::recursiveCopy($pluginResources . $resourcePacks, $data . $resourcePacks);
        }

        // removes existing web files on startup
        if ($startupSettings->getBool("reload-web-files") || $reloadWebFiles) {
            // reload web files on startup
            Filesystem::recursiveUnlink($this->getDataFolder() . "web");
        }

        // load the web server files
        $web = "web/";
        if (!is_dir($data . $web)) {
            Filesystem::recursiveCopy($pluginResources . $web, $data . $web);
        }

        // create the renders folder
        $renders = "renders/";
        if (!is_dir($data . $renders)) {
            mkdir($data . $renders);
        }

        // create the tmp folder for storing temp data

        self::$tmpDataPath = $data . "tmp/";
        if (!is_dir(self::$tmpDataPath)) {
            mkdir(self::$tmpDataPath);
        }


        // create the regions folder inside tmp
        $tmpRegions = "regions/";
        if (!is_dir(self::$tmpDataPath . $tmpRegions)) {
            mkdir(self::$tmpDataPath . $tmpRegions);
        }

        // create render folders for each world
        $worldFolders = scandir($this->getServer()->getDataPath() . "worlds/");
        foreach ($worldFolders as $worldName) {
            if (!is_dir($data . $renders . $worldName)) {
                mkdir($data . $renders . $worldName);
            }
            if (!is_dir(self::$tmpDataPath . $tmpRegions . $worldName)) {
                mkdir(self::$tmpDataPath . $tmpRegions . $worldName);
            }
        }

        if (!is_dir(self::$tmpDataPath."api")) {
            mkdir(self::$tmpDataPath."api");
        }
    }

    protected function onEnable(): void
    {
        // load all resources
        $this->loadResources();

        $this->loadResourcePacks();

        WebServer::register($this);

        try {
            // create the web server
            $this->createWebServer();
        } catch (Exception $e) {
            $this->getLogger()->alert("Could not start the web server.");
            $this->getLogger()->error($e);
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        // start the render scheduler
        $this->renderScheduler = new RenderSchedulerTask($this);
        $this->getScheduler()->scheduleRepeatingTask($this->renderScheduler, self::$configManager->getInt("renderer.scheduler.run-period", 5));

        // start the chunk update task, this check every period if regions have to be updated
        $this->chunkRenderer = new ChunkRenderTask($this);
        $this->getScheduler()->scheduleRepeatingTask($this->chunkRenderer, self::$configManager->getInt("renderer.chunk-renderer.run-period", 10));

        // start the api update task
        $updateApiTask = new UpdateApiTask($this, self::$tmpDataPath."api/");
        $this->getScheduler()->scheduleRepeatingTask($updateApiTask, self::$configManager->getInt("api.update-period", 20));

        // register the event listener
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
    }

    private function loadResourcePacks(): void {
        $textureSettings = self::$configManager->getManager("textures");

        // get the fallback block
        $fallbackBlockId = $textureSettings->getString("fallback-block", "minecraft:bedrock");
        $fallbackBlock = StringToItemParser::getInstance()->parse($fallbackBlockId)->getBlock();

        // get the height overlay data
        $heightColor = $textureSettings->getInt("height-overlay.color", 0x000000);
        $heightAlpha = $textureSettings->getInt("height-overlay.alpha", 3);

        $path = $this->getDataFolder() . self::RESOURCE_PACK_PATH . self::RESOURCE_PACK_NAME . "/";

        // create the resource pack instance
        $this->resourcePack = new ResourcePack($path, self::TEXTURE_SIZE, $fallbackBlock, $heightColor, $heightAlpha);

        $tmpPath = self::$tmpDataPath."resource_packs";
        if (!is_dir($tmpPath)) mkdir($tmpPath);

        $lastLoaded = json_decode("$tmpPath/loaded_packs.json", true) ?? [];

        $loaded = [];

        $manager = $this->getServer()->getResourcePackManager();
        $packs = $manager->getPackIdList();
        foreach ($packs as $uuid) {
            // get the zipped resource pack
            $pack = $this->getServer()->getResourcePackManager()->getPackById($uuid);
            if (!$pack instanceof ZippedResourcePack) continue;

            $key = $manager->getPackEncryptionKey($uuid);

            $info = [
                "uuid" => $pack->getPackId(),
                "version" => $pack->getPackVersion(),
                "sha256" => utf8_encode($pack->getSha256())
            ];

            // this pack is already loaded in a previous startup
            if (array_key_exists($uuid, $lastLoaded)) {
                $lp = $lastLoaded[$uuid];

                if ($lp["version"] === $info["version"] && $lp["sha256"] === $info["sha256"]) {
                    var_dump("Pack already loaded");
                    $loaded[$uuid] = $info;
                    continue;
                }
            }

            if ($this->loadResourcePack($pack, $key)) {
                $loaded[$uuid] = $info;
            }
        }

        file_put_contents("$tmpPath/loaded_packs.json", json_encode($loaded));
    }

    private function loadResourcePack(ZippedResourcePack $pack, string $key = null): bool {
        // TODO: encrypted packs
        if ($key !== null) return false;
        $uuid = $pack->getPackId();

        // open the zip archive
        $archive = new \ZipArchive();
        if(($openResult = $archive->open($pack->getPath())) !== true){
            throw new ResourcePackException("Encountered ZipArchive error code $openResult while trying to open {$pack->getPath()}");
        }

        $tmpPath = self::$tmpDataPath."resource_packs/$uuid";
        if (!is_dir($tmpPath)) mkdir("$tmpPath/textures/blocks", 0777, true);

        $blocks = $archive->getFromName("manifest.json");
        if ($blocks !== false) file_put_contents("$tmpPath/manifest.json", $blocks);
        $blocks = $archive->getFromName("blocks.json");
        if ($blocks !== false) file_put_contents("$tmpPath/blocks.json", $blocks);
        $terrainTexture = $archive->getFromName("textures/terrain_texture.json");
        if ($terrainTexture !== false) file_put_contents("$tmpPath/textures/terrain_texture.json", $terrainTexture);

        $texturePaths = [];

        // get all texture paths given in the terrain texture file
        $terrainTextureData = json_decode($terrainTexture, true);
        foreach ($terrainTextureData["texture_data"] as $block=>$blockData) {
            $textures = $blockData["textures"];
            if (is_string($textures)) $texturePaths[] = $textures;
            else if (is_array($textures)) {
                if (isset($textures["path"])) $texturePaths[] = $textures["path"];
                else {
                    foreach ($textures as $path) {
                        $texturePaths[] = $path;
                    }
                }
            }
        }

        // remove all .png/.tga file extensions from the path names
        $texturePaths = str_replace([".png", ".tga"], "", $texturePaths);

        // remove all duplicates (if they exist)
        $texturePaths = array_unique($texturePaths);

        // store all textures
        foreach ($texturePaths as $path) {
            $ext = "png";
            $texture = $archive->getFromName("$path.png");

            // it is possible that some textures use tga instead of png, but it's not that common
            if ($texture === false) {
                $ext = "tga";
                $texture = $archive->getFromName("$path.tga");
            }

            if ($texture !== false) file_put_contents("$tmpPath/$path.$ext", $texture);
            else $this->getLogger()->warning("Could not find texture with path '$path' in resource pack '$uuid'");
        }

        // close the archive
        $archive->close();

        return true;
    }

    /**
     * Create the web server and set the routes
     * @throws PhpVersionNotSupportedException
     * @throws WebServerException
     */
    private function createWebServer(): void
    {
        $webSettings = self::$configManager->getManager("web-server", true, ["address" => "127.0.0.1", "port" => 3000]);

        // create the web server
        $this->webServer = new WebServer($webSettings->getString("address", "127.0.0.1"), $webSettings->getInt("port", 3000));
        $router = $this->webServer->getRouter();

        // main route
        $router->getFile("/", $this->getDataFolder()."web/pages/index.html");

        // all static files used by web pages
        $web = $this->getDataFolder() . "web";
        $router->getStatic("/static", "$web/static");

        // register the api router
        $router->route("/api/pocketmap", $this->registerApiRoutes());

        // start the web server
        $this->webServer->start();
    }

    /**
     * Register all API routes to the web server
     * @throws PhpVersionNotSupportedException
     * @throws WebServerException
     */
    private function registerApiRoutes(): Router
    {
        $router = new Router();

        $router->get("/", function (HttpRequest $req, HttpResponse $res) {
            $res->send("Hello World", "text/plain");
            $res->end();
        });

        // get the world data
        $router->getFile("/worlds", self::$tmpDataPath."api/worlds.json", "[]");

        // get player data
        $router->getFile("/players", self::$tmpDataPath."api/players.json", "[]");

        // get the player heads
        $router->getStatic("/players/skin", self::$tmpDataPath."api/skin");

        // get image renders
        $router->getStatic("/render", $this->getDataFolder() . "renders");

        return $router;
    }


    protected function onDisable(): void
    {
        // close the socket
        if ($this->webServer->isStarted()) $this->webServer->close();
    }
}